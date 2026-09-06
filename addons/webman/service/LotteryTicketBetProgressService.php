<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\Player;

/**
 * 摸奖券打码进度服务
 * 负责玩家打码进度追踪和自动发券
 *
 * ⚠️ 重要说明：
 * 本服务现在主要由后台扫描任务（LotteryBetProgressScanTask）调用
 * 后台任务每20秒扫描一次，批量处理打码进度和发券
 */
class LotteryTicketBetProgressService
{

    /**
     * 为玩家创建进度记录
     * ⭐ 改为 public，供 LotteryBetProgressScanTask 调用
     *
     * @param int $activityId 活动ID
     * @param int $playerId 玩家ID
     * @return LotteryTicketBetProgress|null
     */
    public static function createProgressForPlayer(int $activityId, int $playerId): ?LotteryTicketBetProgress
    {
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->status !== LotteryTicketActivity::STATUS_ONGOING) {
            return null;
        }

        // ⚠️ 检查是否超过结束时间
        $now = date('Y-m-d H:i:s');
        if ($now >= $activity->end_time) {
            return null;  // 超过结束时间，不创建新进度
        }

        $player = Player::find($playerId);
        if (!$player) {
            return null;
        }

        // 查找该玩家VIP等级对应的配置
        $config = LotteryTicketVipConfig::where('activity_id', $activityId)
            ->where('vip_level_id', $player->vip_level_id)
            ->where('status', LotteryTicketVipConfig::STATUS_ENABLED)
            ->first();

        if (!$config) {
            return null;
        }

        // 【P1修复】使用 firstOrCreate 防止并发重复创建
        return LotteryTicketBetProgress::firstOrCreate(
            [
                'activity_id' => $activityId,
                'player_id' => $playerId,
            ],
            [
                'department_id' => $activity->department_id,
                'vip_level_id' => $player->vip_level_id,
                'bet_amount_required' => $config->bet_amount_required,
                'ticket_count_per_cycle' => $config->ticket_count,
                'current_bet_amount' => 0,
                'cycles_completed' => 0,
                'total_tickets_issued' => 0,
                'status' => LotteryTicketBetProgress::STATUS_ACTIVE,
            ]
        );
    }

    /**
     * 结束活动的所有进度记录
     *
     * @param int $activityId 活动ID
     * @return int 更新数量
     */
    public static function endActivityProgress(int $activityId): int
    {
        return LotteryTicketBetProgress::query()->where('activity_id', $activityId)
            ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
            ->update(['status' => LotteryTicketBetProgress::STATUS_ENDED]);
    }
}
