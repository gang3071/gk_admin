<?php
/**
 * Redis 队列消费者进程配置
 *
 * 消费者目录：app/queue/redis/
 *
 * 队列列表：
 * - lottery-bet-progress (打码进度更新)
 * - lottery-ticket-push (推送通知)
 */
return [
    'consumer'  => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => 3, // 3个消费者进程（处理2个队列）
        'constructor' => [
            // 消费者类目录
            'consumer_dir' => app_path() . '/queue/redis'
        ]
    ]
];