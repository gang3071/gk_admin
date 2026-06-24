<?php

namespace app\queue\redis;

use addons\webman\service\LotteryTicketBetProgressService;
use addons\webman\service\LotteryTicketPushService;
use support\Log;
use Webman\RedisQueue\Consumer;

/**
 * 摸奖券打码进度队列消费者
 * 异步处理玩家打码进度更新和自动发券
 */
class LotteryBetProgressConsumer implements Consumer
{
    /**
     * 队列名称
     * @var string
     */
    public $queue = 'lottery-bet-progress';

    /**
     * 连接名称
     * @var string
     */
    public $connection = 'default';

    /**
     * 消费消息
     *
     * @param mixed $data 队列数据
     * @return void
     */
    public function consume($data)
    {
        try {
            $playerId = $data['player_id'] ?? null;
            $chipAmount = $data['chip_amount'] ?? 0;

            if (!$playerId || $chipAmount <= 0) {
                Log::warning('摸奖券队列数据无效', ['data' => $data]);
                return;
            }

            // 调用服务更新打码进度
            $result = LotteryTicketBetProgressService::updateBetProgress(
                $playerId,
                $chipAmount
            );

            // 如果发放了摸奖券，记录日志并推送通知
            if ($result['success'] && !empty($result['results'])) {
                foreach ($result['results'] as $activityResult) {
                    Log::info('摸奖券自动发放成功', [
                        'player_id' => $playerId,
                        'activity_id' => $activityResult['activity_id'],
                        'activity_name' => $activityResult['activity_name'],
                        'tickets_issued' => $activityResult['tickets_issued'],
                        'total_tickets' => $activityResult['total_tickets'],
                    ]);

                    // ✅ 推送通知给玩家
                    if (!empty($activityResult['tickets_issued'])) {
                        $message = sprintf(
                            '您在活動「%s」中獲得了 %d 張摸獎券！',
                            $activityResult['activity_name'],
                            $activityResult['tickets_issued']
                        );
                        LotteryTicketPushService::pushPlayerTicketsUpdate($playerId, $message);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('摸奖券打码进度队列处理失败', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
