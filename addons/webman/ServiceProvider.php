<?php

namespace addons\webman;

use addons\webman\database\seeders\AdminSeeder;
use ExAdmin\ui\plugin\Plugin;
use support\Db;
use Webman\Route;

// 加载辅助函数
require_once __DIR__ . '/helpers.php';

class ServiceProvider extends Plugin
{
    /**
     * 注册服务
     *
     */
    public function register()
    {
        //上传初始化
        Admin::uploadInit();

        admin_config($this->config(),'admin');
        admin_config($this->config('ui'),'ui');
    }

    public function route(){
        // 渠道后台路由
        Route::group('/channel', function () {
            Route::get('', function () {
                $content = file_get_contents(public_path('exadmin') . '/index.html');
                return str_replace(
                    [
                        '{{Ex-Admin}}',
                        '{{Ex-Admin-App-Name}}',
                    ],
                    [
                        admin_sysconf('web_name'),
                        'channel',
                    ],
                    $content);
            });
        });

        // 代理后台路由
        Route::group('/agent', function () {
            Route::get('', function () {
                $content = file_get_contents(public_path('exadmin') . '/index.html');
                return str_replace(
                    [
                        '{{Ex-Admin}}',
                        '{{Ex-Admin-App-Name}}',
                    ],
                    [
                        admin_sysconf('web_name'),
                        'agent',
                    ],
                    $content);
            });
        });

        // 店家后台路由
        Route::group('/store', function () {
            Route::get('', function () {
                $content = file_get_contents(public_path('exadmin') . '/index.html');
                return str_replace(
                    [
                        '{{Ex-Admin}}',
                        '{{Ex-Admin-App-Name}}',
                    ],
                    [
                        admin_sysconf('web_name'),
                        'store',
                    ],
                    $content);
            });
        });

        // 主站后台路由（默认）
        Route::group(plugin()->webman->config('route.prefix'), function () {
            Route::get('', function () {
                $content = file_get_contents(public_path('exadmin') . '/index.html');
                return str_replace(
                    [
                        '{{Ex-Admin}}',
                        '{{Ex-Admin-App-Name}}',
                    ],
                    [
                        admin_sysconf('web_name'),
                        plugin()->webman->config('route.prefix'),
                    ],
                    $content);
            });
        });

        Route::any('/ex-admin/{class}/{function}', function ($class, $function) {
            return \ExAdmin\ui\Route::dispatch($class, $function);
        })->middleware(plugin()->webman->config('route.middleware'));
    }

    /**
     * 安装
     * @return mixed
     */
    public function install()
    {
        $sql = file_get_contents($this->getPath().'/database/webman.sql');
        Db::unprepared($sql);
    }
    /**
     * 更新
     * @param string $old_version 旧版本
     * @param string $version 更新版本
     * @return mixed
     */
    public function update(string $old_version,string $version)
    {

    }
    /**
     * 卸载
     * @return mixed
     */
    public function uninstall()
    {

        // TODO: Implement uninstall() method.
    }
}
