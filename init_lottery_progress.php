<?php
/**
 * 手动初始化摸奖券活动的玩家打码进度
 *
 * 使用方法：
 * php init_lottery_progress.php 17
 */

require_once __DIR__ . '/support/bootstrap.php';

use addons\webman\service\LotteryTicketBetProgressService;
use addons\webman\model\LotteryTicketActivity;

$activityId = $argv[1] ?? null;

if (!$activityId) {
    echo "使用方法：php init_lottery_progress.php <活动ID>\n";
    echo "例如：php init_lottery_progress.php 17\n";
    exit(1);
}

echo "开始初始化活动 ID {$activityId} 的玩家打码进度...\n\n";

// 检查活动是否存在
$activity = LotteryTicketActivity::find($activityId);
if (!$activity) {
    echo "❌ 错误：活动ID {$activityId} 不存在！\n";
    exit(1);
}

echo "活动名称：{$activity->name}\n";
echo "活动状态：{$activity->status}\n";
echo "开始时间：{$activity->start_time}\n";
echo "结束时间：{$activity->end_time}\n\n";

// 调用初始化方法
try {
    $count = LotteryTicketBetProgressService::initializeActivityProgress($activityId);

    if ($count > 0) {
        echo "✅ 成功！共初始化了 {$count} 条玩家打码进度记录\n\n";

        // 显示前5条
        echo "前5条记录：\n";
        $records = \addons\webman\model\LotteryTicketBetProgress::where('activity_id', $activityId)
            ->with('player:id,name,uuid')
            ->take(5)
            ->get();

        foreach ($records as $record) {
            echo "  - 玩家ID: {$record->player_id}";
            if ($record->player) {
                echo " ({$record->player->name})";
            }
            echo ", VIP等级: {$record->vip_level_id}";
            echo ", 需打码: {$record->bet_amount_required}";
            echo ", 达标发券: {$record->ticket_count_per_cycle}\n";
        }

        echo "\n初始化完成！\n";
        echo "现在玩家打码后，系统会自动累加打码量并发放摸奖券。\n";
    } else {
        echo "⚠️ 警告：没有创建任何打码进度记录\n";
        echo "可能的原因：\n";
        echo "1. 活动没有配置VIP打码量\n";
        echo "2. 该渠道下没有符合条件的玩家\n";
        echo "3. 玩家VIP等级与配置不匹配\n";
    }

} catch (\Exception $e) {
    echo "❌ 初始化失败：{$e->getMessage()}\n";
    echo "文件：{$e->getFile()}\n";
    echo "行号：{$e->getLine()}\n";
    exit(1);
}
