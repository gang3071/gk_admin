<?php
/**
 * 诊断中间件状态
 *
 * 用途：检查 AccessLogger 和 MemoryTracker 是否正确加载
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== 中间件诊断工具 ===\n\n";

// 1. 检查中间件文件是否存在
echo "1. 检查中间件文件:\n";
$middlewares = [
    'AccessLogger' => __DIR__ . '/addons/webman/middleware/AccessLogger.php',
    'MemoryTracker' => __DIR__ . '/addons/webman/middleware/MemoryTracker.php',
];

foreach ($middlewares as $name => $path) {
    if (file_exists($path)) {
        echo "  ✅ {$name}: 存在\n";

        // 检查语法
        exec("php -l " . escapeshellarg($path), $output, $returnCode);
        if ($returnCode === 0) {
            echo "     语法检查: 通过\n";
        } else {
            echo "     ❌ 语法检查: 失败\n";
        }
    } else {
        echo "  ❌ {$name}: 不存在于 {$path}\n";
    }
}

echo "\n";

// 2. 检查配置文件
echo "2. 检查 config/middleware.php:\n";
$configFile = __DIR__ . '/config/middleware.php';
$config = require $configFile;

if (isset($config['']) && is_array($config[''])) {
    echo "  全局中间件配置:\n";
    foreach ($config[''] as $middleware) {
        $className = is_string($middleware) ? $middleware : get_class($middleware);
        echo "    - {$className}\n";
    }
} else {
    echo "  ❌ 未找到全局中间件配置\n";
}

echo "\n";

// 3. 检查日志文件
echo "3. 检查日志文件:\n";
$logFiles = [
    'runtime/logs/webman.log',
    'runtime/logs/webman-' . date('Y-m-d') . '.log',
];

foreach ($logFiles as $logFile) {
    $fullPath = __DIR__ . '/' . $logFile;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $lines = count(file($fullPath));
        echo "  ✅ {$logFile}\n";
        echo "     大小: " . round($size / 1024, 2) . " KB\n";
        echo "     行数: {$lines}\n";

        // 检查是否有 AccessLog 记录
        $content = file_get_contents($fullPath);
        $accessLogCount = substr_count($content, '[AccessLog]');
        $memTrackCount = substr_count($content, '[MemTrack]');

        echo "     AccessLog 记录: {$accessLogCount} 条\n";
        echo "     MemTrack 记录: {$memTrackCount} 条\n";

        if ($accessLogCount === 0 && $memTrackCount === 0) {
            echo "     ⚠️ 警告: 没有中间件日志记录！\n";
            echo "     → 可能原因: 服务未重启或没有请求\n";
        }
    } else {
        echo "  ❌ {$logFile}: 不存在\n";
    }
}

echo "\n";

// 4. 检查进程状态
echo "4. 检查 Webman 进程:\n";
if (PHP_OS_FAMILY === 'Windows') {
    exec('wmic process where "name=\'php.exe\'" get ProcessId,CreationDate,CommandLine', $output);
    echo "  Windows 进程列表:\n";
    foreach ($output as $line) {
        if (stripos($line, 'webman') !== false || stripos($line, 'start.php') !== false) {
            echo "    " . trim($line) . "\n";
        }
    }
} else {
    exec('ps aux | grep webman | grep -v grep', $output);
    if (!empty($output)) {
        echo "  Webman 进程数: " . count($output) . "\n";
        echo "  第一个进程: " . $output[0] . "\n";
    } else {
        echo "  ❌ 未找到 Webman 进程\n";
    }
}

echo "\n";

// 5. 建议
echo "=== 诊断建议 ===\n";
if (!file_exists(__DIR__ . '/runtime/logs/webman-' . date('Y-m-d') . '.log')) {
    echo "❌ 日志文件不存在\n";
    echo "   → 服务可能未启动\n";
    echo "   → 执行: php start.php restart\n";
} else {
    $logContent = file_get_contents(__DIR__ . '/runtime/logs/webman-' . date('Y-m-d') . '.log');
    if (strpos($logContent, '[AccessLog]') === false) {
        echo "⚠️ 日志文件存在但没有访问日志\n";
        echo "   → 可能原因:\n";
        echo "      1. 服务重启后没有收到任何请求\n";
        echo "      2. 使用了 reload 而不是 restart（reload 不会重新加载中间件配置）\n";
        echo "   → 解决方法:\n";
        echo "      1. 执行: php start.php restart (必须 restart)\n";
        echo "      2. 在浏览器访问后台页面\n";
        echo "      3. 再次检查日志: tail -20 runtime/logs/webman-" . date('Y-m-d') . ".log\n";
    } else {
        echo "✅ 中间件工作正常\n";
    }
}

echo "\n";
