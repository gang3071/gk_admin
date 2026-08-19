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

        Db::transaction(function () use ($currentPeriod, $player, $nextLevel, $newStartBetAmount, $oldLevelId, $newLevelId) {
            $currentPeriod->status = PlayerVipPeriod::STATUS_COMPLETED;
            $currentPeriod->save();

            $player->vip_level_id = $nextLevel->id;
            $player->save();

            static::createRetainPeriod($player, $nextLevel, $newStartBetAmount);

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
            'new_start_bet_amount' => $newStartBetAmount,
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

        Db::transaction(function () use ($period, $player, $prevLevel, $oldLevelId, $newLevelId) {
            $period->status = PlayerVipPeriod::STATUS_EXPIRED;
            $period->save();

            $player->vip_level_id = $prevLevel->id;
            $player->save();

            static::createRetainPeriod($player, $prevLevel);

            // ⭐ 同步更新摸奖券打码进度的 VIP 等级和配置
            static::updateLotteryBetProgressOnDowngrade($player, $oldLevelId, $newLevelId);
        });

        static::log('info', 'VIP downgrade', [
            'player_id' => $playerId,
            'old_level' => $oldLevelId,
            'new_level' => $newLevelId,
            'new_start_bet_amount' => $totalBetBefore,
        ]);

        // 降级不推送通知
    }

    /**
     * 创建保级周期记录
     *
     * @param Player $player 玩家
     * @param VipLevel $level VIP等级
     * @param float|null $startBetAmount 周期开始时的打码量
     */
    private static function createRetainPeriod(
        Player $player,
        VipLevel $level,
        ?float $startBetAmount = null
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
