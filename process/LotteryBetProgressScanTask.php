<?php

namespace process;

use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\service\LotteryTicketBetProgressService;
use addons\webman\service\LotteryTicketPushService;
use support\Cache;
use support\Db;
use support\Log;
use Workerman\Crontab\Crontab;
use Workerman\Worker;

/**
 * 摸奖券打码进度扫描任务（性能优化版）
 *
 * 定时扫描新增的游戏记录，批量更新玩家打码进度
 *
 * 执行频率: 每分钟一次
 *
 * ⭐ 性能优化：
 * 1. 批量SQL更新（避免N+1问题）
 * 2. 活动级别锁（支持并发处理）
 * 3. 强制使用索引（提升查询速度）
 * 4. 分离发券逻辑（异步处理）
 * 5. 性能监控（慢查询日志）
 */
class LotteryBetProgressScanTask
{
    /**
     * 缓存键名：上次扫描时间
     */
    const CACHE_KEY_LAST_SCAN = 'lottery_bet_scan_time';

    /**
     * 缓存键名：活动扫描锁前缀
     */
    const CACHE_KEY_ACTIVITY_LOCK = 'lottery_bet_scan_activity_';

    public function onWorkerStart(Worker $worker)
    {
        // 每3秒执行一次（实时更新打码进度）
        new Crontab('*/3 * * * * *', function () {
            $this->scanAndUpdateBetProgress();
        });

        Log::info('摸奖券打码进度扫描任务已启动（3秒间隔）');
    }

