<?php

namespace process;

use addons\webman\service\LeaderboardService;
use Carbon\Carbon;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * 排行榜：月榜結算（只算，不發錢）
 *
 * 每月第一個週二 16:00 結算上個月
 * 期區間：上月 1 號 08:00 ~ 本月 1 號 08:00
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
            'schedule' => '每月第一個週二 16:00',
            'pid' => getmypid(),
        ]);

        // 每週二 16:00 觸發，實際只在本月第一個週二執行（見 doWork）
        new Crontab('0 0 16 * * 2', function () {
            $this->doWork();
        });
    }

    /**
     * 執行月榜結算
     */
    private function doWork(): void
    {
        // 只在本月第一個週二執行（週二觸發 + 日期 <= 7）
        if ((int)Carbon::now('Asia/Taipei')->day > 7) {
            return;
        }

        ini_set('memory_limit', '512M');
        $startTime = microtime(true);

        try {
            $result = LeaderboardService::settleMonthly();

            $this->log->info('LeaderboardSettleMonthlyTask 結算完成', [
                'settled' => $result['settled'],
                'skipped' => $result['skipped'],
                'entries' => $result['entries'],
                'elapsed' => round(microtime(true) - $startTime, 3),
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
