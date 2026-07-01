#!/bin/bash
# Redis 队列监控脚本

PROJECT_DIR="/www/wwwroot/admin.supergames9.com"
LOG_FILE="$PROJECT_DIR/runtime/logs/queue_monitor.log"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "====================================="
echo "Redis 队列监控面板"
echo "====================================="
echo ""

# 1. 检查 Redis 连接
echo -e "${BLUE}[1] Redis 连接状态${NC}"
if redis-cli ping > /dev/null 2>&1; then
    echo -e "  ${GREEN}✅ 连接正常${NC}"
else
    echo -e "  ${RED}❌ 连接失败${NC}"
    exit 1
fi

# 2. 检查队列长度
echo ""
echo -e "${BLUE}[2] 队列状态${NC}"
QUEUE_LENGTH=$(redis-cli -n 0 LLEN "{redis-queue}-default-queue" 2>/dev/null || echo 0)
DELAY_LENGTH=$(redis-cli -n 0 LLEN "{redis-queue}-default-delay" 2>/dev/null || echo 0)
FAILED_LENGTH=$(redis-cli -n 0 LLEN "{redis-queue}-default-failed" 2>/dev/null || echo 0)

echo "  待处理队列: $QUEUE_LENGTH"
echo "  延迟队列:   $DELAY_LENGTH"
echo "  失败队列:   $FAILED_LENGTH"

if [ $QUEUE_LENGTH -gt 1000 ]; then
    echo -e "  ${RED}⚠️  警告：队列堆积严重！${NC}"
elif [ $QUEUE_LENGTH -gt 100 ]; then
    echo -e "  ${YELLOW}⚠️  注意：队列有堆积${NC}"
else
    echo -e "  ${GREEN}✅ 队列正常${NC}"
fi

# 3. 检查消费者进程
echo ""
echo -e "${BLUE}[3] 消费者进程${NC}"
CONSUMER_COUNT=$(ps aux | grep "ex_admin_consumer" | grep -v grep | wc -l)
EXPECTED_COUNT=2  # 根据 process.php 配置

echo "  期望进程数: $EXPECTED_COUNT"
echo "  实际进程数: $CONSUMER_COUNT"

if [ $CONSUMER_COUNT -ge $EXPECTED_COUNT ]; then
    echo -e "  ${GREEN}✅ 进程正常${NC}"
else
    echo -e "  ${RED}❌ 进程数量不足！${NC}"
fi

# 显示进程详情
echo ""
echo "  进程列表:"
ps aux | grep "ex_admin_consumer" | grep -v grep | awk '{printf "    PID: %-8s CPU: %-6s MEM: %-6s TIME: %s\n", $2, $3"%", $4"%", $10}'

# 4. 检查进程内存占用
echo ""
echo -e "${BLUE}[4] 内存使用${NC}"
TOTAL_MEM=$(ps aux | grep "webman" | grep -v grep | awk '{sum+=$4} END {print sum}')
echo "  Webman 总内存占用: ${TOTAL_MEM}%"

if (( $(echo "$TOTAL_MEM > 50" | bc -l) )); then
    echo -e "  ${RED}⚠️  警告：内存占用过高！${NC}"
elif (( $(echo "$TOTAL_MEM > 30" | bc -l) )); then
    echo -e "  ${YELLOW}⚠️  注意：内存占用较高${NC}"
else
    echo -e "  ${GREEN}✅ 内存占用正常${NC}"
fi

# 5. 检查最近错误
echo ""
echo -e "${BLUE}[5] 最近错误（最近 100 行日志）${NC}"
ERROR_COUNT=$(tail -100 "$PROJECT_DIR/runtime/logs/webman.log" | grep -c "RuntimeException\|Fatal error\|exit with status 64000\|Timeout")

echo "  错误数量: $ERROR_COUNT"

if [ $ERROR_COUNT -gt 10 ]; then
    echo -e "  ${RED}❌ 错误频繁，请检查日志！${NC}"
    echo ""
    echo "  最近 5 个错误:"
    tail -100 "$PROJECT_DIR/runtime/logs/webman.log" | grep -i "error\|exception" | tail -5 | sed 's/^/    /'
elif [ $ERROR_COUNT -gt 0 ]; then
    echo -e "  ${YELLOW}⚠️  有少量错误${NC}"
else
    echo -e "  ${GREEN}✅ 无错误${NC}"
fi

# 6. Redis 内存使用
echo ""
echo -e "${BLUE}[6] Redis 内存${NC}"
REDIS_MEM=$(redis-cli info memory | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
REDIS_PEAK=$(redis-cli info memory | grep "used_memory_peak_human" | cut -d: -f2 | tr -d '\r')

echo "  当前内存: $REDIS_MEM"
echo "  峰值内存: $REDIS_PEAK"

# 7. 系统建议
echo ""
echo -e "${BLUE}[7] 系统建议${NC}"

if [ $QUEUE_LENGTH -gt 1000 ] || [ $ERROR_COUNT -gt 10 ] || [ $CONSUMER_COUNT -lt $EXPECTED_COUNT ]; then
    echo -e "  ${RED}⚠️  需要立即处理：${NC}"

    if [ $CONSUMER_COUNT -lt $EXPECTED_COUNT ]; then
        echo "    • 重启 Webman 服务: cd $PROJECT_DIR && php start.php restart"
    fi

    if [ $QUEUE_LENGTH -gt 1000 ]; then
        echo "    • 检查队列任务代码是否有 Bug"
        echo "    • 考虑增加消费者进程数"
    fi

    if [ $ERROR_COUNT -gt 10 ]; then
        echo "    • 查看详细错误: tail -100 $PROJECT_DIR/runtime/logs/webman.log"
    fi
else
    echo -e "  ${GREEN}✅ 系统运行正常${NC}"
fi

echo ""
echo "====================================="
echo "监控完成 - $(date '+%Y-%m-%d %H:%M:%S')"
echo "====================================="
echo ""
echo "实时监控命令："
echo "  实时日志: tail -f $PROJECT_DIR/runtime/logs/webman.log"
echo "  队列长度: watch -n 5 'redis-cli -n 0 LLEN \"{redis-queue}-default-queue\"'"
echo "  进程状态: watch -n 5 'php $PROJECT_DIR/start.php status'"
echo ""

# 记录到日志
echo "$(date '+%Y-%m-%d %H:%M:%S') - Queue: $QUEUE_LENGTH, Failed: $FAILED_LENGTH, Consumer: $CONSUMER_COUNT, Errors: $ERROR_COUNT" >> $LOG_FILE
