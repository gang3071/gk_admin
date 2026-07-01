<?php
/**
 * 摸奖券活动诊断脚本
 * 用于排查活动ID 16为什么没有发券
 *
 * 使用方法：
 * php check_lottery_activity.php 16
 */

require_once __DIR__ . '/support/bootstrap.php';

use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\LotteryTicket;
use support\Db;

$activityId = $argv[1] ?? 16;

echo "========================================\n";
echo "摸奖券活动诊断报告\n";
echo "活动ID: $activityId\n";
echo "========================================\n\n";

// 1. 检查活动基本信息
echo "【1】活动基本信息\n";
$activity = LotteryTicketActivity::find($activityId);
if (!$activity) {
    echo "❌ 错误：活动不存在！\n";
    exit(1);
}

echo "活动名称：{$activity->name}\n";
echo "活动状态：{$activity->status} (";
echo match($activity->status) {
    0 => '未开始',
    1 => '进行中',
    2 => '已结束',
    3 => '已关闭',
    5 => '待开奖',
    6 => '开奖中',
    default => '未知'
};
echo ")\n";
echo "部门ID：{$activity->department_id}\n";
echo "开始时间：{$activity->start_time}\n";
echo "结束时间：{$activity->end_time}\n";
echo "当前券号：{$activity->current_ticket_no}\n";
echo "最大券号：{$activity->max_ticket_no}\n";
echo "总发放券数：{$activity->total_tickets}\n\n";

// 2. 检查VIP配置
echo "【2】VIP打码量配置\n";
$vipConfigs = LotteryTicketVipConfig::where('activity_id', $activityId)->get();
if ($vipConfigs->isEmpty()) {
    echo "❌ 严重错误：活动没有配置VIP打码量！\n";
    echo "   原因：没有VIP配置，系统无法知道玩家需要打多少码才能发券\n";
    echo "   解决：在活动管理页面配置VIP打码量\n\n";
} else {
    echo "✅ 找到 {$vipConfigs->count()} 个VIP配置：\n";
    foreach ($vipConfigs as $config) {
        $statusText = $config->status == 1 ? '启用' : '禁用';
        echo "   - VIP等级ID: {$config->vip_level_id}, ";
        echo "打码量: {$config->bet_amount_required}, ";
        echo "发券数: {$config->ticket_count}, ";
        echo "状态: {$statusText}\n";

        if ($config->status != 1) {
            echo "     ⚠️ 警告：此配置已禁用！\n";
        }
    }
    echo "\n";
}

// 3. 检查玩家打码进度记录
echo "【3】玩家打码进度记录\n";
$progressRecords = LotteryTicketBetProgress::where('activity_id', $activityId)->get();
if ($progressRecords->isEmpty()) {
    echo "❌ 严重错误：没有初始化玩家打码进度记录！\n";
    echo "   原因：活动开始时没有调用初始化方法\n";
    echo "   解决：需要手动触发初始化或重新开始活动\n\n";
} else {
    echo "✅ 找到 {$progressRecords->count()} 条打码进度记录\n";
    $activeCount = $progressRecords->where('status', 1)->count();
    $endedCount = $progressRecords->where('status', 0)->count();
    echo "   - 活跃中: {$activeCount}\n";
    echo "   - 已结束: {$endedCount}\n";

    // 显示前5条记录
    echo "   前5条记录：\n";
    foreach ($progressRecords->take(5) as $progress) {
        echo "   玩家ID: {$progress->player_id}, ";
        echo "当前打码: {$progress->current_bet_amount}/{$progress->bet_amount_required}, ";
        echo "已发券: {$progress->total_tickets_issued}\n";
    }
    echo "\n";
}

// 4. 检查游戏记录
echo "【4】活动期间游戏记录\n";
$gameLogStats = Db::table('player_game_log')
    ->select([
        Db::raw('COUNT(*) as total_count'),
        Db::raw('COUNT(DISTINCT player_id) as player_count'),
        Db::raw('SUM(chip_amount) as total_chip')
    ])
    ->where('department_id', $activity->department_id)
    ->where('created_at', '>=', $activity->start_time)
    ->where('created_at', '<=', $activity->end_time)
    ->where('chip_amount', '>', 0)
    ->first();

