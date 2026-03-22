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
];
