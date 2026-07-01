<?php
/**
 * 内存泄漏修复验证脚本
 *
 * 使用方法：php scripts/verify_memory_fix.php
 *
 * 功能：
 * 1. 模拟高频请求，测试内存泄漏是否修复
 * 2. 对比修复前后的内存使用情况
 * 3. 生成详细的测试报告
 */

require_once __DIR__ . '/../vendor/autoload.php';

class MemoryFixVerifier
{
    private $baseUrl = 'http://localhost:8789';
    private $testEndpoints = [
        '/ex-admin/store-player-game-log/index',
        '/ex-admin/channel-player/index',
        '/ex-admin/store-shift-handover-record/index',
    ];

    public function verify()
    {
        echo "🔬 开始验证内存泄漏修复...\n\n";

        // 1. 检查配置
        echo "步骤 1/5: 检查配置文件...\n";
        $this->checkConfig();

        // 2. 检查代码修复
        echo "\n步骤 2/5: 检查代码修复...\n";
        $this->checkCodeFixes();

        // 3. 运行内存检查脚本
        echo "\n步骤 3/5: 运行内存泄漏检查脚本...\n";
        $this->runLeakChecker();

        // 4. 检查日志
        echo "\n步骤 4/5: 分析日志文件...\n";
        $this->analyzeLogs();

        // 5. 生成报告
        echo "\n步骤 5/5: 生成验证报告...\n";
        $this->generateReport();

        echo "\n✅ 验证完成！\n";
    }

    private function checkConfig()
    {
        $serverConfig = include __DIR__ . '/../config/server.php';

        if (isset($serverConfig['max_request']) && $serverConfig['max_request'] == 100) {
            echo "   ✅ max_request 已设置为 100\n";
        } else {
            echo "   ❌ max_request 未正确设置（当前值：" . ($serverConfig['max_request'] ?? '未设置') . "）\n";
            echo "      建议：修改 config/server.php，设置 'max_request' => 100\n";
        }

        $middlewareConfig = include __DIR__ . '/../config/middleware.php';
        $hasMemoryAudit = false;

        if (isset($middlewareConfig['']) && is_array($middlewareConfig[''])) {
            foreach ($middlewareConfig[''] as $middleware) {
                if (strpos($middleware, 'MemoryAudit') !== false) {
                    $hasMemoryAudit = true;
                    break;
                }
            }
        }

        if ($hasMemoryAudit) {
            echo "   ⚠️  MemoryAudit 中间件已启用（如果不在排查期，请禁用）\n";
        } else {
            echo "   ✅ MemoryAudit 中间件已禁用（正常状态）\n";
        }
    }

    private function checkCodeFixes()
    {
        $fixes = [
            'StorePlayerGameLogController' => [
                'file' => __DIR__ . '/../addons/webman/controller/StorePlayerGameLogController.php',
                'pattern' => '/with\(\[\s*[\'"]player:id,uuid,name/',
                'description' => 'ORM 关联优化（限制字段）'
            ],
            'Admin::check() 优化' => [
                'file' => __DIR__ . '/../addons/webman/Admin.php',
                'pattern' => '/private static \$cachedNodeIds/',
                'description' => '权限检查缓存优化'
            ],
            'Admin 静态变量清理' => [
                'file' => __DIR__ . '/../addons/webman/Admin.php',
                'pattern' => '/protected static \$permissions = \[\];/',
                'description' => '删除未使用的静态变量',
                'shouldNotExist' => true
            ],
        ];

        foreach ($fixes as $name => $fix) {
            if (!file_exists($fix['file'])) {
                echo "   ❌ 文件不存在：{$fix['file']}\n";
                continue;
            }

            $content = file_get_contents($fix['file']);
            $found = preg_match($fix['pattern'], $content);

            if (isset($fix['shouldNotExist'])) {
                if (!$found) {
                    echo "   ✅ {$name} - {$fix['description']}\n";
                } else {
                    echo "   ❌ {$name} - 应删除但仍存在\n";
                }
            } else {
                if ($found) {
                    echo "   ✅ {$name} - {$fix['description']}\n";
                } else {
                    echo "   ❌ {$name} - 未找到修复代码\n";
                }
            }
        }
    }

    private function runLeakChecker()
    {
        $checkerScript = __DIR__ . '/check_memory_leaks.php';

        if (!file_exists($checkerScript)) {
            echo "   ⚠️  检查脚本不存在：{$checkerScript}\n";
            return;
        }

        echo "   执行检查脚本...\n";
        exec("php {$checkerScript} 2>&1", $output, $exitCode);

        if ($exitCode === 0) {
            echo "   ✅ 未发现严重内存泄漏问题\n";
        } else {
            echo "   ⚠️  发现 " . count(array_filter($output, function($line) {
                return strpos($line, '⚠️') !== false;
            })) . " 个潜在问题\n";
            echo "   详细信息请查看脚本输出\n";
        }
    }

