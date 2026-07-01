#!/usr/bin/env php
<?php
/**
 * 内存热点分析工具
 *
 * 功能：
 * 1. 分析哪些接口消耗内存最多
 * 2. 查找最近的高内存请求
 * 3. 生成优化建议报告
 *
 * 使用方法：
 * php analyze_memory_hotspot.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use support\Log;

// 颜色定义
class Color {
    const RED = "\033[0;31m";
    const YELLOW = "\033[1;33m";
    const GREEN = "\033[0;32m";
    const BLUE = "\033[0;34m";
    const CYAN = "\033[0;36m";
    const MAGENTA = "\033[0;35m";
    const NC = "\033[0m"; // No Color
}

echo Color::BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;
echo Color::BLUE . "    内存热点分析工具\n" . Color::NC;
echo Color::BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;
echo "\n";

// 1. 分析热点统计文件
$hotspotFile = __DIR__ . '/runtime/cache/memory_hotspot.json';

if (file_exists($hotspotFile)) {
    echo Color::CYAN . "📊 接口内存消耗排行榜（Top 20）\n" . Color::NC;
    echo Color::CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;

    $stats = json_decode(file_get_contents($hotspotFile), true);

    if (!empty($stats)) {
        // 按平均内存排序
        uasort($stats, function($a, $b) {
            return $b['avg_memory'] <=> $a['avg_memory'];
        });

        $rank = 1;
        foreach (array_slice($stats, 0, 20, true) as $controller => $data) {
            $color = Color::GREEN;
            $icon = '✅';

            if ($data['avg_memory'] >= 10) {
                $color = Color::RED;
                $icon = '🔴';
            } elseif ($data['avg_memory'] >= 5) {
                $color = Color::YELLOW;
                $icon = '⚠️ ';
            }

            printf(
                "%s#%-2d %s\n",
                $color,
                $rank,
                $controller
            );

            printf(
                "    平均内存: %.2f MB | 最大: %.2f MB | 调用次数: %d | 平均时间: %.2f ms%s\n",
                $data['avg_memory'],
                $data['max_memory'],
                $data['count'],
                $data['avg_time'],
                Color::NC
            );

            // 给出优化建议
            if ($data['avg_memory'] >= 5) {
                echo Color::YELLOW . "    💡 建议: 此接口内存消耗过高，需要优化\n" . Color::NC;

                // 具体建议
                if (strpos($controller, 'Index') !== false) {
                    echo "       → 检查是否一次性加载了过多列表数据\n";
                    echo "       → 建议使用分页或 lazy() 加载\n";
                }
                if (strpos($controller, 'export') !== false || strpos($controller, 'Export') !== false) {
                    echo "       → 导出功能应使用 chunk() 或 lazy() 分批处理\n";
                }
                if ($data['avg_memory'] >= 10) {
                    echo Color::RED . "       ⚠️ 严重问题！可能存在全量数据加载\n" . Color::NC;
                }
            }

            echo "\n";
            $rank++;
        }
    } else {
        echo Color::YELLOW . "⚠️  暂无统计数据\n" . Color::NC;
        echo "   请等待系统运行一段时间后再查看\n\n";
    }
} else {
    echo Color::YELLOW . "⚠️  热点统计文件不存在\n" . Color::NC;
    echo "   文件位置: {$hotspotFile}\n";
    echo "   请确保MemoryTracker中间件已启用\n\n";
}

// 2. 分析最近的高内存请求日志
echo Color::CYAN . "🔍 最近的高内存请求（最近10条）\n" . Color::NC;
echo Color::CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;

$logFile = __DIR__ . '/runtime/logs/webman.log';

if (file_exists($logFile)) {
    // 读取日志文件的最后1000行
    $lines = [];
    exec("tail -n 1000 " . escapeshellarg($logFile), $lines);

    $highMemoryRequests = [];

    foreach ($lines as $line) {
        // 匹配高内存请求日志
        if (preg_match('/⚠️\s+高内存请求检测/', $line)) {
            $request = ['timestamp' => '', 'controller' => '', 'memory' => 0, 'uri' => ''];

            // 提取时间戳
            if (preg_match('/\[([\d\-: ]+)\]/', $line, $matches)) {
                $request['timestamp'] = $matches[1];
            }

            $highMemoryRequests[] = $request;
        } elseif (!empty($highMemoryRequests) && preg_match('/控制器:\s*(.+)$/', $line, $matches)) {
            $highMemoryRequests[count($highMemoryRequests) - 1]['controller'] = trim($matches[1]);
        } elseif (!empty($highMemoryRequests) && preg_match('/请求:\s*(.+)$/', $line, $matches)) {
            $highMemoryRequests[count($highMemoryRequests) - 1]['uri'] = trim($matches[1]);
        } elseif (!empty($highMemoryRequests) && preg_match('/内存消耗:\s*([\d\.]+)\s*MB/', $line, $matches)) {
            $highMemoryRequests[count($highMemoryRequests) - 1]['memory'] = floatval($matches[1]);
        }
    }

    if (!empty($highMemoryRequests)) {
        // 只显示最后10条
        $recentRequests = array_slice($highMemoryRequests, -10);

        foreach ($recentRequests as $req) {
            if (empty($req['controller'])) continue;

            $color = $req['memory'] >= 10 ? Color::RED : Color::YELLOW;
            $icon = $req['memory'] >= 10 ? '🔴' : '⚠️ ';

            echo "{$color}{$icon} {$req['timestamp']}\n";
            echo "   控制器: {$req['controller']}\n";
            echo "   请求: {$req['uri']}\n";
            echo "   内存: {$req['memory']} MB" . Color::NC . "\n\n";
        }
    } else {
        echo Color::GREEN . "✅ 未检测到高内存请求（所有请求内存 < 5 MB）\n" . Color::NC;
        echo "   这是一个好消息！说明内存优化已生效。\n\n";
    }
} else {
    echo Color::YELLOW . "⚠️  日志文件不存在: {$logFile}\n" . Color::NC;
    echo "\n";
}

// 3. 检查极高内存紧急报告
$emergencyReports = glob(__DIR__ . '/runtime/logs/memory_critical_*.log');

if (!empty($emergencyReports)) {
    echo Color::RED . "🚨 发现 " . count($emergencyReports) . " 个紧急报告\n" . Color::RED;
    echo Color::RED . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;

    // 显示最近5个
    rsort($emergencyReports);
    foreach (array_slice($emergencyReports, 0, 5) as $reportFile) {
        $filename = basename($reportFile);
        $time = '';
        if (preg_match('/memory_critical_(\d{14})\.log/', $filename, $matches)) {
            $time = date('Y-m-d H:i:s', strtotime($matches[1]));
        }

        echo Color::RED . "📄 {$filename}\n";
        if ($time) {
            echo "   时间: {$time}\n";
        }

        // 读取报告内容，提取控制器信息
        $content = file_get_contents($reportFile);
        if (preg_match('/控制器:\s*(.+)$/m', $content, $matches)) {
            echo "   控制器: " . trim($matches[1]) . "\n";
        }
        if (preg_match('/内存消耗:\s*([\d\.]+)\s*MB/m', $content, $matches)) {
            echo "   内存: " . $matches[1] . " MB\n";
        }
        echo Color::NC . "\n";
    }

    echo Color::YELLOW . "💡 提示: 打开这些报告文件查看详细信息和修复建议\n" . Color::NC;
    echo "\n";
}

// 4. 生成优化建议
echo Color::CYAN . "💡 优化建议汇总\n" . Color::NC;
echo Color::CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;

if (!empty($stats)) {
    $needOptimization = array_filter($stats, function($data) {
        return $data['avg_memory'] >= 5;
    });

    if (!empty($needOptimization)) {
        echo Color::YELLOW . "发现 " . count($needOptimization) . " 个需要优化的接口：\n\n" . Color::NC;

        $index = 1;
        foreach ($needOptimization as $controller => $data) {
            echo "{$index}. {$controller}\n";
            echo "   平均内存: {$data['avg_memory']} MB\n";
            echo "   优先级: ";

            if ($data['avg_memory'] >= 10) {
                echo Color::RED . "🔴 高（立即处理）\n" . Color::NC;
            } elseif ($data['avg_memory'] >= 7) {
                echo Color::YELLOW . "⚠️  中（尽快处理）\n" . Color::NC;
            } else {
                echo Color::GREEN . "💡 低（可以排期）\n" . Color::NC;
            }

            echo "\n";
            $index++;
        }

        echo Color::CYAN . "通用优化技巧：\n" . Color::NC;
        echo "  1. 使用 lazy(500) 替代 get() - 降低80%内存\n";
        echo "  2. 使用 whereExists 替代 whereIn 大数组 - 零额外内存\n";
        echo "  3. 避免在循环中查询数据库 - 使用 with() 预加载\n";
        echo "  4. 导出功能使用 chunk() 分批处理\n";
        echo "  5. 添加查询条件限制返回数据量\n";
        echo "\n";
        echo "📖 参考文档: D:\\gk_admin\\MEMORY_OPTIMIZATION_GUIDE.md\n";
    } else {
        echo Color::GREEN . "✅ 所有接口内存消耗正常（平均 < 5 MB）\n" . Color::NC;
        echo "   继续保持！\n";
    }
} else {
    echo Color::YELLOW . "⚠️  暂无统计数据，请稍后再试\n" . Color::NC;
}

echo "\n";
echo Color::BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;
echo Color::GREEN . "✅ 分析完成\n" . Color::NC;
echo Color::BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Color::NC;
echo "\n";
