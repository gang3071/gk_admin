@echo off
REM ============================================
REM 自动交班多条记录问题一键修复脚本 (Windows)
REM ============================================

echo.
echo ========================================
echo   自动交班配置表修复工具
echo ========================================
echo.
echo 此脚本将修复以下问题：
echo   1. 清理重复的自动交班配置记录
echo   2. 修复错误的唯一索引设计
echo   3. 确保每个店家只有一条配置
echo.
echo 修复前会自动备份数据，请放心运行。
echo.
pause

REM 检查 Phinx 是否可用
echo.
echo [检查] 检测 Phinx 迁移工具...
if exist "vendor\bin\phinx.bat" (
    echo [成功] Phinx 可用
    goto USE_PHINX
) else (
    echo [警告] Phinx 不可用，将使用 SQL 脚本方式
    goto USE_SQL
)

:USE_PHINX
echo.
echo ========================================
echo   方式 1: 使用 Phinx 迁移 (推荐)
echo ========================================
echo.
echo 正在执行迁移...
vendor\bin\phinx.bat migrate

if %ERRORLEVEL% EQU 0 (
    echo.
    echo [成功] Phinx 迁移执行完成！
    goto VERIFICATION
) else (
    echo.
    echo [错误] Phinx 迁移执行失败，错误码: %ERRORLEVEL%
    echo 请查看上面的错误信息，或尝试使用 SQL 脚本方式。
    echo.
    echo 是否尝试使用 SQL 脚本方式修复？
    choice /C YN /M "继续"
    if %ERRORLEVEL% EQU 1 goto USE_SQL
    goto END
)

:USE_SQL
echo.
echo ========================================
echo   方式 2: 使用 SQL 脚本
echo ========================================
echo.
echo 请选择执行方式：
echo   1. 使用 MySQL 命令行工具（需要配置环境变量）
echo   2. 手动复制 SQL 到 phpMyAdmin 或 Navicat 执行
echo.
choice /C 12 /M "请选择"

if %ERRORLEVEL% EQU 1 (
    REM 使用 MySQL 命令行
    echo.
    set /p DB_HOST="请输入数据库主机（默认: localhost）: "
    if "%DB_HOST%"=="" set DB_HOST=localhost

    set /p DB_NAME="请输入数据库名（默认: yjb_platform）: "
    if "%DB_NAME%"=="" set DB_NAME=yjb_platform

    set /p DB_USER="请输入数据库用户名（默认: root）: "
    if "%DB_USER%"=="" set DB_USER=root

    echo.
    echo 正在执行 SQL 脚本...
    mysql -h %DB_HOST% -u %DB_USER% -p %DB_NAME% < database\fixes\fix_auto_shift_unique_index.sql

    if %ERRORLEVEL% EQU 0 (
        echo [成功] SQL 脚本执行完成！
        goto VERIFICATION
    ) else (
        echo [错误] SQL 脚本执行失败
        echo 请检查数据库连接信息是否正确。
        goto END
    )
) else (
    REM 手动执行
    echo.
    echo ========================================
    echo   手动执行指南
    echo ========================================
    echo.
    echo 请按照以下步骤操作：
    echo.
    echo 1. 打开 phpMyAdmin 或 Navicat
    echo 2. 选择数据库
    echo 3. 打开 SQL 窗口
    echo 4. 复制以下文件的内容：
    echo    database\fixes\fix_auto_shift_unique_index.sql
    echo 5. 粘贴并执行
    echo.
    echo 文件路径已复制到剪贴板。
    echo database\fixes\fix_auto_shift_unique_index.sql | clip
    echo.
    pause
    goto END
)

:VERIFICATION
echo.
echo ========================================
echo   验证修复结果
echo ========================================
echo.
echo 请手动验证以下内容：
echo.
echo 1. 登录店家账号
echo 2. 进入"店家中心"
echo 3. 检查自动交班状态是否显示"已禁用"
echo 4. 点击"手动交班"按钮
echo 5. 应该能够正常打开交班表单
echo.
echo 如果仍然无法手动交班，请运行以下 SQL 验证：
echo.
echo   SELECT * FROM yjb_store_auto_shift_config WHERE is_enabled = 1;
echo   （应该返回空结果集，或者只包含确实需要启用自动交班的记录）
echo.
pause

:END
echo.
echo ========================================
echo   修复完成
echo ========================================
echo.
echo 感谢使用！
echo.
pause
