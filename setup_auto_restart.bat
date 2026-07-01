@echo off
echo ===================================
echo Windows 自动重启任务设置脚本
echo ===================================
echo.

:: 获取当前脚本目录
set SCRIPT_DIR=%~dp0

echo 当前目录: %SCRIPT_DIR%
echo.

echo 创建 Windows 计划任务...
echo.

:: 创建计划任务（每 5 分钟运行一次内存监控）
schtasks /create /tn "Webman内存监控" /tr "php %SCRIPT_DIR%windows_memory_monitor.php" /sc minute /mo 5 /f

if %ERRORLEVEL% EQU 0 (
    echo ✅ 计划任务创建成功！
    echo.
    echo 📋 任务详情:
    echo    任务名称: Webman内存监控
    echo    运行频率: 每 5 分钟
    echo    执行脚本: windows_memory_monitor.php
    echo    内存阈值: 500 MB
    echo    运行时间阈值: 2 小时
    echo.
    echo 📊 查看任务:
    echo    schtasks /query /tn "Webman内存监控"
    echo.
    echo 🗑️ 删除任务:
    echo    schtasks /delete /tn "Webman内存监控" /f
    echo.
) else (
    echo ❌ 任务创建失败！
    echo.
    echo 可能原因:
    echo   1. 没有管理员权限（请右键 - 以管理员身份运行）
    echo   2. PHP 不在 PATH 中
    echo.
    echo 手动创建任务:
    echo   1. 打开"任务计划程序"
    echo   2. 创建基本任务
    echo   3. 触发器: 每 5 分钟
    echo   4. 操作: 启动程序
    echo   5. 程序: php
    echo   6. 参数: %SCRIPT_DIR%windows_memory_monitor.php
)

echo.
pause
