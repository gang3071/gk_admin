@echo off
REM Webman 内存监控脚本 (Windows版本)
REM 用途: 手动监控Webman进程内存使用情况
REM 作者: Claude (Staff Engineer)
REM 日期: 2026-05-28

chcp 65001 >nul
setlocal enabledelayedexpansion

set WARNING_THRESHOLD=400
set DANGER_THRESHOLD=800
set ITERATION=0

echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo     Webman 进程内存监控（Windows版）
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo.
echo 警告阈值: %WARNING_THRESHOLD% MB
echo 危险阈值: %DANGER_THRESHOLD% MB
echo 监控间隔: 60 秒
echo 按 Ctrl+C 停止监控
echo.

:LOOP
set /a ITERATION+=1

REM 获取当前时间
for /f "tokens=1-3 delims=/:. " %%a in ("%time%") do (
    set HOUR=%%a
    set MINUTE=%%b
    set SECOND=%%c
)
if !HOUR! lss 10 set HOUR=0!HOUR:~1!

set TIMESTAMP=%date:~0,10% !HOUR!:!MINUTE!:!SECOND!

echo =========================================
echo [%TIMESTAMP%] 监控报告 #%ITERATION%
echo =========================================

REM 获取PHP进程信息
set TOTAL_MEMORY=0
set PROCESS_COUNT=0
set WARNING_COUNT=0
set DANGER_COUNT=0

REM 临时文件
set TEMP_FILE=%TEMP%\webman_processes_%RANDOM%.txt
wmic process where "name='php.exe'" get ProcessId,WorkingSetSize /format:csv > "%TEMP_FILE%" 2>nul

REM 检查是否有进程
findstr /R "[0-9]" "%TEMP_FILE%" >nul
if errorlevel 1 (
    echo ❌ 未检测到Webman进程
    echo.
    del "%TEMP_FILE%" 2>nul
    timeout /t 60 /nobreak >nul
    goto LOOP
)

REM 解析进程数据
for /f "skip=2 tokens=1,2,3 delims=," %%a in (%TEMP_FILE%) do (
    if not "%%c"=="" (
        set PID=%%b
        set MEMORY_BYTES=%%c

        REM 计算内存（MB）
        set /a MEMORY_MB=!MEMORY_BYTES! / 1048576

        REM 过滤小进程（< 30 MB）
        if !MEMORY_MB! geq 30 (
            set /a PROCESS_COUNT+=1
            set /a TOTAL_MEMORY+=!MEMORY_MB!

            REM 状态判断
            set STATUS=正常
            set ICON=✅

            if !MEMORY_MB! geq %DANGER_THRESHOLD% (
                set STATUS=危险
                set ICON=🔴
                set /a DANGER_COUNT+=1
            ) else if !MEMORY_MB! geq %WARNING_THRESHOLD% (
                set STATUS=警告
                set ICON=⚠️
                set /a WARNING_COUNT+=1
            )

            REM 打印进程信息
            echo !ICON! PID: !PID!       ^| 内存: !MEMORY_MB! MB ^| 状态: !STATUS!
        )
    )
)

del "%TEMP_FILE%" 2>nul

echo ─────────────────────────────────────────

REM 汇总统计
if %PROCESS_COUNT% gtr 0 (
    set /a AVG_MEMORY=%TOTAL_MEMORY% / %PROCESS_COUNT%
    echo 📊 汇总 ^| 进程数: %PROCESS_COUNT% ^| 总内存: %TOTAL_MEMORY% MB ^| 平均: !AVG_MEMORY! MB
) else (
    echo ⚠️  无有效进程数据
)

REM 警告信息
if %DANGER_COUNT% gtr 0 (
    echo 🔴 危险: %DANGER_COUNT% 个进程超过 %DANGER_THRESHOLD% MB
)

if %WARNING_COUNT% gtr 0 (
    echo ⚠️  警告: %WARNING_COUNT% 个进程超过 %WARNING_THRESHOLD% MB
)

echo =========================================
echo.

REM 修复验证提示
if %ITERATION% equ 1 (
    echo 💡 修复验证指南:
    echo    - 平均内存 ^< 100 MB = 修复生效 ✅
    echo    - 平均内存 100-200 MB = 部分生效 ⚠️
    echo    - 平均内存 ^> 200 MB = 修复未生效 ❌
    echo.
)

REM 每5次显示趋势提示
set /a MOD_5=%ITERATION% %% 5
if %MOD_5% equ 0 (
    echo 📈 已监控 %ITERATION% 次（%ITERATION% 分钟）
    echo    建议继续观察至少 10-20 次以评估趋势
    echo.
)

REM 等待60秒
echo 下次检查倒计时...
timeout /t 60 /nobreak >nul

goto LOOP

endlocal
