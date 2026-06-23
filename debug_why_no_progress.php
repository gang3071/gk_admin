<?php
/**
 * 深度诊断：为什么没有自动创建打码进度记录
 *
 * 使用方法：
 * php debug_why_no_progress.php 17
 */

require_once __DIR__ . '/support/bootstrap.php';

use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\Player;
use addons\webman\model\PlayerGameLog;
use support\Db;

$activityId = $argv[1] ?? 17;

echo "\n========================================\n";
echo "深度诊断：为什么没有自动创建打码进度\n";
echo "活动ID: $activityId\n";
echo "========================================\n\n";

// 1. 检查活动状态
echo "【检查点1】活动状态\n";
$activity = LotteryTicketActivity::find($activityId);
if (!$activity) {
    echo "❌ 致命错误：活动不存在\n";
    exit(1);
}

echo "活动名称: {$activity->name}\n";
echo "活动状态: {$activity->status} ";

if ($activity->status !== 1) {
    echo "❌ 错误！必须是 1（进行中）\n";
    echo "   当前状态不是进行中，createProgressForPlayer() 会在第312行返回null\n\n";
} else {
    echo "✅ 正确（进行中）\n";
}

// 2. 检查时间范围
echo "\n【检查点2】时间范围\n";
$now = date('Y-m-d H:i:s');
echo "当前时间: {$now}\n";
echo "开始时间: {$activity->start_time}\n";
echo "结束时间: {$activity->end_time}\n";

if ($now >= $activity->end_time) {
    echo "❌ 错误！当前时间已超过结束时间\n";
    echo "   createProgressForPlayer() 会在第318行返回null\n\n";
} else if ($now < $activity->start_time) {
    echo "⚠️ 警告：活动尚未开始\n\n";
} else {
    echo "✅ 正确（活动进行中）\n";
}

// 3. 检查是否有游戏记录
echo "\n【检查点3】游戏记录\n";
$gameLogStats = Db::table('player_game_log')
    ->select([
        Db::raw('COUNT(*) as total_count'),
        Db::raw('COUNT(DISTINCT player_id) as player_count'),
        Db::raw('SUM(chip_amount) as total_chip')
    ])
    ->where('department_id', $activity->department_id)
    ->where('created_at', '>=', $activity->start_time)
    ->where('created_at', '<=', $now)
    ->where('chip_amount', '>', 0)
    ->first();

echo "游戏记录数: {$gameLogStats->total_count}\n";
echo "参与玩家数: {$gameLogStats->player_count}\n";
echo "总打码量: " . number_format($gameLogStats->total_chip ?? 0, 2) . "\n";

if ($gameLogStats->total_count == 0) {
    echo "❌ 错误！没有游戏记录，后台任务无法触发\n";
    echo "   LotteryBetProgressScanTask 只处理有游戏记录的玩家\n\n";
    exit(0);
} else {
    echo "✅ 正确（有游戏记录）\n";
}

// 4. 获取有打码的玩家列表
echo "\n【检查点4】玩家详细信息\n";
$playerBets = Db::table('player_game_log')
    ->select([
        'player_id',
        Db::raw('SUM(chip_amount) as total_chip')
    ])
    ->where('department_id', $activity->department_id)
    ->where('created_at', '>=', $activity->start_time)
    ->where('created_at', '<=', $now)
    ->where('chip_amount', '>', 0)
    ->groupBy('player_id')
    ->orderBy('total_chip', 'desc')
    ->limit(10)
    ->get();

echo "检查前10个打码玩家...\n\n";

$successCount = 0;
$failureReasons = [];

