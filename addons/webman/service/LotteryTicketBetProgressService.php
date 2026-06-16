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

        // 【P1修复】添加事务保护，确保批量创建的原子性
        Db::beginTransaction();
        try {
            // 为每个VIP等级配置，查找对应的玩家并创建进度记录
            foreach ($vipConfigs as $config) {
                // 查找该渠道下该VIP等级的玩家
                $players = Player::where('department_id', $activity->department_id)
                    ->where('vip_level_id', $config->vip_level_id)
                    ->where('status', Player::STATUS_ENABLE)
                    ->get();

                foreach ($players as $player) {
                    // 【P1修复】使用 firstOrCreate 防止并发重复创建
                    $progress = LotteryTicketBetProgress::firstOrCreate(
                        [
                            'activity_id' => $activityId,
                            'player_id' => $player->id,
                        ],
                        [
                            'department_id' => $activity->department_id,
                            'vip_level_id' => $config->vip_level_id,
                            'bet_amount_required' => $config->bet_amount_required,
                            'ticket_count_per_cycle' => $config->ticket_count,
                            'current_bet_amount' => 0,
                            'cycles_completed' => 0,
                            'total_tickets_issued' => 0,
                            'status' => LotteryTicketBetProgress::STATUS_ACTIVE,
                        ]
                    );

                    if ($progress->wasRecentlyCreated) {
                        $createdCount++;
                    }
                }
            }

            Db::commit();

            Log::info('初始化打码进度完成', [
                'activity_id' => $activityId,
                'created_count' => $createdCount,
            ]);

        } catch (\Exception $e) {
            Db::rollBack();
            Log::error('初始化打码进度失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return 0;
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

        // 查找该玩家参与的所有进行中的活动（预加载活动关联，避免N+1）
        $query = LotteryTicketBetProgress::where('player_id', $playerId)
            ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
            ->with('activity');

        if ($activityId) {
            $query->where('activity_id', $activityId);
        }

        // 只获取ID列表，稍后逐个锁定
        $progressIds = $query->pluck('id')->toArray();

        if (empty($progressIds)) {
            // 如果没有进度记录，尝试为玩家创建
            if ($activityId) {
                self::createProgressForPlayer($activityId, $playerId);
                $progressIds = LotteryTicketBetProgress::where('player_id', $playerId)
                    ->where('activity_id', $activityId)
                    ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
                    ->pluck('id')
                    ->toArray();
            }

            if (empty($progressIds)) {
                return ['success' => false, 'message' => '玩家未参与任何进行中的摸奖券活动'];
            }
        }

        $results = [];

        // 逐个处理进度记录
        foreach ($progressIds as $progressId) {
            // 统一事务管理
            Db::beginTransaction();
            try {
                // 【P0修复】关键修复：锁定进度记录，防止并发更新丢失
                $progress = LotteryTicketBetProgress::where('id', $progressId)
                    ->with('activity')
                    ->lockForUpdate()
                    ->first();

                // 检查记录是否存在（可能被其他事务删除）
                if (!$progress) {
                    Db::rollBack();
                    continue;
                }

                // 检查活动是否仍在进行中
                $activity = $progress->activity;
                if (!$activity || $activity->status !== LotteryTicketActivity::STATUS_ONGOING) {
                    Db::rollBack();
                    continue;
                }

                // ⚠️ 检查是否超过结束时间（超过则停止发券）
                $now = date('Y-m-d H:i:s');
                if ($now >= $activity->end_time) {
                    // 超过结束时间，不再发券，但仍记录打码量
                    $progress->current_bet_amount += $chipAmount;
                    $progress->save();
                    Db::commit();

                    Log::info('活动已超过结束时间，停止发券但记录打码', [
                        'activity_id' => $activity->id,
                        'player_id' => $playerId,
                        'chip_amount' => $chipAmount,
                        'end_time' => $activity->end_time,
                    ]);

                    continue;
                }

                // 1. 更新打码量（已锁定，并发安全）
                $progress->current_bet_amount += $chipAmount;

                // 2. 检查并发券（在同一事务内）
                $issuedCount = 0;
                $firstTicketNo = null;

                if ($progress->canIssueTickets()) {
                    $ticketsToIssue = $progress->getTicketsToIssue();

                    // 发放摸奖券（内部锁定活动）
                    $issueResult = self::issueTickets($progress, $ticketsToIssue);
                    $issuedCount = $issueResult['issued_count'];
                    $firstTicketNo = $issueResult['first_ticket_no'];

                    // 更新周期数和发券数
                    if ($issuedCount > 0) {
                        $newCycles = floor($progress->current_bet_amount / $progress->bet_amount_required);
                        $progress->cycles_completed = $newCycles;
                        $progress->total_tickets_issued += $issuedCount;
                        $progress->last_issued_at = date('Y-m-d H:i:s');
                    }
                }

                // 3. 保存进度（已锁定，并发安全）
                $progress->save();

                // 统一提交
                Db::commit();

                // 4. 事务外推送（不阻塞事务）
                try {
                    $shouldPushProgress = false;

                    // ✅ 达标发券时必须推送进度
                    if ($issuedCount > 0 && $firstTicketNo) {
                        $shouldPushProgress = true;

                        // 查询第一张券用于推送发券通知（弹窗通知）
                        $firstTicket = LotteryTicket::where('activity_id', $activity->id)
                            ->where('player_id', $progress->player_id)
                            ->where('ticket_no', $firstTicketNo)
                            ->first();

                        if ($firstTicket) {
                            LotteryTicketPushService::pushTicketIssued($firstTicket, $issuedCount);
                        }

                        $results[] = [
                            'activity_id' => $progress->activity_id,
                            'activity_name' => $activity->name,
                            'tickets_issued' => $issuedCount,
                            'total_tickets' => $progress->total_tickets_issued,
                        ];
                    }
                    // ✅ 或者进度变化超过5%
                    else {
                        $oldPercent = 0;
                        if ($progress->bet_amount_required > 0) {
                            $oldAmount = $progress->current_bet_amount - $chipAmount;
                            $oldPercent = floor(($oldAmount / $progress->bet_amount_required) * 100);
                        }
                        $newPercent = floor($progress->progress_percent);

                        // 进度变化 ≥ 5% 时才推送
                        if (abs($newPercent - $oldPercent) >= 5) {
                            $shouldPushProgress = true;
                        }
                    }

                    // 推送打码进度更新（静默推送，只在必要时推送）
                    if ($shouldPushProgress) {
                        LotteryTicketPushService::pushBetProgressUpdate(
                            $progress->player_id,
                            $activity->id,
                            $progress->progress_percent,
                            $progress->remaining_bet_amount
                        );
                    }

                } catch (\Exception $e) {
                    Log::warning('推送通知失败', [
                        'player_id' => $progress->player_id,
                        'activity_id' => $activity->id,
                        'error' => $e->getMessage(),
                    ]);
                }

            } catch (\Exception $e) {
                Db::rollBack();
                Log::error('更新打码进度失败', [
                    'player_id' => $playerId,
                    'progress_id' => $progressId,
                    'chip_amount' => $chipAmount,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
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
     * 发放摸奖券（自增序列方式）
     *
     * 券号规则：
     * - 从 000000 开始依次递增
     * - 第1张券：000000
     * - 第2张券：000001
     * - 第15张券：000014
     * - 最后1张券：999999
     *
     * 重要：本方法不开启事务，由调用方统一管理事务
     *
     * @param LotteryTicketBetProgress $progress 进度记录
     * @param int $count 发放数量
     * @return array ['issued_count' => int, 'first_ticket_no' => string|null]
     */
    protected static function issueTickets(LotteryTicketBetProgress $progress, int $count): array
    {
        if ($count <= 0) {
            return ['issued_count' => 0, 'first_ticket_no' => null];
        }

        $activity = $progress->activity;
        if (!$activity) {
            return ['issued_count' => 0, 'first_ticket_no' => null];
        }

        // 锁定活动记录（在外层事务内）
        $activity = LotteryTicketActivity::where('id', $activity->id)
            ->lockForUpdate()
            ->first();

        // 检查是否还有足够的券号
        $currentNo = $activity->current_ticket_no;
        $maxNo = $activity->max_ticket_no;

        if ($currentNo >= $maxNo) {
            Log::warning('摸奖券已发放完毕', [
                'activity_id' => $activity->id,
                'current' => $currentNo,
                'max' => $maxNo,
            ]);
            return ['issued_count' => 0, 'first_ticket_no' => null];
        }

        // 计算实际可发放数量
        $availableCount = $maxNo - $currentNo;
        $actualCount = min($count, $availableCount);

        // 批量准备券数据
        $ticketsData = [];
        $now = date('Y-m-d H:i:s');
        $firstTicketNo = null;

        for ($i = 0; $i < $actualCount; $i++) {
            $ticketNo = str_pad($currentNo + $i, 6, '0', STR_PAD_LEFT);

            if ($i === 0) {
                $firstTicketNo = $ticketNo;
            }

            $ticketsData[] = [
                'activity_id' => $progress->activity_id,
                'player_id' => $progress->player_id,
                'department_id' => $progress->department_id,
                'ticket_no' => $ticketNo,
                'source' => 'betting',
                'status' => LotteryTicket::STATUS_UNUSED,
                'expired_at' => $activity->end_time,  // ✅ 修正字段名
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 批量插入摸奖券
        if (!empty($ticketsData)) {
            LotteryTicket::insert($ticketsData);
        }

        // 更新活动的当前券号
        $activity->current_ticket_no = $currentNo + $actualCount;
        $activity->total_tickets += $actualCount;
        $activity->save();

        return [
            'issued_count' => $actualCount,
            'first_ticket_no' => $firstTicketNo,
        ];
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
