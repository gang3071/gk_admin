#!/usr/bin/env php
<?php
/**
 * 手动删除分区脚本（在归档成功后使用）
 *
 * 前提：数据已归档到历史表
 * 用途：单独删除指定分区
 */

const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

require_once __DIR__ . '/../vendor/autoload.php';

// 加载 .env
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
║              手动删除分区工具                               ║
╚═══════════════════════════════════════════════════════════╝
" . COLOR_RESET . "\n";

// 计算 2 个月前的分区
$twoMonthsAgo = (new DateTime())->modify('-2 months');
$partitionToDelete = 'p' . $twoMonthsAgo->format('Ym');

echo "目标分区: " . COLOR_YELLOW . $partitionToDelete . COLOR_RESET . "\n\n";

// 检查分区是否存在
echo "1. 检查分区是否存在...\n";
$stmt = $pdo->prepare("
    SELECT
        PARTITION_NAME,
        TABLE_ROWS,
        ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
        ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_mb
    FROM INFORMATION_SCHEMA.PARTITIONS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'play_game_record'
      AND PARTITION_NAME = ?
");
$stmt->execute([$partitionToDelete]);
$partition = $stmt->fetch();

if (!$partition) {
    echo COLOR_GREEN . "✅ 分区 {$partitionToDelete} 不存在，已经被删除\n" . COLOR_RESET;
    exit(0);
}

echo COLOR_YELLOW . "   分区详情:\n";
echo "   - 数据行数: " . number_format($partition->TABLE_ROWS) . " 条\n";
echo "   - 数据大小: {$partition->data_mb} MB\n";
echo "   - 索引大小: {$partition->index_mb} MB\n" . COLOR_RESET;

// 检查是否已归档
echo "\n2. 检查数据是否已归档...\n";
$archiveDate = $twoMonthsAgo->format('Y-m-01');
$archiveEndDate = (clone $twoMonthsAgo)->modify('+1 month')->format('Y-m-01');

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS count
    FROM play_game_record_history
    WHERE created_at >= ? AND created_at < ?
");
$stmt->execute([$archiveDate, $archiveEndDate]);
$archivedCount = $stmt->fetchColumn();

if ($archivedCount == 0) {
    echo COLOR_RED . "❌ 错误: 数据未归档到历史表，禁止删除！\n" . COLOR_RESET;
    echo COLOR_YELLOW . "   请先执行归档:\n";
    echo "   INSERT INTO play_game_record_history\n";
    echo "   SELECT * FROM play_game_record PARTITION ({$partitionToDelete});\n" . COLOR_RESET;
    exit(1);
}

echo COLOR_GREEN . "   ✅ 已归档 " . number_format($archivedCount) . " 条数据\n" . COLOR_RESET;

// 检查活跃连接
echo "\n3. 检查活跃连接...\n";
$stmt = $pdo->query("
    SELECT COUNT(*) AS count
    FROM information_schema.processlist
    WHERE db = '{$dbName}'
      AND command != 'Sleep'
      AND id != CONNECTION_ID()
");
$activeConnections = $stmt->fetchColumn();

if ($activeConnections > 0) {
    echo COLOR_YELLOW . "   ⚠️  警告: 发现 {$activeConnections} 个活跃连接\n";
    echo "   建议先停止业务服务:\n";
    echo "   cd D:/gk_admin && php start.php stop\n";
    echo "   cd D:/gk_api && php start.php stop\n";
    echo "   cd D:/gk_work && php start.php stop\n" . COLOR_RESET;
} else {
    echo COLOR_GREEN . "   ✅ 无活跃连接\n" . COLOR_RESET;
}

// 确认删除
echo "\n" . COLOR_YELLOW . "确认删除分区 {$partitionToDelete}？ (yes/no): " . COLOR_RESET;
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes') {
    echo COLOR_YELLOW . "❌ 操作已取消\n" . COLOR_RESET;
    exit(0);
}

// 设置更长的超时时间
echo "\n4. 设置超时参数...\n";
$pdo->exec("SET SESSION lock_wait_timeout = 600");
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 600");
echo COLOR_GREEN . "   ✅ 超时时间已设置为 600 秒\n" . COLOR_RESET;

// 删除分区
echo "\n5. 正在删除分区 {$partitionToDelete}...\n";
$startTime = microtime(true);

try {
    $pdo->exec("ALTER TABLE play_game_record DROP PARTITION {$partitionToDelete}");
    $duration = round(microtime(true) - $startTime, 2);

    echo COLOR_GREEN . "
╔═══════════════════════════════════════════════════════════╗
║                    🎉 删除成功！                           ║
╚═══════════════════════════════════════════════════════════╝
" . COLOR_RESET;

    echo "\n删除摘要:\n";
    echo "  - 分区名: {$partitionToDelete}\n";
    echo "  - 删除行数: " . number_format($partition->TABLE_ROWS) . " 条\n";
    echo "  - 释放空间: " . ($partition->data_mb + $partition->index_mb) . " MB\n";
    echo "  - 耗时: {$duration} 秒\n";

    // 显示当前分区状态
    echo "\n📊 当前分区状态:\n";
    $stmt = $pdo->query("
        SELECT
            PARTITION_NAME,
            TABLE_ROWS,
            ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb
        FROM INFORMATION_SCHEMA.PARTITIONS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'play_game_record'
          AND PARTITION_NAME IS NOT NULL
          AND PARTITION_NAME != 'p_future'
        ORDER BY PARTITION_ORDINAL_POSITION
    ");

    $currentPartitions = $stmt->fetchAll();
    $totalRows = 0;
    $totalSize = 0;

    foreach ($currentPartitions as $p) {
        echo "  - {$p->PARTITION_NAME}: " . number_format($p->TABLE_ROWS) . " 条, {$p->data_mb} MB\n";
        $totalRows += $p->TABLE_ROWS;
        $totalSize += $p->data_mb;
    }

    echo COLOR_GREEN . "\n✅ 主表总计: " . number_format($totalRows) . " 条, {$totalSize} MB\n";
    echo "✅ 保留期已调整为: 最近 2 个月数据\n" . COLOR_RESET;

} catch (PDOException $e) {
    echo COLOR_RED . "❌ 删除失败: {$e->getMessage()}\n" . COLOR_RESET;

    if (strpos($e->getMessage(), 'Lock wait timeout') !== false) {
        echo COLOR_YELLOW . "\n⚠️  锁等待超时，请执行以下操作:\n";
        echo "1. 运行诊断脚本: php scripts/check_locks.php\n";
        echo "2. 停止所有业务服务\n";
        echo "3. 重新运行此脚本\n" . COLOR_RESET;
    }

    exit(1);
}

echo "\n";
