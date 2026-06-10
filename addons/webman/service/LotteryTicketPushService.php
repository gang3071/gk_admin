<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketRecord;
use support\Log;

/**
 * 摸奖券推送通知服务
 *
 * 负责向客户端推送摸奖券相关通知
 * 集成 gk_api 的 WebSocket Push 服务
 */
class LotteryTicketPushService
{
    /**
     * Push API 地址
     * @var string
     */
    protected static $pushApiUrl;

    /**
     * Push App Key
     * @var string
     */
    protected static $appKey;

    /**
     * Push App Secret
     * @var string
     */
    protected static $appSecret;

    /**
     * 初始化配置
     */
    protected static function initConfig()
    {
        if (self::$pushApiUrl === null) {
            self::$pushApiUrl = env('PUSH_API_URL', 'http://localhost:3232');
            self::$appKey = env('PUSH_APP_KEY', '');
            self::$appSecret = env('PUSH_APP_SECRET', '');
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
     * 推送给单个玩家
     *
     * @param int $playerId 玩家ID
     * @param string $channel 频道名称
     * @param array $data 数据
     * @param bool $showNotification 是否显示通知
     * @return bool
     */
    protected static function pushToPlayer(
        int $playerId,
        string $channel,
        array $data,
        bool $showNotification = true
    ): bool {
        self::initConfig();

        if (empty(self::$appKey) || empty(self::$appSecret)) {
            Log::warning('Push服务未配置，跳过推送', ['player_id' => $playerId]);
            return false;
        }

        try {
            // 频道名称格式: player_{player_id}
            $channelName = "player_{$playerId}";

            // 调用 gk_api Push API
            $client = new \GuzzleHttp\Client([
                'timeout' => 5,
                'verify' => false,
            ]);

            $pushData = array_merge($data, [
                'show_notification' => $showNotification,
                'timestamp' => time(),
            ]);

            $response = $client->post(self::$pushApiUrl . '/api/push', [
                'json' => [
                    'channel' => $channelName,
                    'event' => $channel,
                    'data' => $pushData,
                ],
                'headers' => [
                    'X-App-Key' => self::$appKey,
                    'X-App-Secret' => self::$appSecret,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if ($result['code'] === 200 || $result['code'] === 0) {
                Log::info('摸奖券推送成功 - 单个玩家', [
                    'player_id' => $playerId,
                    'channel' => $channel,
                    'channel_name' => $channelName,
                ]);
                return true;
            }

            Log::warning('摸奖券推送失败 - 单个玩家', [
                'player_id' => $playerId,
                'response' => $result,
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('推送给玩家失败', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * 推送给整个渠道（广播）
     *
     * @param int $departmentId 渠道ID
     * @param string $channel 频道名称
     * @param array $data 数据
     * @return bool
     */
    protected static function pushToDepartment(int $departmentId, string $channel, array $data): bool
    {
        self::initConfig();

        if (empty(self::$appKey) || empty(self::$appSecret)) {
            Log::warning('Push服务未配置，跳过推送', ['department_id' => $departmentId]);
            return false;
        }

        try {
            // 频道名称格式: department_{department_id}
            $channelName = "department_{$departmentId}";

            // 调用 gk_api Push API
            $client = new \GuzzleHttp\Client([
                'timeout' => 5,
                'verify' => false,
            ]);

            $pushData = array_merge($data, [
                'timestamp' => time(),
            ]);

            $response = $client->post(self::$pushApiUrl . '/api/push', [
                'json' => [
                    'channel' => $channelName,
                    'event' => $channel,
                    'data' => $pushData,
                ],
                'headers' => [
                    'X-App-Key' => self::$appKey,
                    'X-App-Secret' => self::$appSecret,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if ($result['code'] === 200 || $result['code'] === 0) {
                Log::info('摸奖券推送成功 - 渠道广播', [
                    'department_id' => $departmentId,
                    'channel' => $channel,
                    'channel_name' => $channelName,
                ]);
                return true;
            }

            Log::warning('摸奖券推送失败 - 渠道广播', [
                'department_id' => $departmentId,
                'response' => $result,
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('推送给渠道失败', [
                'department_id' => $departmentId,
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
     * 批量推送中奖通知（优化版）
     *
     * @param array $winnerRecords 中奖记录数组
     * @return int 成功推送数量
     */
    public static function batchPushWinNotifications(array $winnerRecords): int
    {
        $successCount = 0;

        foreach ($winnerRecords as $record) {
            if (self::pushWinNotification($record)) {
                $successCount++;
            }

            // 避免推送过快，稍微延迟
            usleep(50000); // 50ms
        }

        Log::info('批量中奖通知推送完成', [
            'total' => count($winnerRecords),
            'success' => $successCount,
            'failed' => count($winnerRecords) - $successCount,
        ]);

        return $successCount;
    }
}
