<?php
/**
 * 检查摸奖券的实际来源分布
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

echo "\n========================================\n";
echo "摸奖券来源分布统计\n";
echo "========================================\n\n";

// 统计所有券的来源分布
$stmt = $pdo->query("
    SELECT
        source,
        COUNT(*) as count,
        COUNT(DISTINCT player_id) as player_count,
        COUNT(DISTINCT activity_id) as activity_count
    FROM lottery_ticket
    GROUP BY source
    ORDER BY count DESC
");

$sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sources)) {
    echo "数据库中没有任何摸奖券记录\n\n";
    exit(0);
}

echo "【来源分布】\n\n";

$sourceNames = [
    'betting' => '打码获得',
    'recharge' => '充值赠送',
    'activity' => '活动赠送',
    'manual' => '手动发放',
];

$totalTickets = 0;

foreach ($sources as $source) {
    $sourceName = $sourceNames[$source['source']] ?? $source['source'];
    $totalTickets += $source['count'];

    echo "来源: {$sourceName} ({$source['source']})\n";
    echo "  券数: {$source['count']}\n";
    echo "  涉及玩家: {$source['player_count']}\n";
    echo "  涉及活动: {$source['activity_count']}\n\n";
}

echo "总券数: {$totalTickets}\n\n";

// 检查是否有未定义的来源
echo "【来源值验证】\n";
$stmt = $pdo->query("SELECT DISTINCT source FROM lottery_ticket ORDER BY source");
$allSources = $stmt->fetchAll(PDO::FETCH_COLUMN);

$definedSources = ['betting', 'recharge', 'activity', 'manual'];
$undefinedSources = array_diff($allSources, $definedSources);

if (!empty($undefinedSources)) {
    echo "⚠️ 发现未定义的来源值: " . implode(', ', $undefinedSources) . "\n";
    echo "   需要在 LotteryTicket 模型中添加对应的常量\n\n";
} else {
    echo "✅ 所有来源值都已定义\n\n";
}

// 按活动统计
echo "【按活动统计】\n\n";
$stmt = $pdo->query("
    SELECT
        a.id,
        a.name,
        COUNT(t.id) as ticket_count,
        GROUP_CONCAT(DISTINCT t.source) as sources
    FROM lottery_ticket_activity a
    LEFT JOIN lottery_ticket t ON t.activity_id = a.id
    GROUP BY a.id
    ORDER BY ticket_count DESC
    LIMIT 10
");

$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activities as $activity) {
    echo "活动ID {$activity['id']}: {$activity['name']}\n";
    echo "  总券数: {$activity['ticket_count']}\n";
    echo "  来源: " . ($activity['sources'] ?: '无') . "\n\n";
}

echo "检查完成！\n\n";
