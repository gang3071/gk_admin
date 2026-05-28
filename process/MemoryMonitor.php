<?php

namespace process;

use support\Log;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 内存监控进程
 *
 * 功能：
 * 1. 每分钟检测所有Webman进程的内存使用情况
 * 2. 记录内存增长趋势
 * 3. 自动分析并定位内存泄漏源
 * 4. 生成内存分析报告
 */
class MemoryMonitor
{
    /**
     * 进程名称
     */
    const PROCESS_NAME = 'MemoryMonitor';

    /**
     * 内存警告阈值 (MB)
     */
    const WARNING_THRESHOLD = 400;

    /**
     * 内存危险阈值 (MB)
     */
    const DANGER_THRESHOLD = 800;

    /**
     * 异常增长率阈值 (MB/分钟)
     */
    const ABNORMAL_GROWTH_RATE = 10;

    /**
     * 历史数据保留时间（分钟）
     */
    const HISTORY_RETENTION = 120; // 2小时

    /**
     * 历史内存数据
     * 格式: [pid => [timestamp => memory_mb, ...]]
     */
    private static $memoryHistory = [];

    /**
     * 进程启动时间
     * 格式: [pid => timestamp]
     */
    private static $processStartTime = [];

    /**
     * 进程请求计数（估算）
     * 格式: [pid => count]
     */
    private static $requestCount = [];

    /**
     * 进程入口
     */
    public function onWorkerStart(Worker $worker)
    {
        Log::info("=== 内存监控进程启动 ===");
        Log::info("监控间隔: 60秒");
        Log::info("警告阈值: " . self::WARNING_THRESHOLD . " MB");
        Log::info("危险阈值: " . self::DANGER_THRESHOLD . " MB");
        Log::info("异常增长率: " . self::ABNORMAL_GROWTH_RATE . " MB/分钟");

        // 首次执行
        $this->monitorMemory();

        // 每60秒执行一次
        Timer::add(60, function () {
            $this->monitorMemory();
        });

        // 每10分钟生成一次分析报告
        Timer::add(600, function () {
            $this->generateAnalysisReport();
        });

        // 每小时清理历史数据
        Timer::add(3600, function () {
            $this->cleanupHistory();
        });
    }