if ($gameLogStats->total_count == 0) {
    echo "❌ 严重错误：活动期间没有任何游戏记录！\n";
    echo "   原因：玩家在活动期间没有游戏打码\n";
    echo "   解决：确保玩家在活动期间进行游戏\n\n";
} else {
    echo "✅ 找到 {$gameLogStats->total_count} 条游戏记录\n";
    echo "   - 参与玩家数: {$gameLogStats->player_count}\n";
    echo "   - 总打码量: " . number_format($gameLogStats->total_chip, 2) . "\n\n";
}

// 5. 检查已发放的摸奖券
echo "【5】已发放摸奖券统计\n";
$ticketStats = Db::table('lottery_ticket')
    ->select([
        Db::raw('COUNT(*) as total_count'),
        Db::raw('COUNT(DISTINCT player_id) as player_count'),
        Db::raw('MIN(ticket_no) as min_no'),
        Db::raw('MAX(ticket_no) as max_no')
    ])
    ->where('activity_id', $activityId)
    ->first();

if ($ticketStats->total_count == 0) {
    echo "❌ 确认：活动确实没有发放任何摸奖券\n\n";
} else {
    echo "✅ 已发放 {$ticketStats->total_count} 张摸奖券\n";
    echo "   - 获券玩家数: {$ticketStats->player_count}\n";
    echo "   - 券号范围: {$ticketStats->min_no} ~ {$ticketStats->max_no}\n\n";
}

// 6. 检查后台进程配置
echo "【6】后台进程配置检查\n";
$processConfig = include __DIR__ . '/config/process.php';
$hasLotteryScanTask = isset($processConfig['LotteryBetProgressScanTask']);
$hasStatusTransitionTask = isset($processConfig['LotteryActivityStatusTransitionTask']);

echo "打码进度扫描任务 (LotteryBetProgressScanTask): ";
echo $hasLotteryScanTask ? "✅ 已配置\n" : "❌ 未配置\n";

echo "活动状态流转任务 (LotteryActivityStatusTransitionTask): ";
echo $hasStatusTransitionTask ? "✅ 已配置\n" : "❌ 未配置\n";
echo "\n";

// 7. 诊断结论
echo "========================================\n";
echo "【诊断结论】\n";
echo "========================================\n";

$issues = [];

if ($vipConfigs->isEmpty()) {
    $issues[] = "❌ 致命：缺少VIP打码量配置";
}

if ($progressRecords->isEmpty()) {
    $issues[] = "❌ 致命：缺少玩家打码进度记录";
}

if ($gameLogStats->total_count == 0) {
    $issues[] = "⚠️ 警告：活动期间无游戏记录";
}

if (!$hasLotteryScanTask) {
    $issues[] = "❌ 致命：打码进度扫描任务未配置";
}

if (empty($issues)) {
    echo "✅ 所有检查项正常，可能是其他原因导致未发券\n";
    echo "建议：检查后台日志文件 runtime/logs/webman.log\n";
} else {
    echo "发现以下问题：\n\n";
    foreach ($issues as $issue) {
        echo "$issue\n";
    }

    echo "\n【解决建议】\n";

    if ($vipConfigs->isEmpty()) {
        echo "1. 在活动管理页面配置VIP打码量：\n";
        echo "   - 为每个VIP等级设置 bet_amount_required（所需打码量）\n";
        echo "   - 设置 ticket_count（达标后发放的券数）\n";
        echo "   - 确保配置状态为「启用」\n\n";
    }

    if ($progressRecords->isEmpty() && !$vipConfigs->isEmpty()) {
        echo "2. 初始化玩家打码进度记录：\n";
        echo "   - 方法A：重启活动（将状态改为未开始，再改回进行中）\n";
        echo "   - 方法B：手动调用初始化接口\n\n";
    }
}

echo "\n程序执行完毕\n";
