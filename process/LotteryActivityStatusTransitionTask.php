<?php

namespace process;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\service\LotteryTicketBetProgressService;
use addons\webman\service\LotteryTicketIssueService;
use addons\webman\service\LotteryTicketPushService;
use support\Db;
use support\Log;
use Workerman\Crontab\Crontab;
use Workerman\Worker;

/**
 * 摸奖券活动状态自动流转任务
 * 定时检查活动状态并自动更新
 *
 * 执行频率: 每5秒一次
 * 处理逻辑: 检查活动时间节点，自动更新状态
 *
 * 状态流转规则（新增"待开奖"状态）:
 * 1. 活动开始 → STATUS_ONGOING (start_time 到达，自动)
 * 2. 活动结束 → STATUS_PENDING_DRAW (end_time 到达，自动进入待开奖，停止发券)
 * 3. 开始开奖 → STATUS_DRAWING (管理员手动点击"开奖"按钮)
 * 4. 完全结束 → STATUS_ENDED (管理员手动点击"停止开奖"按钮)
 *
 * 注意：
 * - end_time 到达后自动进入待开奖状态（STATUS_PENDING_DRAW），停止发券
 * - 待开奖状态需要管理员手动触发开奖
 * - 开奖期可由管理员手动结束
 * - 预热期、打码中、已开奖待发放等状态已废弃
 * - draw_time 字段已删除
 */
class LotteryActivityStatusTransitionTask
{
    public function onWorkerStart(Worker $worker)
    {
        // 每5秒执行一次（更及时的状态流转）
        new Crontab('*/5 * * * * *', function () {
            $this->checkAndTransitionStatus();
        });

        Log::info('摸奖券活动状态流转任务已启动（每5秒执行）');
    }

