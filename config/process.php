<?php

use Workerman\Worker;

return [
    'monitor' => ['handler' => process\Monitor::class, 'reloadable' => false, 'constructor' => ['monitorDir' => [app_path(), config_path(), base_path().'/.env'], 'monitorExtensions' => ['php','env'], 'options' => ['enable_file_monitor' => !Worker::$daemonize && DIRECTORY_SEPARATOR === '/']]],

    // 自动交班定时任务
    'auto_shift' => [
        'handler' => process\AutoShiftTask::class,
        'reloadable' => true,
        'constructor' => []
    ],

    // 客户端维护时间监听任务
    'client_maintain' => [
        'handler' => process\ClientMaintainTask::class,
        'reloadable' => true,
        'constructor' => []
    ],

    // 游戏平台维护时间监听任务
    'game_platform_maintain' => [
        'handler' => process\GamePlatformMaintainTask::class,
        'reloadable' => true,
        'constructor' => []
    ],
//
//    // 内存监控进程（每分钟监控一次，自动分析内存泄漏）
//    'memory_monitor' => [
//        'handler' => process\MemoryMonitor::class,
//        'reloadable' => true,
//        'constructor' => []
//    ],

    // VIP反水补算定时任务
    'vip_cashback' => [
        'handler' => process\VipCashbackTask::class,
        'reloadable' => true,
        'constructor' => []
    ],

    // VIP生日礼金定时任务
    'vip_birthday_bonus' => [
        'handler' => process\VipBirthdayBonusTask::class,
        'reloadable' => true,
        'constructor' => []
    ],

    // ✅ 摸奖券队列消费者已迁移到标准 redis-queue 插件
    // 配置位置：config/plugin/webman/redis-queue/process.php
    // 消费者目录：app/queue/redis/
    // 队列列表：lottery-bet-progress, lottery-ticket-push

    // ⭐ 摸奖券过期处理已废弃
    // 改为在活动结束/关闭时直接将券标记为过期，不需要定时扫描

    // 摸奖券打码进度扫描任务（定时扫描增量游戏记录，批量更新进度）
    // 用于处理 gk_work 批量插入的游戏记录
    'lottery_bet_progress_scan' => [
        'handler' => process\LotteryBetProgressScanTask::class,
        'reloadable' => true,
        'constructor' => []
    ],

    // 摸奖券活动状态自动流转任务（检查时间节点，自动更新活动状态）
    'lottery_activity_status_transition' => [
        'handler' => process\LotteryActivityStatusTransitionTask::class,
        'reloadable' => true,
        'constructor' => []
    ],
    // ✅ 内存监控进程已禁用（问题已解决，不再需要监控）
    // 'memory_monitor' => [
    //     'handler' => process\MemoryMonitor::class,
    //     'reloadable' => true,
    //     'constructor' => []
    // ],
];
