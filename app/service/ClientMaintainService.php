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
     * 维护通知已发送标记键
     */
    private const REDIS_KEY_NOTIFIED = self::REDIS_KEY_PREFIX . 'notified:';

    /**
     * 维护状态缓存键（缓存格式：client_maintain:status:{departmentId}）
     */
    private const REDIS_KEY_STATUS = self::REDIS_KEY_PREFIX . 'status:';

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

        $redis = Redis::connection();

        // 更新维护状态缓存
        $this->updateStatusCache($departmentId, $isInMaintenance, $config);

        if ($isInMaintenance) {
            // 在维护时间段内，推送开始维护通知（防重复推送）
            $notifiedKey = self::REDIS_KEY_NOTIFIED . $configKey . ':start';

            if (!$redis->get($notifiedKey)) {
                $this->sendMaintenanceNotification($config, true);

                // 缓存时间 = 维护时段长度，确保维护期间不重复推送
                $duration = $this->getMaintenanceDuration($config);
                $redis->setex($notifiedKey, $duration, '1');

                Log::info('客户端维护开始推送', [
                    'department_id' => $departmentId,
                    'config_id' => $config->id,
                    'week' => $config->num,
                    'time_range' => $config->date_start . ' ~ ' . $config->date_end,
                    'cache_duration' => $duration,
                ]);
            }
        } else {
            // 不在维护时间段内，推送结束维护通知（防重复推送）
            $notifiedKey = self::REDIS_KEY_NOTIFIED . $configKey . ':end';

            if (!$redis->get($notifiedKey)) {
                $this->sendMaintenanceNotification($config, false);

                // 缓存较短时间，避免频繁推送 end
                $redis->setex($notifiedKey, 300, '1');

                Log::info('客户端维护结束推送', [
                    'department_id' => $departmentId,
                    'config_id' => $config->id,
                ]);
            }
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
     * 计算维护时段长度（秒）
     */
    private function getMaintenanceDuration(SystemSetting $config): int
    {
        $start = strtotime($config->date_start);
        $end = strtotime($config->date_end);

        if ($start === false || $end === false) {
            return 3600; // 默认 1 小时
        }

        $duration = $end - $start;

        // 最少 60 秒，最多 24 小时
        return max(60, min($duration, 86400));
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
     * 更新维护状态缓存
     *
     * @param int $departmentId 渠道ID
     * @param bool $isInMaintenance 是否在维护中
     * @param SystemSetting $config 维护配置
     */
    private function updateStatusCache(int $departmentId, bool $isInMaintenance, SystemSetting $config): void
    {
        try {
            $cacheKey = self::REDIS_KEY_STATUS . $departmentId;
            $cacheData = [
                'is_maintenance' => $isInMaintenance,
                'config' => [
                    'status' => $config->status,
                    'week' => $config->num,
                    'start_time' => $config->date_start,
                    'end_time' => $config->date_end,
                ],
                'message' => $config->status != 1
                    ? '维护功能已关闭'
                    : ($isInMaintenance ? '系统维护中' : '系统正常运行'),
                'updated_at' => time(),
            ];

            $redis = Redis::connection();
            // 缓存 5 分钟（与定时任务检查频率一致）
            $redis->setex($cacheKey, 300, json_encode($cacheData));

        } catch (\Throwable $e) {
            Log::error('更新维护状态缓存失败', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
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
            // 优先从缓存读取
            $cacheKey = self::REDIS_KEY_STATUS . $departmentId;
            $redis = Redis::connection();
            $cached = $redis->get($cacheKey);

            if ($cached) {
                $data = json_decode($cached, true);
                if ($data && isset($data['is_maintenance'])) {
                    return $data;
                }
            }

            // 缓存未命中，查询数据库
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

            $result = [
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

            // 回写缓存
            $this->updateStatusCache($departmentId, $isInMaintenance, $config);

            return $result;

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
