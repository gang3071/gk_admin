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
 * 1. 升级：升级经验（period_bet_amount）达到升级要求 → 立即升级（可连升多级）
 * 2. 升级后：超额结转进新等级的升级经验
 * 3. 保级（周期 retain_level_days 到期时判定）：
 *    比对的是【本保级窗口内新增打码量】= total_bet_amount - start_bet_amount，
 *    不是可结转的升级经验 —— 否则经验一旦超过保级量就会永远保级成功。
 *
 *    - 窗口打码 ≥ 保级量 → 保级成功，升级经验【不扣】结转，窗口重新开始
 *    - 窗口打码 < 保级量 且 非最低级 → 降一级并【清空经验】，从低一级从 0 累计
 *    - 窗口打码 < 保级量 且 已是最低级 → 不降级、不清经验，窗口重新开始
 *
 * 注：PlayerVipPeriod（PERIOD_TYPE_RETAIN）同时承载两个独立计数：
 *     - period_bet_amount：升级经验（跨窗口结转）
 *     - start_bet_amount：保级窗口起点（每窗口重置）
 */
class VipService
{
    /**
     * 是否启用保级/降级检查
     *
     * true  = 按上述规则 3 判定保级与降级
     * false = 只走升级：不评估保级打码量、不判周期到期，也不会降级
     */
    const RETAIN_CHECK_ENABLED = true;

    /** @var array 渠道VIP状态缓存 [departmentId => [val, ts]] */
    private static array $channelVipCache = [];

    /** @var array VIP等级缓存 [levelId => [val, ts]] */
    private static array $levelCache = [];

