<?php
/**
 * Windows 下的内存监控和自动重启脚本
 *
 * 用途：由于 Windows 不支持 max_request，使用此脚本监控内存并自动重启
 *
 * 使用方法：
 *   1. 在 Windows 任务计划程序中设置每 5 分钟运行一次
 *   2. 或手动运行: php windows_memory_monitor.php
 */

$config = [
    // 内存阈值（MB）
    'memory_threshold' => 500,  // 超过 500 MB 就重启

    // 运行时间阈值（秒）
    'runtime_threshold' => 7200,  // 运行超过 2 小时就重启

    // 日志文件
    'log_file' => __DIR__ . '/runtime/logs/auto_restart.log',

    // 锁文件（防止同时重启）
    'lock_file' => __DIR__ . '/runtime/restart.lock',

    // Webman 目录
    'webman_dir' => __DIR__,
];

// 记录日志
function writeLog($message)
{
    global $config;

    $logDir = dirname($config['log_file']);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $config['log_file'],
        "[{$timestamp}] {$message}\n",
        FILE_APPEND
    );
}

// 检查是否正在重启
function isRestarting()
{
    global $config;

    if (file_exists($config['lock_file'])) {
        $lockTime = filemtime($config['lock_file']);
        // 如果锁文件超过 5 分钟，认为是僵死锁，删除
        if (time() - $lockTime > 300) {
            unlink($config['lock_file']);
            writeLog("清理僵死锁文件");
            return false;
        }
        return true;
    }
    return false;
}

// 创建锁文件
function createLock()
{
    global $config;
    file_put_contents($config['lock_file'], time());
}

// 删除锁文件
function removeLock()
{
    global $config;
    if (file_exists($config['lock_file'])) {
        unlink($config['lock_file']);
    }
}

// 获取 Webman 进程信息
function getWebmanProcesses()
{
    $processes = [];

    // Windows: 使用 wmic
    exec('wmic process where "name=\'php.exe\'" get ProcessId,WorkingSetSize,CreationDate,CommandLine /format:csv', $output);

    foreach ($output as $line) {
        // 只检查 webman 相关的进程
        if (stripos($line, 'webman') !== false || stripos($line, 'start.php') !== false || stripos($line, 'windows.php') !== false) {
            $parts = str_getcsv($line);
            if (count($parts) >= 4 && is_numeric($parts[2])) {
                $processes[] = [
                    'pid' => $parts[2],
                    'memory_bytes' => $parts[3],
                    'memory_mb' => round($parts[3] / 1024 / 1024, 2),
                    'creation_date' => $parts[1],
                ];
            }
        }
    }

    return $processes;
}

// 解析 Windows 时间格式
function parseWindowsDateTime($dateTime)
{
    // 格式: 20260529103045.123456+480
    if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $dateTime, $matches)) {
        return mktime(
            intval($matches[4]),  // hour
            intval($matches[5]),  // minute
            intval($matches[6]),  // second
            intval($matches[2]),  // month
            intval($matches[3]),  // day
            intval($matches[1])   // year
        );
    }
    return null;
}

// 重启 Webman
function restartWebman($reason)
{
    global $config;

    writeLog("准备重启 Webman，原因: {$reason}");

    // 停止
    chdir($config['webman_dir']);
    exec('php stop.php 2>&1', $stopOutput, $stopCode);

    writeLog("停止命令执行完毕，返回码: {$stopCode}");
    writeLog("输出: " . implode("\n", $stopOutput));

    // 等待 5 秒
    sleep(5);

    // 启动
    exec('start /B php windows.php start 2>&1', $startOutput, $startCode);

    writeLog("启动命令执行完毕，返回码: {$startCode}");
    writeLog("输出: " . implode("\n", $startOutput));

    writeLog("Webman 已重启");

    return true;
}

// 主逻辑
echo "=== Windows 内存监控脚本 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// 检查是否正在重启
if (isRestarting()) {
    echo "⚠️ 正在重启中，跳过本次检查\n";
    writeLog("跳过检查：正在重启中");
    exit(0);
}

// 获取进程信息
$processes = getWebmanProcesses();

if (empty($processes)) {
    echo "⚠️ 未检测到 Webman 进程\n";
    echo "可能原因：\n";
    echo "  1. Webman 未启动\n";
    echo "  2. 进程名称不匹配\n\n";

    writeLog("未检测到 Webman 进程");
    exit(0);
}

echo "检测到 " . count($processes) . " 个 Webman 进程\n\n";

$needRestart = false;
$restartReason = '';

foreach ($processes as $proc) {
    echo "PID: {$proc['pid']}\n";
    echo "  内存: {$proc['memory_mb']} MB\n";

    // 检查内存
    if ($proc['memory_mb'] >= $config['memory_threshold']) {
        $needRestart = true;
        $restartReason = "进程 {$proc['pid']} 内存超过阈值 ({$proc['memory_mb']} MB >= {$config['memory_threshold']} MB)";
        echo "  🔴 内存超过阈值！\n";
    }

    // 检查运行时间
    if (!empty($proc['creation_date'])) {
        $startTime = parseWindowsDateTime($proc['creation_date']);
        if ($startTime) {
            $runtime = time() - $startTime;
            $runtimeMinutes = round($runtime / 60, 1);
            echo "  运行时间: {$runtimeMinutes} 分钟\n";

            if ($runtime >= $config['runtime_threshold']) {
                $needRestart = true;
                $restartReason = "进程 {$proc['pid']} 运行时间超过阈值 ({$runtimeMinutes} 分钟 >= " . round($config['runtime_threshold'] / 60, 1) . " 分钟)";
                echo "  🔴 运行时间过长！\n";
            }
        }
    }

    echo "\n";
}

// 是否需要重启
if ($needRestart) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔄 触发自动重启\n";
    echo "原因: {$restartReason}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // 创建锁文件
    createLock();

    try {
        $success = restartWebman($restartReason);

        if ($success) {
            echo "✅ 重启成功\n";
        } else {
            echo "❌ 重启失败\n";
        }
    } finally {
        // 删除锁文件
        removeLock();
    }
} else {
    echo "✅ 内存和运行时间正常，无需重启\n";
    writeLog("检查通过：内存和运行时间正常");
}

echo "\n";
echo "日志文件: {$config['log_file']}\n";
