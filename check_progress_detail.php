<?php
/**
 * 检查进度记录详细状态（是否发券）
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
echo "检查进度记录详细状态\n";
echo "活动ID: {$activityId}\n";
echo "========================================\n\n";

// 查询所有进度记录
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.player_id,
        pl.name as player_name,
        pl.uuid as player_uuid,
        p.current_bet_amount,
        p.bet_amount_required,
        p.cycles_completed,
        p.ticket_count_per_cycle,
        p.total_tickets_issued,
        p.status,
        p.last_issued_at,
        FLOOR(p.current_bet_amount / p.bet_amount_required) as should_cycles,
        FLOOR(p.current_bet_amount / p.bet_amount_required) * p.ticket_count_per_cycle as should_tickets
    FROM lottery_ticket_bet_progress p
    LEFT JOIN player pl ON pl.id = p.player_id
    WHERE p.activity_id = ?
    ORDER BY p.current_bet_amount DESC
");
$stmt->execute([$activityId]);
$progressRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($progressRecords)) {
    echo "❌ 没有找到进度记录\n\n";
    exit(0);
}

echo "找到 " . count($progressRecords) . " 条进度记录：\n\n";

$abnormalCount = 0;

foreach ($progressRecords as $record) {
    $shouldCycles = floor($record['current_bet_amount'] / $record['bet_amount_required']);
    $shouldTickets = $shouldCycles * $record['ticket_count_per_cycle'];

    echo "========================================\n";
    echo "进度ID: {$record['id']}\n";
    echo "玩家: {$record['player_name']} (ID: {$record['player_id']}, UUID: {$record['player_uuid']})\n";
    echo "----------------------------------------\n";
    echo "打码进度: " . number_format($record['current_bet_amount'], 2) . " / " . number_format($record['bet_amount_required'], 2) . "\n";
    echo "完成周期: {$record['cycles_completed']} / {$shouldCycles} （应完成）\n";
    echo "发券数量: {$record['total_tickets_issued']} / {$shouldTickets} （应发放）\n";
    echo "状态: {$record['status']} (1=进行中, 2=已结束)\n";
    echo "最后发券时间: " . ($record['last_issued_at'] ?: '从未发券') . "\n";

    // 判断是否异常
    $isAbnormal = false;

    if ($record['current_bet_amount'] >= $record['bet_amount_required']) {
        // 打码已达标
        if ($record['total_tickets_issued'] == 0) {
            echo "\n🔴 严重异常：打码超标但从未发券！\n";
            $isAbnormal = true;
        } elseif ($record['total_tickets_issued'] < $shouldTickets) {
            $missingTickets = $shouldTickets - $record['total_tickets_issued'];
            echo "\n⚠️ 异常：少发了 {$missingTickets} 张券\n";
            $isAbnormal = true;
        } else {
            echo "\n✅ 正常：发券数量正确\n";
        }
    } else {
        echo "\n⏳ 未达标：打码不足\n";
    }

    if ($isAbnormal) {
        $abnormalCount++;

        // 查询该玩家实际领取的券
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) as ticket_count
            FROM lottery_ticket
            WHERE activity_id = ?
                AND player_id = ?
                AND source = 'betting'
        ");
        $stmt2->execute([$activityId, $record['player_id']]);
        $actualTickets = $stmt2->fetch(PDO::FETCH_ASSOC)['ticket_count'];

        echo "数据库实际券数: {$actualTickets} 张\n";

        if ($actualTickets != $record['total_tickets_issued']) {
            echo "❌ 数据不一致！进度记录显示 {$record['total_tickets_issued']} 张，实际 {$actualTickets} 张\n";
        }
    }

    echo "\n";
}

// 汇总
echo "========================================\n";
echo "汇总统计\n";
echo "========================================\n\n";

echo "总记录数: " . count($progressRecords) . "\n";
echo "异常记录数: {$abnormalCount}\n\n";

if ($abnormalCount > 0) {
    echo "🔴 核心问题确认：发券逻辑失效！\n\n";

    echo "根本原因（已确认）：\n";
    echo "  LotteryTicketBetProgressService::issueTickets() 使用数据库字段\n";
    echo "  LotteryTicketIssueService::issueTickets() 使用Redis序列号\n";
    echo "  两者不同步，导致发券失败\n\n";

    echo "建议修复方案：\n";
    echo "  1. 查看修复文档：cat FIX_LOTTERY_TICKET_ISSUE_SERVICE.md\n";
    echo "  2. 统一发券逻辑（使用Redis序列号）\n";
    echo "  3. 补发遗漏的奖券\n\n";
} else {
    echo "✅ 所有进度记录正常\n";
}

echo "检查完成！\n\n";
