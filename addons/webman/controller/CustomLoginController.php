<?php

namespace addons\webman\controller;

use addons\webman\service\TurnstileService;
use support\Request;
use support\Response;

/**
 * 自定义登录控制器（带 Turnstile 验证）
 *
 * 使用方法：
 * 1. 配置环境变量 TURNSTILE_ENABLED=true
 * 2. 访问 /custom-login 查看登录页面
 * 3. TurnstileMiddleware 会自动验证
 */
class CustomLoginController
{
    /**
     * 登录页面
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $siteKey = TurnstileService::getSiteKey();
        $enabled = TurnstileService::isEnabled();

        // 如果已登录，重定向到后台首页
        if (admin()) {
            return redirect('/ex-admin/');
        }

        return view('custom_login', [
            'turnstile_enabled' => $enabled,
            'turnstile_site_key' => $siteKey,
        ]);
    }

    /**
     * 登录处理
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        // 到达这里说明 Turnstile 验证已通过（被中间件拦截验证）

        $username = $request->post('username', '');
        $password = $request->post('password', '');

        // 验证输入
        if (empty($username) || empty($password)) {
            return json([
                'code' => 400,
                'message' => '请输入账号和密码',
                'data' => null,
            ], 400);
        }

        // TODO: 这里接入你的登录逻辑
        // 示例：调用 ExAdmin 的登录方法或自己实现

        // 示例代码（需要根据实际情况修改）:
        /*
        $admin = \addons\webman\model\AdminUser::where('username', $username)->first();

        if (!$admin) {
            return json([
                'code' => 400,
                'message' => '账号不存在',
                'data' => null,
            ], 400);
        }

        if (!password_verify($password, $admin->password)) {
            return json([
                'code' => 400,
                'message' => '密码错误',
                'data' => null,
            ], 400);
        }

        // 设置 session
        $request->session()->set('admin_id', $admin->id);

        return json([
            'code' => 0,
            'message' => '登录成功',
            'data' => [
                'redirect' => '/ex-admin/',
            ],
        ]);
        */

        // 临时返回（请替换为实际逻辑）
        return json([
            'code' => 400,
            'message' => '请在 CustomLoginController 中实现实际的登录逻辑',
            'data' => null,
        ], 400);
    }

    /**
     * 退出登录
     *
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $request->session()->flush();

        return redirect('/custom-login');
    }
}
