<?php

namespace process;

use addons\webman\service\LeaderboardService;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * 排行榜：週榜（即時刷新當期 + 定案已結束的期；只算，不發錢）
 *
 * 每 1 分鐘刷新「本週進行中」的名次，並定案已結束的週（測試用，上線前改回 30 分鐘）
 * 期區間：本週一 08:00 ~ 下週一 08:00
 */
class LeaderboardSettleWeeklyTask
{
    /**
     * @var \Monolog\Logger|null
     */
    private $log = null;

    public function onWorkerStart()
    {
        $this->log = Log::channel('leaderboard');

        $this->log->info('LeaderboardSettleWeeklyTask 進程已啟動', [
            'schedule' => '每 1 分鐘',
            'pid' => getmypid(),
        ]);

        // Cron：秒 分 時 日 月 週（每 1 分鐘，測試用）
        new Crontab('0 */30 * * * *', function () {
            $this->doWork();
        });
    }

    /**
     * 執行週榜刷新（當期）+ 定案（已結束）
     */
    private function doWork(): void
    {
        ini_set('memory_limit', '512M');
        $startTime = microtime(true);

        try {
            $result = LeaderboardService::settleWeekly();

            $this->log->info('LeaderboardSettleWeeklyTask 結算完成', [
                'refreshed' => $result['refreshed'],
                'finalized' => $result['finalized'],
                'skipped'   => $result['skipped'],
                'entries'   => $result['entries'],
                'elapsed'   => round(microtime(true) - $startTime, 3),
            ]);

        } catch (\Throwable $e) {
            $this->log->error('LeaderboardSettleWeeklyTask 結算異常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'elapsed' => round(microtime(true) - $startTime, 3),
            ]);
        }
    }
}
