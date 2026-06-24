<?php
/**
 * 修复被错误标记为过期的摸奖券
 *
 * 问题：之前的逻辑根据 expired_at 字段判断，导致活动在 PENDING_DRAW 和 DRAWING 状态时券被错误标记为过期
 * 修复：将活动未结束（ONGOING、PENDING_DRAW、DRAWING）的过期券恢复为未使用状态
 */

require_once __DIR__ . '/support/bootstrap.php';

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use support\Db;
use support\Log;

echo "\n=== 修复被错误标记为过期的摸奖券 ===\n\n";

try {
    // 1. 查询活动仍在进行中、待开奖、开奖中的活动ID
    $activeActivityIds = LotteryTicketActivity::query()
        ->whereIn('status', [
            LotteryTicketActivity::STATUS_ONGOING,        // 1 进行中
            LotteryTicketActivity::STATUS_PENDING_DRAW,   // 2 待开奖
            LotteryTicketActivity::STATUS_DRAWING,        // 3 开奖中
        ])
        ->pluck('id')
        ->toArray();

    if (empty($activeActivityIds)) {
        echo "✅ 没有活跃的活动，无需修复\n";
        exit(0);
    }

    echo "找到 " . count($activeActivityIds) . " 个活跃活动（进行中/待开奖/开奖中）\n";
    echo "活动ID: " . implode(', ', $activeActivityIds) . "\n\n";

    // 2. 查询这些活动下被错误标记为过期的券
    $wronglyExpiredTickets = LotteryTicket::query()
        ->whereIn('activity_id', $activeActivityIds)
        ->where('status', LotteryTicket::STATUS_EXPIRED)  // 状态=2（已过期）
        ->get(['id', 'activity_id', 'ticket_no', 'player_id']);

    if ($wronglyExpiredTickets->isEmpty()) {
        echo "✅ 没有被错误标记为过期的券\n";
        exit(0);
    }

    echo "找到 " . $wronglyExpiredTickets->count() . " 张被错误标记为过期的券\n\n";

    // 按活动分组显示
    $groupedByActivity = $wronglyExpiredTickets->groupBy('activity_id');
    foreach ($groupedByActivity as $activityId => $tickets) {
        $activity = LotteryTicketActivity::find($activityId);
        $statusName = match($activity->status) {
            1 => '进行中',
            2 => '待开奖',
            3 => '开奖中',
            default => '未知'
        };
        echo "  活动 #{$activityId} ({$activity->name}) [{$statusName}]: {$tickets->count()} 张券\n";
    }

    // 3. 确认是否修复
    echo "\n是否将这些券恢复为「未使用」状态？(y/n): ";
    $input = trim(fgets(STDIN));

    if (strtolower($input) !== 'y') {
        echo "已取消修复\n";
        exit(0);
    }

    // 4. 批量修复
    Db::beginTransaction();
    try {
        $ticketIds = $wronglyExpiredTickets->pluck('id')->toArray();

        $count = LotteryTicket::query()
            ->whereIn('id', $ticketIds)
            ->update(['status' => LotteryTicket::STATUS_UNUSED]);

        Db::commit();

        echo "\n✅ 修复成功！\n";
        echo "   恢复了 {$count} 张券为「未使用」状态\n";

        Log::info('[摸奖券] 修复被错误标记为过期的券', [
            'fixed_count' => $count,
            'activity_ids' => $activeActivityIds,
        ]);

    } catch (\Exception $e) {
        Db::rollBack();
        echo "\n❌ 修复失败：" . $e->getMessage() . "\n";
        Log::error('[摸奖券] 修复过期券失败', [
            'error' => $e->getMessage(),
        ]);
        exit(1);
    }

} catch (\Exception $e) {
    echo "\n❌ 执行失败：" . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== 修复完成 ===\n\n";
