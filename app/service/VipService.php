<?php

namespace app\service;

use addons\webman\model\Player;
use addons\webman\model\PlayerVipPeriod;
use addons\webman\model\VipLevel;
use support\Log;

/**
 * VIP服务
 * 处理VIP等级升级、保级逻辑
 *
 * 规则：
 * 1. 只有保级周期，没有升级周期
 * 2. 保级：周期内完成打码量 → 保级成功，否则降级
 * 3. 升级：累计打码量达到要求 → 立即升级
 * 4. 升降级后：保级周期和打码量重置
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

            // 3. 更新当前保级周期的打码量
            static::updatePeriodBetAmount($player, $betAmount);

            // 4. 检查升级条件（累计打码量达到要求就升级）
            static::checkUpgrade($player);

            // 5. 检查保级条件（保级周期内完成打码量）
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

        // 创建保级周期
        static::createRetainPeriod($player, $minLevel);

        static::log('info', 'VIP init level', [
            'player_id' => $player->id,
            'level_id' => $minLevel->id,
            'level_name' => $minLevel->name,
        ]);
    }

    /**
     * 更新当前保级周期的打码量
     * @param Player $player
     * @param float $betAmount 本次下注金额
     * @return void
     */
    private static function updatePeriodBetAmount(Player $player, float $betAmount): void
    {
        PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $player->vip_level_id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->increment('period_bet_amount', $betAmount);
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
     * 保级周期内打码量达到升级要求就升级
     *
     * @param Player $player
     * @return void
     */
    public static function checkUpgrade(Player $player): void
    {
        $currentLevel = VipLevel::query()->find($player->vip_level_id);
        if (!$currentLevel) {
            static::log('debug', 'checkUpgrade: current level not found', [
                'player_id' => $player->id,
                'vip_level_id' => $player->vip_level_id,
            ]);
            return;
        }

        $nextLevel = $currentLevel->getNextLevel();
        if (!$nextLevel) {
            static::log('debug', 'checkUpgrade: already max level', [
                'player_id' => $player->id,
                'current_level' => $currentLevel->id,
            ]);
            return; // 已经是最高等级
        }

        // 获取当前保级周期
        $period = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        if (!$period) {
            static::log('debug', 'checkUpgrade: no active retain period', [
                'player_id' => $player->id,
                'current_level' => $currentLevel->id,
            ]);
            return; // 没有保级周期，不检查升级
        }

        static::log('info', 'checkUpgrade', [
            'player_id' => $player->id,
            'current_level' => $currentLevel->id,
            'next_level' => $nextLevel->id,
            'period_bet_amount' => $period->period_bet_amount,
            'upgrade_bet_amount' => $currentLevel->upgrade_bet_amount,
            'can_upgrade' => $period->period_bet_amount >= $currentLevel->upgrade_bet_amount,
        ]);

        // 检查保级周期内打码量是否满足升级条件
        if ($period->period_bet_amount >= $currentLevel->upgrade_bet_amount) {
            static::doUpgrade($player, $currentLevel, $nextLevel);
        }
    }

    /**
     * 执行升级
     * 升级后重置保级周期，多余的打码量带到新周期
     *
     * @param Player $player
     * @param VipLevel $currentLevel
     * @param VipLevel $nextLevel
     * @return void
     */
    private static function doUpgrade(Player $player, VipLevel $currentLevel, VipLevel $nextLevel): void
    {
        // 获取当前保级周期
        $currentPeriod = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        // 计算多余的打码量（当前打码量 - 升级所需打码量）
        $excessBetAmount = $currentPeriod
            ? max(0, $currentPeriod->period_bet_amount - $currentLevel->upgrade_bet_amount)
            : 0;

        // 标记当前保级周期为已完成（如果存在）
        if ($currentPeriod) {
            $currentPeriod->status = PlayerVipPeriod::STATUS_COMPLETED;
            $currentPeriod->save();
        }

        // 更新玩家VIP等级
        $player->vip_level_id = $nextLevel->id;
        $player->save();

        // 创建新等级的保级周期，多余的打码量带到新周期
        static::createRetainPeriod($player, $nextLevel, $excessBetAmount);

        static::log('info', 'VIP upgrade success', [
            'player_id' => $player->id,
            'old_level' => $currentLevel->id,
            'new_level' => $nextLevel->id,
            'old_period_bet_amount' => $currentPeriod->period_bet_amount ?? 0,
            'upgrade_bet_amount' => $currentLevel->upgrade_bet_amount,
            'excess_bet_amount' => $excessBetAmount,
        ]);
    }

    /**
     * 检查保级条件
     * 保级周期内完成打码量 → 保级成功（不重置周期）
     * 保级周期到期 + 未完成打码量 → 降级
     * 保级周期到期 + 完成打码量 → 重置周期
     *
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
            static::log('info', 'No active retain period, creating new one', [
                'player_id' => $player->id,
                'total_bet_amount' => $player->total_bet_amount,
            ]);
            static::createRetainPeriod($player, $currentLevel);
            return;
        }

        static::log('debug', 'Retain check', [
            'player_id' => $player->id,
            'period_bet_amount' => $period->period_bet_amount,
            'required_bet_amount' => $currentLevel->retain_level_bet_amount,
        ]);

        // 检查保级周期是否已到期
        if ($period->isExpired($currentLevel->retain_level_days)) {
            // 保级周期到期
            if ($period->period_bet_amount >= $currentLevel->retain_level_bet_amount) {
                // 满足保级条件，标记为已完成，创建新周期（保留之前的打码量）
                $period->status = PlayerVipPeriod::STATUS_COMPLETED;
                $period->save();

                // 创建新周期，保留之前的打码量
                static::createRetainPeriod($player, $currentLevel, $period->period_bet_amount);

                static::log('info', 'VIP retain success, reset period', [
                    'player_id' => $player->id,
                    'level_id' => $currentLevel->id,
                    'period_bet_amount' => $period->period_bet_amount,
                ]);
            } else {
                // 不满足保级条件，降级
                static::doDowngrade($player, $currentLevel, $period);
            }
        }
        // 保级周期未到期：完成打码量 = 保级成功（不重置周期），未完成 = 继续观察
    }

    /**
     * 执行降级
     * 降级后重置保级周期
     *
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

        // 标记当前保级周期为已过期
        $period->status = PlayerVipPeriod::STATUS_EXPIRED;
        $period->save();

        // 更新玩家VIP等级
        $player->vip_level_id = $prevLevel->id;
        $player->save();

        // 创建新等级的保级周期（打码量重置，时间重置）
        static::createRetainPeriod($player, $prevLevel);

        static::log('info', 'VIP downgrade', [
            'player_id' => $player->id,
            'old_level' => $currentLevel->id,
            'new_level' => $prevLevel->id,
        ]);
    }

    /**
     * 创建保级周期记录
     * @param Player $player
     * @param VipLevel $level
     * @param float $initialBetAmount 初始周期内打码量（保级成功时保留之前的打码量）
     * @return PlayerVipPeriod
     */
    private static function createRetainPeriod(Player $player, VipLevel $level, float $initialBetAmount = 0): PlayerVipPeriod
    {
        static::log('info', 'Creating retain period', [
            'player_id' => $player->id,
            'level_id' => $level->id,
            'total_bet_amount' => $player->total_bet_amount,
            'initial_bet_amount' => $initialBetAmount,
        ]);

        return PlayerVipPeriod::query()->create([
            'player_id' => $player->id,
            'vip_level_id' => $level->id,
            'period_type' => PlayerVipPeriod::PERIOD_TYPE_RETAIN,
            'start_bet_amount' => $player->total_bet_amount,
            'period_bet_amount' => $initialBetAmount,
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

        // 获取保级周期
        $retainPeriod = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        $nextLevel = $currentLevel->getNextLevel();

        // 计算升级进度（累计打码量）
        $upgradeProgress = $currentLevel->upgrade_bet_amount > 0
            ? min(100, round($player->total_bet_amount / $currentLevel->upgrade_bet_amount * 100, 2))
            : 0;

        // 计算保级进度（周期内打码量）
        $retainBetAmount = $retainPeriod ? $retainPeriod->getPeriodBetAmount($player->total_bet_amount) : 0;
        $retainProgress = $currentLevel->retain_level_bet_amount > 0
            ? min(100, round($retainBetAmount / $currentLevel->retain_level_bet_amount * 100, 2))
            : 0;

        return [
            'level' => [
                'id' => $currentLevel->id,
                'name' => $currentLevel->name,
            ],
            'total_bet_amount' => $player->total_bet_amount,
            'upgrade' => $nextLevel ? [
                'next_level_name' => $nextLevel->name,
                'required_bet_amount' => $currentLevel->upgrade_bet_amount,
                'current_bet_amount' => $player->total_bet_amount,
                'progress' => $upgradeProgress,
            ] : null,
            'retain' => [
                'required_bet_amount' => $currentLevel->retain_level_bet_amount,
                'current_bet_amount' => $retainBetAmount,
                'progress' => $retainProgress,
                'remaining_days' => $retainPeriod ? $retainPeriod->getRemainingDays($currentLevel->retain_level_days) : 0,
                'is_expired' => $retainPeriod ? $retainPeriod->isExpired($currentLevel->retain_level_days) : false,
            ],
        ];
    }
}