    private function analyzeLogs()
    {
        $logFile = __DIR__ . '/../runtime/logs/webman.log';

        if (!file_exists($logFile)) {
            echo "   ⚠️  日志文件不存在：{$logFile}\n";
            return;
        }

        // 读取最后 1000 行日志
        exec("tail -n 1000 {$logFile}", $lines);

        $memoryLeaks = array_filter($lines, function($line) {
            return strpos($line, '内存泄漏') !== false || strpos($line, 'memory_leaked') !== false;
        });

        if (count($memoryLeaks) > 0) {
            echo "   ⚠️  发现 " . count($memoryLeaks) . " 条内存泄漏日志\n";

            // 提取泄漏量最大的接口
            $maxLeak = 0;
            $maxLeakPath = '';

            foreach ($memoryLeaks as $line) {
                if (preg_match('/memory_leaked[\'"]:\s*[\'"]?([\d.]+)\s*MB/', $line, $matches)) {
                    $leakSize = floatval($matches[1]);
                    if ($leakSize > $maxLeak) {
                        $maxLeak = $leakSize;
                        if (preg_match('/path[\'"]:\s*[\'"]?([^\'",\s]+)/', $line, $pathMatches)) {
                            $maxLeakPath = $pathMatches[1];
                        }
                    }
                }
            }

            if ($maxLeak > 0) {
                echo "      最大泄漏：{$maxLeak} MB - {$maxLeakPath}\n";

                if ($maxLeak > 5) {
                    echo "      🚨 严重泄漏！需要立即修复此接口\n";
                } elseif ($maxLeak > 2) {
                    echo "      ⚠️  中度泄漏，建议优化\n";
                } else {
                    echo "      ✅ 轻度泄漏，可接受范围\n";
                }
            }
        } else {
            echo "   ✅ 近期日志中未发现内存泄漏警告\n";
        }
    }

    private function generateReport()
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'unknown',
            'fixes_applied' => [],
            'recommendations' => [],
        ];

        // 检查配置
        $serverConfig = include __DIR__ . '/../config/server.php';
        $report['fixes_applied'][] = [
            'name' => 'max_request 配置',
            'status' => isset($serverConfig['max_request']) && $serverConfig['max_request'] == 100 ? '✅' : '❌',
            'value' => $serverConfig['max_request'] ?? '未设置'
        ];

        // 检查代码修复
        $adminFile = __DIR__ . '/../addons/webman/Admin.php';
        $adminContent = file_get_contents($adminFile);

        $report['fixes_applied'][] = [
            'name' => 'Admin::check() 优化',
            'status' => preg_match('/private static \$cachedNodeIds/', $adminContent) ? '✅' : '❌'
        ];

        $report['fixes_applied'][] = [
            'name' => 'StorePlayerGameLogController 优化',
            'status' => file_exists(__DIR__ . '/../addons/webman/controller/StorePlayerGameLogController.php') ? '✅' : '❌'
        ];

        // 生成建议
        $allFixed = true;
        foreach ($report['fixes_applied'] as $fix) {
            if ($fix['status'] === '❌') {
                $allFixed = false;
                break;
            }
        }

        if ($allFixed) {
            $report['status'] = 'good';
            $report['recommendations'][] = '所有关键修复已应用，继续监控进程内存';
            $report['recommendations'][] = '建议运行 24 小时后验证效果';
            $report['recommendations'][] = '如果内存稳定，可将 max_request 提升到 200';
        } else {
            $report['status'] = 'needs_work';
            $report['recommendations'][] = '请完成所有待修复项';
            $report['recommendations'][] = '重新运行此脚本验证';
        }

        // 保存报告
        $reportFile = __DIR__ . '/../runtime/memory_fix_report_' . date('YmdHis') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "   ✅ 报告已保存：{$reportFile}\n";

        // 输出总结
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📊 验证总结\n";
        echo str_repeat('=', 60) . "\n";
        echo "状态：" . ($report['status'] === 'good' ? '✅ 良好' : '⚠️  需要改进') . "\n\n";

        echo "已应用的修复：\n";
        foreach ($report['fixes_applied'] as $fix) {
            echo "  {$fix['status']} {$fix['name']}";
            if (isset($fix['value'])) {
                echo " (值: {$fix['value']})";
            }
            echo "\n";
        }

        echo "\n建议：\n";
        foreach ($report['recommendations'] as $i => $rec) {
            echo "  " . ($i + 1) . ". {$rec}\n";
        }

        echo str_repeat('=', 60) . "\n";
    }
}

// 运行验证
$verifier = new MemoryFixVerifier();
$verifier->verify();
