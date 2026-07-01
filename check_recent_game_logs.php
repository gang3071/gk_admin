<?php
/**
 * 检查最近的游戏记录（排查为什么检测不到）
 */

// 加载环境变量
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// 数据库连接
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? 3306;
$database = $_ENV['DB_DATABASE'] ?? 'gk_admin';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage() . "\n");
}

$activityId = $argv[1] ?? 17;

echo "\n========================================\n";
echo "检查最近的游戏记录\n";
echo "活动ID: {$activityId}\n";
echo "========================================\n\n";

// 1. 获取活动信息
$stmt = $pdo->prepare("SELECT * FROM lottery_ticket_activity WHERE id = ?");
$stmt->execute([$activityId]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    die("活动不存在\n");
}

echo "【活动信息】\n";
echo "活动名称: {$activity['name']}\n";
echo "渠道ID (department_id): {$activity['department_id']}\n";
echo "活动时间: {$activity['start_time']} ~ {$activity['end_time']}\n\n";

// 2. 检查表名（可能有前缀）
echo "【检查数据库表】\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%player_game_log%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "找到的表:\n";
foreach ($tables as $table) {
    echo "  - {$table}\n";
}

if (empty($tables)) {
    die("\n❌ 错误：找不到 player_game_log 表！\n");
}

$tableName = $tables[0];
echo "\n使用表名: {$tableName}\n\n";

// 3. 查看最近10分钟的所有游戏记录（不限条件）
echo "【最近10分钟的所有游戏记录】\n";
$stmt = $pdo->query("
    SELECT
        id, player_id, department_id, chip_amount, created_at
    FROM {$tableName}
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY created_at DESC
    LIMIT 20
");
$recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentLogs)) {
    echo "❌ 最近10分钟没有任何游戏记录\n";
    echo "   这说明：\n";
    echo "   1. 打码操作可能还没写入数据库（异步队列延迟）\n";
    echo "   2. 或者写入了其他表（如 play_game_record）\n";
    echo "   3. 或者服务器时间不同步\n\n";
} else {
    echo "✅ 找到 " . count($recentLogs) . " 条记录：\n\n";

    foreach ($recentLogs as $log) {
        echo "记录ID: {$log['id']}\n";
        echo "  玩家ID: {$log['player_id']}\n";
        echo "  渠道ID: {$log['department_id']} ";

        if ($log['department_id'] == $activity['department_id']) {
            echo "✅ 匹配活动渠道\n";
        } else {
            echo "❌ 不匹配（活动要求 {$activity['department_id']}）\n";
        }

        echo "  打码量: {$log['chip_amount']}\n";
        echo "  时间: {$log['created_at']} ";

        if ($log['created_at'] >= $activity['start_time'] && $log['created_at'] <= $activity['end_time']) {
            echo "✅ 在活动期间\n";
        } else {
            echo "❌ 不在活动期间\n";
        }

        echo "\n";
    }
}

// 4. 检查电子游戏记录（play_game_record）
echo "【检查电子游戏记录表】\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%play_game_record%'");
$playTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($playTables)) {
    $playTableName = $playTables[0];
    echo "找到表: {$playTableName}\n";

    $stmt = $pdo->query("
        SELECT
            id, player_id, department_id, bet, created_at
        FROM {$playTableName}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $playLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($playLogs)) {
        echo "✅ 找到 " . count($playLogs) . " 条电子游戏记录：\n\n";

        foreach ($playLogs as $log) {
            echo "记录ID: {$log['id']}, 玩家ID: {$log['player_id']}, 打码: {$log['bet']}, 时间: {$log['created_at']}\n";
        }
        echo "\n";
    } else {
        echo "最近10分钟没有电子游戏记录\n\n";
    }
} else {
    echo "未找到电子游戏记录表\n\n";
}

// 5. 统计活动期间的记录（按渠道ID）
echo "【按渠道ID统计活动期间的游戏记录】\n";
$stmt = $pdo->prepare("
    SELECT
        department_id,
        COUNT(*) as record_count,
        COUNT(DISTINCT player_id) as player_count,
        SUM(chip_amount) as total_chip
    FROM {$tableName}
    WHERE created_at >= ?
        AND created_at <= ?
    GROUP BY department_id
    ORDER BY record_count DESC
");
$stmt->execute([$activity['start_time'], date('Y-m-d H:i:s')]);
$deptStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($deptStats)) {
    echo "❌ 活动期间没有任何游戏记录\n\n";
} else {
    echo "找到以下渠道的记录：\n\n";

    foreach ($deptStats as $stat) {
        echo "渠道ID: {$stat['department_id']} ";

        if ($stat['department_id'] == $activity['department_id']) {
            echo "✅ 这是活动的渠道\n";
        } else {
            echo "（其他渠道）\n";
        }

        echo "  记录数: {$stat['record_count']}\n";
        echo "  玩家数: {$stat['player_count']}\n";
        echo "  总打码: " . number_format($stat['total_chip'], 2) . "\n\n";
    }
}

// 6. 检查当前时间
echo "【时间检查】\n";
$stmt = $pdo->query("SELECT NOW() as db_time");
$dbTime = $stmt->fetch(PDO::FETCH_ASSOC);

echo "服务器时间: " . date('Y-m-d H:i:s') . "\n";
echo "数据库时间: {$dbTime['db_time']}\n";

$timeDiff = strtotime($dbTime['db_time']) - time();
if (abs($timeDiff) > 60) {
    echo "⚠️ 警告：时间差异 {$timeDiff} 秒\n";
} else {
    echo "✅ 时间同步正常\n";
}

echo "\n========================================\n";
echo "诊断完成\n";
echo "========================================\n\n";
