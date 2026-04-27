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
     * 维护状态键
     */
    private const REDIS_KEY_STATUS = self::REDIS_KEY_PREFIX . 'status';

    /**
     * 维护通知已发送标记键
     */
    private const REDIS_KEY_NOTIFIED = self::REDIS_KEY_PREFIX . 'notified:';

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

        // 获取上一次的维护状态
        $redis = Redis::connection();
        $lastStatus = $redis->hGet(self::REDIS_KEY_STATUS, (string)$platformId);
        $notifiedKey = self::REDIS_KEY_NOTIFIED . $platformId;

        // 状态转换：进入维护时间
        if ($isInMaintenance && $lastStatus !== '1') {
            // 检查是否已经发送过通知（避免重复推送）
            $notified = $redis->get($notifiedKey);
            if (!$notified) {
                $this->sendMaintenanceNotification($platform, true);
                // 标记已发送，设置过期时间（维护时段内有效）
                $redis->setex($notifiedKey, 3600, '1');
            }
            // 更新状态
            $redis->hSet(self::REDIS_KEY_STATUS, (string)$platformId, '1');

            Log::info('游戏平台进入维护时间', [
                'platform_id' => $platformId,
                'platform_code' => $platformCode,
                'platform_name' => $platform->name,
                'week' => $platform->maintenance_week,
                'time_range' => $platform->maintenance_start_time . ' ~ ' . $platform->maintenance_end_time,
            ]);
        }
        // 状态转换：离开维护时间
        elseif (!$isInMaintenance && $lastStatus === '1') {
            $this->sendMaintenanceNotification($platform, false);
            // 清除已发送标记
            $redis->del($notifiedKey);
            // 更新状态
            $redis->hSet(self::REDIS_KEY_STATUS, (string)$platformId, '0');

            Log::info('游戏平台离开维护时间', [
                'platform_id' => $platformId,
                'platform_code' => $platformCode,
                'platform_name' => $platform->name,
            ]);
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
