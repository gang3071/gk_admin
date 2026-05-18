<?php

namespace addons\webman\middleware;

use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 内存审计中间件
 *
 * 用于追踪每个请求的内存使用情况，定位内存泄漏源
 *
 * 使用方法：
 * 1. 在 config/middleware.php 中启用此中间件
 * 2. 访问后台接口，观察日志
 * 3. 找到 memory_leaked > 0 的接口，这些就是泄漏源
 * 4. 定位完毕后，务必禁用此中间件（影响性能）
 */
class MemoryAudit implements MiddlewareInterface
{
    /**
     * 请求前记录初始内存
     */
    public function process(Request $request, callable $handler): Response
    {
        // 记录请求开始时的内存使用量
        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        // 执行请求
        $response = $handler($request);

        // 记录请求结束后的内存使用量
        $memoryAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        // 计算内存差值（泄漏量）
        $memoryLeaked = $memoryAfter - $memoryBefore;
        $peakIncreased = $peakAfter - $peakBefore;

        // 获取请求路径
        $path = $request->path();
        $method = $request->method();

        // 只记录内存增长 > 1MB 的请求（小于1MB可能是正常波动）
        if ($memoryLeaked > 1024 * 1024 || $peakIncreased > 1024 * 1024) {
            Log::warning('⚠️ 内存泄漏检测', [
                'path' => $path,
                'method' => $method,
                'memory_before' => $this->formatBytes($memoryBefore),
                'memory_after' => $this->formatBytes($memoryAfter),
                'memory_leaked' => $this->formatBytes($memoryLeaked),
                'peak_before' => $this->formatBytes($peakBefore),
                'peak_after' => $this->formatBytes($peakAfter),
                'peak_increased' => $this->formatBytes($peakIncreased),
                'process_id' => posix_getpid(),
            ]);
        }

        // 如果单次请求泄漏 > 5MB，立即告警（这是严重泄漏）
        if ($memoryLeaked > 5 * 1024 * 1024) {
            Log::error('🚨 严重内存泄漏！', [
                'path' => $path,
                'method' => $method,
                'memory_leaked' => $this->formatBytes($memoryLeaked),
                'process_id' => posix_getpid(),
                'user_agent' => $request->header('user-agent'),
                'post_data_size' => strlen(json_encode($request->post())),
            ]);
        }

        return $response;
    }

    /**
     * 格式化字节数为可读格式
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
