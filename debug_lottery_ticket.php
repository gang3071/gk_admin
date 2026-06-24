<?php
require_once __DIR__ . '/vendor/autoload.php';
\support\App::run();

// ========== 请修改这里的参数 ==========
$activityId = 17;        // 你的活动ID
$ticketNo = '000001';    // 你要录入的券号（不带大括号）
// =====================================

echo "\n=== 摸奖券调试工具 ===\n\n";

// 1. 检查活动是否存在
$activity = \addons\webman\model\LotteryTicketActivity::find($activityId);
if (!$activity) {
    echo "❌ 活动ID {$activityId} 不存在！\n";
    exit;
}
echo "✅ 活动ID: {$activityId}\n";
echo "   活动名称: {$activity->name}\n";
echo "   活动状态: {$activity->status}\n";
echo "   状态说明: " . match($activity->status) {
    0 => '未开始',
    1 => '进行中',
    2 => '待开奖',
    3 => '开奖中',
    4 => '已结束',
    5 => '已关闭',
    default => '未知'
} . "\n\n";

// 2. 查询券号
$ticket = \addons\webman\model\LotteryTicket::where('ticket_no', $ticketNo)
    ->where('activity_id', $activityId)
    ->first();

if (!$ticket) {
    echo "❌ 券号 {$ticketNo} 在活动 {$activityId} 下不存在！\n\n";
    
    // 检查是否在其他活动
    $otherTicket = \addons\webman\model\LotteryTicket::where('ticket_no', $ticketNo)->first();
    if ($otherTicket) {
        echo "   ⚠️ 但这个券号存在于活动 {$otherTicket->activity_id}\n";
    }
    
    // 显示该活动的前10张券
    echo "\n   该活动的前10张券：\n";
    $tickets = \addons\webman\model\LotteryTicket::where('activity_id', $activityId)
        ->limit(10)
        ->get();
    foreach ($tickets as $t) {
        echo "   - 券号: {$t->ticket_no}, 状态: {$t->status}, 玩家ID: {$t->player_id}\n";
    }
    exit;
}

echo "✅ 找到券号: {$ticketNo}\n";
echo "   券ID: {$ticket->id}\n";
echo "   玩家ID: {$ticket->player_id}\n";
echo "   券状态: {$ticket->status}\n";
echo "   状态说明: " . match($ticket->status) {
    0 => '未使用 ✅',
    1 => '已使用 ❌',
    2 => '已过期 ❌',
    default => '未知'
} . "\n\n";

// 3. 检查是否可以录入
if ($ticket->status != 0) {
    echo "❌ 无法录入！券已被使用或过期（状态: {$ticket->status}）\n\n";
    
    // 查询是否已有中奖记录
    $record = \addons\webman\model\LotteryTicketRecord::where('ticket_id', $ticket->id)->first();
    if ($record) {
        echo "   该券已有中奖记录：\n";
        echo "   - 奖品: {$record->prize_name}\n";
        echo "   - 奖金: {$record->prize_amount}\n";
        echo "   - 状态: {$record->status}\n";
    }
    exit;
}

echo "✅ 该券可以录入中奖！\n\n";

// 4. 显示奖品等级
$prizeLevels = \addons\webman\model\LotteryTicketPrizeLevel::where('activity_id', $activityId)
    ->get();

echo "📋 该活动的奖品等级：\n";
foreach ($prizeLevels as $level) {
    echo "   - ID: {$level->id}, 名称: {$level->level_name}, 奖金: {$level->prize_amount}\n";
}

echo "\n=== 调试完成 ===\n\n";
