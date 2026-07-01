@echo off
REM MongoDB 依赖移除后更新脚本
REM 执行时间: 2026-04-02

echo ==========================================
echo   MongoDB 依赖移除 - Composer 更新
echo ==========================================
echo.

echo 当前目录: %CD%
echo.

REM 检查 composer 是否可用
where composer >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [错误] Composer 未安装或不在 PATH 中
    echo 请先安装 Composer: https://getcomposer.org/
    pause
    exit /b 1
)

echo [1/4] 备份 composer.lock...
if exist composer.lock (
    copy composer.lock composer.lock.backup.%date:~0,4%%date:~5,2%%date:~8,2%
    echo       已备份到 composer.lock.backup.%date:~0,4%%date:~5,2%%date:~8,2%
) else (
    echo       composer.lock 不存在，跳过备份
)

echo.
echo [2/4] 验证 composer.json...
composer validate
if %ERRORLEVEL% NEQ 0 (
    echo [错误] composer.json 验证失败
    pause
    exit /b 1
)

echo.
echo [3/4] 更新 composer.lock（不更新依赖版本）...
composer update --lock --no-install
if %ERRORLEVEL% NEQ 0 (
    echo [错误] composer.lock 更新失败
    echo.
    echo 如需回滚，执行:
    echo   copy composer.lock.backup.%date:~0,4%%date:~5,2%%date:~8,2% composer.lock
    pause
    exit /b 1
)

echo.
echo [4/4] 验证 MongoDB 依赖已移除...
composer show 2>nul | findstr /i "mongodb jenssegers"
if %ERRORLEVEL% EQU 0 (
    echo [警告] 检测到 MongoDB 相关包仍在 vendor 目录中
    echo.
    echo 如需完全移除，请执行:
    echo   composer install --no-dev
    echo.
    echo 注意: 这会重新安装所有依赖，请在测试环境先验证！
) else (
    echo       未检测到 MongoDB 依赖包
)

echo.
echo ==========================================
echo   完成
echo ==========================================
echo.
echo 下一步:
echo   1. 检查 composer.lock 是否正确更新
echo   2. 提交 composer.json 和 composer.lock 到版本控制
echo   3. 在测试环境执行 composer install 验证
echo   4. 验证后台管理系统功能正常
echo.

pause