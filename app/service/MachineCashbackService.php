<?php

namespace app\service;

use addons\webman\model\Player;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerGameRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerVipPeriod;
use addons\webman\model\SystemSetting;
use addons\webman\model\VipLevel;
use addons\webman\model\VipLevelCashback;
use support\Log;

/**
 * 机台游戏VIP反水补算服务
 *
 * 定时查询已结束但未反水的机台游戏记录，补算VIP反水金额
 */
class MachineCashbackService
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
     * 执行机台游戏反水补算
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
            // 检查机台反水开关是否开启
            $machineRebateEnabled = $this->isMachineRebateEnabled();
            if (!$machineRebateEnabled) {
                $this->log('info', '机台反水开关未开启，仅标记记录已处理');
            }

            // 查询已结束但未计算反水的机台游戏记录
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
            $allVipLevelIds = VipLevel::query()
                ->where('status', VipLevel::STATUS_ENABLED)
                ->pluck('id')
                ->toArray();

            // 获取实体机平台ID
            $platformId = $this->getMachinePlatformId();
            $cashbackMap = $this->preloadCashbackRatios($allVipLevelIds, [$platformId]);

            // 逐条处理
            foreach ($records as $record) {
                try {
                    $player = $players->get($record->player_id);
                    if (!$player) {
                        // 标记为已处理（vip_level_id=0），避免下次重复查询
                        $record->vip_level_id = 0;
                        $record->save();
                        $result['skipped']++;
                        $this->log('debug', '跳过记录：玩家不存在', [
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

                    // 如果机台反水开关关闭，标记记录已处理但不计算打码量和反水
                    if (!$machineRebateEnabled) {
                        $record->vip_level_id = $vipLevelId;
                        $record->chip_amount = 0;
                        $record->cashback_ratio = null;
                        $record->cashback_amount = null;
                        $record->save();
                        $result['skipped']++;
                        $this->log('debug', '跳过机台游戏反水：反水开关已关闭', [
                            'record_id' => $record->id,
                        ]);
                        continue;
                    }

                    // 汇总 chip_amount（从 PlayerGameLog）
                    $totalChipAmount = PlayerGameLog::query()
                        ->where('game_record_id', $record->id)
                        ->sum('chip_amount') ?? 0;

                    if ($totalChipAmount <= 0) {
                        // 标记为已处理，避免下次重复查询
                        $record->vip_level_id = $vipLevelId;
                        $record->chip_amount = 0;
                        $record->save();
                        $result['skipped']++;
                        $this->log('debug', '跳过记录：打码量为0', [
                            'record_id' => $record->id,
                            'player_id' => $record->player_id,
                        ]);
                        continue;
                    }

                    // 计算反水（从预加载缓存获取）
                    $cashbackRatio = $cashbackMap[$vipLevelId][$platformId] ?? 0;
                    $cashbackAmount = VipLevelCashback::calculateCashbackAmount($totalChipAmount, $cashbackRatio);
                    $storageData = VipLevelCashback::formatForStorage($cashbackRatio, $cashbackAmount);

                    // 更新游戏记录
                    $this->updateRecordCashback($record, $vipLevelId, $totalChipAmount, $storageData);

                    // 更新玩家反水金额（待领取 + 总反水）
                    if ($cashbackAmount > 0) {
                        $this->updatePlayerCashbackAmount($player, $cashbackAmount);
                    }

                    // 触发VIP升降级检查（已包含打码量更新）
                    $this->triggerVipUpgradeCheck($player, $totalChipAmount);

                    $result['updated']++;

                } catch (\Throwable $e) {
                    $result['errors']++;
                    $this->log('error', '机台游戏VIP反水补算单条记录失败', [
                        'record_id' => $record->id,
                        'player_id' => $record->player_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($result['updated'] > 0) {
                $this->log('info', '机台游戏VIP反水补算完成', $result);
            }

        } catch (\Throwable $e) {
            $this->log('error', '机台游戏VIP反水补算服务异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    /**
     * 检查机台反水开关是否启用
     *
     * @return bool
     */
    protected function isMachineRebateEnabled(): bool
    {
        try {
            $setting = SystemSetting::query()
                ->where('feature', 'machine_rebate')
                ->where('status', 1)
                ->first();

            return (bool) $setting;
        } catch (\Throwable $e) {
            // 查询失败时默认开启，避免影响正常业务
            $this->log('warning', '查询机台反水开关失败，默认开启', [
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    /**
     * 获取实体机平台ID
     *
     * @return int
     */
    protected function getMachinePlatformId(): int
    {
        return PlayerPlatformCash::PLATFORM_SELF;
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
     * 查询已结束但未反水的机台游戏记录
     *
     * @return \Illuminate\Support\Collection
     */
    protected function queryUnsettledRecords()
    {
        $table = (new PlayerGameRecord())->getTable();

        $query = PlayerGameRecord::query()
            ->whereNull($table . '.vip_level_id')
            ->where($table . '.status', PlayerGameRecord::STATUS_END)
            ->join('player', $table . '.player_id', '=', 'player.id')
            ->select($table . '.*');

        if ($this->sinceDate) {
            $query->where($table . '.updated_at', '>=', $this->sinceDate);
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

        // 检查是否已存在该等级的保级周期记录
        $existingPeriod = PlayerVipPeriod::query()
            ->where('player_id', $player->id)
            ->where('vip_level_id', $levelId)
            ->where('status', PlayerVipPeriod::STATUS_ACTIVE)
            ->first();

        if ($existingPeriod) {
            // 已存在记录，只更新打码量
            $existingPeriod->increment('period_bet_amount', $player->total_bet_amount ?? 0);
        } else {
            // 不存在记录，创建新的保级周期记录
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
    }

    /**
     * 更新游戏记录的反水数据
     * @param PlayerGameRecord $record
     * @param int $vipLevelId
     * @param float $chipAmount
     * @param array $storageData
     */
    protected function updateRecordCashback($record, int $vipLevelId, float $chipAmount, array $storageData): void
    {
        $record->vip_level_id = $vipLevelId;
        $record->cashback_ratio = $storageData['cashback_ratio'];
        $record->cashback_amount = $storageData['cashback_amount'];
        $record->chip_amount = $chipAmount;
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
