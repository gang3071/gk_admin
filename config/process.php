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
];
