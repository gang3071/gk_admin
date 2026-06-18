<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketRecord;
use app\queue\redis\LotteryTicketPushQueue;
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
     * 活动缓存（避免批量推送时N+1查询）
     * @var array<int, LotteryTicketActivity|null>
     */
    protected static $activityCache = [];

    /**
     * 获取活动（带缓存）
     *
     * @param int $activityId 活动ID
     * @return LotteryTicketActivity|null
     */
    protected static function getActivity(int $activityId): ?LotteryTicketActivity
    {
        if (!isset(self::$activityCache[$activityId])) {
            self::$activityCache[$activityId] = LotteryTicketActivity::find($activityId);
        }
        return self::$activityCache[$activityId];
    }

    /**
     * 清除活动缓存（可选，通常不需要主动调用）
     *
     * @param int|null $activityId 活动ID（null表示清除所有）
     */
    public static function clearActivityCache(?int $activityId = null): void
    {
        if ($activityId) {
            unset(self::$activityCache[$activityId]);
        } else {
            self::$activityCache = [];
        }
    }

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
            // ✅ 使用缓存获取活动
            $activity = self::getActivity($ticket->activity_id);
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
                    'expired_at' => $ticket->expired_at,  // ✅ 修正字段名
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
            // ✅ 使用缓存获取活动
            $activity = self::getActivity($record->activity_id);
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
                'activity_start' => [  // ⚠️ 修正：betting_start → activity_start
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
            // ✅ 生成完整的直播播放地址（供客户端直接使用）
            $playUrls = null;
            if (!empty($activity->live_url)) {
                try {
                    // 使用固定配置ID=1，生成30天有效期的播放地址
                    $urls = generateLotteryLiveUrls(1, $activity->live_url, 30);
                    $playUrls = [
                        'webrtc' => $urls['webrtc'], // 推荐：超低延迟 <1秒
                        'flv' => $urls['flv'],       // 备选：HTTP-FLV
                        'hls' => $urls['hls'],       // 备选：HLS（兼容性好）
                        'expire_time' => $urls['expire_time'],
                        'region' => $urls['region'], // CN（大陆）或 Global（全球）
                    ];
                } catch (\Exception $e) {
                    // 生成播放地址失败时记录日志，但不影响推送
                    Log::warning('生成直播播放地址失败，推送将只包含流名称', [
                        'activity_id' => $activity->id,
                        'stream_name' => $activity->live_url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $message = [
                'type' => 'live_started',
                'title' => '直播開始',
                'message' => sprintf('活動「%s」直播已開始，快來觀看！', $activity->name),
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'stream_name' => $activity->live_url, // ⭐ 流名称（用于备用）
                    'play_urls' => $playUrls,             // ⭐ 完整播放地址（客户端可直接使用）
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
     * 推送直播结束通知
     *
     * @param LotteryTicketActivity $activity 活动对象
     * @return bool
     */
    public static function pushLiveEnded(LotteryTicketActivity $activity): bool
    {
        try {
            $message = [
                'type' => 'live_ended',
                'title' => '直播已結束',
                'message' => sprintf('活動「%s」直播已結束，感謝觀看！', $activity->name),
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'live_status' => $activity->live_status,
                ],
            ];

            // 广播给所有渠道用户
            return self::pushToDepartment($activity->department_id, 'live', $message);

        } catch (\Exception $e) {
            Log::error('直播结束推送失败', [
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
            // ✅ 使用缓存获取活动
            $activity = self::getActivity($activityId);
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

            // ✅ 成功入队，无需记录详细日志（减少磁盘占用）
            // 只在debug模式才记录
            if (config('app.debug')) {
                Log::debug('推送入队', [
                    'player' => $playerId,
                    'type' => $eventType,
                ]);
            }

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
     * @param string $target 推送目标 ('both' = 同时推送玩家和管理员, 'player' = 仅客户端玩家, 'admin' = 仅后台管理员)
     * @return bool
     */
    protected static function pushToDepartment(
        int $departmentId,
        string $eventType,
        array $data,
        string $target = 'both'
    ): bool {
        try {
            // ✅ 根据目标选择频道名称（支持同时推送多个频道）
            $channels = [];

            if ($target === 'both' || $target === 'player') {
                // 客户端玩家频道: player-channel-{department_id}
                $channels[] = "player-channel-{$departmentId}";
            }

            if ($target === 'both' || $target === 'admin') {
                // 后台管理员频道: private-admin_group-channel-{department_id}
                $channels[] = "private-admin_group-channel-{$departmentId}";
            }

            if (empty($channels)) {
                Log::warning('推送目标无效', ['target' => $target]);
                return false;
            }

            // 构造推送内容
            $content = array_merge($data, [
                'timestamp' => time(),
            ]);

            // ✅ 支持多频道推送：将每个频道分别入队
            $successCount = 0;
            foreach ($channels as $channelName) {
                Client::send(
                    LotteryTicketPushQueue::QUEUE_NAME,
                    [
                        'channels' => $channelName,
                        'content' => $content,
                        'from' => self::PUSH_FROM,
                    ],
                    self::QUEUE_DELAY
                );
                $successCount++;
            }

            // ✅ 成功入队，无需记录详细日志
            if (config('app.debug')) {
                Log::debug('广播入队', [
                    'dept' => $departmentId,
                    'type' => $eventType,
                    'target' => $target,
                    'channels' => $channels,
                    'count' => $successCount,
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('推送给渠道失败（入队失败）', [
                'department_id' => $departmentId,
                'event_type' => $eventType,
                'target' => $target,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
        // ✅ 按奖金额从大到小排序，大奖优先推送
        usort($winnerRecords, function($a, $b) {
            return $b->prize_amount <=> $a->prize_amount;
        });

        $successCount = 0;
        $delay = 0; // 初始延迟0秒

        foreach ($winnerRecords as $record) {
            // ✅ 大奖（≥10000元）立即推送，小奖使用延迟
            if ($record->prize_amount >= 10000) {
                $pushDelay = 0;  // 立即推送大奖
            } else {
                $pushDelay = $delay;
            }

            // 每条推送入队
            if (self::pushWinNotificationWithDelay($record, $pushDelay)) {
                $successCount++;
                // 只对小奖增加延迟，大奖不影响延迟计数
                if ($record->prize_amount < 10000) {
                    $delay = floor($successCount / 10);
                }
            }
        }

        // ✅ 简化日志：只记录总数和失败数（成功数可推算）
        if ($successCount < count($winnerRecords)) {
            Log::warning('批量中奖通知部分失败', [
                'total' => count($winnerRecords),
                'failed' => count($winnerRecords) - $successCount,
            ]);
        } else if (config('app.debug')) {
            Log::info('批量中奖通知已入队', [
                'count' => $successCount,
                'max_delay' => $delay . 's',
            ]);
        }

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

    /**
     * 推送奖励已发放通知（发放时才推送）⭐ 新增方法
     *
     * 当管理员手动发放奖励后，推送中奖通知给玩家
     * 与旧的pushDrawResult不同，这个方法在发放时才推送
     *
     * @param int $playerId 玩家ID
     * @param LotteryTicketActivity $activity 活动
     * @param string $ticketNo 券号
     * @param string $prizeName 奖品名称
     * @param float $prizeAmount 奖金金额
     * @return bool
     */
    public static function pushPrizeDistributed(
        int $playerId,
        LotteryTicketActivity $activity,
        string $ticketNo,
        string $prizeName,
        float $prizeAmount
    ): bool
    {
        try {
            $pushData = [
                'event' => 'lottery_prize_distributed',  // 奖励已发放事件 ⭐ 新事件类型
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'ticket_no' => $ticketNo,
                    'prize_level' => $prizeName,
                    'prize_amount' => $prizeAmount,
                    'message' => '恭喜中獎！',
                    'timestamp' => time()
                ]
            ];

            // 推送到指定玩家
            Client::send(
                'push',
                [
                    'type' => 'player',
                    'player_id' => $playerId,
                    'event' => 'lottery_prize_distributed',
                    'data' => $pushData
                ],
                self::QUEUE_DELAY
            );

            Log::info('[摸奖券] 推送奖励发放通知', [
                'player_id' => $playerId,
                'activity_id' => $activity->id,
                'ticket_no' => $ticketNo,
                'prize_name' => $prizeName,
                'prize_amount' => $prizeAmount
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('[摸奖券] 推送奖励发放通知失败', [
                'player_id' => $playerId,
                'activity_id' => $activity->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e; // ⭐ 重新抛出异常，让调用方决定如何处理
        }
    }
}
