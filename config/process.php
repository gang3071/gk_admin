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

    // 游戏记录分区维护任务（每月1日自动归档+删除旧分区）
    'play_game_record_partition_maintenance' => [
        'handler' => process\PartitionMaintenanceTask::class,
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
