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

return [
    // 全局中间件
    '' => [
        AccessControl::class,  // CORS跨域支持

        // ⚠️ 临时禁用：内存泄漏审计中间件（需要时取消注释）
        // 使用方法：
        // 1. 取消下行注释
        // 2. 重启服务: php start.php restart
        // 3. 观察日志: tail -f runtime/logs/webman.log | grep "内存泄漏"
        // 4. 定位完毕后重新注释掉
        // MemoryAudit::class,
    ],
];
