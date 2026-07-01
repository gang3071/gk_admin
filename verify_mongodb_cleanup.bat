@echo off
REM MongoDB 清理验证脚本 (Windows)
REM 用于检查 MongoDB 相关代码是否已完全清理

echo ==========================================
echo MongoDB 清理验证脚本
echo ==========================================
echo.

set ERRORS=0
set WARNINGS=0

REM 1. 检查 MongoDB 模型目录
echo 1. 检查 MongoDB 模型目录...
if exist "addons\webman\model\mongo" (
    echo [错误] MongoDB 模型目录仍然存在
    set /a ERRORS+=1
) else (
    echo [通过] MongoDB 模型目录已删除
)

REM 2. 检查 MongoDB 控制器
echo.
echo 2. 检查 MongoDB 控制器...
if exist "addons\webman\controller\MachineOperationLogController.php" (
    echo [错误] MachineOperationLogController.php 仍然存在
    set /a ERRORS+=1
) else (
    echo [通过] MachineOperationLogController.php 已删除
)

if exist "addons\webman\controller\MachineReceiveLogController.php" (
    echo [错误] MachineReceiveLogController.php 仍然存在
    set /a ERRORS+=1
) else (
    echo [通过] MachineReceiveLogController.php 已删除
)

if exist "addons\webman\controller\ChannelMachineOperationLogController.php" (
    echo [错误] ChannelMachineOperationLogController.php 仍然存在
    set /a ERRORS+=1
) else (
    echo [通过] ChannelMachineOperationLogController.php 已删除
)

if exist "addons\webman\controller\LotteryAddLogController.php" (
    echo [错误] LotteryAddLogController.php 仍然存在
    set /a ERRORS+=1
) else (
    echo [通过] LotteryAddLogController.php 已删除
)

REM 3. 检查 vendor 目录
echo.
echo 3. 检查 vendor 目录...
if exist "vendor\jenssegers\mongodb" (
    echo [错误] vendor\jenssegers\mongodb 仍然存在
    set /a ERRORS+=1
) else (
    echo [通过] vendor\jenssegers\mongodb 已删除
)

if exist "vendor\mongodb" (
    echo [错误] vendor\mongodb 仍然存在
    set /a ERRORS+=1
) else (
    echo [通过] vendor\mongodb 已删除
)

REM 4. 检查配置文件
echo.
echo 4. 检查配置文件...
findstr /C:"'mongodb'" config\database.php >nul 2>&1
if %errorlevel% equ 0 (
    echo [错误] config\database.php 中仍有 mongodb 配置
    set /a ERRORS+=1
) else (
    echo [通过] config\database.php 中无 mongodb 配置
)

findstr /C:"MONGODB_" .env.example >nul 2>&1
if %errorlevel% equ 0 (
    echo [错误] .env.example 中仍有 MONGODB_ 环境变量
    set /a ERRORS+=1
) else (
    echo [通过] .env.example 中无 MONGODB 环境变量
)

REM 5. 检查权限配置
echo.
echo 5. 检查权限配置...
findstr /C:"MachineOperationLogController" config\admin_node.php >nul 2>&1
if %errorlevel% equ 0 (
    echo [错误] config\admin_node.php 中仍有 MongoDB 控制器权限
    set /a ERRORS+=1
) else (
    echo [通过] config\admin_node.php 中无 MongoDB 控制器权限
)

findstr /C:"ChannelMachineOperationLogController" config\channel_node.php >nul 2>&1
if %errorlevel% equ 0 (
    echo [错误] config\channel_node.php 中仍有 MongoDB 控制器权限
    set /a ERRORS+=1
) else (
    echo [通过] config\channel_node.php 中无 MongoDB 控制器权限
)

REM 6. 检查 Composer 包
echo.
echo 6. 检查 Composer 包...
composer show 2>nul | findstr /I "mongodb" >nul 2>&1
if %errorlevel% equ 0 (
    echo [警告] Composer 仍显示 MongoDB 包已安装
    echo         请运行: composer update --ignore-platform-reqs
    set /a WARNINGS+=1
) else (
    echo [通过] Composer 中无 MongoDB 包
)

REM 总结
echo.
echo ==========================================
echo 验证总结
echo ==========================================
if %ERRORS% equ 0 (
    if %WARNINGS% equ 0 (
        echo [成功] MongoDB 清理完成！所有检查通过。
        exit /b 0
    ) else (
        echo [警告] MongoDB 清理基本完成，但有 %WARNINGS% 个警告。
        exit /b 0
    )
) else (
    echo [失败] 发现 %ERRORS% 个错误和 %WARNINGS% 个警告。
    echo.
    echo 建议执行以下操作：
    echo 1. 运行 composer update --ignore-platform-reqs 更新依赖
    echo 2. 运行 composer dump-autoload --optimize 重新生成 autoload
    echo 3. 检查上述错误提示，手动清理剩余的 MongoDB 引用
    exit /b 1
)