    /** @var array 最低等级缓存 [departmentId => [val, ts]] */
    private static array $minLevelCache = [];

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
        self::$minLevelCache = [];
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
     * 获取指定渠道的最低等级（带TTL缓存）
     *
     * 必须限定渠道：否则玩家会被初始化到其他渠道的等级上。
     * department_id = 0 表示全局等级，对所有渠道可见。
     *
     * @return VipLevel|null
     */
    private static function getMinLevelCached(int $departmentId): ?VipLevel
    {
        if (isset(self::$minLevelCache[$departmentId])) {
            $cache = self::$minLevelCache[$departmentId];
            if (time() - $cache['ts'] < self::CACHE_TTL) {
                return $cache['val'];
            }
        }
        /** @var VipLevel|null $level */
        $level = VipLevel::query()
            ->where('status', VipLevel::STATUS_ENABLED)
            ->whereIn('department_id', [0, $departmentId])
            ->orderBy('sort', 'asc')
            ->first();
        self::$minLevelCache[$departmentId] = ['val' => $level, 'ts' => time()];
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

            // 更新总打码量 + 升级经验
            // ⚠️ 两个字段必须同进同退：
            //    period_bet_amount = 升级经验（升级判定）
            //    total_bet_amount - start_bet_amount = 保级窗口打码量（保级判定）
            Db::transaction(function () use ($player, $betAmount) {
                $player->increment('total_bet_amount', $betAmount);
                $player->refresh();

                if (empty($player->vip_level_id)) {
                    return;
                }

                $period = PlayerVipPeriod::query()
                    ->where('player_id', $player->id)
                    ->where('vip_level_id', $player->vip_level_id)
                    ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                    ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                    ->first();

                if ($period) {
                    $period->increment('period_bet_amount', $betAmount);
                }
            });

            if (empty($player->vip_level_id)) {
                // 首笔打码也要计入经验：initVipLevel 会把这一笔写进新周期
                static::initVipLevel($player, $betAmount);

                // 渠道无可用等级时到此为止
                if (empty($player->vip_level_id)) {
                    return;
                }
                // 不再提前返回：首笔可能已满足升级条件，继续走升级检查
            }

            // 查询升级经验累计周期（PERIOD_TYPE_RETAIN，同时兼作保级周期）
            /** @var PlayerVipPeriod|null $period */
            $period = PlayerVipPeriod::query()
                ->where('player_id', $player->id)
                ->where('vip_level_id', $player->vip_level_id)
                ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                ->first();

            // 检查升级（可连升多级）
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

            if (static::RETAIN_CHECK_ENABLED) {
                // 检查保级（含降级）
                static::checkRetain($player, $period, $betAmount);
            } elseif (!$period) {
                // 保级关闭时不再由 checkRetain 补建周期，
                // 这里兜底建一次，并把这一笔计入经验与本窗口
                $currentLevel = static::getLevelCached($player->vip_level_id);
                if ($currentLevel) {
                    static::createRetainPeriod($player, $currentLevel, $betAmount, $betAmount);
                }
            }
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
     *
     * @param Player $player
     * @param float $betAmount 触发初始化的那一笔打码量（计入新周期经验，避免首笔丢失）
     * @return void
     */
    public static function initVipLevel(Player $player, float $betAmount = 0): void
    {
        $minLevel = static::getMinLevelCached((int)$player->department_id);
        if (!$minLevel) {
            return;
        }

        $player->vip_level_id = $minLevel->id;
        $player->save();

        // 首笔既计入升级经验，也计入本保级窗口
        static::createRetainPeriod($player, $minLevel, $betAmount, $betAmount);

        static::log('info', 'VIP init level', [
            'player_id' => $player->id,
            'level_id' => $minLevel->id,
            'level_name' => $minLevel->name,
            'period_bet_amount' => $betAmount,
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

        $level = static::getMinLevelCached((int)$player->department_id);
        if ($level) {
            $player->vip_level_id = $level->id;
            $player->save();
        }

        return $level;
    }

    /**
     * 检查升级条件
     *
     * 一次下注可能同时跨过多个等级门槛（例如一笔巨额打码），
     * 因此循环升级，直到经验不够或已到顶级为止。
     *
     * @param Player $player
     * @param PlayerVipPeriod|null $period 当前等级的经验周期
     * @return bool 是否发生了升级
     */
    public static function checkUpgrade(Player $player, ?PlayerVipPeriod $period = null): bool
    {
        $upgradedAny = false;

        // 安全阀：正常等级数远小于该上限，防止配置异常导致死循环
        for ($step = 0; $step < 20; $step++) {
            $currentLevel = static::getLevelCached($player->vip_level_id);
            if (!$currentLevel || $currentLevel->upgrade_bet_amount <= 0) {
                break;
            }

            $nextLevel = $currentLevel->getNextLevel();
            if (!$nextLevel) {
                break;
            }

            if (!$period) {
                $period = PlayerVipPeriod::query()
                    ->where('player_id', $player->id)
                    ->where('vip_level_id', $currentLevel->id)
                    ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                    ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                    ->first();
            }

            if (!$period || $period->period_bet_amount < $currentLevel->upgrade_bet_amount) {
                break;
            }

            static::doUpgrade($player, $currentLevel, $nextLevel, $period, $period->period_bet_amount);
            $upgradedAny = true;

            // 升级后取新等级的经验周期，继续判断能否再升
            $period = PlayerVipPeriod::query()
                ->where('player_id', $player->id)
                ->where('vip_level_id', $player->vip_level_id)
                ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
                ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
                ->first();
        }

        return $upgradedAny;
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
        $playerId = $player->id;
        $oldLevelId = $currentLevel->id;
        $newLevelId = $nextLevel->id;

        Db::transaction(function () use ($currentPeriod, $player, $nextLevel, $excessBetAmount, $oldLevelId, $newLevelId) {
            $currentPeriod->status = PlayerVipPeriod::STATUS_COMPLETED;
            $currentPeriod->save();

            $player->vip_level_id = $nextLevel->id;
            $player->save();

            // 升级后新等级经验 = 超出升级量的部分（excessBetAmount）
            // 升级经验 = 超额；$currentBetAmount = 0 → 新保级窗口从 0 起
            static::createRetainPeriod($player, $nextLevel, $excessBetAmount, 0);

            // ⭐ 同步更新摸奖券打码进度的 VIP 等级和配置
            static::updateLotteryBetProgressOnUpgrade($player, $oldLevelId, $newLevelId);
        });

        static::log('info', 'VIP upgrade success', [
            'player_id' => $playerId,
            'old_level' => $oldLevelId,
            'new_level' => $newLevelId,
            'period_bet_amount' => $periodBetAmount,
            'upgrade_bet_amount' => $currentLevel->upgrade_bet_amount,
            'excess_bet_amount' => $excessBetAmount,
            'new_start_bet_amount' => $player->total_bet_amount - $excessBetAmount,
        ]);

        // 创建通知记录并推送升级通知
        try {
            $title = 'VIP等級升級';
            $upgradeBonus = (float)($nextLevel->upgrade_bonus ?? 0);

            // 构建推送消息内容
            $content = sprintf('恭喜！您的VIP等級已從 %s 升級至 %s', $currentLevel->name, $nextLevel->name);
            if ($upgradeBonus > 0) {
                $content .= sprintf('，升級禮金 %s 元可領取', number_format($upgradeBonus, 2));
            }

            // 先创建 notice 记录获取 ID
            $notice = Notice::query()->create([
                'department_id' => $player->department_id,
                'player_id' => $playerId,
                'type' => Notice::TYPE_VIP_LEVEL_CHANGE_UPGRADE,
                'title' => $title,
                'content' => '{}', // 临时占位
                'status' => 0,
                'receiver' => Notice::RECEIVER_PLAYER,
                'is_private' => 1,
            ]);

            // 构建完整推送消息（包含 notice_id）
            $pushMessage = [
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
                'upgrade_bonus' => $upgradeBonus,
            ];

            // 更新 notice 的 content（包含完整消息）
            $notice->update([
                'content' => json_encode($pushMessage, JSON_UNESCAPED_UNICODE),
            ]);

            // 推送消息
            sendSocketMessage('player-' . $playerId, $pushMessage);
        } catch (\Throwable $e) {
            static::log('warning', 'VIP upgrade push failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查保级条件（周期 retain_level_days 到期时判定）
     *
     * - 经验 ≥ 保级量 → 保级成功，经验【不扣】，结转进新周期（重置保级计时）
     * - 经验 < 保级量 且 非最低级 → 降一级并【清空经验】，从低一级从 0 累计
     * - 经验 < 保级量 且 已是最低级 → 不降级、不清经验，经验继续累计
     *
     * @param Player $player
     * @param PlayerVipPeriod|null $period 经验/保级周期
     * @param float $betAmount 本次打码量（周期缺失补建时计入经验）
     * @return void
     */
    public static function checkRetain(Player $player, ?PlayerVipPeriod $period = null, float $betAmount = 0): void
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
            // 周期缺失补建时把这一笔计入经验与本窗口，避免打码量丢失
            static::createRetainPeriod($player, $currentLevel, $betAmount, $betAmount);
            return;
        }

        if (!$period->isExpired($currentLevel->retain_level_days)) {
            return;
        }

        // 本保级窗口内新增打码量
        // ⚠️ 必须用 total - start，不能用 period_bet_amount（那是可结转的升级经验，
        //    用它判定会导致「经验曾经超保级量就永远保级成功」）
        $windowBetAmount = $period->getRetainWindowBetAmount($player->total_bet_amount);

        // ---------- 保级成功：经验不扣，结转进新周期；窗口重新开始 ----------
        if ($windowBetAmount >= $currentLevel->retain_level_bet_amount) {
            $period->status = PlayerVipPeriod::STATUS_COMPLETED;
            $period->save();

            // 升级经验原样结转；$currentBetAmount = 0 → 新窗口从 0 起
            static::createRetainPeriod($player, $currentLevel, $period->period_bet_amount, 0);

            static::log('info', 'VIP retain success', [
                'player_id' => $player->id,
                'level_id' => $currentLevel->id,
                'window_bet_amount' => $windowBetAmount,
                'retain_level_bet_amount' => $currentLevel->retain_level_bet_amount,
                'carried_experience' => $period->period_bet_amount,
            ]);
            return;
        }

        // ---------- 保级失败 ----------
        $prevLevel = $currentLevel->getPrevLevel();

        // 已是最低级：不降级、不清经验，经验继续累计，窗口重新开始
        if (!$prevLevel) {
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();

            static::createRetainPeriod($player, $currentLevel, $period->period_bet_amount, 0);

            static::log('info', 'VIP retain failed at min level, no downgrade', [
                'player_id' => $player->id,
                'level_id' => $currentLevel->id,
                'window_bet_amount' => $windowBetAmount,
                'carried_experience' => $period->period_bet_amount,
                'cleared' => false,
            ]);
            return;
        }

        // 非最低级：降一级并清空经验（doDowngrade 里 createRetainPeriod 经验传 0）
        static::doDowngrade($player, $currentLevel, $period);
    }

    /**
     * 执行降级：降一级，并清空经验从 0 开始累计
     *
     * ⚠️ 调用方需保证当前不是最低级（checkRetain 已先行判断）。
     * 此处的 no-prevLevel 分支只是防御性兜底。
     */
    private static function doDowngrade(Player $player, VipLevel $currentLevel, PlayerVipPeriod $period): void
    {
        $prevLevel = $currentLevel->getPrevLevel();
        if (!$prevLevel) {
            // 兜底：无更低等级时不应走到这里，仅关闭周期
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();
            return;
        }

        $playerId = $player->id;
        $oldLevelId = $currentLevel->id;
        $newLevelId = $prevLevel->id;

        Db::transaction(function () use ($period, $player, $prevLevel, $oldLevelId, $newLevelId) {
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();

            $player->vip_level_id = $prevLevel->id;
            $player->save();

            // 经验清 0，新保级窗口也从 0 起
            static::createRetainPeriod($player, $prevLevel, 0, 0);

            // ⭐ 同步更新摸奖券打码进度的 VIP 等级和配置
            static::updateLotteryBetProgressOnDowngrade($player, $oldLevelId, $newLevelId);
        });

        static::log('info', 'VIP downgrade', [
            'player_id' => $playerId,
            'old_level' => $oldLevelId,
            'new_level' => $newLevelId,
            'period_cleared' => true,
        ]);

        // 降级不推送通知
    }

    /**
     * 创建经验 / 保级窗口周期记录
     *
     * 两个字段语义相互独立，不可混用：
     *
     * - period_bet_amount：**升级经验**。跨保级窗口结转，只在升级（结转超额）或
     *   降级（清零）时改变。升级判定读它。
     *
     * - start_bet_amount：**保级窗口起点**。本窗口内新增打码量
     *   = total_bet_amount - start_bet_amount。每次重建周期都会重置，
     *   因此保级判定只看本窗口新增打码，不会被结转过来的经验「白送」保级成功。
     *
     * @param Player $player 玩家
     * @param VipLevel $level VIP等级
     * @param float|null $carryOverExperience 结转的升级经验（null / 0 = 从 0 累计）
     * @param float|null $currentBetAmount 触发建周期的那一笔（计入本保级窗口）
     */
    private static function createRetainPeriod(
        Player $player,
        VipLevel $level,
        ?float $carryOverExperience = null,
        ?float $currentBetAmount = null
    ){
        // 检查是否已存在相同 vip_level_id 的活跃记录，避免重复创建
        $existingPeriod = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $level->id)
            ->where('period_type', PlayerVipPeriod::PERIOD_TYPE_RETAIN)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        if ($existingPeriod) {
            static::log('info', 'VIP retain period already exists, skip creation', [
                'player_id' => $player->id,
                'vip_level_id' => $level->id,
                'existing_period_id' => $existingPeriod->id,
            ]);
            return $existingPeriod;
        }

        $carryOverExperience = max(0, (float)($carryOverExperience ?? 0));
        $currentBetAmount = max(0, (float)($currentBetAmount ?? 0));

        return PlayerVipPeriod::query()->create([
            'player_id' => $player->id,
            'vip_level_id' => $level->id,
            'period_type' => PlayerVipPeriod::PERIOD_TYPE_RETAIN,
            // 窗口起点 = 当前总量 - 触发的那一笔 → 窗口打码量从 0（或该笔）起
            'start_bet_amount' => $player->total_bet_amount - $currentBetAmount,
            // 升级经验：可结转
            'period_bet_amount' => $carryOverExperience,
            'started_at' => date('Y-m-d H:i:s'),
            'status' => PlayerVipPeriod::STATUS_ACTIVE,
        ]);
    }

    /**
     * VIP 升级时更新摸奖券打码进度
     * ⭐ 保留原有打码量，只更新 VIP 配置
     *
     * @param Player $player 玩家对象
     * @param int $oldLevelId 旧 VIP 等级 ID
     * @param int $newLevelId 新 VIP 等级 ID
     * @return void
     */
    private static function updateLotteryBetProgressOnUpgrade(Player $player, int $oldLevelId, int $newLevelId): void
    {
        try {
            // 查找所有进行中的摸奖券活动
            $activeActivities = \addons\webman\model\LotteryTicketActivity::query()
                ->where('department_id', $player->department_id)
                ->where('status', \addons\webman\model\LotteryTicketActivity::STATUS_ONGOING)
                ->where('start_time', '<=', date('Y-m-d H:i:s'))
                ->where('end_time', '>=', date('Y-m-d H:i:s'))
                ->pluck('id');

            if ($activeActivities->isEmpty()) {
                return;
            }

            foreach ($activeActivities as $activityId) {
                // 查找新 VIP 等级的配置
                $newConfig = \addons\webman\model\LotteryTicketVipConfig::query()
                    ->where('activity_id', $activityId)
                    ->where('vip_level_id', $newLevelId)
                    ->where('status', \addons\webman\model\LotteryTicketVipConfig::STATUS_ENABLED)
                    ->first();

                // 查找旧的打码进度记录
                $progress = \addons\webman\model\LotteryTicketBetProgress::query()
                    ->where('player_id', $player->id)
                    ->where('activity_id', $activityId)
                    ->where('vip_level_id', $oldLevelId)
                    ->where('status', \addons\webman\model\LotteryTicketBetProgress::STATUS_ACTIVE)
                    ->first();

                if (!$progress) {
                    continue; // 该活动没有旧进度记录，跳过
                }

                if (!$newConfig) {
                    // 新 VIP 等级没有配置，标记旧进度为结束状态（不删除，保留历史数据）
                    $progress->update([
                        'status' => \addons\webman\model\LotteryTicketBetProgress::STATUS_ENDED,
                    ]);

                    static::log('info', 'VIP 升级后无新配置，结束打码进度', [
                        'player_id' => $player->id,
                        'activity_id' => $activityId,
                        'old_level_id' => $oldLevelId,
                        'new_level_id' => $newLevelId,
                        'old_bet_amount' => $progress->current_bet_amount,
                    ]);
                    continue;
                }

                // ⭐ 保留原有打码量，只更新 VIP 配置
                $oldBetAmount = $progress->current_bet_amount;
                $oldBetRequired = $progress->bet_amount_required;
                $oldTicketPerCycle = $progress->ticket_count_per_cycle;
                $oldCycles = $progress->cycles_completed;
                $alreadyIssued = $progress->total_tickets_issued;

                // 根据新配置重新计算已完成的周期数
                $newCycles = $newConfig->bet_amount_required > 0
                    ? floor($oldBetAmount / $newConfig->bet_amount_required)
                    : 0;

                // ⭐ 修复：正确计算应补发的券数
                // 应该发放的总券数（按新配置）
                $shouldIssueTotal = $newCycles * $newConfig->ticket_count;
                // 应补发券数 = 应发总数 - 已发数量
                $ticketsToIssue = max(0, $shouldIssueTotal - $alreadyIssued);

                // 准备更新数据（先不执行，等发券后一起更新）
                $updateData = [
                    'vip_level_id' => $newLevelId,
                    'bet_amount_required' => $newConfig->bet_amount_required,
                    'ticket_count_per_cycle' => $newConfig->ticket_count,
                    'cycles_completed' => $newCycles,
                    // current_bet_amount 保持不变！
                ];

                // ⭐ 如果应补发券数 > 0，先发放券（在更新进度之前）
                $issuedCount = 0;
                if ($ticketsToIssue > 0) {
                    try {
                        $issueService = new \addons\webman\service\LotteryTicketIssueService();

                        $result = $issueService->issueTicketsBatch(
                            $activityId,
                            $player->id,
                            $ticketsToIssue,
                            \addons\webman\model\LotteryTicket::SOURCE_VIP_UPGRADE
                        );

                        $issuedCount = $result['count'];

                        // 更新数据中包含发券信息
                        $updateData['total_tickets_issued'] = $alreadyIssued + $issuedCount;
                        $updateData['last_issued_at'] = date('Y-m-d H:i:s');

                        static::log('info', 'VIP 升级补发摸奖券', [
                            'player_id' => $player->id,
                            'activity_id' => $activityId,
                            'tickets_to_issue' => $ticketsToIssue,
                            'tickets_issued' => $issuedCount,
                        ]);

                    } catch (\Throwable $e) {
                        // 发券失败不影响配置更新，继续执行
                        static::log('error', 'VIP 升级补发券失败', [
                            'player_id' => $player->id,
                            'activity_id' => $activityId,
                            'tickets_to_issue' => $ticketsToIssue,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // ⭐ 修复：统一更新（避免重复更新）
                $progress->update($updateData);

                static::log('info', 'VIP 升级更新打码进度', [
                    'player_id' => $player->id,
                    'activity_id' => $activityId,
                    'old_level_id' => $oldLevelId,
                    'new_level_id' => $newLevelId,
                    'preserved_bet_amount' => $oldBetAmount,
                    'old_bet_required' => $oldBetRequired,
                    'new_bet_required' => $newConfig->bet_amount_required,
                    'old_ticket_per_cycle' => $oldTicketPerCycle,
                    'new_ticket_per_cycle' => $newConfig->ticket_count,
                    'old_cycles' => $oldCycles,
                    'new_cycles' => $newCycles,
                    'already_issued' => $alreadyIssued,
                    'should_issue_total' => $shouldIssueTotal,
                    'tickets_to_issue' => $ticketsToIssue,
                    'actual_issued' => $issuedCount,
                ]);

                // ⭐ 推送通知（在事务外，失败不影响主流程）
                if ($issuedCount > 0) {
                    try {
                        $activity = \addons\webman\model\LotteryTicketActivity::find($activityId);
                        if ($activity) {
                            $message = sprintf(
                                'VIP升級獎勵：您在活動「%s」中獲得了 %d 張摸獎券！',
                                $activity->name,
                                $issuedCount
                            );
                            \addons\webman\service\LotteryTicketPushService::pushPlayerTicketsUpdate($player->id, $message);
                        }
                    } catch (\Throwable $e) {
                        static::log('warning', 'VIP 升级推送通知失败', [
                            'player_id' => $player->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

        } catch (\Throwable $e) {
            static::log('error', 'VIP 升级更新打码进度失败', [
                'player_id' => $player->id,
                'old_level_id' => $oldLevelId,
                'new_level_id' => $newLevelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * VIP 降级时更新摸奖券打码进度
     * ⭐ 保留原有打码量，只更新 VIP 配置
     *
     * @param Player $player 玩家对象
     * @param int $oldLevelId 旧 VIP 等级 ID
     * @param int $newLevelId 新 VIP 等级 ID
     * @return void
     */
    private static function updateLotteryBetProgressOnDowngrade(Player $player, int $oldLevelId, int $newLevelId): void
    {
        try {
            // 查找所有进行中的摸奖券活动
            $activeActivities = \addons\webman\model\LotteryTicketActivity::query()
                ->where('department_id', $player->department_id)
                ->where('status', \addons\webman\model\LotteryTicketActivity::STATUS_ONGOING)
                ->where('start_time', '<=', date('Y-m-d H:i:s'))
                ->where('end_time', '>=', date('Y-m-d H:i:s'))
                ->pluck('id');

            if ($activeActivities->isEmpty()) {
                return;
            }

            foreach ($activeActivities as $activityId) {
                // 查找新 VIP 等级的配置
                $newConfig = \addons\webman\model\LotteryTicketVipConfig::query()
                    ->where('activity_id', $activityId)
                    ->where('vip_level_id', $newLevelId)
                    ->where('status', \addons\webman\model\LotteryTicketVipConfig::STATUS_ENABLED)
                    ->first();

                // 查找旧的打码进度记录
                $progress = \addons\webman\model\LotteryTicketBetProgress::query()
                    ->where('player_id', $player->id)
                    ->where('activity_id', $activityId)
                    ->where('vip_level_id', $oldLevelId)
                    ->where('status', \addons\webman\model\LotteryTicketBetProgress::STATUS_ACTIVE)
                    ->first();

                if (!$progress) {
                    continue; // 该活动没有旧进度记录，跳过
                }

                if (!$newConfig) {
                    // 新 VIP 等级没有配置，标记旧进度为结束状态
                    $progress->update([
                        'status' => \addons\webman\model\LotteryTicketBetProgress::STATUS_ENDED,
                    ]);

                    static::log('info', 'VIP 降级后无新配置，结束打码进度', [
                        'player_id' => $player->id,
                        'activity_id' => $activityId,
                        'old_level_id' => $oldLevelId,
                        'new_level_id' => $newLevelId,
                        'old_bet_amount' => $progress->current_bet_amount,
                    ]);
                    continue;
                }

                // ⭐ 保留原有打码量，只更新 VIP 配置
                $oldBetAmount = $progress->current_bet_amount;
                $oldBetRequired = $progress->bet_amount_required;
                $oldTicketPerCycle = $progress->ticket_count_per_cycle;
                $oldCycles = $progress->cycles_completed;
                $alreadyIssued = $progress->total_tickets_issued;

                // 根据新配置重新计算已完成的周期数（降级后要求可能更高）
                $newCycles = $newConfig->bet_amount_required > 0
                    ? floor($oldBetAmount / $newConfig->bet_amount_required)
                    : 0;

                // ⭐ 降级：保留 total_tickets_issued（不重新计算，保留历史真实值）
                $progress->update([
                    'vip_level_id' => $newLevelId,
                    'bet_amount_required' => $newConfig->bet_amount_required,
                    'ticket_count_per_cycle' => $newConfig->ticket_count,
                    'cycles_completed' => $newCycles,
                    // current_bet_amount 保持不变！
                    // total_tickets_issued 保持不变！（保留历史真实发券数）
                ]);

                static::log('info', 'VIP 降级更新打码进度', [
                    'player_id' => $player->id,
                    'activity_id' => $activityId,
                    'old_level_id' => $oldLevelId,
                    'new_level_id' => $newLevelId,
                    'preserved_bet_amount' => $oldBetAmount,
                    'old_bet_required' => $oldBetRequired,
                    'new_bet_required' => $newConfig->bet_amount_required,
                    'old_ticket_per_cycle' => $oldTicketPerCycle,
                    'new_ticket_per_cycle' => $newConfig->ticket_count,
                    'old_cycles' => $oldCycles,
                    'new_cycles' => $newCycles,
                    'total_tickets_issued' => $alreadyIssued,
                ]);
            }

        } catch (\Throwable $e) {
            static::log('error', 'VIP 降级更新打码进度失败', [
                'player_id' => $player->id,
                'old_level_id' => $oldLevelId,
                'new_level_id' => $newLevelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
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

        $retainBetAmount = $retainPeriod ? $retainPeriod->getRetainWindowBetAmount($player->total_bet_amount) : 0;
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