foreach ($playerBets as $bet) {
    $playerId = $bet->player_id;
    $chipAmount = $bet->total_chip;

    echo "玩家ID: {$playerId}, 打码量: " . number_format($chipAmount, 2) . "\n";

    // 检查玩家是否存在
    $player = Player::find($playerId);
    if (!$player) {
        echo "  ❌ 失败：玩家不存在（createProgressForPlayer 第323行返回null）\n\n";
        $failureReasons['player_not_found'] = ($failureReasons['player_not_found'] ?? 0) + 1;
        continue;
    }

    echo "  玩家名称: {$player->name}\n";
    echo "  玩家UUID: {$player->uuid}\n";
    echo "  VIP等级ID: {$player->vip_level_id}\n";
    echo "  玩家状态: {$player->status}\n";

    // 检查VIP配置
    $config = LotteryTicketVipConfig::where('activity_id', $activityId)
        ->where('vip_level_id', $player->vip_level_id)
        ->where('status', 1)
        ->first();

    if (!$config) {
        echo "  ❌ 失败：该玩家的VIP等级({$player->vip_level_id})没有配置打码量\n";
        echo "     createProgressForPlayer 第333行返回null\n";
        echo "     解决方法：在活动配置中添加VIP等级 {$player->vip_level_id} 的配置\n\n";

        $failureReasons['vip_config_missing'][$player->vip_level_id] =
            ($failureReasons['vip_config_missing'][$player->vip_level_id] ?? 0) + 1;
        continue;
    }

    echo "  ✅ VIP配置存在：打码要求 {$config->bet_amount_required}, 发券数 {$config->ticket_count}\n";

    // 检查是否已有进度记录
    $progress = \addons\webman\model\LotteryTicketBetProgress::where('activity_id', $activityId)
        ->where('player_id', $playerId)
        ->first();

    if ($progress) {
        echo "  ℹ️ 已存在进度记录（当前打码: {$progress->current_bet_amount}）\n\n";
    } else {
        echo "  ⚠️ 没有进度记录，理论上应该自动创建\n\n";
    }

    $successCount++;
}

// 5. 汇总分析
echo "\n========================================\n";
echo "诊断结果汇总\n";
echo "========================================\n\n";

if (empty($failureReasons)) {
    if ($successCount > 0) {
        echo "✅ 所有检查点通过！理论上应该能自动创建进度记录\n\n";
        echo "可能的原因：\n";
        echo "1. 后台任务 LotteryBetProgressScanTask 没有运行\n";
        echo "   检查方法：tail -50 runtime/logs/webman.log | grep '摸奖券打码进度'\n";
        echo "2. 后台任务执行时出现异常但被 try-catch 捕获\n";
        echo "   检查方法：tail -100 runtime/logs/webman.log | grep 'ERROR'\n";
        echo "3. Redis缓存锁导致任务跳过\n";
        echo "   检查方法：redis-cli GET lottery_bet_scan_status\n\n";

        echo "建议操作：\n";
        echo "1. 重启 Webman 服务：php windows.php restart\n";
        echo "2. 等待1-2分钟后检查日志：tail -50 runtime/logs/webman.log\n";
        echo "3. 如果还是不行，手动初始化：php init_lottery_progress.php {$activityId}\n";
    }
} else {
    echo "❌ 发现以下问题：\n\n";

    if (isset($failureReasons['vip_config_missing'])) {
        echo "【严重】缺少VIP配置：\n";
        foreach ($failureReasons['vip_config_missing'] as $vipId => $count) {
            echo "  - VIP等级 {$vipId}：{$count} 个玩家无法创建进度\n";
        }
        echo "\n解决方法：\n";
        echo "1. 进入活动管理 → 活动ID {$activityId} → VIP配置\n";
        echo "2. 为以上VIP等级添加配置：\n";
        foreach (array_keys($failureReasons['vip_config_missing']) as $vipId) {
            echo "   INSERT INTO lottery_ticket_vip_config \n";
            echo "   (activity_id, vip_level_id, bet_amount_required, ticket_count, status) \n";
            echo "   VALUES ({$activityId}, {$vipId}, 10.00, 1, 1);\n";
        }
        echo "\n";
    }

    if (isset($failureReasons['player_not_found'])) {
        echo "【警告】{$failureReasons['player_not_found']} 个玩家ID在player表中不存在\n";
        echo "这可能是数据不一致导致的，需要清理 player_game_log 表\n\n";
    }
}

echo "诊断完成！\n\n";
