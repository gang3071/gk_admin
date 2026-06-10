<?php

namespace addons\webman\queue;

use Webman\Push\Api;
use support\Log;

/**
 * 摸奖券推送队列消费者
 *
 * 队列名称: lottery-ticket-push
 * 消费模式: 异步消费
 * 并发数: 5（防止推送过载）
 */
class LotteryTicketPushQueue
{
    /**
     * 队列名称
     */
    const QUEUE_NAME = 'lottery-ticket-push';

    /**
     * 消费队列消息
     *
     * @param array $data 推送数据
     * @return void
     */
    public function consume($data)
    {
        try {
            $channels = $data['channels'] ?? '';
            $content = $data['content'] ?? [];
            $from = $data['from'] ?? 'lottery_system';

            if (empty($channels) || empty($content)) {
                Log::warning('摸奖券推送队列：参数缺失', [
                    'data' => $data,
                ]);
                return;
            }

            // 调用系统的 sendSocketMessage 函数
            $result = $this->sendSocketMessage($channels, $content, $from);

            if ($result) {
                Log::info('摸奖券推送成功', [
                    'channels' => $channels,
                    'type' => $content['type'] ?? 'unknown',
                    'from' => $from,
                ]);
            } else {
                Log::error('摸奖券推送失败', [
                    'channels' => $channels,
                    'content' => $content,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('摸奖券推送队列消费异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $data ?? null,
            ]);
        }
    }

    /**
     * 发送Socket消息（复制系统函数逻辑）
     *
     * @param string|array $channels 频道名称
     * @param array $content 消息内容
     * @param string $from 发送者
     * @return bool|string
     */
    protected function sendSocketMessage($channels, $content, string $from = 'system')
    {
        try {
            // 直接读取 .env 配置，连接到 gk_api 的推送服务
            $api = new Api(
                env('PUSH_API_URL', 'http://10.140.0.6:3232'),
                env('PUSH_APP_KEY', '20f94408fc4c52845f162e92a253c7a3'),
                env('PUSH_APP_SECRET', '3151f8648a6ccd9d4515386f34127e28')
            );

            return $api->trigger($channels, 'message', [
                'from_uid' => $from,
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE)
            ]);

        } catch (\Exception $e) {
            Log::error('sendSocketMessage异常: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'channels' => $channels,
            ]);
            return false;
        }
    }
}
