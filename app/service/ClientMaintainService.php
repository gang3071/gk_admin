<?php

namespace app\service;

use addons\webman\model\SystemSetting;
use support\Log;
use support\Redis;

/**
 * 客户端维护时间服务
 */
class ClientMaintainService
{
    /**
     * Redis 键前缀
     */
    private const REDIS_KEY_PREFIX = 'client_maintain:';

    /**
     * 维护状态键
     */
    private const REDIS_KEY_STATUS = self::REDIS_KEY_PREFIX . 'status';

    /**
     * 维护通知已发送标记键
     */
    private const REDIS_KEY_NOTIFIED = self::REDIS_KEY_PREFIX . 'notified:';

    /**
     * 检查并处理客户端维护时间
     */
    public function checkAndNotify(): void
    {
        try {
            // 获取所有客户端维护配置（包括关闭的，用于处理状态转换）
            $configs = SystemSetting::query()
                ->where('feature', 'client_maintain')
                ->get();

            if ($configs->isEmpty()) {
                return;
            }

            foreach ($configs as $config) {
                $this->processConfig($config);
            }

        } catch (\Throwable $e) {
            Log::error('检查客户端维护时间失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 处理单个维护配置
     */
    private function processConfig(SystemSetting $config): void
    {
        $departmentId = $config->department_id ?? 0;
        $configKey = $departmentId . ':' . $config->id;

        // 检查配置是否启用（0-关闭，1-打开）
        $isEnabled = $config->status == 1;

        // 检查是否处于维护时间段（只有启用状态才检查时间）
        $isInMaintenance = $isEnabled && $this->isInMaintenanceTime($config);

        // 获取上一次的维护状态
        $redis = Redis::connection();
        $lastStatus = $redis->hGet(self::REDIS_KEY_STATUS, $configKey);
        $notifiedKey = self::REDIS_KEY_NOTIFIED . $configKey;

        // 状态转换：进入维护时间
        if ($isInMaintenance && $lastStatus !== '1') {
            // 检查是否已经发送过通知（避免重复推送）
            $notified = $redis->get($notifiedKey);
            if (!$notified) {
                $this->sendMaintenanceNotification($config, true);
                // 标记已发送，设置过期时间（维护时段内有效）
                $redis->setex($notifiedKey, 3600, '1');
            }
            // 更新状态
            $redis->hSet(self::REDIS_KEY_STATUS, $configKey, '1');

            Log::info('客户端进入维护时间', [
                'department_id' => $departmentId,
                'config_id' => $config->id,
                'status' => $config->status,
                'week' => $config->num,
                'time_range' => $config->date_start . ' ~ ' . $config->date_end,
            ]);
        }
        // 状态转换：离开维护时间
        elseif (!$isInMaintenance && $lastStatus === '1') {
            $this->sendMaintenanceNotification($config, false);
            // 清除已发送标记
            $redis->del($notifiedKey);
            // 更新状态
            $redis->hSet(self::REDIS_KEY_STATUS, $configKey, '0');

            Log::info('客户端离开维护时间', [
                'department_id' => $departmentId,
                'config_id' => $config->id,
                'status' => $config->status,
            ]);
        }
    }

    /**
     * 判断当前时间是否在维护时间段内
     */
    private function isInMaintenanceTime(SystemSetting $config): bool
    {
        // 当前星期几（1-7，1=星期一，7=星期天）
        $currentWeek = (int)date('N');
        $currentTime = date('H:i:s');

        // 检查星期是否匹配
        if ($config->num != $currentWeek) {
            return false;
        }

        // 检查时间段是否匹配
        if (empty($config->date_start) || empty($config->date_end)) {
            return false;
        }

        return $currentTime >= $config->date_start && $currentTime <= $config->date_end;
    }

    /**
     * 发送维护通知
     *
     * @param SystemSetting $config 维护配置
     * @param bool $isStarting 是否开始维护（true=开始，false=结束）
     */
    private function sendMaintenanceNotification(SystemSetting $config, bool $isStarting): void
    {
        try {
            $departmentId = $config->department_id ?? 0;

            // 构建推送消息
            $messageData = [
                'msg_type' => 'client_maintenance',
                'status' => $isStarting ? 'start' : 'end',
                'department_id' => $departmentId,
                'message' => $isStarting
                    ? '系统维护中，请稍后再试'
                    : '系统维护已结束',
                'maintenance_info' => [
                    'week' => $config->num,
                    'start_time' => $config->date_start,
                    'end_time' => $config->date_end,
                ],
                'timestamp' => time(),
            ];

            // 确定推送频道
            // 如果是总配置（department_id=0），推送到所有客户端
            // 如果是渠道配置，推送到对应渠道的客户端
            $channel = $departmentId === 0
                ? 'client-all'
                : 'client-department-' . $departmentId;

            // 发送 WebSocket 推送
            $result = sendSocketMessage($channel, $messageData);

            if ($result) {
                Log::info('发送客户端维护通知成功', [
                    'channel' => $channel,
                    'status' => $isStarting ? 'start' : 'end',
                    'department_id' => $departmentId,
                ]);
            } else {
                Log::warning('发送客户端维护通知失败', [
                    'channel' => $channel,
                    'department_id' => $departmentId,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('发送客户端维护通知异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'config_id' => $config->id,
            ]);
        }
    }

    /**
     * 获取当前维护状态
     *
     * @param int $departmentId 渠道ID（0=总配置）
     * @return array
     */
    public function getMaintenanceStatus(int $departmentId = 0): array
    {
        try {
            $config = SystemSetting::query()
                ->where('feature', 'client_maintain')
                ->where('department_id', $departmentId)
                ->first();

            if (!$config) {
                return [
                    'is_maintenance' => false,
                    'message' => '未配置维护时间',
                ];
            }

            // 检查配置是否启用（0-关闭，1-打开）
            $isEnabled = $config->status == 1;

            // 只有启用状态才检查维护时间
            $isInMaintenance = $isEnabled && $this->isInMaintenanceTime($config);

            return [
                'is_maintenance' => $isInMaintenance,
                'config' => [
                    'status' => $config->status,
                    'week' => $config->num,
                    'start_time' => $config->date_start,
                    'end_time' => $config->date_end,
                ],
                'message' => !$isEnabled
                    ? '维护功能已关闭'
                    : ($isInMaintenance ? '系统维护中' : '系统正常运行'),
            ];

        } catch (\Throwable $e) {
            Log::error('获取维护状态失败', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
            ]);

            return [
                'is_maintenance' => false,
                'message' => '获取维护状态失败',
            ];
        }
    }
}
