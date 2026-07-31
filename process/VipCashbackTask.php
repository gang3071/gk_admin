<?php

namespace process;

use app\service\MachineCashbackService;
use app\service\VipCashbackService;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * VIP反水补算定时任务
 *
 * 功能：
 * - 每1分钟检查一次已结算但未计算反水的游戏记录
 * - 自动补算VIP反水金额（三方游戏 + 机台游戏）
 * - 更新玩家总打码量
 */
class VipCashbackTask
{
    /**
     * @var \Monolog\Logger|null
     */
    private $log = null;

    /**
     * Worker 启动时的回调
     */
    public function onWorkerStart()
    {
        $this->log = Log::channel('vip');

        $this->log->info('VipCashbackTask 进程已启动', [
            'schedule' => '0 */1 * * * *',
            'pid' => getmypid(),
        ]);

        echo "VipCashbackTask: VIP反水补算任务已启动，每1分钟执行一次\n";

        // 进程启动时立即执行一次
        $this->doWork();

        // 每1分钟执行一次（Cron 表达式：秒 分 时 日 月 周）
        new Crontab('0 */1 * * * *', function () {
            $this->doWork();
        });
    }

    /**
     * 执行反水补算
     */
    private function doWork(): void
    {
        ini_set('memory_limit', '512M');

        $startTime = microtime(true);

        // 动态计算起始日期（昨天00:00:00）
        $sinceDate = date('Y-m-d 00:00:00', strtotime('-1 day'));

        try {
            $this->log->info('VipCashbackTask 开始执行', [
                'since_date' => $sinceDate,
                'memory' => memory_get_usage(true),
            ]);

            // 处理三方游戏反水
            $this->log->info('开始处理三方游戏反水');
            $electronicService = new VipCashbackService();
            $electronicService->setSinceDate($sinceDate);
            $electronicResult = $electronicService->execute();

            // 处理机台游戏反水
            $this->log->info('开始处理机台游戏反水');
            $machineService = new MachineCashbackService();
            $machineService->setSinceDate($sinceDate);
            $machineResult = $machineService->execute();

            $elapsed = round(microtime(true) - $startTime, 3);

            $this->log->info('VipCashbackTask 执行完成', [
                '三方游戏' => [
                    'processed' => $electronicResult['processed'],
                    'updated' => $electronicResult['updated'],
                    'skipped' => $electronicResult['skipped'],
                    'errors' => $electronicResult['errors'],
                ],
                '机台游戏' => [
                    'processed' => $machineResult['processed'],
                    'updated' => $machineResult['updated'],
                    'skipped' => $machineResult['skipped'],
                    'errors' => $machineResult['errors'],
                ],
                'elapsed_seconds' => $elapsed,
                'memory_peak' => memory_get_peak_usage(true),
            ]);

            if ($electronicResult['errors'] > 0 || $machineResult['errors'] > 0) {
                $this->log->warning('VipCashbackTask 存在错误', [
                    '三方游戏_errors' => $electronicResult['errors'],
                    '机台游戏_errors' => $machineResult['errors'],
                ]);
            }

            if ($electronicResult['processed'] > 0 || $machineResult['processed'] > 0) {
                echo "[VipCashback] 三方游戏: processed={$electronicResult['processed']}, updated={$electronicResult['updated']}, errors={$electronicResult['errors']}\n";
                echo "[VipCashback] 机台游戏: processed={$machineResult['processed']}, updated={$machineResult['updated']}, errors={$machineResult['errors']}\n";
                echo "[VipCashback] 总耗时: {$elapsed}s\n";
            }

            unset($electronicService, $electronicResult, $machineService, $machineResult);
            gc_collect_cycles();

        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $startTime, 3);

            $this->log->error('VipCashbackTask 执行异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'elapsed_seconds' => $elapsed,
            ]);

            echo "[VipCashback] 执行异常 - Error: {$e->getMessage()}\n";
            gc_collect_cycles();
        }
    }
}
