<?php
/**
 * 测试打码量计算（机台 + 电子游戏）
 *
 * 使用方法：
 * php test_bet_amount_calculation.php 17
 */

require_once __DIR__ . '/support/bootstrap.php';

use addons\webman\model\LotteryTicketActivity;
use support\Db;

$activityId = $argv[1] ?? 17;

echo "\n========================================\n";
echo "测试打码量计算（机台 + 电子游戏）\n";
echo "活动ID: $activityId\n";
echo "========================================\n\n";

// 获取活动信息
$activity = LotteryTicketActivity::find($activityId);
if (!$activity) {
    echo "❌ 活动不存在\n";
    exit(1);
}

echo "活动名称: {$activity->name}\n";
echo "部门ID: {$activity->department_id}\n";
echo "开始时间: {$activity->start_time}\n";
echo "结束时间: {$activity->end_time}\n\n";

// 1. 统计机台游戏打码量
echo "【1】机台游戏打码量统计\n";
$machineStats = Db::table('player_game_log')
    ->select([
        Db::raw('COUNT(*) as record_count'),
        Db::raw('COUNT(DISTINCT player_id) as player_count'),
        Db::raw('SUM(chip_amount) as total_chip')
    ])
    ->where('department_id', $activity->department_id)
    ->where('created_at', '>=', $activity->start_time)
    ->where('created_at', '<=', date('Y-m-d H:i:s'))
    ->where('chip_amount', '>', 0)
    ->first();

echo "记录数: {$machineStats->record_count}\n";
echo "玩家数: {$machineStats->player_count}\n";
echo "总打码: " . number_format($machineStats->total_chip ?? 0, 2) . "\n\n";

// 2. 统计电子游戏打码量
echo "【2】电子游戏打码量统计（未结算 + 已结算）\n";
$onlineStats = Db::table('play_game_record')
    ->select([
        Db::raw('COUNT(*) as record_count'),
        Db::raw('COUNT(DISTINCT player_id) as player_count'),
        Db::raw('SUM(bet) as total_bet')
    ])
    ->where('department_id', $activity->department_id)
    ->where('created_at', '>=', $activity->start_time)
    ->where('created_at', '<=', date('Y-m-d H:i:s'))
    ->where('bet', '>', 0)
    ->whereIn('settlement_status', [0, 1]) // 未结算 + 已结算
    ->first();

echo "记录数: {$onlineStats->record_count}\n";
echo "玩家数: {$onlineStats->player_count}\n";
echo "总打码: " . number_format($onlineStats->total_bet ?? 0, 2) . "\n\n";

// 3. 汇总
echo "【3】汇总统计\n";
$totalChip = floatval($machineStats->total_chip ?? 0);
$totalBet = floatval($onlineStats->total_bet ?? 0);
$grandTotal = $totalChip + $totalBet;

echo "机台游戏打码: " . number_format($totalChip, 2) . "\n";
echo "电子游戏打码: " . number_format($totalBet, 2) . "\n";
echo "================================\n";
echo "总打码量: " . number_format($grandTotal, 2) . "\n\n";

// 4. 按玩家统计（前10名）
echo "【4】按玩家统计（前10名）\n";
echo "注意：现在会同时统计机台和电子游戏的打码量\n\n";

// 机台游戏
$machinePlayers = Db::table('player_game_log')
    ->select(['player_id', Db::raw('SUM(chip_amount) as total_chip')])
    ->where('department_id', $activity->department_id)
    ->where('created_at', '>=', $activity->start_time)
    ->where('created_at', '<=', date('Y-m-d H:i:s'))
    ->where('chip_amount', '>', 0)
    ->groupBy('player_id')
    ->get()
    ->keyBy('player_id');

// 电子游戏
$onlinePlayers = Db::table('play_game_record')
    ->select(['player_id', Db::raw('SUM(bet) as total_bet')])
    ->where('department_id', $activity->department_id)
    ->where('created_at', '>=', $activity->start_time)
    ->where('created_at', '<=', date('Y-m-d H:i:s'))
    ->where('bet', '>', 0)
    ->whereIn('settlement_status', [0, 1]) // 未结算 + 已结算
    ->groupBy('player_id')
    ->get()
    ->keyBy('player_id');

// 合并数据
$allPlayerIds = collect($machinePlayers->keys())->merge($onlinePlayers->keys())->unique();
$playerTotals = [];

foreach ($allPlayerIds as $playerId) {
    $machineChip = isset($machinePlayers[$playerId]) ? floatval($machinePlayers[$playerId]->total_chip) : 0;
    $onlineBet = isset($onlinePlayers[$playerId]) ? floatval($onlinePlayers[$playerId]->total_bet) : 0;
    $total = $machineChip + $onlineBet;

    $playerTotals[$playerId] = [
        'machine' => $machineChip,
        'online' => $onlineBet,
        'total' => $total,
    ];
}

// 排序并显示前10名
arsort($playerTotals);
$top10 = array_slice($playerTotals, 0, 10, true);

foreach ($top10 as $playerId => $data) {
    echo sprintf(
        "玩家ID: %5d | 机台: %10s | 电子: %10s | 总计: %10s\n",
        $playerId,
        number_format($data['machine'], 2),
        number_format($data['online'], 2),
        number_format($data['total'], 2)
    );
}

echo "\n如果电子游戏打码量 > 0，说明修复成功！\n";
echo "如果电子游戏打码量 = 0，请检查:\n";
echo "1. play_game_record 表是否有数据\n";
echo "2. settlement_status 是否为 0（未结算）或 1（已结算）\n";
echo "3. department_id 是否匹配\n";
echo "4. 时间范围是否正确\n\n";
