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
use addons\webman\middleware\Lang;

return [
    // 全局中间件
    '' => [
        AccessControl::class,  // CORS跨域支持
        Lang::class,           // 多语言支持
    ],
];
