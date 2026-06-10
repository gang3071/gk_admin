<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketRecord;
use addons\webman\queue\LotteryTicketPushQueue;
use support\Log;
use Webman\RedisQueue\Client;

/**
 * 摸奖券推送通知服务
 *
 * 负责向客户端推送摸奖券相关通知
 * 使用Redis队列异步推送，防止大量消息阻塞
 */
class LotteryTicketPushService
{
    /**
     * 队列延迟时间（秒）
     * 0 = 立即推送
     */
    const QUEUE_DELAY = 0;

    /**
     * 推送来源标识
     */
    const PUSH_FROM = 'lottery_system';

    /**
     * 推送摸奖券发放通知
     *
     * @param LotteryTicket $ticket 摸奖券对象
     * @param int $count 发放数量（批量发放时）
     * @return bool
     */
    public static function pushTicketIssued(LotteryTicket $ticket, int $count = 1): bool
    {
        try {
            $activity = LotteryTicketActivity::find($ticket->activity_id);
            if (!$activity) {
                return false;
            }

            $message = [
                'type' => 'ticket_issued',
                'title' => '恭喜獲得摸獎券',
                'message' => sprintf('您在活動「%s」中獲得了 %d 張摸獎券！', $activity->name, $count),
                'data' => [
                    'activity_id' => $ticket->activity_id,
                    'activity_name' => $activity->name,
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'count' => $count,
                    'expires_at' => $ticket->expires_at,
                ],
            ];

            // 推送给玩家
            return self::pushToPlayer($ticket->player_id, 'lottery_ticket', $message);

        } catch (\Exception $e) {
            Log::error('摸奖券发放推送失败', [
                'ticket_id' => $ticket->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 推送中奖通知
     *
     * @param LotteryTicketRecord $record 中奖记录
     * @return bool
     */
    public static function pushWinNotification(LotteryTicketRecord $record): bool
    {
        try {
            $activity = LotteryTicketActivity::find($record->activity_id);
            if (!$activity) {
                return false;
            }

            $message = [
                'type' => 'lottery_win',
                'title' => '🎉 恭喜中獎！',
                'message' => sprintf(
                    '您在活動「%s」中獲得 %s - %s 元！',
                    $activity->name,
                    $record->prize_name,
                    number_format($record->prize_amount, 2)
                ),
                'data' => [
                    'activity_id' => $record->activity_id,
                    'activity_name' => $activity->name,
                    'ticket_no' => $record->ticket_no,
                    'prize_level' => $record->prize_name,
                    'prize_type' => $record->prize_type,
                    'prize_amount' => $record->prize_amount,
                    'record_id' => $record->id,
                ],
            ];

            // 推送给玩家
            return self::pushToPlayer($record->player_id, 'lottery_win', $message);

        } catch (\Exception $e) {
            Log::error('中奖推送失败', [
                'record_id' => $record->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 推送活动状态变更通知
     *
     * @param LotteryTicketActivity $activity 活动对象
     * @param string $event 事件类型 (preheat_start, betting_start, drawing_start, ended)
     * @return bool
     */
    public static function pushActivityStatusChange(LotteryTicketActivity $activity, string $event): bool
    {
        try {
            $messages = [
                'preheat_start' => [
                    'title' => '摸獎券活動預熱中',
                    'message' => sprintf('活動「%s」即將開始，快來參加吧！', $activity->name),
                ],
                'betting_start' => [
                    'title' => '摸獎券活動開始',
                    'message' => sprintf('活動「%s」正式開始，快來參與打碼領券！', $activity->name),
                ],
                'drawing_start' => [
                    'title' => '開獎進行中',
                    'message' => sprintf('活動「%s」開獎中，快來查看中獎結果！', $activity->name),
                ],
                'ended' => [
                    'title' => '活動已結束',
                    'message' => sprintf('活動「%s」已結束，感謝參與！', $activity->name),
                ],
            ];

            if (!isset($messages[$event])) {
                return false;
            }

            $message = [
                'type' => 'activity_status_change',
                'title' => $messages[$event]['title'],
                'message' => $messages[$event]['message'],
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'status' => $activity->status,
                    'event' => $event,
                ],
            ];

            // 广播给所有渠道用户
            return self::pushToDepartment($activity->department_id, 'lottery_activity', $message);

        } catch (\Exception $e) {
            Log::error('活动状态变更推送失败', [
                'activity_id' => $activity->id ?? null,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 推送直播开始通知
     *
     * @param LotteryTicketActivity $activity 活动对象
     * @return bool
     */
    public static function pushLiveStarted(LotteryTicketActivity $activity): bool
    {
        try {
            $message = [
                'type' => 'live_started',
                'title' => '直播開始',
                'message' => sprintf('活動「%s」直播已開始，快來觀看！', $activity->name),
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'live_url' => $activity->live_url,
                    'live_status' => $activity->live_status,
                ],
            ];

            // 广播给所有渠道用户
            return self::pushToDepartment($activity->department_id, 'live', $message);

        } catch (\Exception $e) {
            Log::error('直播开始推送失败', [
                'activity_id' => $activity->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 推送打码进度更新通知
     *
     * @param int $playerId 玩家ID
     * @param int $activityId 活动ID
     * @param float $progressPercent 进度百分比
     * @param float $remainingAmount 剩余打码量
     * @return bool
     */
    public static function pushBetProgressUpdate(
        int $playerId,
        int $activityId,
        float $progressPercent,
        float $remainingAmount
    ): bool {
        try {
            $activity = LotteryTicketActivity::find($activityId);
            if (!$activity) {
                return false;
            }

            $message = [
                'type' => 'bet_progress_update',
                'data' => [
                    'activity_id' => $activityId,
                    'activity_name' => $activity->name,
                    'progress_percent' => round($progressPercent, 2),
                    'remaining_amount' => $remainingAmount,
                ],
            ];

            // 推送给玩家（静默推送，不显示通知）
            return self::pushToPlayer($playerId, 'bet_progress', $message, false);

        } catch (\Exception $e) {
            Log::error('打码进度推送失败', [
                'player_id' => $playerId,
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 推送给单个玩家（使用队列异步推送）
     *
     * @param int $playerId 玩家ID
     * @param string $eventType 事件类型（用于日志分类）
     * @param array $data 数据
     * @param bool $showNotification 是否显示通知
     * @return bool
     */
    protected static function pushToPlayer(
        int $playerId,
        string $eventType,
        array $data,
        bool $showNotification = true
    ): bool {
        try {
            // 频道名称格式: player-{player_id}（遵循系统规范）
            $channelName = "player-{$playerId}";

            // 构造推送内容
            $content = array_merge($data, [
                'show_notification' => $showNotification,
                'timestamp' => time(),
            ]);

            // 将推送任务加入队列（异步处理，不阻塞主流程）
            Client::send(
                LotteryTicketPushQueue::QUEUE_NAME,
                [
                    'channels' => $channelName,
                    'content' => $content,
                    'from' => self::PUSH_FROM,
                ],
                self::QUEUE_DELAY
            );

            Log::debug('摸奖券推送任务已入队 - 单个玩家', [
                'player_id' => $playerId,
                'event_type' => $eventType,
                'channel' => $channelName,
                'show_notification' => $showNotification,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('推送给玩家失败（入队失败）', [
                'player_id' => $playerId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * 推送给整个渠道（广播，使用队列异步推送）
     *
     * @param int $departmentId 渠道ID
     * @param string $eventType 事件类型
     * @param array $data 数据
     * @return bool
     */
    protected static function pushToDepartment(int $departmentId, string $eventType, array $data): bool
    {
        try {
            // 频道名称格式: private-admin_group-channel-{department_id}（遵循系统规范）
            $channelName = "private-admin_group-channel-{$departmentId}";

            // 构造推送内容
            $content = array_merge($data, [
                'timestamp' => time(),
            ]);

            // 将推送任务加入队列（异步处理，不阻塞主流程）
            Client::send(
                LotteryTicketPushQueue::QUEUE_NAME,
                [
                    'channels' => $channelName,
                    'content' => $content,
                    'from' => self::PUSH_FROM,
                ],
                self::QUEUE_DELAY
            );

            Log::debug('摸奖券推送任务已入队 - 渠道广播', [
                'department_id' => $departmentId,
                'event_type' => $eventType,
                'channel' => $channelName,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('推送给渠道失败（入队失败）', [
                'department_id' => $departmentId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * 推送活动摇球结果（广播给所有参与玩家）
     *
     * @param LotteryTicketActivity $activity 活动对象
     * @param array $ballResult 摇球结果
     * @param int $winningCount 中奖数量
     * @return bool
     */
    public static function pushDrawResult(
        LotteryTicketActivity $activity,
        array $ballResult,
        int $winningCount
    ): bool {
        try {
            $message = [
                'type' => 'draw_result',
                'title' => '🎉 开奖结果公布',
                'message' => sprintf(
                    '活动「%s」开奖完成！中奖券号：%s，共 %d 人中奖！',
                    $activity->name,
                    $ballResult['winning_no'],
                    $winningCount
                ),
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'ball_result' => $ballResult,
                    'winning_count' => $winningCount,
                ],
            ];

            // 广播给所有渠道用户
            return self::pushToDepartment($activity->department_id, 'draw_result', $message);

        } catch (\Exception $e) {
            Log::error('摇球结果推送失败', [
                'activity_id' => $activity->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 批量推送中奖通知（使用队列，避免阻塞）
     *
     * @param array $winnerRecords 中奖记录数组
     * @return int 成功入队数量
     */
    public static function batchPushWinNotifications(array $winnerRecords): int
    {
        $successCount = 0;
        $delay = 0; // 初始延迟0秒

        foreach ($winnerRecords as $record) {
            // 每条推送入队，使用递增延迟避免瞬时压力
            if (self::pushWinNotificationWithDelay($record, $delay)) {
                $successCount++;
                // 每10条增加1秒延迟，平滑推送
                $delay = floor($successCount / 10);
            }
        }

        Log::info('批量中奖通知已入队', [
            'total' => count($winnerRecords),
            'success' => $successCount,
            'failed' => count($winnerRecords) - $successCount,
            'max_delay' => $delay . 's',
        ]);

        return $successCount;
    }

    /**
     * 推送中奖通知（带延迟入队）
     *
     * @param LotteryTicketRecord $record 中奖记录
     * @param int $delay 延迟秒数
     * @return bool
     */
    protected static function pushWinNotificationWithDelay(LotteryTicketRecord $record, int $delay = 0): bool
    {
        try {
            $activity = LotteryTicketActivity::find($record->activity_id);
            if (!$activity) {
                return false;
            }

            $channelName = "player-{$record->player_id}";

            $content = [
                'type' => 'lottery_win',
                'title' => '🎉 恭喜中獎！',
                'message' => sprintf(
                    '您在活動「%s」中獲得 %s - %s 元！',
                    $activity->name,
                    $record->prize_name,
                    number_format($record->prize_amount, 2)
                ),
                'data' => [
                    'activity_id' => $record->activity_id,
                    'activity_name' => $activity->name,
                    'ticket_no' => $record->ticket_no,
                    'prize_level' => $record->prize_name,
                    'prize_type' => $record->prize_type,
                    'prize_amount' => $record->prize_amount,
                    'record_id' => $record->id,
                ],
                'show_notification' => true,
                'priority' => 'high',
                'timestamp' => time(),
            ];

            // 使用延迟入队，平滑推送压力
            Client::send(
                LotteryTicketPushQueue::QUEUE_NAME,
                [
                    'channels' => $channelName,
                    'content' => $content,
                    'from' => self::PUSH_FROM,
                ],
                $delay
            );

            return true;

        } catch (\Exception $e) {
            Log::error('中奖推送入队失败', [
                'record_id' => $record->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
