@echo off
echo ========================================
echo 摸奖券性能优化 - 一键部署脚本
echo ========================================
echo.

echo [1/4] 切换到 gk_api 项目...
cd /d D:\gk_api
if errorlevel 1 (
    echo ERROR: 无法切换到 D:\gk_api 目录
    pause
    exit /b 1
)
echo OK
echo.

echo [2/4] 运行数据库迁移（添加索引）...
php vendor\bin\phinx migrate
if errorlevel 1 (
    echo ERROR: 迁移失败
    pause
    exit /b 1
)
echo OK
echo.

echo [3/4] 切换回 gk_admin 项目...
cd /d D:\gk_admin
if errorlevel 1 (
    echo ERROR: 无法切换到 D:\gk_admin 目录
    pause
    exit /b 1
)
echo OK
echo.

echo [4/4] 重启 Webman 服务...
php windows.php restart
if errorlevel 1 (
    echo WARNING: 重启服务失败，请手动重启
) else (
    echo OK
)
echo.

echo ========================================
echo 部署完成！
echo ========================================
echo.
echo 下一步：
echo 1. 运行性能测试：php test_scan_task_performance.php
echo 2. 监控日志：tail -f runtime/logs/webman.log ^| grep "打码"
echo.
pause
