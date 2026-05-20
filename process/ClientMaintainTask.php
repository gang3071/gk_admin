<?php

namespace process;

use app\service\ClientMaintainService;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * 客户端维护时间监听任务
 *
 * 功能：
 * - 每分钟检查一次客户端维护时间配置
 * - 到达维护时间自动推送 WebSocket 通知给客户端
 * - 维护结束时推送恢复通知
 */
class ClientMaintainTask
{
    /**
     * Worker 启动时的回调
     */
    public function onWorkerStart(): void
    {
        // 每分钟检查一次维护时间（Cron 表达式：秒 分 时 日 月 周）
        new Crontab('0 */1 * * * *', function () {
            $this->checkMaintenanceTime();
        });

        echo "ClientMaintainTask: 客户端维护时间监听任务已启动，每分钟检查一次\n";

        // 启动时立即执行一次检查
        $this->checkMaintenanceTime();
    }

    /**
     * 检查维护时间并发送通知
     */
    private function checkMaintenanceTime(): void
    {
        // ✅ 设置内存限制，防止无限增长
        ini_set('memory_limit', '512M');

        try {
            $service = new ClientMaintainService();
            $service->checkAndNotify();

            // ✅ 显式释放 Service 实例
            unset($service);

            // ✅ 强制垃圾回收
            gc_collect_cycles();

        } catch (\Throwable $e) {
            Log::error('客户端维护时间检查异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // ✅ 异常情况也要清理内存
            gc_collect_cycles();
        }
    }
}
