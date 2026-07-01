#!/bin/bash

# 内存监控脚本 - 用于验证内存泄漏修复效果
# 使用方法: bash scripts/monitor_memory.sh

echo "=========================================="
echo "内存泄漏修复效果监控脚本"
echo "开始时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# 检查进程状态
echo "📊 当前进程状态:"
php start.php status | grep "webman" | head -10
echo ""

# 创建日志目录
mkdir -p runtime/logs

# 监控日志文件
LOG_FILE="runtime/logs/memory_monitor_$(date '+%Y%m%d').log"

echo "📝 监控日志将保存到: $LOG_FILE"
echo ""

# 记录初始状态
echo "[$(date '+%Y-%m-%d %H:%M:%S')] 初始状态" >> $LOG_FILE
php start.php status >> $LOG_FILE
echo "----------------------------------------" >> $LOG_FILE

echo "🔍 开始持续监控（每5分钟记录一次）..."
echo "提示: 按 Ctrl+C 停止监控"
echo ""

# 计数器
counter=0

while true; do
    # 等待5分钟
    sleep 300

    counter=$((counter + 1))
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    # 记录到日志
    echo "[${timestamp}] 第${counter}次检查" >> $LOG_FILE
    php start.php status >> $LOG_FILE
    echo "----------------------------------------" >> $LOG_FILE

    # 显示到控制台
    echo "[$timestamp] 第${counter}次检查 - 已监控 $((counter * 5)) 分钟"

    # 提取内存信息（如果可用）
    php start.php status | grep "webman" | awk '{print "  进程", $1, "内存:", $7}' | head -5
    echo ""

    # 每小时生成一次摘要
    if [ $((counter % 12)) -eq 0 ]; then
        echo "📊 过去1小时摘要:" | tee -a $LOG_FILE
        echo "已监控 $((counter * 5)) 分钟 ($((counter / 12)) 小时)" | tee -a $LOG_FILE
        echo "请检查日志文件: $LOG_FILE" | tee -a $LOG_FILE
        echo "" | tee -a $LOG_FILE
    fi
done
