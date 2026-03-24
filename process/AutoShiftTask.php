<?php

namespace process;

use addons\webman\model\StoreAutoShiftConfig;
use app\service\store\AutoShiftService;
use Workerman\Timer;
use Workerman\Worker;
use support\Log;

/**
 * 自动交班定时任务
 *
 * 功能：
 * - 每分钟检查一次待执行的自动交班配置
 * - 自动执行到期的交班任务
 * - 记录执行日志
 */
class AutoShiftTask
{
    /**
     * Worker 启动时的回调
     */
    public function onWorkerStart(Worker $worker)
    {
        // 每分钟执行一次检查
        Timer::add(60, function() {
            $this->checkAndExecuteAutoShift();
        });

        echo "AutoShiftTask: 自动交班定时任务已启动，每60秒检查一次\n";
    }

    /**
     * 检查并执行自动交班
     */
    private function checkAndExecuteAutoShift(): void
    {
        try {
            /** @var AutoShiftService $service */
            $service = new AutoShiftService();
            $configs = $service->getPendingConfigs();

            if (empty($configs)) {
                return;
            }

            Log::info('检测到待执行的自动交班配置', [
                'count' => count($configs),
                'time' => date('Y-m-d H:i:s')
            ]);

            foreach ($configs as $configData) {
                try {
                    /** @var StoreAutoShiftConfig|null $config */
                    $config = StoreAutoShiftConfig::query()->find($configData['id']);

                    if (!$config || !$config->is_enabled) {
                        continue;
                    }

                    Log::info('开始执行自动交班', [
                        'config_id' => $config->id,
                        'department_id' => $config->department_id,
                        'bind_admin_user_id' => $config->bind_admin_user_id
                    ]);

                    $result = $service->executeAutoShift($config);

                    if ($result['code'] === 0) {
                        echo "[AutoShift] 成功执行自动交班 - Config ID: {$config->id}\n";
                    } else {
                        echo "[AutoShift] 执行失败 - Config ID: {$config->id}, Error: {$result['msg']}\n";
                    }

                } catch (\Exception $e) {
                    Log::error('执行单个自动交班任务失败', [
                        'config_id' => $configData['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    echo "[AutoShift] 执行异常 - Error: {$e->getMessage()}\n";
                }
            }

        } catch (\Exception $e) {
            Log::error('自动交班定时任务执行异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            echo "[AutoShift] 定时任务异常: {$e->getMessage()}\n";
        }
    }
}
