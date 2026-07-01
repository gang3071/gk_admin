#!/bin/bash
# 检查内存监控日志文件位置

echo "=== 1. 检查 runtime 目录结构 ==="
ls -lh runtime/

echo ""
echo "=== 2. 检查 logs 目录 ==="
ls -lh runtime/logs/ 2>/dev/null || echo "logs 目录不存在"

echo ""
echo "=== 3. 查找所有 .log 文件 ==="
find runtime -name "*.log" -type f -exec ls -lh {} \;

echo ""
echo "=== 4. 检查 webman.log 是否存在 ==="
if [ -f "runtime/logs/webman.log" ]; then
    echo "✅ webman.log 存在"
    ls -lh runtime/logs/webman.log
    echo ""
    echo "最后 10 行内容："
    tail -10 runtime/logs/webman.log
else
    echo "❌ webman.log 不存在"
fi

echo ""
echo "=== 5. 检查是否有其他日志文件 ==="
ls -lh runtime/logs/*.log 2>/dev/null || echo "没有 .log 文件"

echo ""
echo "=== 6. 检查 MemoryTracker 是否记录了日志 ==="
grep "MemTrack\|高内存请求" runtime/logs/webman.log 2>/dev/null | tail -5 || echo "未找到 MemoryTracker 日志"

echo ""
echo "=== 7. 检查 runtime_path() 实际路径 ==="
php -r "require __DIR__ . '/vendor/autoload.php'; echo 'runtime_path() = ' . runtime_path() . PHP_EOL;"
