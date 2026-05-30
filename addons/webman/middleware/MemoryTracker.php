<?php

namespace addons\webman\middleware;

use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 请求级别内存追踪中间件
 *
 * 功能：
 * 1. 记录每个请求的内存消耗
 * 2. 自动记录高内存请求的详细信息
 * 3. 生成热点接口分析报告
 * 4. 帮助快速定位内存泄漏源
 */
class MemoryTracker implements MiddlewareInterface
{
    /**
     * 高内存请求阈值 (MB)
     * 超过此阈值的请求会被详细记录
     */
    const HIGH_MEMORY_THRESHOLD = 5;

    /**
     * 极高内存请求阈值 (MB)
     * 超过此阈值的请求会触发警报
     */
    const CRITICAL_MEMORY_THRESHOLD = 10;

    /**
     * 热点统计缓存文件
     */
    const HOTSPOT_CACHE_FILE = 'runtime/cache/memory_hotspot.json';

    /**
     * 处理请求
     */
    public function process(Request $request, callable $handler): Response
    {
        // 记录请求开始时的内存
        $memoryStart = memory_get_usage(true);
        $timeStart = microtime(true);

        // 执行请求
        $response = $handler($request);

        // 记录请求结束时的内存
        $memoryEnd = memory_get_usage(true);
        $timeEnd = microtime(true);

        // 计算消耗
        $memoryUsed = $memoryEnd - $memoryStart;
        $memoryUsedMB = round($memoryUsed / 1024 / 1024, 2);
        $timeUsed = round(($timeEnd - $timeStart) * 1000, 2); // 毫秒

        // 获取请求信息
        $uri = $request->uri();
        $method = $request->method();
        $controller = $this->getControllerInfo($request);

        // 基础日志（所有请求）
        if ($memoryUsedMB > 1) { // 只记录大于1MB的请求
            Log::info(sprintf(
                "[MemTrack] %s %s | Controller: %s | Memory: %.2f MB | Time: %.2f ms",
                $method,
                $uri,
                $controller,
                $memoryUsedMB,
                $timeUsed
            ));
        }

        // 高内存请求详细记录
        if ($memoryUsedMB >= self::HIGH_MEMORY_THRESHOLD) {
            $this->logHighMemoryRequest($request, $memoryUsedMB, $timeUsed, $controller);
        }

        // 极高内存请求警报
        if ($memoryUsedMB >= self::CRITICAL_MEMORY_THRESHOLD) {
            $this->logCriticalMemoryRequest($request, $memoryUsedMB, $timeUsed, $controller);
        }

        // 更新热点统计
        $this->updateHotspotStats($controller, $memoryUsedMB, $timeUsed);

        return $response;
    }

    /**
     * 获取控制器信息
     */
    private function getControllerInfo(Request $request): string
    {
        $route = $request->route;
        if (!$route) {
            return 'Unknown';
        }

        $callback = $route->getCallback();

        if (is_array($callback)) {
            // [ControllerClass, 'method']
            $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
            $method = $callback[1] ?? 'unknown';

            // 简化类名
            $shortClass = $this->getShortClassName($class);

            return "{$shortClass}::{$method}";
        } elseif (is_string($callback)) {
            return $callback;
        }

        return 'Closure';
    }