    /**
     * 检查并流转活动状态
     */
    protected function checkAndTransitionStatus()
    {
        try {
            $now = date('Y-m-d H:i:s');
            $transitionCount = 0;

            // 获取所有未结束的活动（包含待开奖状态）
            $activities = LotteryTicketActivity::whereIn('status', [
                LotteryTicketActivity::STATUS_NOT_STARTED,
                LotteryTicketActivity::STATUS_ONGOING,
                LotteryTicketActivity::STATUS_PENDING_DRAW,
            ])->get();

            foreach ($activities as $activity) {
                $oldStatus = $activity->status;
                $newStatus = $this->determineNewStatus($activity, $now);

                if ($newStatus !== $oldStatus) {
                    $this->performTransition($activity, $newStatus);
                    $transitionCount++;

                    Log::info('摸奖券活动状态自动流转', [
                        'activity_id' => $activity->id,
                        'activity_name' => $activity->name,
                        'old_status' => LotteryTicketActivity::getStatusText($oldStatus),
                        'new_status' => LotteryTicketActivity::getStatusText($newStatus),
                        'time' => $now,
                    ]);
                }
            }

            if ($transitionCount > 0) {
                Log::info('摸奖券活动状态流转完成', [
                    'total_transitions' => $transitionCount,
                    'time' => $now,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('摸奖券活动状态流转失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 判断活动应该处于的状态
     *
     * @param LotteryTicketActivity $activity
     * @param string $now
     * @return int
     */
    protected function determineNewStatus(LotteryTicketActivity $activity, string $now): int
    {
        // 已结束/已关闭/开奖中的活动不再自动流转
        if (in_array($activity->status, [
            LotteryTicketActivity::STATUS_ENDED,
            LotteryTicketActivity::STATUS_CLOSED,
            LotteryTicketActivity::STATUS_DRAWING,
        ])) {
            return $activity->status;
        }

        // 1. 检查是否超过结束时间 → 进入待开奖
        if ($now >= $activity->end_time) {
            return LotteryTicketActivity::STATUS_PENDING_DRAW;
        }

        // 2. 检查是否应该进入进行中
        if ($now >= $activity->start_time && $now < $activity->end_time) {
            return LotteryTicketActivity::STATUS_ONGOING;
        }

        // 3. 默认保持未开始状态
        return LotteryTicketActivity::STATUS_NOT_STARTED;
    }

    /**
     * 执行状态流转
     *
     * @param LotteryTicketActivity $activity
     * @param int $newStatus
     */
    protected function performTransition(LotteryTicketActivity $activity, int $newStatus)
    {
        $oldStatus = $activity->status;

        // 记录状态变更历史
        $activity->recordStatusChange($newStatus, '系统自动流转');
        $activity->save();

        // 根据状态变化执行特定操作
        switch ($newStatus) {
            case LotteryTicketActivity::STATUS_ONGOING:
                // 活动开始（进行中）
                $this->onActivityStart($activity);
                break;

            case LotteryTicketActivity::STATUS_PENDING_DRAW:
                // 进入待开奖，停止发券
                $this->onPendingDraw($activity);
                break;

            case LotteryTicketActivity::STATUS_DRAWING:
                // 开奖期开始（由管理员手动触发）
                $this->onDrawingStart($activity);
                break;

            case LotteryTicketActivity::STATUS_ENDED:
                // 活动结束，清理资源
                $this->onActivityEnd($activity);
                break;
        }
    }

    /**
     * 活动开始（进行中）
     * @param LotteryTicketActivity $activity
     */
    protected function onActivityStart(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动开始', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
        ]);

        // 发送活动开始通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'activity_start');
    }

    /**
     * 进入待开奖状态
     * @param LotteryTicketActivity $activity
     */
    protected function onPendingDraw(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动进入待开奖状态', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
            'end_time' => $activity->end_time,
        ]);

        // ⭐ 关键：在停止打码进度之前，先做最后一次全量扫描
        // 原因：实时扫描间隔20秒，活动结束前最后20秒的打码量可能未被统计
        // 确保活动期间所有打码量都被正确计算
        $this->performFinalScan($activity);

        // 停止所有玩家的打码进度（不再发券）
        LotteryTicketBetProgressService::endActivityProgress($activity->id);

        // 发送待开奖通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'pending_draw');

        // ⭐ 推送券数更新（活动结束后券失效，玩家有效券数减少）
        \addons\webman\service\LotteryTicketPushService::pushActivityPlayersTicketsUpdate(
            $activity->id,
            sprintf('活動「%s」已結束', $activity->name)
        );
    }

    /**
     * 开奖期开始（管理员手动触发）
     * @param LotteryTicketActivity $activity
     */
    protected function onDrawingStart(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动开奖期开始', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
        ]);

        // 停止所有玩家的打码进度（不再发券）
        LotteryTicketBetProgressService::endActivityProgress($activity->id);

        // 发送开奖通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'drawing_start');

        // ⭐ 推送券数更新
        \addons\webman\service\LotteryTicketPushService::pushActivityPlayersTicketsUpdate(
            $activity->id,
            sprintf('活動「%s」開始開獎', $activity->name)
        );
    }

