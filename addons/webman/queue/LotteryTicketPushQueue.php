<?php

namespace addons\webman\queue;

use support\Log;
use Webman\Push\Api;

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
     * @throws \Exception 推送失败时抛出异常触发队列重试
     */
    public function consume($data)
    {
        $channels = $data['channels'] ?? '';
        $content = $data['content'] ?? [];
        $from = $data['from'] ?? 'lottery_system';

        // 数据格式错误，不重试（正常返回，队列删除此消息）
        if (empty($channels) || empty($content)) {
            Log::warning('摸奖券推送队列：参数缺失', [
                'data' => $data,
            ]);
            return;
        }

        // 调用系统的 sendSocketMessage 函数
        $result = $this->sendSocketMessage($channels, $content, $from);

        // ✅ 推送失败时抛出异常，触发队列重试机制
        if (!$result) {
            throw new \Exception('推送失败，触发队列重试机制');
        }

        // 推送成功，记录简洁日志
        Log::info('摸奖券推送成功', [
            'type' => $content['type'] ?? 'unknown',
            'player_id' => $this->extractPlayerId($channels),
        ]);
    }

    /**
     * 从频道名称提取玩家ID（用于日志）
     *
     * @param string $channels 频道名称
     * @return int|null
     */
    protected function extractPlayerId(string $channels): ?int
    {
        if (preg_match('/player-(\d+)/', $channels, $matches)) {
            return (int)$matches[1];
        }
        return null;
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