    /**
     * 获取简短的类名
     */
    private function getShortClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);
        return end($parts);
    }

    /**
     * 记录高内存请求
     */
    private function logHighMemoryRequest(Request $request, float $memoryMB, float $timeMs, string $controller)
    {
        $uri = $request->uri();
        $method = $request->method();
        $params = $request->all();

        // 脱敏参数（移除敏感信息）
        $sanitizedParams = $this->sanitizeParams($params);

        Log::warning("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::warning("⚠️  高内存请求检测");
        Log::warning("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::warning("时间: " . date('Y-m-d H:i:s'));
        Log::warning("控制器: {$controller}");
        Log::warning("请求: {$method} {$uri}");
        Log::warning("内存消耗: {$memoryMB} MB");
        Log::warning("响应时间: {$timeMs} ms");

        if (!empty($sanitizedParams)) {
            Log::warning("请求参数: " . json_encode($sanitizedParams, JSON_UNESCAPED_UNICODE));
        }

        // 获取调用栈（前5层）
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        if (!empty($backtrace)) {
            Log::warning("调用栈:");
            foreach ($backtrace as $index => $trace) {
                if (isset($trace['file']) && isset($trace['line'])) {
                    $file = $this->getRelativePath($trace['file']);
                    Log::warning("  #{$index} {$file}:{$trace['line']}");
                }
            }
        }

        // 分析可能的原因
        $this->analyzeHighMemoryCause($memoryMB, $controller, $params);

        Log::warning("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    }

    /**
     * 脱敏参数
     */
    private function sanitizeParams(array $params): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'api_key', 'access_token'];
        $sanitized = $params;

        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '***REDACTED***';
            }
        }

        return $sanitized;
    }

    /**
     * 获取相对路径
     */
    private function getRelativePath(string $fullPath): string
    {
        $basePath = base_path();
        if (strpos($fullPath, $basePath) === 0) {
            return substr($fullPath, strlen($basePath) + 1);
        }
        return $fullPath;
    }

    /**
     * 分析高内存原因
     */
    private function analyzeHighMemoryCause(float $memoryMB, string $controller, array $params)
    {
        $possibleCauses = [];

        // 根据内存大小判断
        if ($memoryMB > 20) {
            $possibleCauses[] = "严重泄漏 - 极可能存在全量数据加载（Model::get()->all()）";
        } elseif ($memoryMB > 10) {
            $possibleCauses[] = "中度泄漏 - 可能存在大数据集加载或多次查询累积";
        } else {
            $possibleCauses[] = "轻度问题 - 单次查询返回数据较多或复杂计算";
        }

        // 根据控制器名称判断
        if (strpos($controller, 'Index') !== false) {
            $possibleCauses[] = "首页/列表接口 - 检查是否加载了过多数据";
        }

        if (strpos($controller, 'export') !== false || strpos($controller, 'Export') !== false) {
            $possibleCauses[] = "导出功能 - 检查是否应该使用分批导出（chunk/lazy）";
        }

        // 根据参数判断
        if (isset($params['page']) || isset($params['size'])) {
            $size = $params['size'] ?? $params['limit'] ?? 0;
            if ($size > 100) {
                $possibleCauses[] = "分页大小过大 (size={$size}) - 建议限制在100以内";
            }
        }

        if (!empty($possibleCauses)) {
            Log::warning("可能原因:");
            foreach ($possibleCauses as $cause) {
                Log::warning("  • {$cause}");
            }
        }

        // 给出优化建议
        Log::warning("优化建议:");
        Log::warning("  1. 使用 lazy(500) 或 chunk(500) 替代 get()");
        Log::warning("  2. 使用 whereExists 子查询替代 whereIn 大数组");
        Log::warning("  3. 添加查询条件限制返回数据量");
        Log::warning("  4. 检查是否有N+1查询问题（使用 with() 预加载）");
    }

    /**
     * 记录极高内存请求（Critical）
     */
    private function logCriticalMemoryRequest(Request $request, float $memoryMB, float $timeMs, string $controller)
    {
        $uri = $request->uri();
        $method = $request->method();

        // 只有真正严重的情况（>= 20 MB）才发送 Telegram
        if ($memoryMB >= 20) {
            Log::error("🔴 严重内存泄漏！控制器: {$controller} | 内存: {$memoryMB} MB | 请求: {$method} {$uri}");
        } else {
            // 10-20 MB 只记录 warning，不发送 Telegram
            Log::warning("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::warning("⚠️ 极高内存请求检测");
            Log::warning("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::warning("时间: " . date('Y-m-d H:i:s'));
            Log::warning("控制器: {$controller}");
            Log::warning("请求: {$method} {$uri}");
            Log::warning("内存消耗: {$memoryMB} MB（超过 " . self::CRITICAL_MEMORY_THRESHOLD . " MB 阈值）");
            Log::warning("响应时间: {$timeMs} ms");
            Log::warning("");
            Log::warning("🔍 这是一个严重的内存问题！");
            Log::warning("建议立即检查此接口的代码:");
            Log::warning("  1. 查找控制器: {$controller}");
            Log::warning("  2. 检查是否有全量数据加载 (->get())");
            Log::warning("  3. 检查是否有大数组操作 (whereIn with 1000+ IDs)");
            Log::warning("  4. 检查是否有循环中的数据库查询");
            Log::warning("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
        }

        // 同时写入紧急报告文件
        $this->writeEmergencyReport($controller, $memoryMB, $timeMs, $uri, $request);
    }

    /**
     * 写入紧急报告
     */
    private function writeEmergencyReport(string $controller, float $memoryMB, float $timeMs, string $uri, Request $request)
    {
        $reportFile = runtime_path() . '/logs/memory_critical_' . date('YmdHis') . '.log';

        $report = [];
        $report[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $report[] = "🔴 极高内存请求紧急报告";
        $report[] = "时间: " . date('Y-m-d H:i:s');
        $report[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $report[] = "";
        $report[] = "请求信息:";
        $report[] = "  控制器: {$controller}";
        $report[] = "  URL: {$request->method()} {$uri}";
        $report[] = "  内存消耗: {$memoryMB} MB";
        $report[] = "  响应时间: {$timeMs} ms";
        $report[] = "";
        $report[] = "问题定位:";
        $report[] = "  1. 立即检查控制器代码: {$controller}";
        $report[] = "  2. 搜索代码中的问题模式:";
        $report[] = "     • ->get()->pluck() 或 ->get()->mapWithKeys()";
        $report[] = "     • whereIn(\$ids) 且 \$ids 数组很大";
        $report[] = "     • foreach 循环中的数据库查询";
        $report[] = "     • 大量数据的 toArray() 转换";
        $report[] = "";
        $report[] = "修复建议:";
        $report[] = "  • 使用 lazy(500) 替代 get()";
        $report[] = "  • 使用 whereExists 替代 whereIn 大数组";
        $report[] = "  • 使用 chunk() 分批处理";
        $report[] = "  • 添加查询条件限制数据量";
        $report[] = "";
        $report[] = "参考文档:";
        $report[] = "  • D:\\gk_admin\\MEMORY_OPTIMIZATION_GUIDE.md";
        $report[] = "  • D:\\gk_admin\\MEMORY_LEAK_FINAL_ANALYSIS.md";
        $report[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

        file_put_contents($reportFile, implode("\n", $report));

        Log::error("📄 紧急报告已保存: {$reportFile}");
    }

    /**
     * 更新热点统计
     */
    private function updateHotspotStats(string $controller, float $memoryMB, float $timeMs)
    {
        $cacheFile = base_path(self::HOTSPOT_CACHE_FILE);
        $cacheDir = dirname($cacheFile);

        // 确保缓存目录存在
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // 读取现有统计
        $stats = [];
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $stats = json_decode($content, true) ?: [];
        }

        // 初始化控制器统计
        if (!isset($stats[$controller])) {
            $stats[$controller] = [
                'count' => 0,
                'total_memory' => 0,
                'max_memory' => 0,
                'total_time' => 0,
                'max_time' => 0,
                'first_seen' => time(),
                'last_seen' => time(),
            ];
        }

        // 更新统计
        $stats[$controller]['count']++;
        $stats[$controller]['total_memory'] += $memoryMB;
        $stats[$controller]['max_memory'] = max($stats[$controller]['max_memory'], $memoryMB);
        $stats[$controller]['total_time'] += $timeMs;
        $stats[$controller]['max_time'] = max($stats[$controller]['max_time'], $timeMs);
        $stats[$controller]['last_seen'] = time();

        // 计算平均值
        $stats[$controller]['avg_memory'] = round($stats[$controller]['total_memory'] / $stats[$controller]['count'], 2);
        $stats[$controller]['avg_time'] = round($stats[$controller]['total_time'] / $stats[$controller]['count'], 2);

        // 保存统计（只保留最近的统计，避免文件过大）
        // 按平均内存排序，只保留前50个
        uasort($stats, function ($a, $b) {
            return $b['avg_memory'] <=> $a['avg_memory'];
        });
        $stats = array_slice($stats, 0, 50, true);

        file_put_contents($cacheFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
