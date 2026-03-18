<?php

use addons\webman\middleware\AuthMiddleware;
use addons\webman\middleware\IpAuthMiddleware;
use addons\webman\middleware\LoadLangPack;
use addons\webman\middleware\Permission;

return [
    'token' => [
        'driver' => \addons\webman\token\driver\Cache::class,
        //密钥
        'key' => 'QoYEClMJsgOSWUBkSCq26yWkApqSuH3',
        //token有效时长
        'expire' => null,
        //唯一登录
        'unique' => true,
        //验证字段
        'auth_field' => ['password'],

        'model' => addons\webman\model\AdminUser::class,
    ],
    //超级管理员id
    'admin_auth_id' => 1,

    'request_interface' => [
        //ExAdmin\ui\contract\LoginAbstract
        'login' => addons\webman\common\Login::class,
        //ExAdmin\ui\contract\SystemAbstract
        'system' => addons\webman\common\System::class,
    ],
    'grid' => [
        //ExAdmin\ui\Manager
        'manager' => addons\webman\grid\GridManager::class,
    ],
    'form' => [
        //ExAdmin\ui\Manager
        'manager' => addons\webman\form\FormManager::class,
        //ExAdmin\ui\contract\ValidatorAbstract
        'validator' => addons\webman\form\Validator::class,
        //ExAdmin\ui\contract\UploaderAbstract
        'uploader' => addons\webman\form\Uploader::class,
    ],
    'echart' => [
        //ExAdmin\ui\Manager
        'manager' => \addons\webman\echart\EchartManager::class,
    ],
    'route' => [
        //路由前缀
        'prefix' => env('ADMIN_ROUTE_PREFIX', '/admin'),
        //中间件
        'middleware' => [
            AuthMiddleware::class,
            LoadLangPack::class,
            Permission::class,
            IpAuthMiddleware::class,
        ],
    ],
    //菜单
    'menu' => \addons\webman\service\Menu::class,
    //扫描权限目录
    'auth_scan' => [
        __DIR__ . '/controller',
        app_path("admin/controller")
    ],
    'cache' => [
        //缓存目录
        'directory' => runtime_path()
    ],
    // 币种
    'currency' => [
        'TWD' => 'TWD',
        'CNY' => 'CNY',
        'USD' => 'USD',
        'JPY' => 'JPY',
        'USDT' => 'USDT',
    ],
    'admin_node' => config('admin_node'),
    'channel_node' => config('channel_node'),
    'agent_node' => config('agent_node'),
    'store_node' => config('store_node'),
];
