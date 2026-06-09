<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\Player;
use addons\webman\model\PlayerGameLog;
use support\Db;
use support\Log;

/**
 * 摸奖券打码进度服务
 * 负责玩家打码进度追踪和自动发券
 */
class LotteryTicketBetProgressService
{
    /**
     * 初始化玩家的打码进度记录
     * 当活动开始时，为所有符合条件的玩家创建进度记录
     *
     * @param int $activityId 活动ID
     * @return int 创建的记录数
     */
    public static function initializeActivityProgress(int $activityId): int
    {
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return 0;
        }

        // 获取活动的VIP配置
        $vipConfigs = LotteryTicketVipConfig::where('activity_id', $activityId)
            ->where('status', LotteryTicketVipConfig::STATUS_ENABLED)
            ->get();

        if ($vipConfigs->isEmpty()) {
            return 0;
        }

        $createdCount = 0;

        // 为每个VIP等级配置，查找对应的玩家并创建进度记录
        foreach ($vipConfigs as $config) {
            // 查找该渠道下该VIP等级的玩家
            $players = Player::where('department_id', $activity->department_id)
                ->where('vip_level_id', $config->vip_level_id)
                ->where('status', Player::STATUS_ENABLE)
                ->get();

            foreach ($players as $player) {
                // 检查是否已存在进度记录
                $exists = LotteryTicketBetProgress::where('activity_id', $activityId)
                    ->where('player_id', $player->id)
                    ->exists();

                if (!$exists) {
                    LotteryTicketBetProgress::create([
                        'activity_id' => $activityId,
                        'player_id' => $player->id,
                        'department_id' => $activity->department_id,
                        'vip_level_id' => $config->vip_level_id,
                        'bet_amount_required' => $config->bet_amount_required,
                        'ticket_count_per_cycle' => $config->ticket_count,
                        'current_bet_amount' => 0,
                        'cycles_completed' => 0,
                        'total_tickets_issued' => 0,
                        'status' => LotteryTicketBetProgress::STATUS_ACTIVE,
                    ]);
                    $createdCount++;
                }
            }
        }