    /**
     * 监控内存
     */
    private function monitorMemory()
    {
        $timestamp = time();
        $dateTime = date('Y-m-d H:i:s', $timestamp);

        // 获取所有Webman进程
        $processes = $this->getWebmanProcesses();

        if (empty($processes)) {
            Log::warning("[$dateTime] 未检测到Webman进程");
            return;
        }

        $totalMemory = 0;
        $processCount = count($processes);
        $warnings = [];
        $dangers = [];
        $abnormalGrowths = [];

        Log::info("========================================");
        Log::info("[$dateTime] 内存监控报告");
        Log::info("========================================");

        foreach ($processes as $process) {
            $pid = $process['pid'];
            $memoryMB = $process['memory_mb'];
            $totalMemory += $memoryMB;

            // 记录历史数据
            if (!isset(self::$memoryHistory[$pid])) {
                self::$memoryHistory[$pid] = [];
                self::$processStartTime[$pid] = $timestamp;
                self::$requestCount[$pid] = 0;
            }

            self::$memoryHistory[$pid][$timestamp] = $memoryMB;

            // 计算内存增长趋势
            $growth = $this->calculateMemoryGrowth($pid);
            $growthRate = $growth['rate'];
            $trend = $growth['trend'];

            // 估算请求数（假设每个请求处理时间1秒，进程处理60个请求/分钟）
            $runTime = $timestamp - self::$processStartTime[$pid];
            $estimatedRequests = $runTime > 0 ? intval($runTime / 60 * 60) : 0;
            self::$requestCount[$pid] = $estimatedRequests;

            // 计算平均每请求内存消耗
            $avgMemoryPerRequest = $estimatedRequests > 0
                ? ($memoryMB - 50) / $estimatedRequests  // 减去50MB基础内存
                : 0;

            // 状态判断
            $status = '正常';
            $statusIcon = '✅';

            if ($memoryMB >= self::DANGER_THRESHOLD) {
                $status = '危险';
                $statusIcon = '🔴';
                $dangers[] = $pid;
            } elseif ($memoryMB >= self::WARNING_THRESHOLD) {
                $status = '警告';
                $statusIcon = '⚠️';
                $warnings[] = $pid;
            }

            if ($growthRate >= self::ABNORMAL_GROWTH_RATE) {
                $abnormalGrowths[] = [
                    'pid' => $pid,
                    'rate' => $growthRate,
                    'memory' => $memoryMB
                ];
            }

            // 打印进程详情
            Log::info(sprintf(
                "%s PID: %s | 内存: %.2f MB | 增长: %+.2f MB/分 | 趋势: %s | 状态: %s | 预估请求数: %d | 平均: %.4f MB/请求",
                $statusIcon,
                str_pad($pid, 6),
                $memoryMB,
                $growthRate,
                $trend,
                $status,
                $estimatedRequests,
                $avgMemoryPerRequest
            ));
        }

        // 汇总统计
        $avgMemory = $totalMemory / $processCount;

        Log::info("----------------------------------------");
        Log::info(sprintf(
            "📊 汇总 | 进程数: %d | 总内存: %.2f MB | 平均: %.2f MB",
            $processCount,
            $totalMemory,
            $avgMemory
        ));

        // 警告信息
        if (!empty($dangers)) {
            Log::error(sprintf(
                "🔴 危险进程: %d 个 (PID: %s) - 内存超过 %d MB",
                count($dangers),
                implode(', ', $dangers),
                self::DANGER_THRESHOLD
            ));
        }

        if (!empty($warnings)) {
            Log::warning(sprintf(
                "⚠️  警告进程: %d 个 (PID: %s) - 内存超过 %d MB",
                count($warnings),
                implode(', ', $warnings),
                self::WARNING_THRESHOLD
            ));
        }

        if (!empty($abnormalGrowths)) {
            // 检查是否有严重泄漏（增长率超过 20 MB/分钟或内存超过 200 MB）
            $isCritical = false;
            foreach ($abnormalGrowths as $ag) {
                if ($ag['rate'] >= 20 || $ag['memory'] >= 200) {
                    $isCritical = true;
                    break;
                }
            }

            if ($isCritical) {
                // 严重泄漏 - 发送 Telegram 警报
                Log::error("🔴 严重内存泄漏警报！");
                foreach ($abnormalGrowths as $ag) {
                    if ($ag['rate'] >= 20 || $ag['memory'] >= 200) {
                        Log::error(sprintf(
                            "   PID %s: 增长率 %.2f MB/分钟 (当前 %.2f MB)",
                            $ag['pid'],
                            $ag['rate'],
                            $ag['memory']
                        ));
                    }
                }
            } else {
                // 普通异常增长 - 只记录日志，不发送 Telegram
                Log::warning("⚡ 检测到异常内存增长:");
                foreach ($abnormalGrowths as $ag) {
                    Log::warning(sprintf(
                        "   PID %s: 增长率 %.2f MB/分钟 (当前 %.2f MB)",
                        $ag['pid'],
                        $ag['rate'],
                        $ag['memory']
                    ));
                }
            }

            // 自动分析泄漏源
            $this->analyzeLeak($abnormalGrowths);
        }

        Log::info("========================================\n");

        // 如果有危险进程，生成详细报告
        if (!empty($dangers) || !empty($abnormalGrowths)) {
            $this->generateEmergencyReport($dangers, $abnormalGrowths);
        }
    }

