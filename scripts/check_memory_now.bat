@echo off
REM Windows批处理脚本 - 快速检查当前内存状态

echo ==========================================
echo 内存状态快速检查
echo 时间: %date% %time%
echo ==========================================
echo.

echo 📊 Webman 进程状态:
php start.php status | findstr "webman" | findstr /V "findstr"
echo.

echo 📁 日志文件大小:
dir /s runtime\logs\webman.log 2>nul | findstr "webman.log"
echo.

echo 💾 系统内存信息:
systeminfo | findstr /C:"Total Physical Memory" /C:"Available Physical Memory"
echo.

echo ==========================================
echo 提示: 如需持续监控,请运行:
echo   bash scripts/monitor_memory.sh
echo ==========================================

pause
