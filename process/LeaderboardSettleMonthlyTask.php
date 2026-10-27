<?php

namespace process;

use addons\webman\model\SystemSetting;
use addons\webman\service\LeaderboardService;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * 排行榜：月榜（即時刷新當期 + 定案已結束的期；只算，不發錢）
 *
 * 每 1 分鐘刷新「本月進行中」的名次，並定案已結束的月（測試用，上線前改回 30 分鐘）
 * 期區間：本月 1 號 08:00 ~ 下月 1 號 08:00
 */
class LeaderboardSettleMonthlyTask
{
    /**
     * @var \Monolog\Logger|null
     */
    private $log = null;

    public function onWorkerStart()
    {
        $this->log = Log::channel('leaderboard');

        $this->log->info('LeaderboardSettleMonthlyTask 進程已啟動', [
            'schedule' => '每 1 分鐘',
            'pid' => getmypid(),
        ]);

        // Cron：秒 分 時 日 月 週（每 1 分鐘，測試用）
        new Crontab('0 * * * * *', function () {
            $departmentIds = SystemSetting::query()
                ->offDataAuth()
                ->where('feature', 'leaderboard_switch')
                ->where('status', 1)
                ->pluck('department_id')
                ->toArray();

            if (empty($departmentIds)) {
                $this->log->info('LeaderboardSettleMonthlyTask 排行榜開關未啟用，進程退出');
                return;
            }

            $this->doWork($departmentIds);
        });
    }

    /**
     * 執行月榜刷新（當期）+ 定案（已結束）
     */
    private function doWork(array $departmentIds): void
    {
        ini_set('memory_limit', '512M');
        $startTime = microtime(true);

        try {
            $result = LeaderboardService::settleMonthly($departmentIds);

            $this->log->info('LeaderboardSettleMonthlyTask 結算完成', [
                'refreshed' => $result['refreshed'],
                'finalized' => $result['finalized'],
                'skipped'   => $result['skipped'],
                'entries'   => $result['entries'],
                'elapsed'   => round(microtime(true) - $startTime, 3),
            ]);

        } catch (\Throwable $e) {
            $this->log->error('LeaderboardSettleMonthlyTask 結算異常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'elapsed' => round(microtime(true) - $startTime, 3),
            ]);
        }
    }
}
