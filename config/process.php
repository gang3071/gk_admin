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

    // 摸奖券打码进度队列消费者（异步处理，不影响游戏性能）
    'lottery_bet_progress_consumer' => [
        'handler' => process\LotteryBetProgressConsumer::class,
        'reloadable' => true,
        'constructor' => []
    ],

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
];
