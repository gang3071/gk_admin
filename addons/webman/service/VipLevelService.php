<?php

namespace addons\webman\service;

use addons\webman\model\Channel;
use addons\webman\model\VipLevel;
use support\Db;
use support\Log;

/**
 * VIP等级服务类
 */
class VipLevelService
{
    /**
     * 默认VIP等级配置
     */
    private const DEFAULT_VIP_LEVELS = [
        [
            'name' => 'VIP0',
            'sort' => 0,
            'upgrade_limit_days' => 0,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 0,
            'upgrade_bet_amount' => 1000,
            'min_claim_amount' => 0,
            'birthday_bonus' => 0,
        ],
        [
            'name' => 'VIP1',
            'sort' => 1,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 1000,
            'upgrade_bet_amount' => 5000,
            'min_claim_amount' => 10,
            'birthday_bonus' => 50,
        ],
        [
            'name' => 'VIP2',
            'sort' => 2,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 5000,
            'upgrade_bet_amount' => 20000,
            'min_claim_amount' => 20,
            'birthday_bonus' => 100,
        ],
        [
            'name' => 'VIP3',
            'sort' => 3,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 20000,
            'upgrade_bet_amount' => 50000,
            'min_claim_amount' => 50,
            'birthday_bonus' => 200,
        ],
        [
            'name' => 'VIP4',
            'sort' => 4,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 50000,
            'upgrade_bet_amount' => 100000,
            'min_claim_amount' => 100,
            'birthday_bonus' => 500,
        ],
        [
            'name' => 'VIP5',
            'sort' => 5,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 100000,
            'upgrade_bet_amount' => 200000,
            'min_claim_amount' => 200,
            'birthday_bonus' => 1000,
        ],
        [
            'name' => 'VIP6',
            'sort' => 6,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 200000,
            'upgrade_bet_amount' => 500000,
            'min_claim_amount' => 500,
            'birthday_bonus' => 2000,
        ],
        [
            'name' => 'VIP7',
            'sort' => 7,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 500000,
            'upgrade_bet_amount' => 1000000,
            'min_claim_amount' => 1000,
            'birthday_bonus' => 5000,
        ],
        [
            'name' => 'VIP8',
            'sort' => 8,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 1000000,
            'upgrade_bet_amount' => 2000000,
            'min_claim_amount' => 2000,
            'birthday_bonus' => 10000,
        ],
        [
            'name' => 'VIP9',
            'sort' => 9,
            'upgrade_limit_days' => 7,
            'retain_level_days' => 30,
            'retain_level_bet_amount' => 2000000,
            'upgrade_bet_amount' => 0,
            'min_claim_amount' => 5000,
            'birthday_bonus' => 20000,
        ],
    ];

    /**
     * 为渠道创建默认VIP等级
     *
     * @param int $departmentId 渠道部门ID
     * @return array ['success' => bool, 'message' => string, 'count' => int]
     */
    public static function createDefaultLevelsForChannel(int $departmentId): array
    {
        if ($departmentId <= 0) {
            return [
                'success' => false,
                'message' => '无效的渠道ID',
                'count' => 0
            ];
        }

        // 检查渠道是否存在
        $channel = Channel::find($departmentId);
        if (!$channel) {
            return [
                'success' => false,
                'message' => '渠道不存在',
                'count' => 0
            ];
        }

        // 检查是否已经有VIP等级
        $existingCount = VipLevel::query()
            ->where('department_id', $departmentId)
            ->count();

        if ($existingCount > 0) {
            return [
                'success' => false,
                'message' => "该渠道已有 {$existingCount} 个VIP等级，无需重复创建",
                'count' => $existingCount
            ];
        }

        Db::beginTransaction();
        try {
            $createdCount = 0;

            foreach (self::DEFAULT_VIP_LEVELS as $levelData) {
                VipLevel::create(array_merge($levelData, [
                    'department_id' => $departmentId,
                    'status' => VipLevel::STATUS_ENABLED,
                ]));
                $createdCount++;
            }

            Db::commit();

            Log::info("为渠道 {$departmentId} ({$channel->name}) 创建了 {$createdCount} 个默认VIP等级");

            return [
                'success' => true,
                'message' => "成功为渠道【{$channel->name}】创建了 {$createdCount} 个VIP等级",
                'count' => $createdCount
            ];
        } catch (\Exception $e) {
            Db::rollBack();
            Log::error("为渠道 {$departmentId} 创建默认VIP等级失败: " . $e->getMessage());

            return [
                'success' => false,
                'message' => '创建失败：' . $e->getMessage(),
                'count' => 0
            ];
        }
    }

    /**
     * 检查渠道是否有VIP等级
     *
     * @param int $departmentId 渠道部门ID
     * @return bool
     */
    public static function hasVipLevels(int $departmentId): bool
    {
        return VipLevel::query()
            ->where('department_id', $departmentId)
            ->exists();
    }

    /**
     * 获取渠道的VIP等级数量
     *
     * @param int $departmentId 渠道部门ID
     * @return int
     */
    public static function getVipLevelCount(int $departmentId): int
    {
        return VipLevel::query()
            ->where('department_id', $departmentId)
            ->count();
    }

    /**
     * 删除渠道的所有VIP等级（谨慎使用）
     *
     * @param int $departmentId 渠道部门ID
     * @return array ['success' => bool, 'message' => string, 'count' => int]
     */
    public static function deleteAllLevelsForChannel(int $departmentId): array
    {
        if ($departmentId <= 0) {
            return [
                'success' => false,
                'message' => '无效的渠道ID',
                'count' => 0
            ];
        }

        Db::beginTransaction();
        try {
            $count = VipLevel::query()
                ->where('department_id', $departmentId)
                ->delete();

            Db::commit();

            return [
                'success' => true,
                'message' => "成功删除 {$count} 个VIP等级",
                'count' => $count
            ];
        } catch (\Exception $e) {
            Db::rollBack();
            Log::error("删除渠道 {$departmentId} 的VIP等级失败: " . $e->getMessage());

            return [
                'success' => false,
                'message' => '删除失败：' . $e->getMessage(),
                'count' => 0
            ];
        }
    }
}
