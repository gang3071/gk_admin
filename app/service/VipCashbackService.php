<?php

namespace app\service;

use addons\webman\model\Player;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\VipLevel;
use addons\webman\model\VipLevelCashback;
use support\Db;
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
    const BATCH_SIZE = 200;

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
            // 条件：settlement_status=已结算 且 vip_level_id 为 NULL 且 bet > 0
            $records = PlayGameRecord::query()
                ->where('settlement_status', PlayGameRecord::SETTLEMENT_STATUS_SETTLED)
                ->whereNull('vip_level_id')
                ->where('bet', '>', 0)
                ->orderBy('id', 'asc')
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($records->isEmpty()) {
                return $result;
            }

            $result['processed'] = $records->count();

            // 批量获取玩家信息
            $playerIds = $records->pluck('player_id')->unique()->toArray();
            $players = Player::query()
                ->whereIn('id', $playerIds)
                ->get()
                ->keyBy('id');

            // 批量获取VIP等级（用于默认等级）
            $defaultLevel = VipLevel::query()
                ->where('status', VipLevel::STATUS_ENABLED)
                ->orderBy('sort', 'asc')
                ->first();

            // 逐条处理（因为需要关联不同平台的反水比例）
            foreach ($records as $record) {
                try {
                    $player = $players[$record->player_id] ?? null;
                    if (!$player) {
                        $result['skipped']++;
                        continue;
                    }

                    // 获取玩家VIP等级，如果没有则使用默认等级
                    $vipLevelId = $player->vip_level_id ?? null;
                    if (empty($vipLevelId)) {
                        if ($defaultLevel) {
                            $vipLevelId = $defaultLevel->id;
                            // 设置玩家默认VIP等级
                            $player->vip_level_id = $defaultLevel->id;
                            $player->save();
                        } else {
                            $result['skipped']++;
                            continue;
                        }
                    }

                    // 查询该平台的反水比例
                    $cashbackRatio = VipLevelCashback::getCashbackRatio($vipLevelId, $record->platform_id);
                    $cashbackAmount = VipLevelCashback::calculateCashbackAmount($record->bet, $cashbackRatio);
                    $storageData = VipLevelCashback::formatForStorage($cashbackRatio, $cashbackAmount);

                    // 更新游戏记录
                    $record->vip_level_id = $vipLevelId;
                    $record->cashback_ratio = $storageData['cashback_ratio'];
                    $record->cashback_amount = $storageData['cashback_amount'];
                    $record->save();

                    // 更新玩家总打码量
                    $player->increment('total_bet_amount', $record->bet);

                    $result['updated']++;

                } catch (\Throwable $e) {
                    $result['errors']++;
                    Log::error('VIP反水补算单条记录失败', [
                        'record_id' => $record->id,
                        'player_id' => $record->player_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($result['updated'] > 0) {
                Log::info('VIP反水补算完成', $result);
            }

        } catch (\Throwable $e) {
            Log::error('VIP反水补算服务异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }
}