    /**
     * 获取所有Webman进程信息
     *
     * @return array
     */
    private function getWebmanProcesses(): array
    {
        $processes = [];

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows系统
            exec('wmic process where "name=\'php.exe\'" get ProcessId,WorkingSetSize', $output);

            foreach ($output as $line) {
                if (preg_match('/(\d+)\s+(\d+)/', $line, $matches)) {
                    $pid = $matches[1];
                    $memoryBytes = $matches[2];
                    $memoryMB = round($memoryBytes / 1024 / 1024, 2);

                    // 过滤掉小进程（非Webman worker）
                    if ($memoryMB > 30) {
                        $processes[] = [
                            'pid' => $pid,
                            'memory_mb' => $memoryMB
                        ];
                    }
                }
            }
        } else {
            // Linux/Unix系统
            exec("ps aux | grep webman | grep -v grep | awk '{print $2,$6}'", $output);

            foreach ($output as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 2) {
                    $pid = $parts[0];
                    $memoryKB = $parts[1];
                    $memoryMB = round($memoryKB / 1024, 2);

                    $processes[] = [
                        'pid' => $pid,
                        'memory_mb' => $memoryMB
                    ];
                }
            }
        }

        return $processes;
    }

    /**
     * 计算内存增长趋势
     *
     * @param int $pid
     * @return array ['rate' => float, 'trend' => string]
     */
    private function calculateMemoryGrowth(int $pid): array
    {
        if (!isset(self::$memoryHistory[$pid]) || count(self::$memoryHistory[$pid]) < 2) {
            return ['rate' => 0.0, 'trend' => '━'];
        }

        $history = self::$memoryHistory[$pid];
        $timestamps = array_keys($history);
        $latest = end($timestamps);
        $previous = prev($timestamps);

        if ($previous === false) {
            return ['rate' => 0.0, 'trend' => '━'];
        }

        $latestMemory = $history[$latest];
        $previousMemory = $history[$previous];
        $timeDiff = ($latest - $previous) / 60; // 转换为分钟

        $rate = $timeDiff > 0 ? ($latestMemory - $previousMemory) / $timeDiff : 0;

        // 趋势符号
        if ($rate > 5) {
            $trend = '↑↑↑'; // 快速上升
        } elseif ($rate > 2) {
            $trend = '↑↑';  // 上升
        } elseif ($rate > 0.5) {
            $trend = '↑';   // 缓慢上升
        } elseif ($rate > -0.5) {
            $trend = '━';   // 稳定
        } elseif ($rate > -2) {
            $trend = '↓';   // 缓慢下降
        } else {
            $trend = '↓↓';  // 下降
        }

        return ['rate' => round($rate, 2), 'trend' => $trend];
    }

    /**
     * 分析内存泄漏源
     *
     * @param array $abnormalGrowths
     */
    private function analyzeLeak(array $abnormalGrowths)
    {
        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::info("🔍 内存泄漏分析");
        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        foreach ($abnormalGrowths as $ag) {
            $pid = $ag['pid'];
            $rate = $ag['rate'];
            $memory = $ag['memory'];

            // 分析可能的原因
            $possibleCauses = [];

            // 根据增长率判断
            if ($rate > 20) {
                $possibleCauses[] = "严重泄漏 - 可能存在全量数据加载（get()）未释放";
            } elseif ($rate > 10) {
                $possibleCauses[] = "中度泄漏 - 可能存在大数组累积（whereIn大量ID）";
            } else {
                $possibleCauses[] = "轻度泄漏 - 可能存在静态变量累积或缓存未清理";
            }

            // 根据当前内存判断
            if ($memory > 1000) {
                $possibleCauses[] = "已处理大量请求 - 建议检查max_request配置";
            } elseif ($memory > 500) {
                $possibleCauses[] = "内存持续累积 - 建议检查是否有未释放的资源";
            }

            // 检查是否是最近修复的问题
            $estimatedRequests = self::$requestCount[$pid] ?? 0;
            $avgPerRequest = $estimatedRequests > 0 ? ($memory - 50) / $estimatedRequests : 0;

            if ($avgPerRequest > 10) {
                $possibleCauses[] = "⚠️ 单次请求内存过高（{$avgPerRequest} MB/请求）- 可能修复未生效";
            } elseif ($avgPerRequest > 5) {
                $possibleCauses[] = "单次请求内存偏高 - 建议优化查询逻辑";
            }

            Log::info("PID {$pid} 分析:");
            Log::info("  当前内存: {$memory} MB");
            Log::info("  增长率: {$rate} MB/分钟");
            Log::info("  预估请求数: {$estimatedRequests}");
            Log::info("  平均每请求: " . round($avgPerRequest, 2) . " MB");
            Log::info("  可能原因:");
            foreach ($possibleCauses as $cause) {
                Log::info("    • {$cause}");
            }

            // 检查最近的代码修复是否生效
            $this->checkFixEffectiveness($avgPerRequest);

            // 🔥 新增：查找最近的高内存请求
            $this->findRecentHighMemoryRequests($pid);
        }

        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    }

    /**
     * 检查修复有效性
     *
     * @param float $avgPerRequest
     */
    private function checkFixEffectiveness(float $avgPerRequest)
    {
        Log::info("  修复验证:");

        if ($avgPerRequest < 3) {
            Log::info("    ✅ 修复已生效 - 单次请求内存正常（< 3 MB）");
        } elseif ($avgPerRequest < 5) {
            Log::warning("    ⚠️ 修复部分生效 - 单次请求内存偏高（3-5 MB）");
            Log::warning("    建议检查是否还有其他未优化的查询");
        } else {
            // 严重泄漏才发送 error 级别（会触发 Telegram）
            if ($avgPerRequest >= 10) {
                Log::error("    🔴 严重泄漏！单次请求内存: " . round($avgPerRequest, 2) . " MB（预期 < 3 MB）");
                Log::error("    请立即检查:");
                Log::error("      1. 代码是否已正确部署");
                Log::error("      2. 服务是否已重启（php start.php restart）");
                Log::error("      3. 是否还有其他未修复的泄漏点");
            } else {
                // 中度泄漏使用 warning
                Log::warning("    ❌ 修复未生效或存在其他泄漏源");
                Log::warning("    单次请求内存: " . round($avgPerRequest, 2) . " MB（预期 < 3 MB）");
                Log::warning("    请检查:");
                Log::warning("      1. 代码是否已正确部署");
                Log::warning("      2. 服务是否已重启（php start.php restart）");
                Log::warning("      3. 是否还有其他未修复的泄漏点");
            }
        }
    }

    /**
     * 查找最近的高内存请求（定位具体接口）
     *
     * @param int $pid
     */
    private function findRecentHighMemoryRequests(int $pid)
    {
        Log::info("  🎯 定位问题接口:");

        // 支持 RotatingFileHandler 的日期轮转日志
        $logFile = runtime_path() . '/logs/webman.log';

        // 如果 webman.log 不存在，尝试查找今天的日志文件 webman-YYYY-MM-DD.log
        if (!file_exists($logFile)) {
            $todayLogFile = runtime_path() . '/logs/webman-' . date('Y-m-d') . '.log';
            if (file_exists($todayLogFile)) {
                $logFile = $todayLogFile;
            } else {
                Log::warning("    ⚠️ 日志文件不存在: webman.log 和 webman-" . date('Y-m-d') . ".log 都不存在");
                return;
            }
        }

        // 读取最近的1000行日志
        $command = PHP_OS_FAMILY === 'Windows'
            ? "powershell -Command \"Get-Content '{$logFile}' -Tail 1000\""
            : "tail -n 1000 " . escapeshellarg($logFile);

        exec($command, $lines);

        $highMemoryRequests = [];

        foreach ($lines as $line) {
            // 匹配高内存请求
            if (preg_match('/⚠️\s+高内存请求检测/', $line)) {
                $request = ['controller' => '', 'memory' => 0, 'time' => ''];
                $highMemoryRequests[] = &$request;
            } elseif (!empty($highMemoryRequests)) {
                $lastRequest = &$highMemoryRequests[count($highMemoryRequests) - 1];

                if (preg_match('/控制器:\s*(.+)$/', $line, $matches)) {
                    $lastRequest['controller'] = trim($matches[1]);
                } elseif (preg_match('/内存消耗:\s*([\d\.]+)\s*MB/', $line, $matches)) {
                    $lastRequest['memory'] = floatval($matches[1]);
                } elseif (preg_match('/时间:\s*(.+)$/', $line, $matches)) {
                    $lastRequest['time'] = trim($matches[1]);
                }
            }
        }

        if (!empty($highMemoryRequests)) {
            // 显示最近5个
            $recent = array_slice($highMemoryRequests, -5);

            Log::info("    发现 " . count($highMemoryRequests) . " 个高内存请求（显示最近5个）:");

            foreach ($recent as $req) {
                if (empty($req['controller'])) continue;

                $icon = $req['memory'] >= 10 ? '🔴' : '⚠️';
                Log::info("      {$icon} {$req['controller']} - {$req['memory']} MB");
            }

            // 统计最常见的高内存接口
            $controllerCounts = [];
            foreach ($highMemoryRequests as $req) {
                if (empty($req['controller'])) continue;
                $controllerCounts[$req['controller']] = ($controllerCounts[$req['controller']] ?? 0) + 1;
            }

            if (!empty($controllerCounts)) {
                arsort($controllerCounts);
                $topController = array_key_first($controllerCounts);
                $count = $controllerCounts[$topController];

                Log::info("");
                Log::info("    🎯 最可能的问题接口:");
                Log::info("      → {$topController}");
                Log::info("      → 出现次数: {$count}");
                Log::info("      → 建议: 立即检查此控制器的代码");
                Log::info("      → 运行: php analyze_memory_hotspot.php 查看详细分析");
            }
        } else {
            Log::info("    ✅ 最近未检测到高内存请求（< 5 MB）");
            Log::info("    → 可能是旧的泄漏问题或已修复");
        }
    }

    /**
     * 上次生成紧急报告的时间戳
     */
    private static $lastEmergencyReportTime = 0;

    /**
     * 紧急报告生成间隔（秒）
     */
    const EMERGENCY_REPORT_INTERVAL = 3600; // 1小时

    /**
     * 生成紧急报告
     *
     * @param array $dangers
     * @param array $abnormalGrowths
     */
    private function generateEmergencyReport(array $dangers, array $abnormalGrowths)
    {
        // 检查是否需要生成报告
        $now = time();

        // 1. 检查是否有真正严重的问题
        $isCritical = false;

        // 检查是否有危险进程
        if (!empty($dangers)) {
            $isCritical = true;
        }

        // 检查是否有严重泄漏（增长率 >= 20 MB/分钟）
        foreach ($abnormalGrowths as $ag) {
            if ($ag['rate'] >= 20) {
                $isCritical = true;
                break;
            }
        }

        // 2. 只在严重问题时生成报告，且限制频率
        if (!$isCritical) {
            return; // 不是严重问题，不生成报告
        }

        // 3. 限制生成频率（每小时最多一次）
        if ($now - self::$lastEmergencyReportTime < self::EMERGENCY_REPORT_INTERVAL) {
            Log::info("📄 紧急报告已在 " . round(($now - self::$lastEmergencyReportTime) / 60) . " 分钟前生成，跳过本次生成");
            return;
        }

        // 4. 使用每日报告文件（覆盖写入）
        $reportFile = runtime_path() . '/logs/memory_emergency_' . date('Ymd') . '.log';

        $report = [];
        $report[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $report[] = "🚨 内存紧急报告";
        $report[] = "时间: " . date('Y-m-d H:i:s');
        $report[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $report[] = "";

        if (!empty($dangers)) {
            $report[] = "🔴 危险进程（内存超过 " . self::DANGER_THRESHOLD . " MB）:";
            foreach ($dangers as $pid) {
                $memory = self::$memoryHistory[$pid][time()] ?? 0;
                $requests = self::$requestCount[$pid] ?? 0;
                $report[] = "  PID {$pid}: {$memory} MB（预估请求数: {$requests}）";
            }
            $report[] = "";
        }

        if (!empty($abnormalGrowths)) {
            $report[] = "⚡ 异常增长进程:";
            foreach ($abnormalGrowths as $ag) {
                $report[] = sprintf(
                    "  PID %s: 增长率 %.2f MB/分钟（当前 %.2f MB）",
                    $ag['pid'],
                    $ag['rate'],
                    $ag['memory']
                );
            }
            $report[] = "";
        }

        $report[] = "建议措施:";
        $report[] = "1. 立即检查日志: runtime/logs/webman.log";
        $report[] = "2. 查看最近的请求: tail -f runtime/logs/webman.log";
        $report[] = "3. 如果修复未生效，执行: php start.php restart";
        $report[] = "4. 监控进程内存变化";
        $report[] = "";
        $report[] = "预期修复效果:";
        $report[] = "- 单次请求内存: < 3 MB";
        $report[] = "- 100次请求后: < 300 MB";
        $report[] = "- 增长率: < 2 MB/分钟";
        $report[] = "";
        $report[] = "如果仍然异常，请联系开发团队进行深度分析。";
        $report[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

        $reportContent = implode("\n", $report);
        file_put_contents($reportFile, $reportContent);

        // 记录生成时间
        self::$lastEmergencyReportTime = $now;

        Log::error("📄 严重内存问题！紧急报告已保存: {$reportFile}");
    }

    /**
     * 生成分析报告
     */
    private function generateAnalysisReport()
    {
        $timestamp = date('Y-m-d H:i:s');

        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::info("📈 10分钟内存趋势分析报告");
        Log::info("生成时间: {$timestamp}");
        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        if (empty(self::$memoryHistory)) {
            Log::info("暂无历史数据");
            return;
        }

        foreach (self::$memoryHistory as $pid => $history) {
            if (count($history) < 2) {
                continue;
            }

            $timestamps = array_keys($history);
            $values = array_values($history);

            $firstTime = reset($timestamps);
            $lastTime = end($timestamps);
            $firstMemory = reset($values);
            $lastMemory = end($values);

            $duration = ($lastTime - $firstTime) / 60; // 分钟
            $totalGrowth = $lastMemory - $firstMemory;
            $avgRate = $duration > 0 ? $totalGrowth / $duration : 0;

            $min = min($values);
            $max = max($values);
            $avg = array_sum($values) / count($values);

            // 估算max_request触发时间
            $estimatedRequests = self::$requestCount[$pid] ?? 0;
            $requestsPerMin = $duration > 0 ? $estimatedRequests / $duration : 0;
            $timeToMaxRequest = $requestsPerMin > 0 ? (100 - $estimatedRequests % 100) / $requestsPerMin : 0;

            Log::info("PID {$pid}:");
            Log::info("  运行时长: " . round($duration, 1) . " 分钟");
            Log::info("  内存范围: {$min} MB ~ {$max} MB");
            Log::info("  平均内存: " . round($avg, 2) . " MB");
            Log::info("  总增长: " . round($totalGrowth, 2) . " MB");
            Log::info("  平均增长率: " . round($avgRate, 2) . " MB/分钟");
            Log::info("  预估请求数: {$estimatedRequests}");
            Log::info("  预计max_request触发: " . round($timeToMaxRequest, 1) . " 分钟后");

            // 绘制简易趋势图
            $this->drawTrendChart($pid, $history);
        }

        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    }

    /**
     * 绘制趋势图（ASCII图表）
     *
     * @param int $pid
     * @param array $history
     */
    private function drawTrendChart(int $pid, array $history)
    {
        $values = array_values($history);
        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        if ($range < 1) {
            Log::info("  趋势: 内存稳定");
            return;
        }

        $height = 10;
        $width = min(count($values), 60);

        // 采样数据点
        $sampledValues = [];
        $step = count($values) / $width;
        for ($i = 0; $i < $width; $i++) {
            $index = intval($i * $step);
            $sampledValues[] = $values[$index];
        }

        Log::info("  趋势图:");

        for ($y = $height; $y >= 0; $y--) {
            $threshold = $min + ($range * $y / $height);
            $line = sprintf("  %4d MB |", intval($threshold));

            foreach ($sampledValues as $value) {
                if ($value >= $threshold) {
                    $line .= '█';
                } else {
                    $line .= ' ';
                }
            }

            Log::info($line);
        }

        Log::info("         +" . str_repeat('-', $width));
        Log::info("          " . sprintf("%-10s", "开始") . str_repeat(' ', max(0, $width - 20)) . sprintf("%10s", "现在"));
    }

    /**
     * 清理历史数据
     */
    private function cleanupHistory()
    {
        $cutoffTime = time() - (self::HISTORY_RETENTION * 60);

        foreach (self::$memoryHistory as $pid => $history) {
            foreach ($history as $timestamp => $memory) {
                if ($timestamp < $cutoffTime) {
                    unset(self::$memoryHistory[$pid][$timestamp]);
                }
            }

            // 如果进程没有最新数据，说明已停止，清理整个进程的历史
            $latestTimestamp = !empty(self::$memoryHistory[$pid])
                ? max(array_keys(self::$memoryHistory[$pid]))
                : 0;

            if ($latestTimestamp < $cutoffTime) {
                unset(self::$memoryHistory[$pid]);
                unset(self::$processStartTime[$pid]);
                unset(self::$requestCount[$pid]);
            }
        }

        Log::info("✅ 历史数据清理完成（保留最近 " . self::HISTORY_RETENTION . " 分钟）");
    }
}
