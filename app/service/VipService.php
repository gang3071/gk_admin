<?php

namespace app\service;

use addons\webman\model\Channel;
use addons\webman\model\Notice;
use addons\webman\model\Player;
use addons\webman\model\PlayerVipPeriod;
use addons\webman\model\VipLevel;
use support\Db;
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
    /** @var array 渠道VIP状态缓存 [departmentId => [val, ts]] */
    private static array $channelVipCache = [];

    /** @var array VIP等级缓存 [levelId => [val, ts]] */
    private static array $levelCache = [];

    /** @var array 最低等级缓存 */
    private static ?array $minLevelCache = null;

    /** 缓存有效期（秒） */
    const CACHE_TTL = 300;

    /** 推送消息类型：VIP等级变更 */
    const MSG_TYPE_VIP_LEVEL_CHANGE = 'vip_level_change';

    /** 变更类型：升级 */
    const CHANGE_TYPE_UPGRADE = 'upgrade';

    /** 变更类型：降级 */
    const CHANGE_TYPE_DOWNGRADE = 'downgrade';

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
     * 清除静态缓存
     */
    public static function clearCache(): void
    {
        self::$channelVipCache = [];
        self::$levelCache = [];
        self::$minLevelCache = null;
    }

    /**
     * 获取渠道VIP状态（带TTL缓存）
     */
    private static function isChannelVipEnabled(int $departmentId): bool
    {
        if (isset(self::$channelVipCache[$departmentId])) {
            $cache = self::$channelVipCache[$departmentId];
            if (time() - $cache['ts'] < self::CACHE_TTL) {
                return $cache['val'];
            }
        }
        $channel = Channel::query()->where('department_id', $departmentId)->first();
        $enabled = $channel && !empty($channel->vip_level_status);
        self::$channelVipCache[$departmentId] = ['val' => $enabled, 'ts' => time()];
        return $enabled;
    }

    /**
     * 获取VIP等级（带TTL缓存）
     * @return VipLevel|null
     */
    private static function getLevelCached(int $levelId): ?VipLevel
    {
        if (isset(self::$levelCache[$levelId])) {
            $cache = self::$levelCache[$levelId];
            if (time() - $cache['ts'] < self::CACHE_TTL) {
                return $cache['val'];
            }
        }
        /** @var VipLevel|null $level */
        $level = VipLevel::query()->find($levelId);
        self::$levelCache[$levelId] = ['val' => $level, 'ts' => time()];
        return $level;
    }

    /**
     * 获取最低等级（带TTL缓存）
     * @return VipLevel|null
     */
    private static function getMinLevelCached(): ?VipLevel
    {
        if (self::$minLevelCache !== null) {
            if (time() - self::$minLevelCache['ts'] < self::CACHE_TTL) {
                return self::$minLevelCache['val'];
            }
        }
        /** @var VipLevel|null $level */
        $level = VipLevel::query()
            ->where('status', VipLevel::STATUS_ENABLED)
            ->orderBy('sort', 'asc')
            ->first();
        self::$minLevelCache = ['val' => $level, 'ts' => time()];
        return $level;
    }

    /**
     * 处理下注后的VIP逻辑
     *
     * @param Player $player 玩家
     * @param float $betAmount 本次下注金额
     * @return void
     */
    public static function handleBet(Player $player, float $betAmount): void
    {
        try {
            if ($betAmount <= 0) {
                return;
            }

            if (!static::isChannelVipEnabled($player->department_id)) {
                return;
            }

            // 更新总打码量，refresh 获取 DB 最新值
            $player->increment('total_bet_amount', $betAmount);
            $player->refresh();

            if (empty($player->vip_level_id)) {
                static::initVipLevel($player);
                return;
            }

            // 查询保级周期
            /** @var PlayerVipPeriod|null $period */
            $period = PlayerVipPeriod::query()
                ->where('player_id', $player->id)
                ->where('vip_level_id', $player->vip_level_id)
                ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                ->first();

            // 更新周期打码量
            if ($period) {
                $period->increment('period_bet_amount', $betAmount);
                $period->refresh();
            }

            // 检查升级
            $upgraded = static::checkUpgrade($player, $period);

            // 升级后重新查询周期（vip_level_id 已变）
            if ($upgraded) {
                $period = PlayerVipPeriod::query()
                    ->where('player_id', $player->id)
                    ->where('vip_level_id', $player->vip_level_id)
                    ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                    ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                    ->first();
            }

            // 检查保级
            static::checkRetain($player, $period);
        } catch (\Throwable $e) {
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
        $minLevel = static::getMinLevelCached();
        if (!$minLevel) {
            return;
        }

        $player->vip_level_id = $minLevel->id;
        $player->save();

        static::createRetainPeriod($player, $minLevel);

        static::log('info', 'VIP init level', [
            'player_id' => $player->id,
            'level_id' => $minLevel->id,
            'level_name' => $minLevel->name,
        ]);
    }

    /**
     * 获取或初始化玩家VIP等级
     * @return VipLevel|null
     */
    public static function getOrInitVipLevel(Player $player): ?VipLevel
    {
        if (!empty($player->vip_level_id)) {
            /** @var VipLevel|null $level */
            $level = static::getLevelCached($player->vip_level_id);
            if ($level) {
                return $level;
            }
        }

        $level = static::getMinLevelCached();
        if ($level) {
            $player->vip_level_id = $level->id;
            $player->save();
        }

        return $level;
    }

    /**
     * 检查升级条件
     *
     * @param Player $player
     * @param PlayerVipPeriod|null $period 保级周期
     * @return bool 是否升级成功
     */
    public static function checkUpgrade(Player $player, ?PlayerVipPeriod $period = null): bool
    {
        $currentLevel = static::getLevelCached($player->vip_level_id);
        if (!$currentLevel || $currentLevel->upgrade_bet_amount <= 0) {
            return false;
        }

        $nextLevel = $currentLevel->getNextLevel();
        if (!$nextLevel) {
            return false;
        }

        if (!$period) {
            $period = PlayerVipPeriod::query()
                ->where('player_id', $player->id)
                ->where('vip_level_id', $currentLevel->id)
                ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                ->first();
        }

        if (!$period) {
            return false;
        }

        if ($period->period_bet_amount >= $currentLevel->upgrade_bet_amount) {
            static::doUpgrade($player, $currentLevel, $nextLevel, $period, $period->period_bet_amount);
            return true;
        }

        return false;
    }

    /**
     * 执行升级
     */
    private static function doUpgrade(
        Player $player,
        VipLevel $currentLevel,
        VipLevel $nextLevel,
        PlayerVipPeriod $currentPeriod,
        float $periodBetAmount
    ): void {
        $excessBetAmount = max(0, $periodBetAmount - $currentLevel->upgrade_bet_amount);
        $newStartBetAmount = $currentPeriod->start_bet_amount + $currentLevel->upgrade_bet_amount;
        $playerId = $player->id;
        $oldLevelId = $currentLevel->id;
        $newLevelId = $nextLevel->id;

        Db::transaction(function () use ($currentPeriod, $player, $nextLevel, $newStartBetAmount) {
            $currentPeriod->status = PlayerVipPeriod::STATUS_COMPLETED;
            $currentPeriod->save();

            $player->vip_level_id = $nextLevel->id;
            $player->save();

            static::createRetainPeriod($player, $nextLevel, $newStartBetAmount);
        });

        static::log('info', 'VIP upgrade success', [
            'player_id' => $playerId,
            'old_level' => $oldLevelId,
            'new_level' => $newLevelId,
            'period_bet_amount' => $periodBetAmount,
            'upgrade_bet_amount' => $currentLevel->upgrade_bet_amount,
            'excess_bet_amount' => $excessBetAmount,
            'new_start_bet_amount' => $newStartBetAmount,
        ]);

        // 创建通知记录并推送升级通知
        try {
            $title = 'VIP等級升級';
            $content = sprintf('恭喜！您的VIP等級已從 %s 升級至 %s', $currentLevel->name, $nextLevel->name);

            $notice = Notice::query()->create([
                'department_id' => $player->department_id,
                'player_id' => $playerId,
                'type' => Notice::TYPE_VIP_LEVEL_CHANGE_UPGRADE,
                'title' => $title,
                'content' => $content,
                'status' => 0,
                'receiver' => Notice::RECEIVER_PLAYER,
                'is_private' => 1,
            ]);

            sendSocketMessage('player-' . $playerId, [
                'msg_type' => self::MSG_TYPE_VIP_LEVEL_CHANGE,
                'change_type' => self::CHANGE_TYPE_UPGRADE,
                'player_id' => $playerId,
                'notice_id' => $notice->id,
                'title' => $title,
                'content' => $content,
                'old_level_id' => $oldLevelId,
                'old_level_name' => $currentLevel->name,
                'new_level_id' => $newLevelId,
                'new_level_name' => $nextLevel->name,
            ]);
        } catch (\Throwable $e) {
            static::log('warning', 'VIP upgrade push failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查保级条件
     *
     * @param Player $player
     * @param PlayerVipPeriod|null $period 保级周期
     * @return void
     */
    public static function checkRetain(Player $player, ?PlayerVipPeriod $period = null): void
    {
        $currentLevel = static::getLevelCached($player->vip_level_id);
        if (!$currentLevel) {
            return;
        }

        if (!$period) {
            $period = PlayerVipPeriod::query()
                ->where('player_id', $player->id)
                ->where('vip_level_id', $currentLevel->id)
                ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                ->first();
        }

        if (!$period) {
            static::createRetainPeriod($player, $currentLevel);
            return;
        }

        if (!$period->isExpired($currentLevel->retain_level_days)) {
            return;
        }

        if ($period->period_bet_amount >= $currentLevel->retain_level_bet_amount) {
            $period->status = PlayerVipPeriod::STATUS_COMPLETED;
            $period->save();

            static::createRetainPeriod($player, $currentLevel);

            static::log('info', 'VIP retain success', [
                'player_id' => $player->id,
                'level_id' => $currentLevel->id,
                'period_bet_amount' => $period->period_bet_amount,
            ]);
        } else {
            static::doDowngrade($player, $currentLevel, $period);
        }
    }

    /**
     * 执行降级
     */
    private static function doDowngrade(Player $player, VipLevel $currentLevel, PlayerVipPeriod $period): void
    {
        $prevLevel = $currentLevel->getPrevLevel();
        if (!$prevLevel) {
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();
            return;
        }

        $playerId = $player->id;
        $oldLevelId = $currentLevel->id;
        $newLevelId = $prevLevel->id;
        $totalBetBefore = $player->total_bet_amount;

        Db::transaction(function () use ($period, $player, $prevLevel) {
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();

            $player->vip_level_id = $prevLevel->id;
            $player->save();

            static::createRetainPeriod($player, $prevLevel);
        });

        static::log('info', 'VIP downgrade', [
            'player_id' => $playerId,
            'old_level' => $oldLevelId,
            'new_level' => $newLevelId,
            'new_start_bet_amount' => $totalBetBefore,
        ]);

        // 创建通知记录并推送降级通知
        try {
            $title = 'VIP等級降級';
            $content = sprintf('很遺憾，您的VIP等級已從 %s 降級至 %s', $currentLevel->name, $prevLevel->name);

            $notice = Notice::query()->create([
                'department_id' => $player->department_id,
                'player_id' => $playerId,
                'type' => Notice::TYPE_VIP_LEVEL_CHANGE_DOWNGRADE,
                'title' => $title,
                'content' => $content,
                'status' => 0,
                'receiver' => Notice::RECEIVER_PLAYER,
                'is_private' => 1,
            ]);

            sendSocketMessage('player-' . $playerId, [
                'msg_type' => self::MSG_TYPE_VIP_LEVEL_CHANGE,
                'change_type' => self::CHANGE_TYPE_DOWNGRADE,
                'player_id' => $playerId,
                'notice_id' => $notice->id,
                'title' => $title,
                'content' => $content,
                'old_level_id' => $oldLevelId,
                'old_level_name' => $currentLevel->name,
                'new_level_id' => $newLevelId,
                'new_level_name' => $prevLevel->name,
            ]);
        } catch (\Throwable $e) {
            static::log('warning', 'VIP downgrade push failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 创建保级周期记录
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
        /** @var VipLevel|null $currentLevel */
        $currentLevel = static::getLevelCached($player->vip_level_id);
        if (!$currentLevel) {
            return [
                'level' => null,
                'total_bet_amount' => $player->total_bet_amount,
                'upgrade' => null,
                'retain' => null,
            ];
        }

        $retainPeriod = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $currentLevel->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        $nextLevel = $currentLevel->getNextLevel();

        $upgradeProgress = $currentLevel->upgrade_bet_amount > 0
            ? min(100, round($player->total_bet_amount / $currentLevel->upgrade_bet_amount * 100, 2))
            : 0;

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
