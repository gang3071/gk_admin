<?php

namespace addons\webman\middleware;

use addons\webman\service\TurnstileService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Turnstile 验证中间件
 *
 * 用于登录接口的人机验证
 *
 * 使用方法：
 * 1. 在 config/middleware.php 中注册
 * 2. 配置需要验证的路由
 * 3. 前端提交 cf_token 或 cf-turnstile-response 字段
 */
class TurnstileMiddleware implements MiddlewareInterface
{
    /**
     * 需要验证的路由列表（前缀匹配）
     *
     * @var array
     */
    private array $verifyRoutes = [
        '/ex-admin/login/handle',     // ExAdmin 登录处理
        '/api/admin/login',            // API 登录
        '/custom-login/handle',        // 自定义登录
    ];

    /**
     * 是否启用 Turnstile
     *
     * @var bool
     */
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = TurnstileService::isEnabled();
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    public function process(Request $request, callable $handler): Response
    {
        // 如果未启用，直接放行
        if (!$this->enabled) {
            return $handler($request);
        }

        // 检查是否是需要验证的路由
        if (!$this->needVerify($request)) {
            return $handler($request);
        }

        // 检查是否是 POST 请求
        if ($request->method() !== 'POST') {
            return $handler($request);
        }

        // 获取 Turnstile Token
        $cfToken = $request->post('cf_token')
            ?? $request->post('cf-turnstile-response')
            ?? $request->input('cf_token');

        if (empty($cfToken)) {
            return $this->errorResponse('请完成人机验证');
        }

        // 验证 Token
        $clientIp = TurnstileService::getClientIp();
        $isValid = TurnstileService::verify($cfToken, $clientIp);

        if (!$isValid) {
            return $this->errorResponse('人机验证失败，请重试');
        }

        // 验证通过，继续处理
        return $handler($request);
    }

    /**
     * 检查是否需要验证
     *
     * @param Request $request
     * @return bool
     */
    private function needVerify(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->verifyRoutes as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 返回错误响应
     *
     * @param string $message
     * @return Response
     */
    private function errorResponse(string $message): Response
    {
        return json([
            'code' => 400,
            'message' => $message,
            'data' => null,
        ], 400);
    }
}