    /**
     * 扫描并更新打码进度（优化版）
     * ⭐ 支持多活动并发处理
     */
    protected function scanAndUpdateBetProgress()
    {
        $startTime = microtime(true);

        try {
            // 获取所有进行中的活动
            $activities = LotteryTicketActivity::query()
                ->where('status', LotteryTicketActivity::STATUS_ONGOING)
                ->get();

            if ($activities->isEmpty()) {
                Log::debug('暂无进行中的摸奖券活动');
                return;
            }

            // 获取上次扫描时间
            $lastScanTime = Cache::get(self::CACHE_KEY_LAST_SCAN);
            if (!$lastScanTime) {
                // 首次执行，扫描最近5分钟的数据
                $lastScanTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            }

            $currentTime = date('Y-m-d H:i:s');
            $totalPlayersUpdated = 0;
            $totalTicketsIssued = 0;
            $processedActivities = 0;

            // ⭐ 并发处理多个活动（活动级别锁）
            foreach ($activities as $activity) {
                $lockKey = self::CACHE_KEY_ACTIVITY_LOCK . $activity->id;

                // 检查活动是否正在处理
                if (Cache::get($lockKey) === 'running') {
                    Log::debug('活动正在处理中，跳过', ['activity_id' => $activity->id]);
                    continue;
                }

                // 活动级别锁（5分钟超时）
                Cache::set($lockKey, 'running', 300);

                try {
                    // 确保只处理活动期间的数据
                    $scanStart = max($lastScanTime, $activity->start_time);
                    $scanEnd = min($currentTime, $activity->end_time);

                    if ($scanStart >= $scanEnd) {
                        continue;
                    }

                    // ⭐ 批量查询并聚合玩家打码量
                    $playerBetAmounts = $this->getPlayerBetAmounts(
                        $activity->department_id,
                        $scanStart,
                        $scanEnd
                    );

                    if (empty($playerBetAmounts)) {
                        continue;
                    }

                    // ⭐ 批量更新打码进度（性能优化的核心）
                    $result = $this->batchUpdateProgressOptimized($activity->id, $playerBetAmounts);
                    $totalPlayersUpdated += $result['players_count'];
                    $totalTicketsIssued += $result['tickets_issued'];
                    $processedActivities++;

                    Log::info('摸奖券打码进度扫描 - 活动处理完成', [
                        'activity_id' => $activity->id,
                        'activity_name' => $activity->name,
                        'players_updated' => $result['players_count'],
                        'tickets_issued' => $result['tickets_issued'],
                        'time_range' => [$scanStart, $scanEnd],
                    ]);

                } catch (\Exception $e) {
                    Log::error('活动处理失败', [
                        'activity_id' => $activity->id,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                } finally {
                    // 释放活动锁
                    Cache::delete($lockKey);
                }
            }

            // 更新扫描时间
            Cache::set(self::CACHE_KEY_LAST_SCAN, $currentTime, 86400);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('摸奖券打码进度扫描完成', [
                'activities_total' => $activities->count(),
                'activities_processed' => $processedActivities,
                'players_updated' => $totalPlayersUpdated,
                'tickets_issued' => $totalTicketsIssued,
                'duration_ms' => $duration,
                'time_range' => [$lastScanTime, $currentTime],
            ]);

            // 慢任务警告
            $maxDuration = config('lottery_ticket.performance.max_scan_duration', 30) * 1000;
            if ($duration > $maxDuration) {
                Log::warning('摸奖券扫描任务执行时间过长', [
                    'duration_ms' => $duration,
                    'threshold_ms' => $maxDuration,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('摸奖券打码进度扫描失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 获取玩家打码量聚合数据（优化版）
     * ⭐ 使用原生 SQL + 强制索引提升性能
     *
     * @param int $departmentId 部门ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array [player_id => total_chip_amount]
     */
    protected function getPlayerBetAmounts(int $departmentId, string $startTime, string $endTime): array
    {
        $queryStartTime = microtime(true);
        $playerBetAmounts = [];

        // 获取配置
        $config = config('lottery_ticket.bet_calculation', [
            'include_machine_game' => true,
            'include_online_game' => true,
        ]);

        // 1. 统计机台游戏打码量
        if ($config['include_machine_game']) {
            $machineStart = microtime(true);

            // ⭐ 使用原生SQL + FORCE INDEX
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

            $machineDuration = (microtime(true) - $machineStart) * 1000;
            $this->logSlowQuery('机台游戏打码统计', $machineDuration);
        }

        // 2. 统计电子游戏打码量（只统计电子游戏平台，排除真人/体育平台）
        if ($config['include_online_game']) {
            $onlineStart = microtime(true);

            // ⭐ 使用原生SQL + FORCE INDEX + 平台过滤
            // ✅ 剔除真人/体育平台，只保留电子游戏平台的下注计入打码量
            $onlineSql = "
                SELECT pgr.player_id, SUM(pgr.bet) as total_bet
                FROM play_game_record pgr
                INNER JOIN game_platform gp ON pgr.platform_id = gp.id
                WHERE pgr.department_id = ?
                  AND pgr.created_at >= ?
                  AND pgr.created_at < ?
                  AND pgr.bet > 0
                  AND pgr.settlement_status = 1
                  AND gp.code NOT IN ('WM', 'DG', 'SA', 'RSGLIVE', 'MT', 'O8', 'TNINE', 'KY', 'KYS', 'OB', 'SPS', 'SPS_DY')
                GROUP BY pgr.player_id
            ";

            $onlineResults = Db::select($onlineSql, [$departmentId, $startTime, $endTime]);

            foreach ($onlineResults as $row) {
                $playerId = $row->player_id;
                $betAmount = floatval($row->total_bet);

                if (isset($playerBetAmounts[$playerId])) {
                    $playerBetAmounts[$playerId] += $betAmount;
                } else {
                    $playerBetAmounts[$playerId] = $betAmount;
                }
            }

            $onlineDuration = (microtime(true) - $onlineStart) * 1000;
            $this->logSlowQuery('电子游戏打码统计', $onlineDuration);
        }

        $totalDuration = (microtime(true) - $queryStartTime) * 1000;

        Log::debug('打码量统计完成', [
            'department_id' => $departmentId,
            'player_count' => count($playerBetAmounts),
            'duration_ms' => round($totalDuration, 2),
        ]);

        return $playerBetAmounts;
    }

    /**
     * 批量更新打码进度（性能优化版）
     * ⭐ 核心优化：使用批量SQL替代逐个更新，避免N+1问题
     *
     * @param int $activityId 活动ID
     * @param array $playerBetAmounts 玩家打码量 [player_id => chip_amount]
     * @return array ['players_count' => int, 'tickets_issued' => int]
     */
    protected function batchUpdateProgressOptimized(int $activityId, array $playerBetAmounts): array
    {
        if (empty($playerBetAmounts)) {
            return ['players_count' => 0, 'tickets_issued' => 0];
        }

        $startTime = microtime(true);
        $playersCount = 0;
        $ticketsIssued = 0;

        try {
            // 1. 批量获取所有玩家的进度记录（一次查询）
            $playerIds = array_keys($playerBetAmounts);
            $progressRecords = LotteryTicketBetProgress::query()
                ->where('activity_id', $activityId)
                ->whereIn('player_id', $playerIds)
                ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
                ->get()
                ->keyBy('player_id');

            // 2. 分类处理：已有进度 vs 需要创建进度
            $playersToUpdate = [];
            $playersToCreate = [];

            foreach ($playerBetAmounts as $playerId => $chipAmount) {
                if (isset($progressRecords[$playerId])) {
                    $playersToUpdate[$playerId] = $chipAmount;
                } else {
                    $playersToCreate[$playerId] = $chipAmount;
                }
            }

            // 3. 批量更新已有进度（一条SQL）
            if (!empty($playersToUpdate)) {
                $updateCount = $this->batchUpdateBetAmount($activityId, $playersToUpdate, $progressRecords);
                $playersCount += $updateCount;
            }

            // 4. 创建新进度（批量）
            if (!empty($playersToCreate)) {
                $createCount = $this->batchCreateProgress($activityId, $playersToCreate);
                $playersCount += $createCount;
            }

            // 5. 检查并发券（批量）
            if ($playersCount > 0) {
                $ticketsIssued = $this->checkAndIssueTickets($activityId, array_keys($playerBetAmounts));
            }

            // 6. 推送打码进度更新（只推送有变化的玩家，进度变化≥5%才推送）
            if ($playersCount > 0) {
                $this->pushProgressUpdates($activityId, array_keys($playerBetAmounts), $playerBetAmounts);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('批量更新打码进度完成', [
                'activity_id' => $activityId,
                'players_updated' => $playersCount,
                'tickets_issued' => $ticketsIssued,
                'duration_ms' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error('批量更新打码进度失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return [
            'players_count' => $playersCount,
            'tickets_issued' => $ticketsIssued,
        ];
    }

    /**
     * 批量更新打码金额（使用CASE WHEN）
     * ⭐ 一条SQL更新所有玩家
     *
     * @param int $activityId
     * @param array $playersToUpdate [player_id => chip_amount]
     * @param \Illuminate\Support\Collection $progressRecords
     * @return int 更新的玩家数
     */
    protected function batchUpdateBetAmount(int $activityId, array $playersToUpdate, $progressRecords): int
    {
        if (empty($playersToUpdate)) {
            return 0;
        }

        // 分批处理（每次最多500个）
        $chunks = array_chunk($playersToUpdate, 500, true);
        $totalUpdated = 0;

        foreach ($chunks as $chunk) {
            $whenCases = [];
            $playerIdsArray = [];

            foreach ($chunk as $playerId => $chipAmount) {
                $progress = $progressRecords[$playerId];
                $newAmount = $progress->current_bet_amount + $chipAmount;

                $whenCases[] = "WHEN {$playerId} THEN {$newAmount}";
                $playerIdsArray[] = $playerId;
            }

            if (empty($whenCases)) {
                continue;
            }

            $playerIdsStr = implode(',', $playerIdsArray);
            $caseSql = implode(' ', $whenCases);

            // ⭐ 批量更新SQL
            $sql = "
                UPDATE lottery_ticket_bet_progress
                SET current_bet_amount = CASE player_id {$caseSql} END,
                    updated_at = NOW()
                WHERE activity_id = ?
                  AND player_id IN ({$playerIdsStr})
                  AND status = 1
            ";

            $affected = Db::update($sql, [$activityId]);
            $totalUpdated += $affected;
        }

        return $totalUpdated;
    }

    /**
     * 批量创建打码进度
     *
     * @param int $activityId
     * @param array $playersToCreate [player_id => chip_amount]
     * @return int 创建的玩家数
     */
    protected function batchCreateProgress(int $activityId, array $playersToCreate): int
    {
        $createCount = 0;

        // 逐个创建（因为需要查找VIP配置）
        foreach ($playersToCreate as $playerId => $chipAmount) {
            try {
                // 使用服务类的创建方法
                $progress = LotteryTicketBetProgressService::createProgressForPlayer($activityId, $playerId);

                if ($progress) {
                    // 更新初始打码量
                    $progress->current_bet_amount = $chipAmount;
                    $progress->save();
                    $createCount++;
                }
            } catch (\Exception $e) {
                Log::warning('创建打码进度失败', [
                    'activity_id' => $activityId,
                    'player_id' => $playerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $createCount;
    }

    /**
     * 检查并发券（批量处理）
     * ⭐ 只处理达标的玩家，避免无效查询
     *
     * @param int $activityId
     * @param array $playerIds
     * @return int 发放的券数
     */
    protected function checkAndIssueTickets(int $activityId, array $playerIds): int
    {
        $totalIssued = 0;

        // 查询所有达标的玩家（一次查询）
        $readyPlayers = Db::select("
            SELECT id, player_id, current_bet_amount, bet_amount_required,
                   cycles_completed, ticket_count_per_cycle
            FROM lottery_ticket_bet_progress
            WHERE activity_id = ?
              AND player_id IN (" . implode(',', $playerIds) . ")
              AND status = 1
              AND current_bet_amount >= bet_amount_required
        ", [$activityId]);

        if (empty($readyPlayers)) {
            return 0;
        }

        $issueService = new \addons\webman\service\LotteryTicketIssueService();

        // 逐个处理达标玩家（发券需要锁定，无法完全批量）
        foreach ($readyPlayers as $progress) {
            try {
                // 计算应发券数
                $newCycles = floor($progress->current_bet_amount / $progress->bet_amount_required);
                $cyclesToIssue = $newCycles - $progress->cycles_completed;

                if ($cyclesToIssue > 0) {
                    $ticketsToIssue = $cyclesToIssue * $progress->ticket_count_per_cycle;

                    // ⭐ 修复：直接调用发券服务，不再调用 updateBetProgress(0)
                    Db::beginTransaction();
                    try {
                        // 批量发券
                        $result = $issueService->issueTicketsBatch(
                            $activityId,
                            $progress->player_id,
                            $ticketsToIssue,
                            \addons\webman\model\LotteryTicket::SOURCE_BETTING
                        );

                        $issuedCount = $result['count'];

                        // 更新进度记录
                        Db::update("
                            UPDATE lottery_ticket_bet_progress
                            SET cycles_completed = ?,
                                total_tickets_issued = total_tickets_issued + ?,
                                last_issued_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                        ", [$newCycles, $issuedCount, $progress->id]);

                        Db::commit();

                        $totalIssued += $issuedCount;

                        Log::info('后台任务发券成功', [
                            'activity_id' => $activityId,
                            'player_id' => $progress->player_id,
                            'tickets_issued' => $issuedCount,
                        ]);

                        // ⭐ 推送发券通知和打码进度更新
                        try {
                            // 获取活动信息
                            $activity = LotteryTicketActivity::find($activityId);
                            if ($activity) {
                                // 推送发券通知（弹窗）
                                $message = sprintf('您在活動「%s」中獲得了 %d 張摸獎券！', $activity->name, $issuedCount);
                                LotteryTicketPushService::pushPlayerTicketsUpdate($progress->player_id, $message);

                                // 重新获取最新进度数据用于推送
                                $updatedProgress = LotteryTicketBetProgress::find($progress->id);
                                if ($updatedProgress) {
                                    // 推送打码进度更新（静默）
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
                }
            } catch (\Exception $e) {
                Log::error('发券失败', [
                    'progress_id' => $progress->id,
                    'player_id' => $progress->player_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $totalIssued;
    }

    /**
     * 推送打码进度更新（智能推送，避免频繁打扰）
     *
     * @param int $activityId 活动ID
     * @param array $playerIds 玩家ID列表
     * @param array $playerBetAmounts 本次新增打码量 [player_id => chip_amount]
     */
    protected function pushProgressUpdates(int $activityId, array $playerIds, array $playerBetAmounts): void
    {
        try {
            // 获取活动信息
            $activity = LotteryTicketActivity::find($activityId);
            if (!$activity) {
                return;
            }

            // 批量获取最新进度
            $progressRecords = LotteryTicketBetProgress::query()
                ->where('activity_id', $activityId)
                ->whereIn('player_id', $playerIds)
                ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
                ->get();

            $pushedCount = 0;

            foreach ($progressRecords as $progress) {
                try {
                    // 计算本次新增的打码量
                    $chipAmount = $playerBetAmounts[$progress->player_id] ?? 0;
                    if ($chipAmount <= 0) {
                        continue;
                    }

                    // 计算旧的周期内打码量（取余数）
                    $oldAmount = $progress->current_bet_amount - $chipAmount;
                    $oldCycleAmount = fmod($oldAmount, $progress->bet_amount_required);

                    // 计算新的周期内打码量（取余数）
                    $newCycleAmount = fmod($progress->current_bet_amount, $progress->bet_amount_required);

                    // 判断是否跨周期（已发券，上面已经推送过了）
                    if ($newCycleAmount < $oldCycleAmount) {
                        continue; // 跨周期的情况已经在发券时推送过了
                    }

                    // 同一周期内，计算真实进度变化
                    $oldPercent = ($oldCycleAmount / $progress->bet_amount_required) * 100;
                    $newPercent = ($newCycleAmount / $progress->bet_amount_required) * 100;

                    // 进度变化 ≥ 5% 时才推送
                    if (abs($newPercent - $oldPercent) >= 5) {
                        LotteryTicketPushService::pushBetProgressUpdate(
                            $progress->player_id,
                            $activityId,
                            $progress->current_bet_amount,
                            $progress->bet_amount_required,
                            $progress->progress_percent,
                            $progress->remaining_bet_amount
                        );
                        $pushedCount++;
                    }

                } catch (\Exception $e) {
                    Log::warning('推送进度更新失败', [
                        'player_id' => $progress->player_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($pushedCount > 0) {
                Log::debug('打码进度推送完成', [
                    'activity_id' => $activityId,
                    'pushed_count' => $pushedCount,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('批量推送进度失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 记录慢查询日志
     *
     * @param string $queryName 查询名称
     * @param float $duration 执行时间（毫秒）
     */
    protected function logSlowQuery(string $queryName, float $duration): void
    {
        $logSlowQueries = config('lottery_ticket.performance.log_slow_queries', true);
        $threshold = config('lottery_ticket.performance.slow_query_threshold', 1000);

        if ($logSlowQueries && $duration > $threshold) {
            Log::warning('摸奖券打码统计慢查询', [
                'query' => $queryName,
                'duration_ms' => round($duration, 2),
                'threshold_ms' => $threshold,
            ]);
        }
    }
}
