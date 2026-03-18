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
        list($class,$function) = Admin::getDispatch();
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
