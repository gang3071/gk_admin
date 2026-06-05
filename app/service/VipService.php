<?php

namespace app\service;

use addons\webman\model\Player;
use addons\webman\model\PlayerVipPeriod;
use addons\webman\model\VipLevel;
use support\Log;

/**
 * VIP服务
 * 处理VIP等级升级、保级逻辑
 */
class VipService
{
    /**
     * 写VIP日志
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('vip')->$level($message, $context);
        } catch (\Throwable $e) {
            Log::$level($message, $context);
        }
    }

    /**
     * 处理下注后的VIP逻辑
     * @param Player $player 玩家
     * @param float $betAmount 本次下注金额
     * @return void
     */
    public static function handleBet(Player $player, float $betAmount): void
    {
        try {
            // 只处理线上玩家
            if ($player->player_source != Player::PLAYER_SOURCE_ONLINE) {
                return;
            }

            // 1. 更新玩家总打码量（累计不清零）
            $player->increment('total_bet_amount', $betAmount);
            $player->refresh();

            // 2. 如果玩家没有VIP等级，尝试初始化
            if (empty($player->vip_level_id)) {
                static::initVipLevel($player);
                return;
            }

            // 3. 检查升级条件
            static::checkUpgrade($player);

            // 4. 检查保级条件
            static::checkRetain($player);
        } catch (\Exception $e) {
            static::log('error', 'VIP handleBet failed', [
                'player_id' => $player->id,
                'bet_amount' => $betAmount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 初始化玩家VIP等级
     * @param Player $player
     * @return void
     */
    public static function initVipLevel(Player $player): void
    {
        $minLevel = VipLevel::getMinLevel();
        if (!$minLevel) {
            return;
        }

        $player->vip_level_id = $minLevel->id;
        $player->save();

        // 创建升级周期记录
        static::createPeriod($player, $minLevel, PlayerVipPeriod::PERIOD_TYPE_UPGRADE);

        static::log('info', 'VIP init level', [
            'player_id' => $player->id,
            'level_id' => $minLevel->id,
            'level_name' => $minLevel->name,
        ]);
    }

    /**
     * 获取或初始化玩家VIP等级（默认等级1，无则取最低等级）
     * @param Player $player
     * @return VipLevel|null
     */
    public static function getOrInitVipLevel(Player $player): ?VipLevel
    {
        if (!empty($player->vip_level_id)) {
            $level = VipLevel::query()->find($player->vip_level_id);
            if ($level) {
                return $level;
            }
        }

        // 尝试获取等级1（按sort排序第一个）
        $level = VipLevel::query()
            ->where('status', VipLevel::STATUS_ENABLED)
            ->orderBy('sort', 'asc')
            ->first();

        if ($level) {
            $player->vip_level_id = $level->id;
            $player->save();
        }

        return $level;
    }

    /**
     * 检查升级条件
     * @param Player $player
     * @return void
     */
    public static function checkUpgrade(Player $player): void
    {
        $currentLevel = VipLevel::query()->find($player->vip_level_id);
        if (!$currentLevel) {
            return;
        }

        $nextLevel = $currentLevel->getNextLevel();
        if (!$nextLevel) {
            return; // 已经是最高等级
        }

        // 获取当前进行中的升级周期
        $period = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_UPGRADE)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        if (!$period) {
            // 没有活跃的升级周期，创建一个
            static::createPeriod($player, $currentLevel, PlayerVipPeriod::PERIOD_TYPE_UPGRADE);
            return;
        }

        // 计算周期内打码量
        $periodBetAmount = $period->getPeriodBetAmount($player->total_bet_amount);

        // 检查是否已过期
        if ($period->isExpired($currentLevel->upgrade_limit_days)) {
            // 已过期，标记为已过期，打码量清零重置
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();

            // 创建新的升级周期
            static::createPeriod($player, $currentLevel, PlayerVipPeriod::PERIOD_TYPE_UPGRADE);

            static::log('info', 'VIP upgrade period expired', [
                'player_id' => $player->id,
                'level_id' => $currentLevel->id,
                'period_bet_amount' => $periodBetAmount,
            ]);
            return;
        }

        // 检查是否满足升级条件
        if (VipLevel::isUpgradeQualified($periodBetAmount, $currentLevel->upgrade_bet_amount)) {
            static::doUpgrade($player, $currentLevel, $nextLevel, $period);
        }
    }

    /**
     * 执行升级
     * @param Player $player
     * @param VipLevel $currentLevel
     * @param VipLevel $nextLevel
     * @param PlayerVipPeriod $period
     * @return void
     */
    private static function doUpgrade(Player $player, VipLevel $currentLevel, VipLevel $nextLevel, PlayerVipPeriod $period): void
    {
        // 标记当前升级周期为已完成
        $period->status = PlayerVipPeriod::STATUS_COMPLETED;
        $period->save();

        // 标记当前保级周期为已完成（如果存在）
        PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->update(['status' => PlayerVipPeriod::STATUS_COMPLETED]);

        // 更新玩家VIP等级
        $player->vip_level_id = $nextLevel->id;
        $player->save();

        // 创建新等级的升级周期
        static::createPeriod($player, $nextLevel, PlayerVipPeriod::PERIOD_TYPE_UPGRADE);

        // 创建新等级的保级周期
        static::createPeriod($player, $nextLevel, PlayerVipPeriod::PERIOD_TYPE_RETAIN);

        static::log('info', 'VIP upgrade success', [
            'player_id' => $player->id,
            'old_level' => $currentLevel->id,
            'new_level' => $nextLevel->id,
        ]);
    }

    /**
     * 检查保级条件
     * @param Player $player
     * @return void
     */
    public static function checkRetain(Player $player): void
    {
        $currentLevel = VipLevel::query()->find($player->vip_level_id);
        if (!$currentLevel) {
            return;
        }

        // 获取当前进行中的保级周期
        $period = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        if (!$period) {
            // 没有活跃的保级周期，创建一个
            static::createPeriod($player, $currentLevel, PlayerVipPeriod::PERIOD_TYPE_RETAIN);
            return;
        }

        // 检查保级周期是否已到期
        if ($period->isExpired($currentLevel->retain_level_days)) {
            // 计算周期内打码量
            $periodBetAmount = $period->getPeriodBetAmount($player->total_bet_amount);

            if (VipLevel::isRetainQualified($periodBetAmount, $currentLevel->retain_level_bet_amount)) {
                // 满足保级条件，重置保级周期
                $period->status = PlayerVipPeriod::STATUS_COMPLETED;
                $period->save();

                static::createPeriod($player, $currentLevel, PlayerVipPeriod::PERIOD_TYPE_RETAIN);

                static::log('info', 'VIP retain success', [
                    'player_id' => $player->id,
                    'level_id' => $currentLevel->id,
                    'period_bet_amount' => $periodBetAmount,
                ]);
            } else {
                // 不满足保级条件，降级
                static::doDowngrade($player, $currentLevel, $period);
            }
        }
    }

    /**
     * 执行降级
     * @param Player $player
     * @param VipLevel $currentLevel
     * @param PlayerVipPeriod $period
     * @return void
     */
    private static function doDowngrade(Player $player, VipLevel $currentLevel, PlayerVipPeriod $period): void
    {
        $prevLevel = $currentLevel->getPrevLevel();
        if (!$prevLevel) {
            // 没有更低等级，标记周期为已过期
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();
            return;
        }

        // 标记当前周期为已过期
        $period->status = PlayerVipPeriod::STATUS_EXPIRED;
        $period->save();

        // 标记当前升级周期为已过期
        PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_UPGRADE)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->update(['status' => PlayerVipPeriod::STATUS_EXPIRED]);

        // 更新玩家VIP等级
        $player->vip_level_id = $prevLevel->id;
        $player->save();

        // 创建新等级的升级周期
        static::createPeriod($player, $prevLevel, PlayerVipPeriod::PERIOD_TYPE_UPGRADE);

        // 创建新等级的保级周期
        static::createPeriod($player, $prevLevel, PlayerVipPeriod::PERIOD_TYPE_RETAIN);

        static::log('info', 'VIP downgrade', [
            'player_id' => $player->id,
            'old_level' => $currentLevel->id,
            'new_level' => $prevLevel->id,
        ]);
    }

    /**
     * 创建周期记录
     * @param Player $player
     * @param VipLevel $level
     * @param string $periodType
     * @return PlayerVipPeriod
     */
    private static function createPeriod(Player $player, VipLevel $level, string $periodType): PlayerVipPeriod
    {
        return PlayerVipPeriod::query()->create([
            'player_id' => $player->id,
            'vip_level_id' => $level->id,
            'period_type' => $periodType,
            'start_bet_amount' => $player->total_bet_amount,
            'started_at' => date('Y-m-d H:i:s'),
            'status' => PlayerVipPeriod::STATUS_ACTIVE,
        ]);
    }

    /**
     * 获取玩家VIP信息
     * @param Player $player
     * @return array
     */
    public static function getPlayerVipInfo(Player $player): array
    {
        $currentLevel = VipLevel::query()->find($player->vip_level_id);
        if (!$currentLevel) {
            return [
                'level' => null,
                'total_bet_amount' => $player->total_bet_amount,
                'upgrade' => null,
                'retain' => null,
            ];
        }

        // 获取升级周期
        $upgradePeriod = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_UPGRADE)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        // 获取保级周期
        $retainPeriod = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        $nextLevel = $currentLevel->getNextLevel();

        return [
            'level' => [
                'id' => $currentLevel->id,
                'name' => $currentLevel->name,
            ],
            'total_bet_amount' => $player->total_bet_amount,
            'upgrade' => $nextLevel ? [
                'next_level_name' => $nextLevel->name,
                'required_bet_amount' => $currentLevel->upgrade_bet_amount,
                'current_bet_amount' => $upgradePeriod ? $upgradePeriod->getPeriodBetAmount($player->total_bet_amount) : 0,
                'remaining_days' => $upgradePeriod ? $upgradePeriod->getRemainingDays($currentLevel->upgrade_limit_days) : 0,
                'is_expired' => $upgradePeriod ? $upgradePeriod->isExpired($currentLevel->upgrade_limit_days) : false,
            ] : null,
            'retain' => [
                'required_bet_amount' => $currentLevel->retain_level_bet_amount,
                'current_bet_amount' => $retainPeriod ? $retainPeriod->getPeriodBetAmount($player->total_bet_amount) : 0,
                'remaining_days' => $retainPeriod ? $retainPeriod->getRemainingDays($currentLevel->retain_level_days) : 0,
                'is_expired' => $retainPeriod ? $retainPeriod->isExpired($currentLevel->retain_level_days) : false,
            ],
        ];
    }
}
