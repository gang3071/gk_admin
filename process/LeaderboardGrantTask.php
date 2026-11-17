<?php

namespace process;

use addons\webman\model\SystemSetting;
use addons\webman\service\LeaderboardService;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * 排行榜：發獎（只發，不算）
 *
 * 每 5 分鐘掃一次「已結算、未發、有獎金」的名次，發到玩家錢包。
 * 結算與發獎分開：結算算錯時，錢還沒發，可修正後再發。
 */
class LeaderboardGrantTask
{
    /**
     * @var \Monolog\Logger|null
     */
    private $log = null;

    public function onWorkerStart()
    {
        $this->log = Log::channel('leaderboard');

        $this->log->info('LeaderboardGrantTask 進程已啟動', [
            'schedule' => '每 5 分鐘',
            'pid' => getmypid(),
        ]);

        // 每 5 分鐘執行一次
        new Crontab('0 */30 * * * *', function () {
            $codes = [];
            $systemSetting = SystemSetting::query()
                ->offDataAuth()
                ->where('feature', 'like', 'leaderboard_%')
                ->where('department_id', 0)
                ->where('status', 1)
                ->get();

            foreach ($systemSetting as $value) {
                $content = json_decode($value->content, true);

                if (! is_array($content)) {
                    // content 为空/坏 JSON 会被静默跳过，榜单永远进不了发奖白名单——必须留痕
                    $this->log->warning('LeaderboardGrantTask 設定 content 非法，已跳過', [
                        'setting_id' => $value->id,
                        'feature' => $value->feature,
                        'content' => mb_substr((string)$value->content, 0, 100),
                    ]);
                    continue;
                }

                $content['automatic'] = (int)($content['automatic'] ?? 0);

                if ($content['automatic'] == 1) {
                    // 与 LeaderboardService::getSettings 的取码规则保持一致（缺 code 时退回 feature），
                    // 否则榜单按 feature 建出、发奖却拿 null 去匹配，永远发不出去
                    $codes[] = (string)($content['code'] ?? $value->feature);
                }
            }

            if (empty($codes)) {
                $this->log->info('LeaderboardGrantTask 沒有需要發獎的排行榜，進程退出');
                return;
            }

            $this->doWork($codes);
        });
    }

    /**
     * 執行發獎
     */
    private function doWork(array $codes): void
    {
        ini_set('memory_limit', '512M');
        $startTime = microtime(true);

        try {
            $result = LeaderboardService::grant($codes);

            // 每轮都记：0 笔也要留痕，否则「跑了但没匹配上」与「根本没跑」无法区分
            $this->log->info('LeaderboardGrantTask 通知完成', [
                'codes' => $codes,
                'matched' => $result['matched'],
                'notified' => $result['notified'],
                'failed' => $result['failed'],
                'elapsed' => round(microtime(true) - $startTime, 3),
            ]);

        } catch (\Throwable $e) {
            $this->log->error('LeaderboardGrantTask 通知異常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'elapsed' => round(microtime(true) - $startTime, 3),
            ]);
        }
    }
}
