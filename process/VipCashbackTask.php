<?php

namespace process;

use app\service\VipCashbackService;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * VIP反水补算定时任务
 *
 * 功能：
 * - 每5分钟检查一次已结算但未计算反水的游戏记录
 * - 自动补算VIP反水金额
 * - 更新玩家总打码量
 */
class VipCashbackTask
{
    /**
     * Worker 启动时的回调
     */
    public function onWorkerStart()
    {
        // 每5分钟执行一次（Cron 表达式：秒 分 时 日 月 周）
        new Crontab('0 */5 * * * *', function () {
            $this->doWork();
        });

        echo "VipCashbackTask: VIP反水补算任务已启动，每5分钟执行一次\n";
    }

    /**
     * 执行反水补算
     */
    private function doWork(): void
    {
        ini_set('memory_limit', '512M');

        try {
            $service = new VipCashbackService();
            $result = $service->execute();

            if ($result['processed'] > 0) {
                echo "[VipCashback] 处理完成 - processed: {$result['processed']}, updated: {$result['updated']}, skipped: {$result['skipped']}, errors: {$result['errors']}\n";
            }

            unset($service, $result);
            gc_collect_cycles();

        } catch (\Throwable $e) {
            Log::error('VIP反水补算任务异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            echo "[VipCashback] 执行异常 - Error: {$e->getMessage()}\n";
            gc_collect_cycles();
        }
    }
}
