<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    'listen' => env('APP_LISTEN','http://0.0.0.0:8789'),
    'transport' => 'tcp',
    'context' => [],
    'name' => 'webman',
    'count' => cpu_count() * 4,
    'user' => '',
    'group' => '',
    'reusePort' => false,
    'event_loop' => '',
    'stop_timeout' => 2,
    'pid_file' => runtime_path() . '/webman.pid',
    'status_file' => runtime_path() . '/webman.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'log_file' => runtime_path() . '/logs/workerman.log',
    'max_package_size' => 10 * 1024 * 1024,

    // ✅ 内存泄漏紧急修复：Worker 进程自动重启
    // 问题：平均每次请求泄漏 3.2 MB（627次请求 = 2.02GB，477次请求 = 1.50GB）
    // 解决：处理 100 个请求后自动重启，释放累积内存（约 320 MB）
    // 监控结果：进程1214处理627次达到2GB，进程1210处理53次仅205MB
    // 降低到100以防止单进程内存超过500MB
    'max_request' => 100,
];
