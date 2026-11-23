<?php

namespace app\queue\redis;

use addons\webman\model\PlayerPointsRecord;
use addons\webman\service\PlayerPointsService;
use support\Log;
use Webman\RedisQueue\Consumer;

/**
 * 推广积分入账队列
 *
 * 异步处理推广积分的入账和扣回操作，避免在反水补算循环内执行大量数据库操作。
 * 通过 batch_id 幂等机制保证补算任务重跑不会重复入账。
 */
class PromotePointsQueue implements Consumer
{
    public $queue = 'promote-points';
    public $connection = 'default';

    /**
     * 消费队列消息
     *
     * @param array $data 队列消息数据
     */
    public function consume($data): void
    {
        $recordType    = (string)($data['record_type'] ?? '');
        $recordId      = (int)($data['record_id'] ?? 0);
        $betAmount     = (float)($data['bet_amount'] ?? 0);
        $platformId    = (int)($data['platform_id'] ?? 0);
        $childPlayerId = (int)($data['child_player_id'] ?? 0);
        $parentPlayerId = (int)($data['parent_player_id'] ?? 0);
        $promotePoints = (float)($data['promote_points'] ?? 0);
        $deductChild   = (bool)($data['deduct_child'] ?? false);

        // 参数验证
        if ($recordType === '' || $recordId <= 0 || $childPlayerId <= 0 || $parentPlayerId <= 0 || $promotePoints <= 0) {
            Log::warning('PromotePointsQueue: 参数无效', $data);
            return;
        }

        $adminInfo = ['admin_id' => 0, 'admin_name' => 'system', 'admin_ip' => ''];

        try {
            // 上级直接入账 + 记录（幂等：batch_id 唯一索引保证）
            PlayerPointsService::addPoints(
                $parentPlayerId,
                $promotePoints,
                "推廣積分（來自下級玩家 {$childPlayerId}）",
                $adminInfo,
                PlayerPointsRecord::TYPE_PROMOTE_POINTS,
                PlayerPointsRecord::SOURCE_PROMOTE_POINTS,
                true,
                "children_points:parent:{$recordType}:{$recordId}"
            );

            Log::info('PromotePointsQueue: 上级积分入账成功', [
                'parent_player_id' => $parentPlayerId,
                'child_player_id'  => $childPlayerId,
                'promote_points'   => $promotePoints,
                'record_type'      => $recordType,
                'record_id'        => $recordId,
            ]);

            // 下级扣回抽成（仅机台：下级打码积分已实时全额入账）
            if ($deductChild) {
                $available = PlayerPointsService::getPlayerPoints($childPlayerId)['available_points'];
                $deduct = min($promotePoints, floatval($available));

                if ($deduct < $promotePoints) {
                    Log::warning('PromotePointsQueue: 推广积分抽成扣回时余额不足，按可用余额扣减', [
                        'child_player_id' => $childPlayerId,
                        'promote_points'  => $promotePoints,
                        'available'       => $available,
                        'record_type'     => $recordType,
                        'record_id'       => $recordId,
                    ]);
                }

                if ($deduct > 0) {
                    PlayerPointsService::deductPoints(
                        $childPlayerId,
                        $deduct,
                        "推廣積分抽成（上級玩家 {$parentPlayerId}）",
                        $adminInfo,
                        PlayerPointsRecord::TYPE_PROMOTE_DEDUCT,
                        PlayerPointsRecord::SOURCE_PROMOTE_POINTS,
                        "children_points:child:{$recordType}:{$recordId}"
                    );

                    Log::info('PromotePointsQueue: 下级积分扣回成功', [
                        'parent_player_id' => $parentPlayerId,
                        'child_player_id'  => $childPlayerId,
                        'deduct_points'    => $deduct,
                        'record_type'      => $recordType,
                        'record_id'        => $recordId,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PromotePointsQueue: 积分入账失败', [
                'parent_player_id' => $parentPlayerId,
                'child_player_id'  => $childPlayerId,
                'promote_points'   => $promotePoints,
                'record_type'      => $recordType,
                'record_id'        => $recordId,
                'error'            => $e->getMessage(),
            ]);
        }
    }
}
