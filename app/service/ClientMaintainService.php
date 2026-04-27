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
     * 后台修改配置后立即推送通知（不计算时间，直接根据 status 推送）
     *
     * @param int $configId 配置ID
     * @return void
     */
    public function notifyImmediately(int $configId): void
    {
        try {
            /** @var SystemSetting $config */
            $config = SystemSetting::query()
                ->where('id', $configId)
                ->where('feature', 'client_maintain')
                ->first();

            if (!$config) {
                Log::warning('客户端维护配置不存在，无法推送通知', ['config_id' => $configId]);
                return;
            }

            $departmentId = $config->department_id ?? 0;
            $redis = Redis::connection();
            $statusKey = self::REDIS_KEY_STATUS . $departmentId . ':' . $configId;

            // 根据 status 直接判断当前应该推送的状态
            // 1=开始维护，0=结束维护
            $isStarting = $config->status == 1;
            $currentStatus = $isStarting ? 'in_maintenance' : 'not_in_maintenance';

            // 发送通知
            $this->sendMaintenanceNotification($config, $isStarting);

            // 更新状态缓存
            $redis->setex($statusKey, 604800, $currentStatus);

            // 更新维护状态缓存（供客户端查询使用）
            $this->updateStatusCache($departmentId, $isStarting, $config);

            Log::info('后台修改客户端维护配置，立即推送通知', [
                'config_id' => $configId,
                'department_id' => $departmentId,
                'status' => $config->status,
                'notify_status' => $isStarting ? 'start' : 'end',
                'week' => $config->num,
                'time_range' => $config->date_start . ' ~ ' . $config->date_end,
            ]);

        } catch (\Throwable $e) {
            Log::error('立即推送客户端维护通知失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'config_id' => $configId,
            ]);
        }
    }

    /**
     * 处理单个维护配置
     */
    private function processConfig(SystemSetting $config): void
    {
        $departmentId = $config->department_id ?? 0;
        $configId = $config->id;

        // 检查配置是否启用（0-关闭，1-打开）
        $isEnabled = $config->status == 1;

        // 检查是否处于维护时间段（只有启用状态才检查时间）
        $isInMaintenance = $isEnabled && $this->isInMaintenanceTime($config);

        $redis = Redis::connection();

        // 获取上一次的维护状态
        $statusKey = self::REDIS_KEY_STATUS . $departmentId . ':' . $configId;
        $lastStatus = $redis->get($statusKey);

        // 当前状态
        $currentStatus = $isInMaintenance ? 'in_maintenance' : 'not_in_maintenance';

        // 只有状态发生变化时才推送
        if ($lastStatus !== $currentStatus) {
            if ($isInMaintenance) {
                // 状态变为：进入维护
                $this->sendMaintenanceNotification($config, true);

                Log::info('客户端维护开始推送', [
                    'department_id' => $departmentId,
                    'config_id' => $configId,
                    'week' => $config->num,
                    'time_range' => $config->date_start . ' ~ ' . $config->date_end,
                    'last_status' => $lastStatus,
                    'current_status' => $currentStatus,
                ]);
            } else {
                // 状态变为：离开维护（只有从维护状态切换出来才推送）
                if ($lastStatus === 'in_maintenance') {
                    $this->sendMaintenanceNotification($config, false);

                    Log::info('客户端维护结束推送', [
                        'department_id' => $departmentId,
                        'config_id' => $configId,
                        'last_status' => $lastStatus,
                        'current_status' => $currentStatus,
                    ]);
                }
            }

            // 更新状态缓存（7天过期，足够覆盖一个维护周期）
            $redis->setex($statusKey, 604800, $currentStatus);
        }

        // 更新维护状态缓存（供客户端查询使用）
        $this->updateStatusCache($departmentId, $isInMaintenance, $config);
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
