<?php
/**
 * 检查活动的券号配置（找出为什么不发券）
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
echo "检查活动的券号配置\n";
echo "活动ID: {$activityId}\n";
echo "========================================\n\n";

// 查询活动配置
$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        current_ticket_no,
        max_ticket_no,
        status,
        start_time,
        end_time
    FROM lottery_ticket_activity
    WHERE id = ?
");
$stmt->execute([$activityId]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    die("❌ 活动不存在\n");
}

echo "【活动配置】\n";
echo "活动名称: {$activity['name']}\n";
echo "状态: {$activity['status']}\n";
echo "时间: {$activity['start_time']} ~ {$activity['end_time']}\n\n";

echo "【券号配置】\n";
echo "current_ticket_no (当前券号): {$activity['current_ticket_no']}\n";
echo "max_ticket_no (最大券号): {$activity['max_ticket_no']}\n\n";

// 计算可用数量
$available = $activity['max_ticket_no'] - $activity['current_ticket_no'];

echo "【可用性分析】\n";
echo "剩余可发放数量: {$available}\n";

if ($activity['current_ticket_no'] >= $activity['max_ticket_no']) {
    echo "\n🔴 致命问题：current_ticket_no >= max_ticket_no\n";
    echo "   这会导致旧的 issueTickets() 方法在第411行检查时返回0！\n";
    echo "   代码逻辑：\n";
    echo "   if (\$currentNo >= \$maxNo) {\n";
    echo "       return ['issued_count' => 0, 'first_ticket_no' => null];\n";
    echo "   }\n\n";
    echo "⚠️ 这就是为什么完全没发券的根本原因！\n\n";
} else {
    echo "✅ 券号配置正常，可以发券\n\n";
}

// 查询实际发放的券数
$stmt = $pdo->prepare("
    SELECT COUNT(*) as actual_count
    FROM lottery_ticket
    WHERE activity_id = ?
");
$stmt->execute([$activityId]);
$actualCount = $stmt->fetch(PDO::FETCH_ASSOC)['actual_count'];

echo "【实际发券情况】\n";
echo "数据库中的实际券数: {$actualCount}\n";
echo "current_ticket_no 字段: {$activity['current_ticket_no']}\n";

if ($actualCount != $activity['current_ticket_no']) {
    echo "\n⚠️ 不一致！数据库字段与实际券数不符\n";
    echo "   这说明有两套发券逻辑在并行工作：\n";
    echo "   1. 旧逻辑使用 current_ticket_no（数据库字段）\n";
    echo "   2. 新逻辑使用 Redis 序列号\n";
    echo "   但它们没有同步！\n\n";
}

// 检查 Redis 序列号（如果可用）
echo "【Redis 序列号】\n";
if (class_exists('Redis')) {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $key = "lottery_activity:{$activityId}:ticket_sequence";
        $redisSeq = $redis->get($key);

        if ($redisSeq !== false && $redisSeq !== null) {
            echo "Redis序列号: {$redisSeq}\n";

            if ($redisSeq != $actualCount) {
                echo "⚠️ Redis序列号与实际券数不符！\n";
            }
        } else {
            echo "Redis序列号: 未设置\n";
        }
    } catch (Exception $e) {
        echo "无法连接Redis: {$e->getMessage()}\n";
    }
} else {
    echo "PHP未安装Redis扩展\n";
}

echo "\n========================================\n";
echo "诊断结论\n";
echo "========================================\n\n";

if ($activity['current_ticket_no'] >= $activity['max_ticket_no']) {
    echo "🔴 根本原因确认：\n\n";
    echo "活动创建时，current_ticket_no 和 max_ticket_no 的初始值设置不当：\n";
    echo "  - current_ticket_no = {$activity['current_ticket_no']}\n";
    echo "  - max_ticket_no = {$activity['max_ticket_no']}\n\n";

    echo "导致旧的发券逻辑在第一次检查时就返回0（券已用尽）。\n\n";

    echo "解决方案：\n";
    echo "1. 立即修正这两个字段：\n";
    echo "   UPDATE lottery_ticket_activity \n";
    echo "   SET current_ticket_no = {$actualCount}, \n";
    echo "       max_ticket_no = 999999 \n";
    echo "   WHERE id = {$activityId};\n\n";

    echo "2. 或者使用新的统一发券逻辑（已修复，使用Redis序列号）\n";
    echo "   这样就不再依赖这两个字段了。\n\n";
} else {
    echo "✅ 券号配置正常\n";
    echo "可能的其他原因：\n";
    echo "1. 后台任务没有运行\n";
    echo "2. 发券逻辑有异常但被 try-catch 捕获了\n";
    echo "3. 日志中应该有更多线索\n\n";
}

echo "诊断完成！\n\n";
