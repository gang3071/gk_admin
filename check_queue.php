#!/usr/bin/env php
<?php
/**
 * 检查队列状态脚本
 */

require_once __DIR__ . '/vendor/autoload.php';

use support\Redis;

echo "=== 检查 Redis 队列状态 ===" . PHP_EOL . PHP_EOL;

// 队列名称
$queues = [
    'lottery-ticket-push',
    'lottery-bet-progress',
];

foreach ($queues as $queueName) {
    echo "队列: {$queueName}" . PHP_EOL;

    // 获取队列长度（等待消费的消息数）
    $waitingKey = '{redis-queue}-waiting-' . $queueName;
    $delayedKey = '{redis-queue}-delayed-' . $queueName;

    $waitingCount = Redis::lLen($waitingKey);
    $delayedCount = Redis::zCard($delayedKey);

    echo "  等待消费: {$waitingCount} 条" . PHP_EOL;
    echo "  延迟消费: {$delayedCount} 条" . PHP_EOL;

    // 查看最新的几条消息
    if ($waitingCount > 0) {
        echo "  最新消息:" . PHP_EOL;
        $messages = Redis::lRange($waitingKey, 0, 2);
        foreach ($messages as $i => $msg) {
            $data = json_decode($msg, true);
            echo "    [" . ($i + 1) . "] " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        }
    }

    echo PHP_EOL;
}

echo "=== 检查完成 ===" . PHP_EOL;
