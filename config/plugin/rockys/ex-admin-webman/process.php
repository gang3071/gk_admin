<?php
return [
    'ex_admin_consumer'  => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => 2, // ✅ 从 8 降到 2（减少 Redis 空轮询 CPU 占用）
        'constructor' => [
            // 消费者类目录
            'consumer_dir' => base_path() . '/addons/webman/grid/Jobs'
        ]
    ],

    // 摸奖券推送队列消费者（专用进程，避免阻塞其他任务）
    'lottery_push_consumer' => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => 3, // 3个并发进程，平衡推送速度和资源占用
        'constructor' => [
            // 摸奖券队列消费者目录
            'consumer_dir' => base_path() . '/addons/webman/queue'
        ]
    ]
];