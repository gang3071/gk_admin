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

use addons\webman\middleware\AccessControl;
use addons\webman\middleware\AccessLogger;
use addons\webman\middleware\MemoryTracker;

return [
    // 全局中间件
    '' => [
        AccessControl::class,  // CORS跨域支持

        // HTTP访问日志中间件（记录所有请求，类似Nginx access.log）
        // 如果觉得日志太多，可以注释掉此行
        AccessLogger::class,

        // ⚠️ 已禁用：内存追踪中间件（请求级别监控）
        // 自动记录高内存请求（≥ 5 MB），生成热点统计
        // 如需启用，取消下行注释
        // MemoryTracker::class,

        // ⚠️ 临时禁用：内存泄漏审计中间件（需要时取消注释）
        // 使用方法：
        // 1. 取消下行注释
        // 2. 重启服务: php start.php restart
        // 3. 观察日志: tail -f runtime/logs/webman.log | grep "内存泄漏"
        // 4. 定位完毕后重新注释掉
        // MemoryAudit::class,
    ],
];
