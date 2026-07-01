<?php
/**
 * 检查活动2的详细情况
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
echo "活动2详细诊断\n";
echo "========================================\n\n";

// 1. 活动基本信息
$stmt = $pdo->query("
    SELECT id, name, status, start_time, end_time, current_ticket_no, max_ticket_no
    FROM lottery_ticket_activity
    WHERE id = 2
");
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    die("活动2不存在\n");
}

echo "【活动基本信息】\n";
echo "名称: {$activity['name']}\n";
echo "状态: {$activity['status']} (1=进行中, 2=待开奖, 3=开奖中, 4=已结束)\n";
echo "时间: {$activity['start_time']} ~ {$activity['end_time']}\n";
echo "券号: 当前 {$activity['current_ticket_no']}, 最大 {$activity['max_ticket_no']}\n\n";

// 2. 券号分配情况
echo "【券号分配情况】\n";
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS actual_count,
        MIN(CAST(ticket_no AS UNSIGNED)) AS min_no,
        MAX(CAST(ticket_no AS UNSIGNED)) AS max_no
    FROM lottery_ticket
    WHERE activity_id = 2
");
$ticketStats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "实际发券数: {$ticketStats['actual_count']}\n";
echo "最小券号: " . str_pad($ticketStats['min_no'] ?? 0, 6, '0', STR_PAD_LEFT) . "\n";
echo "最大券号: " . str_pad($ticketStats['max_no'] ?? 0, 6, '0', STR_PAD_LEFT) . "\n";

// 对比检查
$dbField = $activity['current_ticket_no'];
$actualMax = ($ticketStats['max_no'] ?? 0) + 1;

echo "\n对比分析：\n";
echo "  数据库字段 current_ticket_no = {$dbField}\n";
echo "  实际最大序列号+1 = {$actualMax}\n";

if ($dbField != $actualMax) {
    echo "  ⚠️ 不一致！这说明两套发券逻辑不同步\n\n";
} else {
    echo "  ✅ 一致\n\n";
}

// 3. 检查Redis序列号（如果可用）
echo "【Redis序列号】\n";
if (class_exists('Redis')) {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redisSeq = $redis->get('lottery_activity:2:ticket_sequence');
        echo "Redis序列号: " . ($redisSeq ?: '未设置') . "\n";

        if ($redisSeq && $redisSeq != $actualMax) {
            echo "⚠️ Redis序列号与实际不符！\n";
            echo "   实际应为: {$actualMax}\n";
        }
    } catch (Exception $e) {
        echo "无法连接Redis: {$e->getMessage()}\n";
    }
} else {
    echo "PHP未安装Redis扩展，跳过检查\n";
}

echo "\n";

// 4. 打码进度异常记录
echo "【打码进度异常记录】（打码超标但未发券）\n";
$stmt = $pdo->query("
    SELECT
        id, player_id,
        current_bet_amount, bet_amount_required,
        cycles_completed, total_tickets_issued,
        status,
        FLOOR(current_bet_amount / bet_amount_required) AS should_cycles
    FROM lottery_ticket_bet_progress
    WHERE activity_id = 2
        AND current_bet_amount >= bet_amount_required
        AND total_tickets_issued = 0
    ORDER BY current_bet_amount DESC
    LIMIT 10
");

$abnormalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($abnormalRecords)) {
    echo "✅ 没有异常记录\n\n";
} else {
    echo "⚠️ 发现 " . count($abnormalRecords) . " 条异常记录：\n\n";

    foreach ($abnormalRecords as $record) {
        $shouldCycles = floor($record['current_bet_amount'] / $record['bet_amount_required']);

        echo "进度ID: {$record['id']}, 玩家ID: {$record['player_id']}\n";
        echo "  打码进度: {$record['current_bet_amount']} / {$record['bet_amount_required']}\n";
        echo "  应完成周期: {$shouldCycles}, 实际完成: {$record['cycles_completed']}\n";
        echo "  已发券数: {$record['total_tickets_issued']}\n";
        echo "  状态: {$record['status']} (1=进行中)\n";
        echo "  ❌ 问题：打码超标 " . round($record['current_bet_amount'] / $record['bet_amount_required'], 2) . " 倍，但未发券！\n\n";
    }
}

// 5. 游戏记录统计
echo "【游戏记录统计】\n";
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_count,
        COUNT(DISTINCT player_id) as player_count,
        SUM(chip_amount) as total_chip
    FROM player_game_log
    WHERE department_id = ?
        AND created_at >= ?
        AND created_at <= ?
");
$stmt->execute([
    $activity['department_id'] ?? 1,
    $activity['start_time'],
    date('Y-m-d H:i:s')
]);
$gameStats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "游戏记录数: {$gameStats['total_count']}\n";
echo "参与玩家数: {$gameStats['player_count']}\n";
echo "总打码量: " . number_format($gameStats['total_chip'] ?? 0, 2) . "\n\n";

// 6. 结论
echo "========================================\n";
echo "诊断结论\n";
echo "========================================\n\n";

if (!empty($abnormalRecords)) {
    echo "🔴 核心问题：发券逻辑失效\n\n";
    echo "症状：\n";
    echo "  - 打码进度记录显示打码量已超标\n";
    echo "  - 但 total_tickets_issued = 0（未发券）\n";
    echo "  - cycles_completed = 0（未记录周期）\n\n";

    echo "根本原因（推测）：\n";
    echo "  1. LotteryTicketBetProgressService::issueTickets() 使用数据库字段\n";
    echo "  2. LotteryTicketIssueService::issueTickets() 使用Redis序列号\n";
    echo "  3. 两者不同步，导致发券失败（可能券号冲突或逻辑不匹配）\n\n";

    echo "建议修复方案：\n";
    echo "  1. 统一发券逻辑（统一使用Redis序列号）\n";
    echo "  2. 数据修复：补发遗漏的奖券\n";
    echo "  3. 测试验证：确保发券正常\n\n";
} else {
    echo "✅ 未发现异常\n";
}

echo "诊断完成！\n\n";