        return $createdCount;
    }

    /**
     * 更新玩家的打码进度
     * 当玩家产生游戏记录时调用
     *
     * @param int $playerId 玩家ID
     * @param float $chipAmount 本次打码量
     * @param int|null $activityId 指定活动ID（可选，不指定则更新所有进行中的活动）
     * @return array 更新结果
     */
    public static function updateBetProgress(int $playerId, float $chipAmount, ?int $activityId = null): array
    {
        if ($chipAmount <= 0) {
            return ['success' => false, 'message' => '打码量必须大于0'];
        }

        $player = Player::find($playerId);
        if (!$player) {
            return ['success' => false, 'message' => '玩家不存在'];
        }

        // 查找该玩家参与的所有进行中的活动
        $query = LotteryTicketBetProgress::where('player_id', $playerId)
            ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE);

        if ($activityId) {
            $query->where('activity_id', $activityId);
        }

        $progressRecords = $query->get();

        if ($progressRecords->isEmpty()) {
            // 如果没有进度记录，尝试为玩家创建
            if ($activityId) {
                self::createProgressForPlayer($activityId, $playerId);
                $progressRecords = LotteryTicketBetProgress::where('player_id', $playerId)
                    ->where('activity_id', $activityId)
                    ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
                    ->get();
            }

            if ($progressRecords->isEmpty()) {
                return ['success' => false, 'message' => '玩家未参与任何进行中的摸奖券活动'];
            }
        }

        $results = [];

        foreach ($progressRecords as $progress) {
            // 检查活动是否仍在进行中
            $activity = $progress->activity;
            if (!$activity || $activity->status != LotteryTicketActivity::STATUS_ONGOING) {
                continue;
            }

            Db::beginTransaction();
            try {
                // 更新打码量
                $oldBetAmount = $progress->current_bet_amount;
                $progress->current_bet_amount += $chipAmount;

                // 检查是否需要发券
                if ($progress->canIssueTickets()) {
                    $ticketsToIssue = $progress->getTicketsToIssue();

                    // 发放摸奖券
                    $issuedCount = self::issueTickets($progress, $ticketsToIssue);

                    // 更新周期数和发券数
                    $newCycles = floor($progress->current_bet_amount / $progress->bet_amount_required);
                    $progress->cycles_completed = $newCycles;
                    $progress->total_tickets_issued += $issuedCount;
                    $progress->last_issued_at = date('Y-m-d H:i:s');

                    $results[] = [
                        'activity_id' => $progress->activity_id,
                        'activity_name' => $activity->name,
                        'tickets_issued' => $issuedCount,
                        'total_tickets' => $progress->total_tickets_issued,
                    ];
                }

                $progress->save();

                Db::commit();
            } catch (\Exception $e) {
                Db::rollBack();
                Log::error('更新打码进度失败: ' . $e->getMessage(), [
                    'player_id' => $playerId,
                    'activity_id' => $progress->activity_id,
                    'chip_amount' => $chipAmount
                ]);
            }
        }

        return [
            'success' => true,
            'message' => '打码进度更新成功',
            'results' => $results
        ];
    }

    /**
     * 为玩家创建进度记录
     *
     * @param int $activityId 活动ID
     * @param int $playerId 玩家ID
     * @return LotteryTicketBetProgress|null
     */
    protected static function createProgressForPlayer(int $activityId, int $playerId): ?LotteryTicketBetProgress
    {
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return null;
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

        return LotteryTicketBetProgress::create([
            'activity_id' => $activityId,
            'player_id' => $playerId,
            'department_id' => $activity->department_id,
            'vip_level_id' => $player->vip_level_id,
            'bet_amount_required' => $config->bet_amount_required,
            'ticket_count_per_cycle' => $config->ticket_count,
            'current_bet_amount' => 0,
            'cycles_completed' => 0,
            'total_tickets_issued' => 0,
            'status' => LotteryTicketBetProgress::STATUS_ACTIVE,
        ]);
    }

    /**
     * 发放摸奖券
     *
     * @param LotteryTicketBetProgress $progress 进度记录
     * @param int $count 发放数量
     * @return int 实际发放数量
     */
    protected static function issueTickets(LotteryTicketBetProgress $progress, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        $issued = 0;
        $firstTicket = null;

        for ($i = 0; $i < $count; $i++) {
            try {
                $ticket = LotteryTicket::create([
                    'activity_id' => $progress->activity_id,
                    'player_id' => $progress->player_id,
                    'department_id' => $progress->department_id,
                    'ticket_no' => self::generateTicketNo(),
                    'source' => 'betting', // 打码获得
                    'status' => LotteryTicket::STATUS_UNUSED,
                    'expires_at' => $progress->activity->end_time,
                ]);

                if ($i === 0) {
                    $firstTicket = $ticket;
                }

                $issued++;
            } catch (\Exception $e) {
                Log::error('发放摸奖券失败: ' . $e->getMessage());
            }
        }

        // 发送推送通知
        if ($issued > 0 && $firstTicket) {
            LotteryTicketPushService::pushTicketIssued($firstTicket, $issued);
        }

        return $issued;
    }

    /**
     * 生成券号
     * 格式：时间戳后6位 + 随机4位数字
     *
     * @return string
     */
    protected static function generateTicketNo(): string
    {
        $timestamp = substr(time(), -6);
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return $timestamp . $random;
    }

    /**
     * 统计玩家在活动期间的总打码量
     * 用于数据校准或初始化
     *
     * @param int $activityId 活动ID
     * @param int $playerId 玩家ID
     * @return float
     */
    public static function calculateTotalBetAmount(int $activityId, int $playerId): float
    {
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return 0;
        }

        // 统计活动期间的打码量
        return PlayerGameLog::where('player_id', $playerId)
            ->where('department_id', $activity->department_id)
            ->where('created_at', '>=', $activity->start_time)
            ->where('created_at', '<=', $activity->end_time)
            ->sum('chip_amount') ?? 0;
    }

    /**
     * 结束活动的所有进度记录
     *
     * @param int $activityId 活动ID
     * @return int 更新数量
     */
    public static function endActivityProgress(int $activityId): int
    {
        return LotteryTicketBetProgress::where('activity_id', $activityId)
            ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
            ->update(['status' => LotteryTicketBetProgress::STATUS_ENDED]);
    }
}
