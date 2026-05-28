<?php

namespace addons\webman\middleware;

use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * HTTP 访问日志中间件
 *
 * 记录所有HTTP请求，类似Nginx的access.log
 *
 * 日志格式：
 * [AccessLog] 192.168.1.100 "GET /api/user/info HTTP/1.1" 200 1234 0.156s "User-Agent"
 */
class AccessLogger implements MiddlewareInterface
{
    /**
     * 是否记录 POST 请求体（敏感，建议关闭）
     */
    const LOG_REQUEST_BODY = false;

    /**
     * 敏感字段过滤（密码、token等）
     */
    const SENSITIVE_FIELDS = ['password', 'token', 'secret', 'api_key', 'access_token'];

    public function process(Request $request, callable $handler): Response
    {
        // 记录请求开始时间
        $startTime = microtime(true);

        // 执行请求
        $response = $handler($request);

        // 计算耗时
        $duration = round((microtime(true) - $startTime) * 1000, 2); // 毫秒

        // 获取请求信息
        $ip = $this->getClientIp($request);
        $method = $request->method();
        $uri = $request->uri();
        $protocol = $request->protocolVersion();
        $statusCode = $response->getStatusCode();
        $contentLength = strlen($response->rawBody());
        $userAgent = $request->header('user-agent', '-');

        // 构建日志消息（类似 Nginx access.log 格式）
        $logMessage = sprintf(
            '[AccessLog] %s "%s %s HTTP/%s" %d %d %.2fms "%s"',
            $ip,
            $method,
            $uri,
            $protocol,
            $statusCode,
            $contentLength,
            $duration,
            $userAgent
        );

        // 根据状态码选择日志级别
        if ($statusCode >= 500) {
            Log::error($logMessage);
        } elseif ($statusCode >= 400) {
            Log::warning($logMessage);
        } else {
            Log::info($logMessage);
        }

        // 可选：记录 POST/PUT 请求体（调试用）
        if (self::LOG_REQUEST_BODY && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = $request->all();
            if (!empty($body)) {
                $sanitizedBody = $this->sanitizeData($body);
                Log::debug('[AccessLog] Request Body: ' . json_encode($sanitizedBody, JSON_UNESCAPED_UNICODE));
            }
        }

        return $response;
    }

    /**
     * 获取客户端真实IP
     */
    private function getClientIp(Request $request): string
    {
        // 优先从代理头获取
        $ip = $request->header('x-real-ip')
            ?? $request->header('x-forwarded-for')
            ?? $request->header('client-ip')
            ?? $request->getRemoteIp();

        // X-Forwarded-For 可能包含多个IP，取第一个
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }

        return trim($ip);
    }

    /**
     * 脱敏敏感数据
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = $data;

        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '***REDACTED***';
            }
        }

        return $sanitized;
    }
}
