<?php

namespace process;

use app\service\GamePlatformMaintainService;
use Workerman\Timer;
use Workerman\Worker;
use support\Log;

/**
 * 游戏平台维护时间监听任务
 *
 * 功能：
 * - 每分钟检查一次游戏平台维护时间配置
 * - 到达维护时间自动推送 WebSocket 通知给客户端
 * - 维护结束时推送恢复通知
 */
class GamePlatformMaintainTask
{
    /**
     * Worker 启动时的回调
     */
    public function onWorkerStart(Worker $worker): void
    {
        // 每分钟检查一次维护时间
        Timer::add(60, function() {
            $this->checkMaintenanceTime();
        });

        echo "GamePlatformMaintainTask: 游戏平台维护时间监听任务已启动，每60秒检查一次\n";

        // 启动时立即执行一次检查
        $this->checkMaintenanceTime();
    }

    /**
     * 检查维护时间并发送通知
     */
    private function checkMaintenanceTime(): void
    {
        try {
            $service = new GamePlatformMaintainService();
            $service->checkAndNotify();

        } catch (\Throwable $e) {
            Log::error('游戏平台维护时间检查异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            echo "[GamePlatformMaintain] 检查异常: {$e->getMessage()}\n";
        }
    }
}