    /**
     * 活动结束
     * @param LotteryTicketActivity $activity
     */
    protected function onActivityEnd(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动结束', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
            'total_tickets' => $activity->total_tickets,
            'used_tickets' => $activity->used_tickets,
        ]);

        // 确保所有打码进度已结束
        LotteryTicketBetProgressService::endActivityProgress($activity->id);

        // 发送活动结束通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'ended');

        // ⭐ 推送券数更新
        \addons\webman\service\LotteryTicketPushService::pushActivityPlayersTicketsUpdate(
            $activity->id,
            sprintf('活動「%s」已完全結束', $activity->name)
        );
    }

    /**
     * 执行活动结束前的最后一次全量扫描
     *
     * ⚠️ 关键说明：
     * - 实时扫描间隔20秒，活动结束前最后20秒的打码可能未被统计
     * - 此方法确保活动期间所有打码量都被正确计入
     * - 使用覆盖方式重新计算，从活动开始到结束的所有打码
     * - ✅ 重新计算后检查并发放摸奖券（新增）
     *
     * 场景示例：
     * 23:59:40 - 最后一次实时扫描
     * 23:59:45 - 玩家A打了500元 ⚠️ 未被扫描
     * 23:59:50 - 玩家A达标应发券，但未发 ⚠️
     * 23:59:59 - 活动结束
     * 24:00:00 - 此方法执行：
     *            1. 重新计算打码量（500元被统计）✅
     *            2. 检查并发券（玩家A获得券）✅
     *
     * @param LotteryTicketActivity $activity
     */
    protected function performFinalScan(LotteryTicketActivity $activity)
    {
        $startTime = microtime(true);

        try {
            Log::info('========== 活动结束前最后全量扫描开始 ==========', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
            ]);

            // 从活动开始到结束，重新统计所有打码量
            $playerBetAmounts = $this->getPlayerBetAmounts(
                $activity->department_id,
                $activity->start_time,
                $activity->end_time
            );

            if (empty($playerBetAmounts)) {
                Log::info('活动期间无打码数据', [
                    'activity_id' => $activity->id,
                ]);
                return;
            }

            // Step 1: 使用覆盖方式重新计算打码进度
            $recalculated = $this->recalculateBetProgress($activity->id, $playerBetAmounts);

            // Step 2: ⭐ 检查并发放摸奖券（关键！）
            $ticketsIssued = $this->checkAndIssueTickets($activity->id, array_keys($playerBetAmounts));

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('========== 活动结束前最后全量扫描完成 ==========', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'players_recalculated' => $recalculated,
                'total_players' => count($playerBetAmounts),
                'tickets_issued' => $ticketsIssued,  // ✅ 新增
                'duration_ms' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error('活动结束前最后全量扫描失败', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 获取玩家打码量聚合数据
     * （复用LotteryBetProgressScanTask的逻辑）
     *
     * @param int $departmentId
     * @param string $startTime
     * @param string $endTime
     * @return array [player_id => total_chip_amount]
     */
    protected function getPlayerBetAmounts(int $departmentId, string $startTime, string $endTime): array
    {
        $playerBetAmounts = [];

        // 1. 统计机台游戏打码量
        $machineSql = "
            SELECT player_id, SUM(chip_amount) as total_chip
            FROM player_game_log
            WHERE department_id = ?
              AND created_at >= ?
              AND created_at < ?
              AND chip_amount > 0
            GROUP BY player_id
        ";

        $machineResults = Db::select($machineSql, [$departmentId, $startTime, $endTime]);

        foreach ($machineResults as $row) {
            $playerBetAmounts[$row->player_id] = floatval($row->total_chip);
        }

        // 2. 统计在线游戏打码量（电子游戏 + 真人游戏，排除体育平台）
        $excludedPlatforms = config('platform_filter.lottery_excluded_platforms', [
            'KYS', 'OB', 'SPS', 'SPS_DY'
        ]);

        if (empty($excludedPlatforms)) {
            $excludedPlatforms = [''];
        }

        $placeholders = implode(',', array_fill(0, count($excludedPlatforms), '?'));

        $onlineSql = "
            SELECT player_id, SUM(chip_amount) as total_bet
            FROM player_platform_game_log
            WHERE department_id = ?
              AND created_at >= ?
              AND created_at < ?
              AND chip_amount > 0
              AND platform_code NOT IN ({$placeholders})
            GROUP BY player_id
        ";

        $params = array_merge(
            [$departmentId, $startTime, $endTime],
            $excludedPlatforms
        );

        $onlineResults = Db::select($onlineSql, $params);

        foreach ($onlineResults as $row) {
            $playerId = $row->player_id;
            $betAmount = floatval($row->total_bet);

            if (isset($playerBetAmounts[$playerId])) {
                $playerBetAmounts[$playerId] += $betAmount;
            } else {
                $playerBetAmounts[$playerId] = $betAmount;
            }
        }

        return $playerBetAmounts;
    }

    /**
     * 重新计算打码进度（覆盖方式）
     * （复用LotteryBetProgressScanTask的逻辑）
     *
     * @param int $activityId
     * @param array $playerBetAmounts [player_id => total_bet_amount]
     * @return int
     */
    protected function recalculateBetProgress(int $activityId, array $playerBetAmounts): int
    {
        if (empty($playerBetAmounts)) {
            return 0;
        }

        $recalculated = 0;

        try {
            // 分批处理（每次最多500个）
            $chunks = array_chunk($playerBetAmounts, 500, true);

            foreach ($chunks as $chunk) {
                $whenCases = [];
                $playerIdsArray = [];

                foreach ($chunk as $playerId => $totalBetAmount) {
                    $safePlayerId = (int)$playerId;
                    $safeTotalBet = round((float)$totalBetAmount, 2);

                    $whenCases[] = "WHEN {$safePlayerId} THEN {$safeTotalBet}";
                    $playerIdsArray[] = $safePlayerId;
                }

                if (empty($whenCases)) {
                    continue;
                }

                $playerIdsStr = implode(',', $playerIdsArray);
                $caseSql = implode(' ', $whenCases);

                // 覆盖式更新
                $sql = "
                    UPDATE lottery_ticket_bet_progress
                    SET current_bet_amount = CASE player_id {$caseSql} END,
                        updated_at = NOW()
                    WHERE activity_id = ?
                      AND player_id IN ({$playerIdsStr})
                      AND status = " . LotteryTicketBetProgress::STATUS_ACTIVE . "
                ";

                $affected = Db::update($sql, [$activityId]);
                $recalculated += $affected;
            }

            // 处理新玩家（数据库中还没有记录的）
            $existingPlayerIds = LotteryTicketBetProgress::query()
                ->where('activity_id', $activityId)
                ->whereIn('player_id', array_keys($playerBetAmounts))
                ->pluck('player_id')
                ->toArray();

            $newPlayerIds = array_diff(array_keys($playerBetAmounts), $existingPlayerIds);

            if (!empty($newPlayerIds)) {
                foreach ($newPlayerIds as $playerId) {
                    try {
                        $progress = LotteryTicketBetProgressService::createProgressForPlayer($activityId, $playerId);

                        if ($progress) {
                            $progress->current_bet_amount = round($playerBetAmounts[$playerId], 2);
                            $progress->save();
                            $recalculated++;
                        }
                    } catch (\Exception $e) {
                        Log::warning('创建新玩家进度失败', [
                            'activity_id' => $activityId,
                            'player_id' => $playerId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('重新计算打码进度失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
        }

        return $recalculated;
    }

    /**
     * 检查并发券（批量处理）
     *
     * ⚠️ 关键说明：
     * - 在重新计算打码量后调用此方法
     * - 查询所有达标玩家并发放摸奖券
     * - 使用乐观锁防止重复发券
     *
     * @param int $activityId 活动ID
     * @param array $playerIds 玩家ID列表
     * @return int 发放的券数
     */
    protected function checkAndIssueTickets(int $activityId, array $playerIds): int
    {
        if (empty($playerIds)) {
            return 0;
        }

        $totalIssued = 0;

        try {
            // 安全处理：类型转换
            $safePlayerIds = array_map('intval', $playerIds);
            $playerIdsStr = implode(',', $safePlayerIds);

            // 查询所有达标的玩家（打码量已达标且未发完券）
            $readyPlayers = Db::select("
                SELECT id, player_id, current_bet_amount, bet_amount_required,
                       cycles_completed, ticket_count_per_cycle
                FROM lottery_ticket_bet_progress
                WHERE activity_id = ?
                  AND player_id IN ({$playerIdsStr})
                  AND status = " . LotteryTicketBetProgress::STATUS_ACTIVE . "
                  AND current_bet_amount >= bet_amount_required
                  AND FLOOR(current_bet_amount / bet_amount_required) > cycles_completed
            ", [$activityId]);

            if (empty($readyPlayers)) {
                Log::debug('无达标玩家需要发券', [
                    'activity_id' => $activityId,
                    'checked_players' => count($playerIds),
                ]);
                return 0;
            }

            $issueService = new LotteryTicketIssueService();

            Log::info('开始发券', [
                'activity_id' => $activityId,
                'ready_players_count' => count($readyPlayers),
            ]);

            // 逐个处理达标玩家
            foreach ($readyPlayers as $progress) {
                try {
                    // 计算应发券数
                    $newCycles = floor($progress->current_bet_amount / $progress->bet_amount_required);
                    $cyclesToIssue = $newCycles - $progress->cycles_completed;

                    if ($cyclesToIssue <= 0) {
                        continue;
                    }

                    $ticketsToIssue = $cyclesToIssue * $progress->ticket_count_per_cycle;

                    Db::beginTransaction();
                    try {
                        // 批量发券
                        $result = $issueService->issueTicketsBatch(
                            $activityId,
                            $progress->player_id,
                            $ticketsToIssue,
                            LotteryTicket::SOURCE_BETTING
                        );

                        $issuedCount = $result['count'];

                        // ⭐ 使用乐观锁更新进度记录
                        $affected = Db::update("
                            UPDATE lottery_ticket_bet_progress
                            SET cycles_completed = ?,
                                total_tickets_issued = total_tickets_issued + ?,
                                last_issued_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                              AND cycles_completed = ?
                        ", [$newCycles, $issuedCount, $progress->id, $progress->cycles_completed]);

                        if ($affected === 0) {
                            // 乐观锁冲突，其他进程已经发券
                            Db::rollBack();
                            Log::warning('发券冲突：其他进程已发券', [
                                'progress_id' => $progress->id,
                                'player_id' => $progress->player_id,
                            ]);
                            continue;
                        }

                        Db::commit();

                        $totalIssued += $issuedCount;

                        Log::info('活动结束前发券成功', [
                            'activity_id' => $activityId,
                            'player_id' => $progress->player_id,
                            'tickets_issued' => $issuedCount,
                            'new_cycles' => $newCycles,
                        ]);

                        // 推送发券通知
                        try {
                            $activity = LotteryTicketActivity::find($activityId);
                            if ($activity) {
                                // 推送发券通知（弹窗）
                                $message = sprintf('您在活動「%s」中獲得了 %d 張摸獎券！', $activity->name, $issuedCount);
                                LotteryTicketPushService::pushPlayerTicketsUpdate($progress->player_id, $message);

                                // 推送打码进度更新（静默）
                                $updatedProgress = LotteryTicketBetProgress::find($progress->id);
                                if ($updatedProgress) {
                                    LotteryTicketPushService::pushBetProgressUpdate(
                                        $progress->player_id,
                                        $activityId,
                                        $updatedProgress->current_bet_amount,
                                        $updatedProgress->bet_amount_required,
                                        $updatedProgress->progress_percent,
                                        $updatedProgress->remaining_bet_amount
                                    );
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('发券推送失败', [
                                'player_id' => $progress->player_id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                    } catch (\Exception $e) {
                        Db::rollBack();
                        throw $e;
                    }

                } catch (\Exception $e) {
                    Log::error('单个玩家发券失败', [
                        'progress_id' => $progress->id,
                        'player_id' => $progress->player_id,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            if ($totalIssued > 0) {
                Log::info('活动结束前发券完成', [
                    'activity_id' => $activityId,
                    'total_tickets_issued' => $totalIssued,
                    'ready_players' => count($readyPlayers),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('检查并发券失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $totalIssued;
    }
}
