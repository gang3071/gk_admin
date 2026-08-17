#!/usr/bin/env php
<?php
/**
 * 检查数据库锁和活跃连接
 */

const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

require_once __DIR__ . '/../vendor/autoload.php';

// 加载 .env 文件
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/..');
    $dotenv->load();
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: 3306;
$dbName = getenv('DB_DATABASE') ?: 'yjb_gamebox';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo COLOR_RED . "❌ 数据库连接失败: {$e->getMessage()}\n" . COLOR_RESET;
    exit(1);
}

echo COLOR_BLUE . "
╔═══════════════════════════════════════════════════════════╗
║              数据库锁和连接诊断工具                         ║
╚═══════════════════════════════════════════════════════════╝
" . COLOR_RESET . "\n";

// ===================================================
// 1. 检查当前活跃连接
// ===================================================
echo COLOR_BLUE . "\n📊 1. 当前活跃连接（使用 play_game_record 表）:\n" . COLOR_RESET;

$stmt = $pdo->query("
    SELECT
        id AS thread_id,
        user,
        host,
        db,
        command,
        time AS duration_sec,
        state,
        LEFT(info, 100) AS query_preview
    FROM information_schema.processlist
    WHERE db = '{$dbName}'
      AND (command != 'Sleep' OR info LIKE '%play_game_record%')
    ORDER BY time DESC
");

$connections = $stmt->fetchAll();

if (empty($connections)) {
    echo COLOR_GREEN . "✅ 无活跃连接\n" . COLOR_RESET;
} else {
    echo COLOR_YELLOW . "⚠️  发现 " . count($connections) . " 个活跃连接:\n" . COLOR_RESET;
    printf("%-10s %-15s %-20s %-10s %-10s %-50s\n",
        "线程ID", "用户", "主机", "命令", "持续(秒)", "查询预览");
    echo str_repeat("-", 120) . "\n";

    foreach ($connections as $conn) {
        printf("%-10s %-15s %-20s %-10s %-10s %-50s\n",
            $conn->thread_id,
            $conn->user,
            $conn->host,
            $conn->command,
            $conn->duration_sec,
            $conn->query_preview ?: 'N/A'
        );
    }

    echo "\n" . COLOR_YELLOW . "建议操作:\n" . COLOR_RESET;
    echo "1. 停止所有业务服务:\n";
    echo "   cd D:/gk_admin && php start.php stop\n";
    echo "   cd D:/gk_api && php start.php stop\n";
    echo "   cd D:/gk_work && php start.php stop\n\n";
    echo "2. 或手动杀掉这些连接（谨慎操作）:\n";
    foreach ($connections as $conn) {
        echo "   KILL {$conn->thread_id};\n";
    }
}

// ===================================================
// 2. 检查锁等待情况
// ===================================================
echo COLOR_BLUE . "\n📊 2. 锁等待情况:\n" . COLOR_RESET;

$stmt = $pdo->query("
    SELECT COUNT(*) AS waiting_count
    FROM information_schema.innodb_lock_waits
");
$waitingCount = $stmt->fetchColumn();

if ($waitingCount == 0) {
    echo COLOR_GREEN . "✅ 无锁等待\n" . COLOR_RESET;
} else {
    echo COLOR_RED . "❌ 发现 {$waitingCount} 个锁等待\n" . COLOR_RESET;

    $stmt = $pdo->query("
        SELECT
            r.trx_id AS waiting_trx,
            r.trx_mysql_thread_id AS waiting_thread,
            LEFT(r.trx_query, 80) AS waiting_query,
            b.trx_id AS blocking_trx,
            b.trx_mysql_thread_id AS blocking_thread,
            LEFT(b.trx_query, 80) AS blocking_query
        FROM information_schema.innodb_lock_waits w
        INNER JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
        INNER JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id
    ");

    $locks = $stmt->fetchAll();
    foreach ($locks as $lock) {
        echo COLOR_YELLOW . "  阻塞线程: {$lock->blocking_thread}\n";
        echo "  被阻塞线程: {$lock->waiting_thread}\n";
        echo "  阻塞查询: {$lock->blocking_query}\n";
        echo "  等待查询: {$lock->waiting_query}\n\n";
        echo "  解决方法: KILL {$lock->blocking_thread};\n" . COLOR_RESET;
    }
}

// ===================================================
// 3. 检查长事务
// ===================================================
echo COLOR_BLUE . "\n📊 3. 长事务检查:\n" . COLOR_RESET;

$stmt = $pdo->query("
    SELECT
        trx_id,
        trx_state,
        trx_started,
        TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS duration_sec,
        trx_mysql_thread_id AS thread_id,
        LEFT(trx_query, 80) AS query_preview
    FROM information_schema.innodb_trx
    WHERE TIMESTAMPDIFF(SECOND, trx_started, NOW()) > 10
    ORDER BY trx_started
");

$longTrx = $stmt->fetchAll();

if (empty($longTrx)) {
    echo COLOR_GREEN . "✅ 无长事务\n" . COLOR_RESET;
} else {
    echo COLOR_RED . "❌ 发现 " . count($longTrx) . " 个长事务:\n" . COLOR_RESET;
    printf("%-15s %-20s %-15s %-10s %-50s\n",
        "事务ID", "开始时间", "持续(秒)", "线程ID", "查询预览");
    echo str_repeat("-", 120) . "\n";

    foreach ($longTrx as $trx) {
        printf("%-15s %-20s %-15s %-10s %-50s\n",
            $trx->trx_id,
            $trx->trx_started,
            $trx->duration_sec,
            $trx->thread_id,
            $trx->query_preview ?: 'N/A'
        );
    }

    echo "\n" . COLOR_YELLOW . "建议操作: 杀掉这些长事务\n" . COLOR_RESET;
    foreach ($longTrx as $trx) {
        echo "  KILL {$trx->thread_id};\n";
    }
}

// ===================================================
// 4. 生成解决方案
// ===================================================
echo COLOR_BLUE . "\n🔧 推荐解决方案:\n" . COLOR_RESET;

if (!empty($connections) || !empty($longTrx)) {
    echo COLOR_YELLOW . "
方案 A: 停止业务服务（推荐）
----------------------------------------
cd D:/gk_admin && php start.php stop
cd D:/gk_api && php start.php stop
cd D:/gk_work && php start.php stop

等待 5 秒后，重新执行删除分区脚本

方案 B: 手动杀掉连接
----------------------------------------
" . COLOR_RESET;

    // 生成 KILL 命令
    $killCommands = [];
    foreach ($connections as $conn) {
        $killCommands[] = "KILL {$conn->thread_id};";
    }
    foreach ($longTrx as $trx) {
        $killCommands[] = "KILL {$trx->thread_id};";
    }

    $killCommands = array_unique($killCommands);

    if (!empty($killCommands)) {
        echo "在 MySQL 客户端执行以下命令:\n\n";
        foreach ($killCommands as $cmd) {
            echo "  {$cmd}\n";
        }

        // 保存到文件
        file_put_contents(__DIR__ . '/kill_connections.sql', implode("\n", $killCommands));
        echo "\n" . COLOR_GREEN . "✅ 命令已保存到: scripts/kill_connections.sql\n" . COLOR_RESET;
        echo "可以直接执行: mysql -h {$dbHost} -u {$dbUser} -p {$dbName} < scripts/kill_connections.sql\n";
    }

} else {
    echo COLOR_GREEN . "✅ 无活跃连接和长事务，可以直接删除分区\n" . COLOR_RESET;
}

echo COLOR_BLUE . "\n📋 下一步:\n" . COLOR_RESET;
echo "1. 执行上述建议操作（停止服务或杀掉连接）\n";
echo "2. 手动删除分区:\n";
echo "   mysql -h {$dbHost} -u {$dbUser} -p {$dbName} -e \"ALTER TABLE play_game_record DROP PARTITION p202606;\"\n";
echo "3. 验证删除结果:\n";
echo "   php scripts/manual_partition_cleanup_simple.php\n";
echo "\n";
