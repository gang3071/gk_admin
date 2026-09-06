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

    /**
     * 缓存键名：实时扫描任务锁（防止任务并发执行）
     */
    const CACHE_KEY_TASK_LOCK = 'lottery_bet_scan_task_lock';

    /**
     * 缓存键名：全量扫描任务锁（防止任务并发执行）
     */
    const CACHE_KEY_FULL_SCAN_LOCK = 'lottery_bet_full_scan_lock';

    public function onWorkerStart(Worker $worker)
    {
        // 实时增量扫描（每20秒）- 处理新增打码量
        new Crontab('*/20 * * * * *', function () {
            $this->scanAndUpdateBetProgress();
        });

        // 全量补偿扫描（每1小时）- 重新计算所有打码量，修复遗漏数据
        new Crontab('0 */1 * * *', function () {
            $this->fullScanAndRecalculate();
        });

        Log::info('摸奖券打码进度扫描任务已启动（实时20秒 + 全量1小时双轨制）');
    }

    /**
     * 扫描并更新打码进度（优化版）
     * ⭐ 支持多活动并发处理
     * ⭐ 任务级别锁：防止定时任务并发执行
     */
    protected function scanAndUpdateBetProgress()
    {
        // ⭐ 任务级别锁：防止上次扫描未完成时，下次扫描就开始
        $taskLockKey = self::CACHE_KEY_TASK_LOCK;

        if (Cache::get($taskLockKey) === 'running') {
            Log::warning('上次扫描任务尚未完成，跳过本次扫描', [
                'lock_key' => $taskLockKey,
            ]);
            return;
        }

        // 获取所有进行中的活动（在设锁之前检查）
        $activities = LotteryTicketActivity::query()
            ->where('status', LotteryTicketActivity::STATUS_ONGOING)
            ->get();

        if ($activities->isEmpty()) {
            Log::debug('暂无进行中的摸奖券活动');
            return;
        }

        // 设置任务锁（60秒超时，正常情况下应该远小于20秒）
        Cache::set($taskLockKey, 'running', 60);

        $startTime = microtime(true);

        try {

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
        } finally {
            // ⭐ 释放任务锁（无论成功或失败都要释放）
            Cache::delete($taskLockKey);
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

        // 2. 统计在线游戏打码量（电子游戏 + 真人游戏，排除体育平台）
        if ($config['include_online_game']) {
            $onlineStart = microtime(true);

            // ⭐ 使用原生SQL + FORCE INDEX + 平台过滤
            // ✅ 业务规则（2026-09-06更新）：
            //    - 电子游戏（BTG, RSG, JDB 等）✅ 计入打码量
            //    - 真人游戏（WM, DG, SA 等）✅ 计入打码量（新增）
            //    - 体育投注（KYS, OB, SPS 等）❌ 不计入打码量
            $excludedPlatforms = config('platform_filter.lottery_excluded_platforms', [
                // 默认值（防止配置文件不存在）- 只排除体育平台
                'KYS', 'OB', 'SPS', 'SPS_DY'
            ]);

            // 构建 NOT IN 子句的占位符
            $placeholders = implode(',', array_fill(0, count($excludedPlatforms), '?'));

            $onlineSql = "
                SELECT pgr.player_id, SUM(pgr.bet) as total_bet
                FROM play_game_record pgr
                INNER JOIN game_platform gp ON pgr.platform_id = gp.id
                WHERE pgr.department_id = ?
                  AND pgr.created_at >= ?
                  AND pgr.created_at < ?
                  AND pgr.bet > 0
                  AND pgr.settlement_status = 1
                  AND gp.code NOT IN ({$placeholders})
                GROUP BY pgr.player_id
            ";

            // 合并SQL参数：department_id, start_time, end_time, ...excluded_platforms
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
                    $percentChange = abs($newPercent - $oldPercent);

                    // ⭐ 智能推送策略：
                    // 1. 单次打码 ≥ 10元，或
                    // 2. 进度变化 ≥ 5%
                    $shouldPush = (
                        $chipAmount >= 10 ||   // 单次打码超过10元
                        $percentChange >= 5    // 进度变化≥5%
                    );

                    if ($shouldPush) {
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

    /**
     * 全量扫描并重新计算（每1小时执行）
     *
     * ⚠️ 重要说明：
     * - 使用覆盖方式更新打码量，而不是累加
     * - 从活动开始时间重新统计所有打码量
     * - 自动修复实时扫描可能遗漏的数据
     * - 确保 current_bet_amount 始终准确
     * - ⭐ 任务级别锁：防止全量扫描并发执行
     *
     * 工作原理：
     * 1. 查询活动从开始到现在的所有打码记录
     * 2. 重新计算每个玩家的总打码量
     * 3. 直接设置 current_bet_amount = 计算值（覆盖，不累加）
     * 4. 无论实时扫描是否失败，都能确保数据最终一致
     */
    protected function fullScanAndRecalculate()
    {
        // ⭐ 全量扫描任务锁：防止并发执行
        $fullScanLockKey = self::CACHE_KEY_FULL_SCAN_LOCK;

        if (Cache::get($fullScanLockKey) === 'running') {
            Log::warning('上次全量扫描尚未完成，跳过本次扫描', [
                'lock_key' => $fullScanLockKey,
            ]);
            return;
        }

        Log::info('========== 开始全量扫描并重新计算打码进度 ==========');

        // 获取所有进行中的活动（在设锁之前检查）
        $activities = LotteryTicketActivity::query()
            ->where('status', LotteryTicketActivity::STATUS_ONGOING)
            ->get();

        if ($activities->isEmpty()) {
            Log::info('暂无进行中的活动，跳过全量扫描');
            return;
        }

        // 设置全量扫描锁（30分钟超时，因为全量扫描可能较慢）
        Cache::set($fullScanLockKey, 'running', 1800);

        $startTime = microtime(true);

        try {

            $currentTime = date('Y-m-d H:i:s');
            $totalRecalculated = 0;
            $totalActivities = $activities->count();
            $processedActivities = 0;
            $failedActivities = 0;

            Log::info('全量扫描开始', [
                'total_activities' => $totalActivities,
                'scan_time' => $currentTime,
            ]);

            foreach ($activities as $activity) {
                try {
                    $activityStartTime = microtime(true);

                    // ⭐ 关键：从活动开始到当前时间，重新统计所有打码量
                    $scanStart = $activity->start_time;
                    $scanEnd = min($currentTime, $activity->end_time);

                    if ($scanStart >= $scanEnd) {
                        Log::debug('活动时间窗口无效，跳过', [
                            'activity_id' => $activity->id,
                            'start' => $scanStart,
                            'end' => $scanEnd,
                        ]);
                        continue;
                    }

                    // 查询整个活动期间的所有打码量
                    $playerBetAmounts = $this->getPlayerBetAmounts(
                        $activity->department_id,
                        $scanStart,
                        $scanEnd
                    );

                    if (empty($playerBetAmounts)) {
                        Log::debug('活动暂无打码数据', [
                            'activity_id' => $activity->id,
                            'activity_name' => $activity->name,
                        ]);
                        $processedActivities++;
                        continue;
                    }

                    // ✅ 使用覆盖方式重新计算（核心方法）
                    $recalculated = $this->recalculateBetProgress($activity->id, $playerBetAmounts);
                    $totalRecalculated += $recalculated;
                    $processedActivities++;

                    $activityDuration = round((microtime(true) - $activityStartTime) * 1000, 2);

                    Log::info('活动全量重算完成', [
                        'activity_id' => $activity->id,
                        'activity_name' => $activity->name,
                        'time_range' => [$scanStart, $scanEnd],
                        'players_recalculated' => $recalculated,
                        'duration_ms' => $activityDuration,
                    ]);

                } catch (\Exception $e) {
                    $failedActivities++;
                    Log::error('活动全量重算失败', [
                        'activity_id' => $activity->id,
                        'activity_name' => $activity->name ?? 'Unknown',
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('========== 全量扫描完成 ==========', [
                'total_activities' => $totalActivities,
                'processed_activities' => $processedActivities,
                'failed_activities' => $failedActivities,
                'total_players_recalculated' => $totalRecalculated,
                'duration_ms' => $duration,
            ]);

            // 慢任务警告
            $maxDuration = config('lottery_ticket.performance.max_full_scan_duration', 60) * 1000;
            if ($duration > $maxDuration) {
                Log::warning('全量扫描执行时间过长', [
                    'duration_ms' => $duration,
                    'threshold_ms' => $maxDuration,
                    'suggestion' => '考虑优化数据库索引或调整扫描策略',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('全量扫描失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // ⭐ 释放全量扫描锁（无论成功或失败都要释放）
            Cache::delete($fullScanLockKey);
        }
    }

    /**
     * 重新计算打码进度（覆盖方式）
     *
     * ⚠️ 关键区别：
     * - batchUpdateBetAmount(): 累加方式 (current_bet_amount += chip_amount)
     * - recalculateBetProgress(): 覆盖方式 (current_bet_amount = total_bet_amount)
     *
     * 使用场景：
     * - 全量扫描时使用此方法
     * - 直接设置为从活动开始到现在的总打码量
     * - 自动修复所有历史遗漏的数据
     *
     * @param int $activityId 活动ID
     * @param array $playerBetAmounts 玩家总打码量（从活动开始累计）[player_id => total_bet_amount]
     * @return int 重新计算的玩家数
     */
    protected function recalculateBetProgress(int $activityId, array $playerBetAmounts): int
    {
        if (empty($playerBetAmounts)) {
            return 0;
        }

        $recalculated = 0;

        try {
            // 分批处理（每次最多500个，避免SQL过长）
            $chunks = array_chunk($playerBetAmounts, 500, true);

            foreach ($chunks as $chunk) {
                $whenCases = [];
                $playerIdsArray = [];

                foreach ($chunk as $playerId => $totalBetAmount) {
                    // ⚠️ 安全处理：类型转换 + 精度控制
                    $safePlayerId = (int)$playerId;
                    $safeTotalBet = round((float)$totalBetAmount, 2);

                    // ✅ 关键：直接设置为总打码量（覆盖）
                    $whenCases[] = "WHEN {$safePlayerId} THEN {$safeTotalBet}";
                    $playerIdsArray[] = $safePlayerId;
                }

                if (empty($whenCases)) {
                    continue;
                }

                $playerIdsStr = implode(',', $playerIdsArray);
                $caseSql = implode(' ', $whenCases);

                // ⭐ 核心SQL：直接设置为计算值，而不是累加
                // 对比：
                //   增量更新：current_bet_amount = current_bet_amount + ?
                //   全量覆盖：current_bet_amount = CASE player_id ... END
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

                Log::debug('批次重算完成', [
                    'activity_id' => $activityId,
                    'batch_size' => count($chunk),
                    'affected_rows' => $affected,
                ]);
            }

            // 处理新玩家（数据库中还没有记录的）
            // 查询所有已有记录的玩家
            $existingPlayerIds = LotteryTicketBetProgress::query()
                ->where('activity_id', $activityId)
                ->whereIn('player_id', array_keys($playerBetAmounts))
                ->pluck('player_id')
                ->toArray();

            // 找出新玩家
            $newPlayerIds = array_diff(array_keys($playerBetAmounts), $existingPlayerIds);

            if (!empty($newPlayerIds)) {
                $newPlayersCreated = 0;

                foreach ($newPlayerIds as $playerId) {
                    try {
                        // 创建新的打码进度记录
                        $progress = LotteryTicketBetProgressService::createProgressForPlayer($activityId, $playerId);

                        if ($progress) {
                            // 设置初始打码量
                            $progress->current_bet_amount = round($playerBetAmounts[$playerId], 2);
                            $progress->save();
                            $newPlayersCreated++;
                        }
                    } catch (\Exception $e) {
                        Log::warning('创建新玩家进度失败', [
                            'activity_id' => $activityId,
                            'player_id' => $playerId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $recalculated += $newPlayersCreated;

                Log::info('新玩家进度创建完成', [
                    'activity_id' => $activityId,
                    'new_players' => $newPlayersCreated,
                ]);
            }

            Log::debug('重新计算打码进度完成', [
                'activity_id' => $activityId,
                'total_recalculated' => $recalculated,
            ]);

        } catch (\Exception $e) {
            Log::error('重新计算打码进度失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $recalculated;
    }
}
