<?php

namespace app\service;

use addons\webman\model\Channel;
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
     *
     * 规则（与单元测试一致）：
     * 1. 反水按下单时的等级比例计算（由调用方负责）
     * 2. 周期内打码量 = total_bet_amount - start_bet_amount（动态计算）
     * 3. 升级后新周期 start = 当前周期start + 升级阈值（溢出继承）
     * 4. 降级后新周期 start = 当前 total_bet_amount（重新开始）
     *
     * @param Player $player 玩家
     * @param float $betAmount 本次下注金额
     * @return void
     */
    public static function handleBet(Player $player, float $betAmount): void
    {
        try {
            // 只处理线上玩家
//            if ($player->player_source != Player::PLAYER_SOURCE_ONLINE) {
//                return;
//            }

            // 检查玩家所在渠道是否开启了VIP等级功能
            $channel = Channel::query()
                ->where('department_id', $player->department_id)
                ->first();
            if (!$channel || empty($channel->vip_level_status)) {
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

            // 4. 检查升级条件（周期内打码量 >= 升级阈值）
            static::checkUpgrade($player);

            // 5. 检查保级条件（保级周期到期 + 打码量不足 → 降级）
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
     * 周期内打码量（动态计算：total - start）达到升级要求就升级
     *
     * @param Player $player
     * @return void
     */
    public static function checkUpgrade(Player $player): void
    {
        $currentLevel = VipLevel::query()->find($player->vip_level_id);
        if (!$currentLevel) {
            return;
        }

        // 已是最高级，无需检查
        if ($currentLevel->upgrade_bet_amount <= 0) {
            return;
        }

        $nextLevel = $currentLevel->getNextLevel();
        if (!$nextLevel) {
            return;
        }

        // 获取当前保级周期
        $period = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        if (!$period) {
            return;
        }

        // 检查是否满足升级条件
        if ($period->period_bet_amount >= $currentLevel->upgrade_bet_amount) {
            static::doUpgrade($player, $currentLevel, $nextLevel, $period, $period->period_bet_amount);
        }
    }

    /**
     * 执行升级
     *
     * 升级逻辑（与单元测试一致）：
     * - 新周期 start_bet_amount = 当前周期start + 升级阈值（溢出继承）
     * - 多余打码量 = period_bet_amount - upgrade_bet_amount
     *
     * @param Player $player
     * @param VipLevel $currentLevel
     * @param VipLevel $nextLevel
     * @param PlayerVipPeriod $currentPeriod 当前保级周期
     * @param float $periodBetAmount 当前周期内打码量（动态计算）
     * @return void
     */
    private static function doUpgrade(
        Player $player,
        VipLevel $currentLevel,
        VipLevel $nextLevel,
        PlayerVipPeriod $currentPeriod,
        float $periodBetAmount
    ): void {
        // 计算溢出打码量
        $excessBetAmount = max(0, $periodBetAmount - $currentLevel->upgrade_bet_amount);

        // 新周期 start = 当前周期start + 升级阈值（溢出正确继承）
        $newStartBetAmount = $currentPeriod->start_bet_amount + $currentLevel->upgrade_bet_amount;

        // 标记当前保级周期为已完成
        $currentPeriod->status = PlayerVipPeriod::STATUS_COMPLETED;
        $currentPeriod->save();

        // 更新玩家VIP等级
        $player->vip_level_id = $nextLevel->id;
        $player->save();

        // 创建新等级的保级周期
        static::createRetainPeriod($player, $nextLevel, $newStartBetAmount);

        static::log('info', 'VIP upgrade success', [
            'player_id' => $player->id,
            'old_level' => $currentLevel->id,
            'new_level' => $nextLevel->id,
            'period_bet_amount' => $periodBetAmount,
            'upgrade_bet_amount' => $currentLevel->upgrade_bet_amount,
            'excess_bet_amount' => $excessBetAmount,
            'new_start_bet_amount' => $newStartBetAmount,
        ]);
    }

    /**
     * 检查保级条件
     *
     * 规则：
     * - 保级周期未到期：继续观察
     * - 保级周期到期 + 打码量足够 → 保级成功，创建新周期（从当前total重新开始）
     * - 保级周期到期 + 打码量不足 → 降级
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
            static::createRetainPeriod($player, $currentLevel);
            return;
        }

        // 保级周期未到期，继续观察
        if (!$period->isExpired($currentLevel->retain_level_days)) {
            return;
        }

        // 保级周期到期，检查打码量
        if ($period->period_bet_amount >= $currentLevel->retain_level_bet_amount) {
            // 保级成功，标记为已完成，创建新周期（从当前total重新开始）
            $period->status = PlayerVipPeriod::STATUS_COMPLETED;
            $period->save();

            static::createRetainPeriod($player, $currentLevel);

            static::log('info', 'VIP retain success', [
                'player_id' => $player->id,
                'level_id' => $currentLevel->id,
                'period_bet_amount' => $period->period_bet_amount,
            ]);
        } else {
            // 保级失败，降级
            static::doDowngrade($player, $currentLevel, $period);
        }
    }

    /**
     * 执行降级
     *
     * 降级逻辑（与单元测试一致）：
     * - 标记当前保级周期为已过期
     * - 新周期 start_bet_amount = 当前 total_bet_amount（重新开始）
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

        // 创建新等级的保级周期（从当前total重新开始）
        static::createRetainPeriod($player, $prevLevel);

        static::log('info', 'VIP downgrade', [
            'player_id' => $player->id,
            'old_level' => $currentLevel->id,
            'new_level' => $prevLevel->id,
            'new_start_bet_amount' => $player->total_bet_amount,
        ]);
    }

    /**
     * 创建保级周期记录
     *
     * @param Player $player
     * @param VipLevel $level
     * @param float|null $startBetAmount 周期起始打码量（默认为当前total_bet_amount）
     * @return PlayerVipPeriod
     */
    private static function createRetainPeriod(
        Player $player,
        VipLevel $level,
        ?float $startBetAmount = null
    ): PlayerVipPeriod {
        $startBetAmount = $startBetAmount ?? $player->total_bet_amount;
        $periodBetAmount = max(0, $player->total_bet_amount - $startBetAmount);

        return PlayerVipPeriod::query()->create([
            'player_id' => $player->id,
            'vip_level_id' => $level->id,
            'period_type' => PlayerVipPeriod::PERIOD_TYPE_RETAIN,
            'start_bet_amount' => $startBetAmount,
            'period_bet_amount' => $periodBetAmount,
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
