<?php

namespace app\service;

use addons\webman\model\GamePlatform;
use addons\webman\model\Player;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerVipPeriod;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\VipLevel;
use addons\webman\model\VipLevelCashback;
use support\Log;

/**
 * VIP反水补算服务
 *
 * 定时查询已结算但未反水的游戏记录，补算VIP反水金额
 */
class VipCashbackService
{
    /**
     * 每批处理记录数
     */
    const BATCH_SIZE = 2000;

    /**
     * @var callable|null 日志回调（可注入，用于单元测试）
     */
    private $logger = null;

    /**
     * @var string|null 起始日期（只查询该时间之后的记录）
     */
    private $sinceDate = null;

    /**
     * 设置日志回调
     * @param callable $logger function(string $level, string $message, array $context = [])
     * @return $this
     */
    public function setLogger(callable $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 设置起始日期过滤
     * @param string $date Y-m-d H:i:s 格式
     * @return $this
     */
    public function setSinceDate(string $date): self
    {
        $this->sinceDate = $date;
        return $this;
    }

    /**
     * 获取起始日期
     * @return string|null
     */
    public function getSinceDate(): ?string
    {
        return $this->sinceDate;
    }

    /**
     * 写日志
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            ($this->logger)($level, $message, $context);
            return;
        }
        try {
            Log::channel('vip')->$level($message, $context);
        } catch (\Throwable $e) {
            // 测试环境忽略日志错误
        }
    }

    /**
     * 执行反水补算
     * @return array 处理结果统计
     */
    public function execute(): array
    {
        $result = [
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        try {
            // 查询已结算但未计算反水的游戏记录
            $records = $this->queryUnsettledRecords();

            if ($records->isEmpty()) {
                return $result;
            }

            $result['processed'] = $records->count();

            // 批量获取玩家信息
            $playerIds = $records->pluck('player_id')->unique();
            $players = $this->queryPlayers($playerIds);

            // 获取默认VIP等级（最低等级）
            /** @var VipLevel|null $defaultLevel */
            $defaultLevel = VipLevel::query()
                ->where('status', VipLevel::STATUS_ENABLED)
                ->orderBy('sort', 'asc')
                ->first();

            // 批量预加载反水比例（避免 N+1 查询）
            $platformIds = $records->pluck('platform_id')->unique()->toArray();
            $vipLevelIds = $players->pluck('vip_level_id')->filter()->unique()->toArray();
            if ($defaultLevel) {
                $vipLevelIds[] = $defaultLevel->id;
            }
            $cashbackMap = $this->preloadCashbackRatios(array_unique($vipLevelIds), $platformIds);

            // 逐条处理
            foreach ($records as $record) {
                try {
                    $player = $players->get($record->player_id);
                    if (!$player) {
                        // 标记为已处理（vip_level_id=0），避免下次重复查询
                        $record->vip_level_id = 0;
                        $record->save();
                        $result['skipped']++;
                        $this->log('debug', '跳过记录：玩家不存在或非线上玩家', [
                            'record_id' => $record->id,
                            'player_id' => $record->player_id,
                        ]);
                        continue;
                    }

                    // 获取玩家VIP等级，如果没有则使用默认等级
                    $vipLevelId = $player->vip_level_id ?? null;
                    if (empty($vipLevelId)) {
                        if ($defaultLevel) {
                            $vipLevelId = $defaultLevel->id;
                            $this->assignDefaultVipLevel($player, $defaultLevel->id);
                        } else {
                            $result['skipped']++;
                            $this->log('debug', '跳过记录：无VIP等级且无默认等级', [
                                'record_id' => $record->id,
                                'player_id' => $record->player_id,
                            ]);
                            continue;
                        }
                    }

                    // 计算反水（从预加载缓存获取）
                    $cashbackRatio = $cashbackMap[$vipLevelId][$record->platform_id] ?? 0;
                    $cashbackAmount = VipLevelCashback::calculateCashbackAmount($record->bet, $cashbackRatio);
                    $storageData = VipLevelCashback::formatForStorage($cashbackRatio, $cashbackAmount);

                    // 更新游戏记录
                    $this->updateRecordCashback($record, $vipLevelId, $storageData);

                    // 更新玩家反水金额（待领取 + 总反水）
                    if ($cashbackAmount > 0) {
                        $this->updatePlayerCashbackAmount($player, $cashbackAmount);
                    }

                    // 触发VIP升降级检查（已包含打码量更新）
                    $this->triggerVipUpgradeCheck($player, $record->bet);

                    $result['updated']++;

                } catch (\Throwable $e) {
                    $result['errors']++;
                    $this->log('error', 'VIP反水补算单条记录失败', [
                        'record_id' => $record->id,
                        'player_id' => $record->player_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($result['updated'] > 0) {
                $this->log('info', 'VIP反水补算完成', $result);
            }

        } catch (\Throwable $e) {
            $this->log('error', 'VIP反水补算服务异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    /**
     * 获取百家平台ID列表（cate_id包含3），带缓存
     * cate_id 存储格式为 JSON 数组：[3,2,4,5,6,7,8]
     * @return array
     */
    private function getBaccaratPlatformIds(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = GamePlatform::query()
            ->whereRaw('JSON_CONTAINS(cate_id, CAST(? AS JSON))', [3])
            ->pluck('id')
            ->toArray();

        return $cache;
    }

    /**
     * 批量预加载反水比例
     * @param array $vipLevelIds VIP等级ID列表
     * @param array $platformIds 平台ID列表
     * @return array [vipLevelId => [platformId => ratio]]
     */
    private function preloadCashbackRatios(array $vipLevelIds, array $platformIds): array
    {
        if (empty($vipLevelIds) || empty($platformIds)) {
            return [];
        }

        $records = VipLevelCashback::query()
            ->whereIn('vip_level_id', $vipLevelIds)
            ->whereIn('platform_id', $platformIds)
            ->where('status', 1)
            ->get();

        $map = [];
        foreach ($records as $r) {
            $map[$r->vip_level_id][$r->platform_id] = $r->cashback_ratio;
        }
        return $map;
    }

    /**
     * 查询已结算但未反水的游戏记录
     * 过滤条件：线上玩家、排除百家平台（cate_id含3）
     *
     * @return \Illuminate\Support\Collection
     */
    protected function queryUnsettledRecords()
    {
        $table = (new PlayGameRecord())->getTable();
        $baccaratIds = $this->getBaccaratPlatformIds();

        $query = PlayGameRecord::query()
            ->whereNull($table . '.vip_level_id')
            ->where($table . '.bet', '>', 0)
            ->where($table . '.settlement_status', PlayGameRecord::SETTLEMENT_STATUS_SETTLED)  // ✅ 只处理已结算记录
            ->join('player', $table . '.player_id', '=', 'player.id')
            ->where('player.player_source', Player::PLAYER_SOURCE_ONLINE)
            ->select($table . '.*');

        // 排除百家平台（用 NOT IN 走索引）
        if (!empty($baccaratIds)) {
            $query->whereNotIn($table . '.platform_id', $baccaratIds);
        }

        if ($this->sinceDate) {
            $query->where($table . '.created_at', '>=', $this->sinceDate);
        }

        return $query->orderBy($table . '.id', 'asc')
            ->limit(self::BATCH_SIZE)
            ->get();
    }

    /**
     * 批量查询玩家
     * @param \Illuminate\Support\Collection $playerIds
     * @return \Illuminate\Support\Collection
     */
    protected function queryPlayers($playerIds)
    {
        return Player::query()
            ->whereIn('id', $playerIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * 为玩家分配默认VIP等级
     * @param Player $player
     * @param int $levelId
     */
    protected function assignDefaultVipLevel($player, int $levelId): void
    {
        $player->vip_level_id = $levelId;
        $player->save();

        // 创建保级周期记录
        PlayerVipPeriod::query()->create([
            'player_id' => $player->id,
            'vip_level_id' => $levelId,
            'period_type' => PlayerVipPeriod::PERIOD_TYPE_RETAIN,
            'start_bet_amount' => $player->total_bet_amount ?? 0,
            'period_bet_amount' => 0,
            'started_at' => date('Y-m-d H:i:s'),
            'status' => PlayerVipPeriod::STATUS_ACTIVE,
        ]);
    }

    /**
     * 更新游戏记录的反水数据
     * @param PlayGameRecord $record
     * @param int $vipLevelId
     * @param array $storageData
     */
    protected function updateRecordCashback($record, int $vipLevelId, array $storageData): void
    {
        $record->vip_level_id = $vipLevelId;
        $record->cashback_ratio = $storageData['cashback_ratio'];
        $record->cashback_amount = $storageData['cashback_amount'];
        $record->save();
    }

    /**
     * 更新玩家反水金额（待领取 + 总反水）
     * 如果 player_extend 不存在则自动创建
     *
     * @param Player $player
     * @param float $cashbackAmount 反水金额
     */
    protected function updatePlayerCashbackAmount($player, float $cashbackAmount): void
    {
        try {
            $playerExtend = $player->player_extend;
            if (!$playerExtend) {
                $playerExtend = new PlayerExtend();
                $playerExtend->player_id = $player->id;
                $playerExtend->pending_cashback_amount = 0;
                $playerExtend->total_cashback_amount = 0;
                $playerExtend->save();
            }
            $playerExtend->addCashback($cashbackAmount);
        } catch (\Throwable $e) {
            $this->log('warning', '更新玩家反水金额失败', [
                'player_id' => $player->id,
                'cashback_amount' => $cashbackAmount,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 增加玩家总打码量
     * @param Player $player
     * @param float $betAmount
     */
    protected function incrementPlayerBetAmount($player, float $betAmount): void
    {
        $player->increment('total_bet_amount', $betAmount);
    }

    /**
     * 触发VIP升降级检查
     * handleBet 内部会自行 refresh 玩家数据，无需重复刷新
     *
     * @param Player $player
     * @param float $betAmount
     */
    protected function triggerVipUpgradeCheck($player, float $betAmount): void
    {
        try {
            VipService::handleBet($player, $betAmount);
        } catch (\Throwable $e) {
            $this->log('warning', 'VIP升降级检查失败', [
                'player_id' => $player->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
