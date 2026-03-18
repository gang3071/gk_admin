@echo off
REM ========================================
REM 设备管理功能数据库迁移脚本 (Windows)
REM ========================================

echo.
echo ========================================
echo 设备管理功能 - 数据库迁移
echo ========================================
echo.

REM 检查当前目录
cd /d "%~dp0..\.."
echo 当前目录: %CD%
echo.

REM 选择迁移方式
echo 请选择迁移方式:
echo.
echo [1] 使用 Phinx 执行迁移 (推荐)
echo [2] 使用 SQL 文件执行迁移
echo [3] 查看迁移状态
echo [4] 回滚迁移
echo [0] 退出
echo.

set /p choice="请输入选项 (0-4): "

if "%choice%"=="1" goto phinx_migrate
if "%choice%"=="2" goto sql_migrate
if "%choice%"=="3" goto phinx_status
if "%choice%"=="4" goto phinx_rollback
if "%choice%"=="0" goto end
goto invalid_choice

:phinx_migrate
echo.
echo ========================================
echo 执行 Phinx 迁移...
echo ========================================
echo.
php vendor/bin/phinx.bat migrate
if %errorlevel% equ 0 (
    echo.
    echo ✅ 迁移执行成功！
    echo.
    echo 下一步:
    echo 1. 在后台添加菜单: 设备管理
    echo 2. 阅读文档: docs/device_management_quick_start.md
) else (
    echo.
    echo ❌ 迁移执行失败！请检查错误信息。
)
goto end

:sql_migrate
echo.
echo ========================================
echo 使用 SQL 文件执行迁移
echo ========================================
echo.
echo 请输入数据库信息:
set /p db_user="MySQL 用户名: "
set /p db_name="数据库名称: "
echo.
echo 正在执行 SQL 文件...
mysql -u %db_user% -p %db_name% < database/migrations/create_device_tables.sql
if %errorlevel% equ 0 (
    echo.
    echo ✅ SQL 执行成功！
    echo.
    echo 下一步:
    echo 1. 在后台添加菜单: 设备管理
    echo 2. 阅读文档: docs/device_management_quick_start.md
) else (
    echo.
    echo ❌ SQL 执行失败！请检查错误信息。
)
goto end

:phinx_status
echo.
echo ========================================
echo 查看迁移状态...
echo ========================================
echo.
php vendor/bin/phinx.bat status
goto end

:phinx_rollback
echo.
echo ========================================
echo 回滚迁移
echo ========================================
echo.
echo ⚠️  警告：回滚将删除设备管理相关表和数据！
echo.
set /p confirm="确认回滚? (yes/no): "
if /i "%confirm%"=="yes" (
    php vendor/bin/phinx.bat rollback -t 20260316120000
    echo.
    echo ✅ 回滚完成！
) else (
    echo.
    echo 已取消回滚。
)
goto end

:invalid_choice
echo.
echo ❌ 无效选项！请重新运行脚本。
goto end

:end
echo.
echo ========================================
echo 脚本执行完毕
echo ========================================
echo.
pause
