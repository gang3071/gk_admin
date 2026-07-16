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
     * 获取玩家在所有有效活动下的总券数
     * ⭐ 统计：进行中、待开奖、开奖中的活动（券仍然有效）
     * ⭐ 排除：未开始、已结束、已关闭的活动（券无效或不存在）
     *
     * @param int $playerId 玩家ID
     * @return int 总券数
     */
    protected static function getPlayerTotalTickets(int $playerId): int
    {
        // ⭐ 查询券仍然有效的活动：
        // - STATUS_ONGOING (进行中，可以发券)
        // - STATUS_PENDING_DRAW (待开奖，不再发券但券有效)
        // - STATUS_DRAWING (开奖中，券有效，可能被抽中)
        //
        // 排除的状态：
        // - STATUS_NOT_STARTED (未开始，无券)
        // - STATUS_ENDED (已结束，券失效)
        // - STATUS_CLOSED (已关闭，券失效)
        $ongoingActivityIds = LotteryTicketActivity::whereIn('status', [
            LotteryTicketActivity::STATUS_ONGOING,
            LotteryTicketActivity::STATUS_PENDING_DRAW,
            LotteryTicketActivity::STATUS_DRAWING,
        ])
            ->pluck('id')
            ->toArray();

        if (empty($ongoingActivityIds)) {
            return 0;
        }

        // 统计玩家在这些活动下的总券数
        $totalTickets = LotteryTicket::query()
            ->where('player_id', $playerId)
            ->whereIn('activity_id', $ongoingActivityIds)
            ->where('status', LotteryTicket::STATUS_UNUSED)  // 只统计未使用的
            ->count();

        return $totalTickets;
    }

    /**
     * 推送玩家有效券数更新（通用方法）
     *
     * @param int $playerId 玩家ID
     * @param string $message 提示消息（可选）
     * @param int|null $totalTickets 总券数（可选，传null则自动查询）
     * @return bool
     */
    public static function pushPlayerTicketsUpdate(int $playerId, string $message = '', ?int $totalTickets = null): bool
    {
        try {
            // ⭐ 如果未传券数，则查询数据库
            if ($totalTickets === null) {
                $totalTickets = self::getPlayerTotalTickets($playerId);
            }

            $pushMessage = [
                'type' => 'ticket_issued',
                'title' => '摸獎券更新',
                'message' => $message ?: '您的摸獎券已更新',
                'data' => [
                    'total_tickets' => $totalTickets,  // 只推送总券数
                ],
            ];

            // 推送给玩家
            return self::pushToPlayer($playerId, 'lottery_ticket', $pushMessage);

        } catch (\Exception $e) {
            Log::error('券数更新推送失败', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 批量推送券数更新（活动状态变化时推送给所有参与玩家）
     *
     * @param int $activityId 活动ID
     * @param string $message 推送消息
     * @return int 推送成功的玩家数量
     */
    public static function pushActivityPlayersTicketsUpdate(int $activityId, string $message = ''): int
    {
        try {
            // 查询该活动下所有有券的玩家
            $playerIds = LotteryTicket::where('activity_id', $activityId)
                ->distinct()
                ->pluck('player_id')
                ->toArray();

            if (empty($playerIds)) {
                return 0;
            }

            $successCount = 0;
            foreach ($playerIds as $playerId) {
                if (self::pushPlayerTicketsUpdate($playerId, $message)) {
                    $successCount++;
                }
            }

            Log::info('批量推送券数更新', [
                'activity_id' => $activityId,
                'message' => $message,
                'total_players' => count($playerIds),
                'success_count' => $successCount,
            ]);

            return $successCount;

        } catch (\Exception $e) {
            Log::error('批量推送券数更新失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * 推送活动状态变更通知
     *
     * @param LotteryTicketActivity $activity 活动对象
     * @param string $event 事件类型 (activity_start, drawing_start, ended)
     * @return bool
     */
    public static function pushActivityStatusChange(LotteryTicketActivity $activity, string $event): bool
    {
        try {
            $messages = [
                'activity_start' => [
                    'title' => '摸獎券活動開始',
                    'message' => sprintf('活動「%s」正式開始，快來參與打碼領券！', $activity->name),
                ],
                'pending_draw' => [
                    'title' => '活動待開獎',
                    'message' => sprintf('活動「%s」打碼階段已結束，即將開獎，請耐心等待！', $activity->name),
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
                    // ⭐ 自动根据 APP_ENV 选择线路（传 null）
                    // - APP_ENV=pro: 走海外线路（useCnDomain=false）
                    // - APP_ENV=其他: 走大陆线路（useCnDomain=true）
                    $urls = generateLotteryLiveUrls(1, $activity->live_url, 30, null);
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
     * @param float $currentBetAmount 当前累计打码量（总打码量）
     * @param float $betAmountRequired 单周期要求打码量
     * @param float $progressPercent 进度百分比（当前周期内）
     * @param float $remainingAmount 剩余打码量
     * @return bool
     */
    public static function pushBetProgressUpdate(
        int $playerId,
        int $activityId,
        float $currentBetAmount,
        float $betAmountRequired,
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
                    'current_bet_amount' => $currentBetAmount,        // 总打码量
                    'bet_amount_required' => $betAmountRequired,      // 单周期要求打码量
                    'progress_percent' => round($progressPercent, 2), // 当前周期进度百分比
                    'remaining_amount' => $remainingAmount,           // 距离下次发券剩余打码量
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
     * 格式化金额显示（整数不显示小数位）
     *
     * @param float $amount 金额
     * @return string
     */
    protected static function formatAmount(float $amount): string
    {
        // 判断是否为整数
        if (floor($amount) == $amount) {
            // 整数：不显示小数位
            return number_format($amount, 0, '.', ',');
        } else {
            // 小数：显示两位小数
            return number_format($amount, 2, '.', ',');
        }
    }

    /**
     * 推送跑马灯广播（特等奖和一等奖中奖公告）
     *
     * 使用场景：
     * - 特等奖中奖（level_rank = 1）
     * - 一等奖中奖（level_rank = 2）
     *
     * 参考：HighScoreBroadcastService（高分广播）
     * 使用全局广播频道：group-lottery-pool
     *
     * @param int $departmentId 渠道ID
     * @param LotteryTicketActivity $activity 活动对象
     * @param string $playerName 玩家名称（脱敏后）
     * @param string $storeName 店家名称
     * @param string $prizeName 奖品名称
     * @param float $prizeAmount 奖金金额
     * @return bool
     */
    public static function pushMarqueeAnnouncement(
        int $departmentId,
        LotteryTicketActivity $activity,
        string $playerName,
        string $storeName,
        string $prizeName,
        float $prizeAmount
    ): bool {
        try {
            // 构造跑马灯消息（活泼热闹风格）
            if (!empty($storeName)) {
                // 有店家：显示店名
                $content = sprintf(
                    '🎊 摸獎大報喜！狂賀【%s】玩家（%s）手氣大爆發，幸運抱走《%s》，狂得 %s 分！',
                    $storeName,
                    $playerName,
                    $prizeName,
                    self::formatAmount($prizeAmount) // 智能格式化：整数不显示小数位
                );
            } else {
                // 无店家：不显示店名
                $content = sprintf(
                    '🎊 摸獎大報喜！狂賀玩家（%s）手氣大爆發，幸運抱走《%s》，狂得 %s 分！',
                    $playerName,
                    $prizeName,
                    self::formatAmount($prizeAmount) // 智能格式化：整数不显示小数位
                );
            }

            $data = [
                'msg_type' => 'high_score_broadcast', // 消息类型（与高分广播统一，客户端可复用同一套跑马灯逻辑）
                'title' => '🎊 摸獎大報喜',
                'content' => $content,
                'timestamp' => time(),
                'department_id' => $departmentId,
                'store_name' => $storeName,
                'player_name' => $playerName,
                'activity_name' => $activity->name,
                'prize_name' => $prizeName,
                'prize_amount' => $prizeAmount,
            ];

            // 使用全局广播频道（与高分广播、彩金通知保持一致）
            sendSocketMessage('group-lottery-pool', $data);

            Log::info('[摸奖券] 跑马灯广播已发送', [
                'channel' => 'group-lottery-pool',
                'department_id' => $departmentId,
                'activity_id' => $activity->id,
                'player_name' => $playerName,
                'store_name' => $storeName,
                'prize_name' => $prizeName,
                'prize_amount' => $prizeAmount,
                'content' => $content
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('[摸奖券] 跑马灯广播失败', [
                'department_id' => $departmentId,
                'activity_id' => $activity->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }

    /**
     * 推送中奖通知（奖励发放时推送）
     *
     * 使用场景：
     * - 录入中奖时自动发放奖励
     * - 批量发放历史待发放记录
     * - 按券号单独发放奖励
     *
     * @param int $playerId 玩家ID
     * @param LotteryTicketActivity $activity 活动对象
     * @param string $ticketNo 券号
     * @param string $prizeName 奖品名称
     * @param float $prizeAmount 奖金金额
     * @param int|null $levelRank 奖品等级排名（1=特等奖，2=一等奖...），用于判断是否发送跑马灯
     * @return bool
     */
    public static function pushPrizeDistributed(
        int $playerId,
        LotteryTicketActivity $activity,
        string $ticketNo,
        string $prizeName,
        float $prizeAmount,
        ?int $levelRank = null
    ): bool
    {
        try {
            // 频道名称格式: player-{player_id}（遵循系统规范）
            $channelName = "player-{$playerId}";

            // 构造推送内容 - 使用统一格式
            $content = [
                'type' => 'lottery_win',
                'title' => '🎉 恭喜中獎！',
                'message' => sprintf(
                    '您在活動「%s」中獲得%s-%s分！獎金已自動發放到您的錢包。',
                    $activity->name,
                    $prizeName,
                    self::formatAmount($prizeAmount) // 智能格式化：整数不显示小数位
                ),
                'data' => [
                    'activity_id' => $activity->id,
                    'activity_name' => $activity->name,
                    'ticket_no' => $ticketNo,
                    'prize_level' => $prizeName,
                    'prize_type' => LotteryTicketRecord::PRIZE_TYPE_CASH,
                    'prize_amount' => $prizeAmount,
                ],
                'show_notification' => true,
                'priority' => 'high',
                'timestamp' => time(),
            ];

            // 将推送任务加入队列
            Client::send(
                LotteryTicketPushQueue::QUEUE_NAME,
                [
                    'channels' => $channelName,
                    'content' => $content,
                    'from' => self::PUSH_FROM,
                ],
                self::QUEUE_DELAY
            );

            Log::info('[摸奖券] 推送中奖通知已入队', [
                'player_id' => $playerId,
                'activity_id' => $activity->id,
                'ticket_no' => $ticketNo,
                'prize_name' => $prizeName,
                'prize_amount' => $prizeAmount
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('[摸奖券] 推送中奖通知失败', [
                'player_id' => $playerId,
                'activity_id' => $activity->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            // 不抛出异常，避免影响发放流程
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
}
