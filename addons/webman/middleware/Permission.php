<?php

namespace addons\webman\middleware;

use addons\webman\Admin;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class Permission implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 直接從路由參數取得控制器與方法，避免 getDispatch() 因 path 比對失敗回傳 null 而放行
        $class = $request->route ? $request->route->param('class') : null;
        $function = $request->route ? $request->route->param('function') : null;
        if ($class === null || $function === null) {
            list($class,$function) = Admin::getDispatch();
        }

        // 子帳號禁止存取「子帳號管理」，即使功能權限節點/快取異常也強制擋下
        // 真正的子帳號：代理類型(type=3)、有上層代理、且上層不是自己
        if ($class === 'addons-webman-controller-AgentSubAdminController') {
            $user = Admin::user();
            $uid = $user ? (int) $user->id : 0;
            $parentId = $user ? (int) ($user->parent_admin_id ?? 0) : 0;
            if ($user && (int) $user->type === 3 && $parentId > 0 && $parentId !== $uid) {
                return response(
                    json_encode(['message' => admin_trans('admin.not_access_permission')]),
                    405,
                    ['Content-Type' => 'application/json']);
            }
        }

        $method = $request->input('_ajax',$request->method());
        if(!Admin::check($class,$function,$method)){
            return response(
                json_encode(['message' =>  admin_trans('admin.not_access_permission')]),
                405,
                ['Content-Type' => 'application/json']);
        }
        return $handler($request);
    }
}
