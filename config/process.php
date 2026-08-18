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

    // ⚠️ TYPE_BET 数据清理任务（需要时取消注释）
    // 功能：每 5 秒删除 5000 条 TYPE_BET (26) 数据
    // 预计耗时：约 5.5 小时清理 2000 万条数据
    // 使用方式：
    //   1. 取消下面的注释
    //   2. 执行 php start.php restart
    //   3. 观察日志输出和 runtime/logs/webman.log
    //   4. 清理完成后，重新注释掉并重启服务
//     'cleanup_type_bet' => [
//         'handler' => process\CleanupTypeBetTask::class,
//         'reloadable' => true,
//         'constructor' => []
//     ],
];
