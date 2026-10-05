<?php

namespace process;

use addons\webman\service\LeaderboardService;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * 排行榜：週榜結算（只算，不發錢）
 *
 * 每週二 16:00 結算上一週
 * 期區間：上週一 08:00 ~ 本週一 08:00
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
            'schedule' => '每週二 16:00',
            'pid' => getmypid(),
        ]);

        // Cron：秒 分 時 日 月 週（週：0=日 1=一 2=二）
        new Crontab('0 0 16 * * 2', function () {
            $this->doWork();
        });
    }

    /**
     * 執行週榜結算
     */
    private function doWork(): void
    {
        ini_set('memory_limit', '512M');
        $startTime = microtime(true);

        try {
            $result = LeaderboardService::settleWeekly();

            $this->log->info('LeaderboardSettleWeeklyTask 結算完成', [
                'settled' => $result['settled'],
                'skipped' => $result['skipped'],
                'entries' => $result['entries'],
                'elapsed' => round(microtime(true) - $startTime, 3),
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
