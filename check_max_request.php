<?php
/**
 * 检查 max_request 是否生效
 */

echo "=== max_request 功能检查 ===\n\n";

// 1. 检查操作系统
echo "1️⃣ 操作系统检查:\n";
echo "  当前系统: " . PHP_OS_FAMILY . "\n";

if (PHP_OS_FAMILY === 'Windows') {
    echo "  ❌ Windows 系统\n";
    echo "  🔴 Workerman/Webman 在 Windows 下有严重限制：\n";
    echo "     - max_request 不生效（会被忽略）\n";
    echo "     - count 不生效（强制单进程）\n";
    echo "     - reload 不生效（只能 restart）\n";
    echo "     - 性能较低\n\n";

    echo "  ⚠️ Windows 只能用于开发调试，不适合生产环境！\n\n";
} else {
    echo "  ✅ Linux/Unix 系统\n";
    echo "  所有功能正常支持\n\n";
}

// 2. 检查配置
echo "2️⃣ 配置检查:\n";
$serverConfig = require __DIR__ . '/config/server.php';

if (isset($serverConfig['max_request'])) {
    echo "  max_request: {$serverConfig['max_request']}\n";

    if (PHP_OS_FAMILY === 'Windows') {
        echo "  状态: ❌ 已配置但不会生效（Windows 限制）\n";
    } else {
        echo "  状态: ✅ 已配置且会生效\n";
    }
} else {
    echo "  max_request: 未配置（默认 0，不限制）\n";
}

if (isset($serverConfig['count'])) {
    echo "  count: {$serverConfig['count']}\n";

    if (PHP_OS_FAMILY === 'Windows') {
        echo "  状态: ❌ 已配置但强制为 1（Windows 限制）\n";
    } else {
        echo "  状态: ✅ 已配置且会生效\n";
    }
}

echo "\n";

// 3. 检查实际运行情况
echo "3️⃣ 实际运行检查:\n";

if (PHP_OS_FAMILY === 'Windows') {
    // Windows 下检查进程
    exec('wmic process where "name=\'php.exe\' and CommandLine like \'%webman%\'" get ProcessId,CreationDate', $output);

    $processCount = 0;
    foreach ($output as $line) {
        if (preg_match('/\d{14}/', $line)) {
            $processCount++;
        }
    }

    echo "  Webman 进程数: {$processCount}\n";
    if ($processCount <= 1) {
        echo "  ✅ 符合 Windows 限制（只能单进程）\n";
    } else {
        echo "  ⚠️ 检测到多个进程，可能是重复启动\n";
    }

    echo "\n  ⚠️ Windows 下无法验证 max_request 是否生效\n";
    echo "  原因: Windows 不支持进程自动重启功能\n";
} else {
    // Linux 下可以检查进程
    exec('ps aux | grep webman | grep -v grep | wc -l', $output);
    $processCount = intval($output[0] ?? 0);

    echo "  Webman 进程数: {$processCount}\n";

    if ($processCount > 0) {
        echo "  ✅ 进程正在运行\n";

        // 检查进程启动时间
        exec('ps -eo pid,etimes,cmd | grep webman | grep -v grep', $lines);
        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s+(\d+)/', $line, $matches)) {
                $pid = $matches[1];
                $uptime = intval($matches[2]);
                $minutes = round($uptime / 60, 1);

                echo "  PID {$pid}: 运行时间 {$minutes} 分钟\n";

                if (isset($serverConfig['max_request']) && $serverConfig['max_request'] > 0) {
                    $expectedRestartTime = $serverConfig['max_request'];  // 假设每秒1个请求
                    if ($uptime > $expectedRestartTime * 2) {
                        echo "    ⚠️ 运行时间过长，max_request 可能未生效\n";
                    }
                }
            }
        }
    } else {
        echo "  ❌ 未检测到运行中的进程\n";
    }
}

echo "\n";

// 4. 解决方案
echo "=== 解决方案 ===\n\n";

if (PHP_OS_FAMILY === 'Windows') {
    echo "🔴 Windows 下的内存泄漏无法通过 max_request 解决！\n\n";

    echo "Windows 下的替代方案：\n\n";

    echo "方案 1：使用定时任务手动重启（推荐）\n";
    echo "  - 创建 Windows 计划任务\n";
    echo "  - 每 2 小时重启一次 Webman 服务\n";
    echo "  - 缺点：有短暂的服务中断\n\n";

    echo "  步骤：\n";
    echo "    1. 创建 restart_webman.bat:\n";
    echo "       @echo off\n";
    echo "       cd /d D:\\gk_admin\n";
    echo "       php stop.php\n";
    echo "       timeout /t 5\n";
    echo "       php windows.php start\n\n";

    echo "    2. 在 Windows 任务计划程序中：\n";
    echo "       - 新建任务\n";
    echo "       - 触发器：每 2 小时\n";
    echo "       - 操作：运行 restart_webman.bat\n\n";

    echo "方案 2：使用监控脚本自动重启\n";
    echo "  - 监控内存使用\n";
    echo "  - 达到阈值时自动重启\n";
    echo "  - 我可以帮您创建这个脚本\n\n";

    echo "方案 3：迁移到 Linux（强烈推荐）\n";
    echo "  - WSL2 (Windows Subsystem for Linux)\n";
    echo "  - Docker for Windows\n";
    echo "  - 独立 Linux 服务器\n\n";

    echo "  WSL2 安装步骤：\n";
    echo "    1. 以管理员身份运行 PowerShell:\n";
    echo "       wsl --install\n";
    echo "    2. 重启电脑\n";
    echo "    3. 在 WSL 中运行 Webman:\n";
    echo "       cd /mnt/d/gk_admin\n";
    echo "       php start.php start -d\n\n";

    echo "方案 4：修复根本问题（最佳方案）\n";
    echo "  - 定位并修复内存泄漏的代码\n";
    echo "  - 使用 lazy()/chunk() 替代 get()\n";
    echo "  - 优化后就不需要频繁重启了\n\n";

} else {
    echo "✅ Linux 系统下 max_request 应该可以正常工作\n\n";

    echo "如果 max_request 仍未生效，检查：\n";
    echo "  1. 确保已重启服务（restart 不是 reload）\n";
    echo "  2. 检查 Workerman 版本（需要 >= 3.5.0）\n";
    echo "  3. 检查是否有多个 start.php 进程\n";
    echo "  4. 查看进程运行时间是否符合预期\n";
}

echo "📊 下一步建议：\n";
echo "   1. 运行诊断脚本: php diagnose_memory_leak.php\n";
echo "   2. 定位高内存接口: php analyze_memory_hotspot.php\n";
echo "   3. 修复代码中的内存泄漏\n\n";
