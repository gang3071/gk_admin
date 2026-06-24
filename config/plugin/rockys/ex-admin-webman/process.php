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

    // ✅ 摸奖券队列消费者已迁移到 webman/redis-queue 插件
    // 配置位置：config/plugin/webman/redis-queue/process.php
    // 消费者目录：app/queue/redis/
];