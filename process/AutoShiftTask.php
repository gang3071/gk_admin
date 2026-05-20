<?php

namespace process;

use addons\webman\model\StoreAutoShiftConfig;
use app\service\store\AutoShiftService;
use support\Log;
use Workerman\Crontab\Crontab;

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
    public function onWorkerStart()
    {
        // 每分钟执行一次检查（Cron 表达式：秒 分 时 日 月 周）
        new Crontab('0 */1 * * * *', function () {
            $this->checkAndExecuteAutoShift();
        });

        echo "AutoShiftTask: 自动交班定时任务已启动，每分钟检查一次\n";
    }

    /**
     * 检查并执行自动交班
     */
    private function checkAndExecuteAutoShift(): void
    {
        // ✅ 设置内存限制，防止无限增长
        ini_set('memory_limit', '512M');

        try {
            /** @var AutoShiftService $service */
            $service = new AutoShiftService();
            $configs = $service->getPendingConfigs();

            if (empty($configs)) {
                // ✅ 显式释放变量
                unset($service, $configs);
                // ✅ 强制垃圾回收
                gc_collect_cycles();
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
                        // ✅ 及时释放不需要的模型实例
                        unset($config);
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

                    // ✅ 循环内释放大对象
                    unset($config, $result);

                } catch (\Exception $e) {
                    Log::error('执行单个自动交班任务失败', [
                        'config_id' => $configData['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    echo "[AutoShift] 执行异常 - Error: {$e->getMessage()}\n";
                }
            }

            // ✅ 任务完成后显式释放所有大对象
            unset($service, $configs);

            // ✅ 强制 PHP 垃圾回收
            gc_collect_cycles();

        } catch (\Exception $e) {
            Log::error('自动交班定时任务执行异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // ✅ 异常情况也要清理内存
            gc_collect_cycles();
        }
    }
}
