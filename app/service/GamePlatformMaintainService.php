<?php

namespace app\service;

use addons\webman\model\GamePlatform;
use support\Log;
use support\Redis;

/**
 * 游戏平台维护时间服务
 */
class GamePlatformMaintainService
{
    /**
     * Redis 键前缀
     */
    private const REDIS_KEY_PREFIX = 'game_platform_maintain:';

    /**
     * 维护通知已发送标记键（已废弃，改用状态缓存）
     */
    private const REDIS_KEY_NOTIFIED = self::REDIS_KEY_PREFIX . 'notified:';

    /**
     * 维护状态缓存键（记录当前维护状态：in_maintenance / not_in_maintenance）
     */
    private const REDIS_KEY_STATUS = self::REDIS_KEY_PREFIX . 'status:';

    /**
     * 检查并处理游戏平台维护时间
     */
    public function checkAndNotify(): void
    {
        try {
            // 获取所有游戏平台（包括关闭的，用于处理状态转换）
            $platforms = GamePlatform::query()
                ->whereNull('deleted_at')
                ->get();

            if ($platforms->isEmpty()) {
                return;
            }

            foreach ($platforms as $platform) {
                $this->processPlatform($platform);
            }

        } catch (\Throwable $e) {
            Log::error('检查游戏平台维护时间失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 后台修改配置后立即推送通知（不计算时间，直接根据 maintenance_status 推送）
     *
     * @param int $platformId 游戏平台ID
     * @return void
     */
    public function notifyImmediately(int $platformId): void
    {
        try {
            /** @var GamePlatform $platform */
            $platform = GamePlatform::query()
                ->where('id', $platformId)
                ->whereNull('deleted_at')
                ->first();

            if (!$platform) {
                Log::warning('游戏平台不存在，无法推送维护通知', ['platform_id' => $platformId]);
                return;
            }

            $redis = Redis::connection();
            $statusKey = self::REDIS_KEY_STATUS . $platformId;

            // 根据 maintenance_status 直接判断当前应该推送的状态
            // 1=开始维护，0=结束维护
            $isStarting = $platform->maintenance_status == 1;
            $currentStatus = $isStarting ? 'in_maintenance' : 'not_in_maintenance';

            // 发送通知
            $this->sendMaintenanceNotification($platform, $isStarting);

            // 更新状态缓存
            $redis->setex($statusKey, 604800, $currentStatus);

            Log::info('后台修改配置，立即推送维护通知', [
                'platform_id' => $platformId,
                'platform_code' => $platform->code,
                'platform_name' => $platform->name,
                'maintenance_status' => $platform->maintenance_status,
                'status' => $isStarting ? 'start' : 'end',
                'week' => $platform->maintenance_week,
                'time_range' => $platform->maintenance_start_time . ' ~ ' . $platform->maintenance_end_time,
            ]);

        } catch (\Throwable $e) {
            Log::error('立即推送维护通知失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'platform_id' => $platformId,
            ]);
        }
    }

    /**
     * 处理单个游戏平台维护配置
     */
    private function processPlatform(GamePlatform $platform): void
    {
        $platformId = $platform->id;
        $platformCode = $platform->code;

        // 检查维护功能是否启用（0-关闭，1-打开）
        $isEnabled = $platform->maintenance_status == 1;

        // 检查是否处于维护时间段（只有启用状态才检查时间）
        $isInMaintenance = $isEnabled && $this->isInMaintenanceTime($platform);

        $redis = Redis::connection();

        // 获取上一次的维护状态
        $statusKey = self::REDIS_KEY_STATUS . $platformId;
        $lastStatus = $redis->get($statusKey);

        // 当前状态
        $currentStatus = $isInMaintenance ? 'in_maintenance' : 'not_in_maintenance';

        // 只有状态发生变化时才推送
        if ($lastStatus !== $currentStatus) {
            if ($isInMaintenance) {
                // 状态变为：进入维护
                $this->sendMaintenanceNotification($platform, true);

                Log::info('游戏平台进入维护，推送开始通知', [
                    'platform_id' => $platformId,
                    'platform_code' => $platformCode,
                    'platform_name' => $platform->name,
                    'week' => $platform->maintenance_week,
                    'time_range' => $platform->maintenance_start_time . ' ~ ' . $platform->maintenance_end_time,
                    'last_status' => $lastStatus,
                    'current_status' => $currentStatus,
                ]);
            } else {
                // 状态变为：离开维护（只有从维护状态切换出来才推送）
                if ($lastStatus === 'in_maintenance') {
                    $this->sendMaintenanceNotification($platform, false);

                    Log::info('游戏平台离开维护，推送结束通知', [
                        'platform_id' => $platformId,
                        'platform_code' => $platformCode,
                        'platform_name' => $platform->name,
                        'last_status' => $lastStatus,
                        'current_status' => $currentStatus,
                    ]);
                }
            }

            // 更新状态缓存（7天过期，足够覆盖一个维护周期）
            $redis->setex($statusKey, 604800, $currentStatus);
        }
    }

    /**
     * 判断当前时间是否在维护时间段内
     */
    private function isInMaintenanceTime(GamePlatform $platform): bool
    {
        // 当前星期几（1-7，1=星期一，7=星期天）
        $currentWeek = (int)date('N');
        $currentTime = date('H:i:s');

        // 检查星期是否匹配
        if ($platform->maintenance_week != $currentWeek) {
            return false;
        }

        // 检查时间段是否匹配
        if (empty($platform->maintenance_start_time) || empty($platform->maintenance_end_time)) {
            return false;
        }

        return $currentTime >= $platform->maintenance_start_time && $currentTime <= $platform->maintenance_end_time;
    }

    /**
     * 计算维护时段长度（秒）
     */
    private function getMaintenanceDuration(GamePlatform $platform): int
    {
        $start = strtotime($platform->maintenance_start_time);
        $end = strtotime($platform->maintenance_end_time);

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
     * @param GamePlatform $platform 游戏平台
     * @param bool $isStarting 是否开始维护（true=开始，false=结束）
     */
    private function sendMaintenanceNotification(GamePlatform $platform, bool $isStarting): void
    {
        try {
            // 构建推送消息
            $messageData = [
                'msg_type' => 'game_platform_maintenance',
                'status' => $isStarting ? 'start' : 'end',
                'platform_id' => $platform->id,
                'platform_code' => $platform->code,
                'platform_name' => $platform->name,
                'message' => $isStarting
                    ? $platform->name . ' 维护中，请稍后再试'
                    : $platform->name . ' 维护已结束',
                'maintenance_info' => [
                    'week' => $platform->maintenance_week,
                    'start_time' => $platform->maintenance_start_time,
                    'end_time' => $platform->maintenance_end_time,
                ],
                'timestamp' => time(),
            ];

            // 推送到所有客户端
            $channel = 'client-all';

            // 发送 WebSocket 推送
            $result = sendSocketMessage($channel, $messageData);

            if ($result) {
                Log::info('发送游戏平台维护通知成功', [
                    'channel' => $channel,
                    'status' => $isStarting ? 'start' : 'end',
                    'platform_id' => $platform->id,
                    'platform_code' => $platform->code,
                ]);
            } else {
                Log::warning('发送游戏平台维护通知失败', [
                    'channel' => $channel,
                    'platform_id' => $platform->id,
                    'platform_code' => $platform->code,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('发送游戏平台维护通知异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'platform_id' => $platform->id,
            ]);
        }
    }

    /**
     * 获取游戏平台当前维护状态
     *
     * @param int $platformId 游戏平台ID
     * @return array
     */
    public function getMaintenanceStatus(int $platformId): array
    {
        try {
            $platform = GamePlatform::query()
                ->where('id', $platformId)
                ->whereNull('deleted_at')
                ->first();

            if (!$platform) {
                return [
                    'is_maintenance' => false,
                    'message' => '游戏平台不存在',
                ];
            }

            // 检查维护功能是否启用（0-关闭，1-打开）
            $isEnabled = $platform->maintenance_status == 1;

            // 只有启用状态才检查维护时间
            $isInMaintenance = $isEnabled && $this->isInMaintenanceTime($platform);

            return [
                'is_maintenance' => $isInMaintenance,
                'platform_info' => [
                    'id' => $platform->id,
                    'code' => $platform->code,
                    'name' => $platform->name,
                ],
                'config' => [
                    'maintenance_status' => $platform->maintenance_status,
                    'week' => $platform->maintenance_week,
                    'start_time' => $platform->maintenance_start_time,
                    'end_time' => $platform->maintenance_end_time,
                ],
                'message' => !$isEnabled
                    ? '维护功能已关闭'
                    : ($isInMaintenance ? '游戏平台维护中' : '游戏平台正常运行'),
            ];

        } catch (\Throwable $e) {
            Log::error('获取游戏平台维护状态失败', [
                'error' => $e->getMessage(),
                'platform_id' => $platformId,
            ]);

            return [
                'is_maintenance' => false,
                'message' => '获取维护状态失败',
            ];
        }
    }

    /**
     * 获取所有维护中的游戏平台
     *
     * @return array
     */
    public function getAllMaintenancePlatforms(): array
    {
        try {
            $platforms = GamePlatform::query()
                ->where('maintenance_status', 1)
                ->whereNull('deleted_at')
                ->get();

            $maintenancePlatforms = [];

            foreach ($platforms as $platform) {
                if ($this->isInMaintenanceTime($platform)) {
                    $maintenancePlatforms[] = [
                        'id' => $platform->id,
                        'code' => $platform->code,
                        'name' => $platform->name,
                        'maintenance_info' => [
                            'week' => $platform->maintenance_week,
                            'start_time' => $platform->maintenance_start_time,
                            'end_time' => $platform->maintenance_end_time,
                        ],
                    ];
                }
            }

            return $maintenancePlatforms;

        } catch (\Throwable $e) {
            Log::error('获取所有维护中游戏平台失败', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
