<?php

namespace process;

use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\PlayerGameLog;
use addons\webman\service\LotteryTicketBetProgressService;
use support\Cache;
use support\Db;
use support\Log;
use Workerman\Crontab\Crontab;
use Workerman\Worker;

/**
 * 摸奖券打码进度扫描任务
 * 定时扫描新增的游戏记录，批量更新玩家打码进度
 *
 * 执行频率: 每分钟一次
 * 处理逻辑: 扫描上次执行后的新增游戏记录，聚合后批量更新
 */
class LotteryBetProgressScanTask
{
    /**
     * 缓存键名：上次扫描时间
     */
    const CACHE_KEY_LAST_SCAN = 'lottery_bet_scan_time';

    /**
     * 缓存键名：扫描任务状态
     */
    const CACHE_KEY_TASK_STATUS = 'lottery_bet_scan_status';

    public function onWorkerStart(Worker $worker)
    {
        // 每分钟执行一次（避开整点，减少服务器压力）
        new Crontab('23 * * * * *', function () {
            $this->scanAndUpdateBetProgress();
        });

        Log::info('摸奖券打码进度扫描任务已启动');
    }

    /**
     * 扫描并更新打码进度
     */
    protected function scanAndUpdateBetProgress()
    {
        $startTime = microtime(true);

        try {
            // 检查是否有任务正在执行（防止并发）
            if (Cache::get(self::CACHE_KEY_TASK_STATUS) === 'running') {
                Log::warning('摸奖券打码进度扫描任务正在执行，跳过本次');
                return;
            }

            // 标记任务开始
            Cache::set(self::CACHE_KEY_TASK_STATUS, 'running', 300);

            // 获取所有进行中的活动
            $activities = LotteryTicketActivity::where('status', LotteryTicketActivity::STATUS_ONGOING)
                ->get();

            if ($activities->isEmpty()) {
                Log::debug('暂无进行中的摸奖券活动');
                Cache::delete(self::CACHE_KEY_TASK_STATUS);
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

            foreach ($activities as $activity) {
                // 确保只处理活动期间的数据
                $scanStart = max($lastScanTime, $activity->start_time);
                $scanEnd = min($currentTime, $activity->end_time);

                if ($scanStart >= $scanEnd) {
                    continue;
                }

                // 批量查询并聚合玩家打码量
                $playerBetAmounts = $this->getPlayerBetAmounts(
                    $activity->department_id,
                    $scanStart,
                    $scanEnd
                );

                if (empty($playerBetAmounts)) {
                    continue;
                }

                // 批量更新打码进度
                $result = $this->batchUpdateProgress($activity->id, $playerBetAmounts);
                $totalPlayersUpdated += $result['players_count'];
                $totalTicketsIssued += $result['tickets_issued'];

                Log::info('摸奖券打码进度扫描 - 活动处理完成', [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'players_updated' => $result['players_count'],
                    'tickets_issued' => $result['tickets_issued'],
                    'time_range' => [$scanStart, $scanEnd],
                ]);
            }

            // 更新扫描时间
            Cache::set(self::CACHE_KEY_LAST_SCAN, $currentTime, 86400);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('摸奖券打码进度扫描完成', [
                'activities_count' => $activities->count(),
                'players_updated' => $totalPlayersUpdated,
                'tickets_issued' => $totalTicketsIssued,
                'duration_ms' => $duration,
                'time_range' => [$lastScanTime, $currentTime],
            ]);

        } catch (\Exception $e) {
            Log::error('摸奖券打码进度扫描失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // 标记任务完成
            Cache::delete(self::CACHE_KEY_TASK_STATUS);
        }
    }

    /**
     * 获取玩家打码量聚合数据
     *
     * @param int $departmentId 部门ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array [player_id => total_chip_amount]
     */
    protected function getPlayerBetAmounts(int $departmentId, string $startTime, string $endTime): array
    {
        $results = Db::table('player_game_log')
            ->select(['player_id', Db::raw('SUM(chip_amount) as total_chip')])
            ->where('department_id', $departmentId)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<', $endTime)
            ->where('chip_amount', '>', 0)
            ->groupBy('player_id')
            ->get();

        $playerBetAmounts = [];
        foreach ($results as $row) {
            $playerBetAmounts[$row->player_id] = floatval($row->total_chip);
        }

        return $playerBetAmounts;
    }

    /**
     * 批量更新打码进度
     *
     * @param int $activityId 活动ID
     * @param array $playerBetAmounts 玩家打码量 [player_id => chip_amount]
     * @return array ['players_count' => int, 'tickets_issued' => int]
     */
    protected function batchUpdateProgress(int $activityId, array $playerBetAmounts): array
    {
        $playersCount = 0;
        $ticketsIssued = 0;

        foreach ($playerBetAmounts as $playerId => $chipAmount) {
            try {
                $result = LotteryTicketBetProgressService::updateBetProgress(
                    $playerId,
                    $chipAmount,
                    $activityId
                );

                if ($result['success']) {
                    $playersCount++;

                    // 统计发放的摸奖券数量
                    foreach ($result['results'] ?? [] as $activityResult) {
                        $ticketsIssued += $activityResult['tickets_issued'] ?? 0;
                    }
                }

            } catch (\Exception $e) {
                Log::error('单个玩家打码进度更新失败', [
                    'player_id' => $playerId,
                    'activity_id' => $activityId,
                    'chip_amount' => $chipAmount,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'players_count' => $playersCount,
            'tickets_issued' => $ticketsIssued,
        ];
    }
}
