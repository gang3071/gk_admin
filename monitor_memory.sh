#!/bin/bash
# Webman 内存监控脚本
# 用途: 手动监控Webman进程内存使用情况
# 作者: Claude (Staff Engineer)
# 日期: 2026-05-28

# 颜色定义
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 阈值配置
WARNING_THRESHOLD=400
DANGER_THRESHOLD=800

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}    Webman 进程内存监控（手动版）${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "警告阈值: ${WARNING_THRESHOLD} MB"
echo "危险阈值: ${DANGER_THRESHOLD} MB"
echo "监控间隔: 60 秒"
echo "按 Ctrl+C 停止监控"
echo ""

# 历史数据文件
HISTORY_FILE="/tmp/webman_memory_history_$$.txt"
touch "$HISTORY_FILE"

# 清理函数
cleanup() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo "生成汇总报告..."

    if [ -s "$HISTORY_FILE" ]; then
        echo ""
        echo "监控汇总（最后10次）:"
        tail -n 10 "$HISTORY_FILE"

        # 计算平均值
        AVG_TOTAL=$(awk '{sum+=$2; count++} END {if(count>0) print sum/count; else print 0}' "$HISTORY_FILE")
        MAX_TOTAL=$(awk 'BEGIN{max=0} {if($2>max) max=$2} END{print max}' "$HISTORY_FILE")

        echo ""
        echo "平均总内存: $(printf "%.2f" $AVG_TOTAL) MB"
        echo "峰值总内存: $(printf "%.2f" $MAX_TOTAL) MB"
    fi

    rm -f "$HISTORY_FILE"
    echo ""
    echo -e "${GREEN}✅ 监控已停止${NC}"
    echo ""
    exit 0
}

# 捕获Ctrl+C
trap cleanup INT TERM

# 监控循环
iteration=0
while true; do
    iteration=$((iteration + 1))
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    echo -e "${BLUE}=========================================${NC}"
    echo -e "${BLUE}[$timestamp] 监控报告 #$iteration${NC}"
    echo -e "${BLUE}=========================================${NC}"

    # 检测操作系统
    if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" || "$OSTYPE" == "cygwin" ]]; then
        # Windows
        processes=$(wmic process where "name='php.exe'" get ProcessId,WorkingSetSize 2>/dev/null | awk 'NR>1 && NF>=2 {print $1, $2}')
    else
        # Linux/Unix
        processes=$(ps aux | grep webman | grep -v grep | awk '{print $2, $6}')
    fi

    if [ -z "$processes" ]; then
        echo -e "${RED}❌ 未检测到Webman进程${NC}"
        echo ""
        sleep 60
        continue
    fi

    # 统计变量
    total_memory=0
    process_count=0
    warning_count=0
    danger_count=0

    # 临时文件存储当前数据
    current_data="/tmp/webman_current_$$.txt"
    > "$current_data"

    # 处理每个进程
    while IFS= read -r line; do
        if [ -z "$line" ]; then
            continue
        fi

        pid=$(echo "$line" | awk '{print $1}')
        memory_value=$(echo "$line" | awk '{print $2}')

        # Windows下是字节，Linux下是KB
        if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" || "$OSTYPE" == "cygwin" ]]; then
            memory_mb=$(echo "scale=2; $memory_value / 1024 / 1024" | bc)
        else
            memory_mb=$(echo "scale=2; $memory_value / 1024" | bc)
        fi

        # 过滤掉小进程（< 30 MB，可能是监控进程本身）
        if (( $(echo "$memory_mb < 30" | bc -l) )); then
            continue
        fi

        process_count=$((process_count + 1))
        total_memory=$(echo "$total_memory + $memory_mb" | bc)

        # 状态判断
        status="正常"
        icon="✅"
        color=$GREEN

        if (( $(echo "$memory_mb >= $DANGER_THRESHOLD" | bc -l) )); then
            status="危险"
            icon="🔴"
            color=$RED
            danger_count=$((danger_count + 1))
        elif (( $(echo "$memory_mb >= $WARNING_THRESHOLD" | bc -l) )); then
            status="警告"
            icon="⚠️ "
            color=$YELLOW
            warning_count=$((warning_count + 1))
        fi

        # 计算增长（如果有历史数据）
        prev_memory=$(grep "^$pid " "$HISTORY_FILE" | tail -n 1 | awk '{print $3}')
        if [ -n "$prev_memory" ]; then
            growth=$(echo "$memory_mb - $prev_memory" | bc)
            growth_str=$(printf "%+.2f MB/分" $growth)

            # 趋势判断
            if (( $(echo "$growth > 5" | bc -l) )); then
                trend="↑↑↑"
            elif (( $(echo "$growth > 2" | bc -l) )); then
                trend="↑↑"
            elif (( $(echo "$growth > 0.5" | bc -l) )); then
                trend="↑"
            elif (( $(echo "$growth > -0.5" | bc -l) )); then
                trend="━"
            else
                trend="↓"
            fi
        else
            growth_str="N/A"
            trend="━"
        fi

        # 打印进程信息
        printf "${color}%s PID: %-8s | 内存: %8.2f MB | 增长: %-15s | 趋势: %s | 状态: %s${NC}\n" \
            "$icon" "$pid" "$memory_mb" "$growth_str" "$trend" "$status"

        # 保存当前数据
        echo "$timestamp $pid $memory_mb" >> "$current_data"

    done <<< "$processes"

    # 更新历史文件
    cat "$current_data" >> "$HISTORY_FILE"
    rm -f "$current_data"

    # 汇总统计
    echo -e "${BLUE}─────────────────────────────────────────${NC}"

    if [ $process_count -gt 0 ]; then
        avg_memory=$(echo "scale=2; $total_memory / $process_count" | bc)
        printf "${BLUE}📊 汇总 | 进程数: %d | 总内存: %.2f MB | 平均: %.2f MB${NC}\n" \
            $process_count $total_memory $avg_memory

        # 记录到历史（用于生成汇总报告）
        echo "$timestamp $total_memory $avg_memory $process_count" >> "${HISTORY_FILE}.summary"
    else
        echo -e "${YELLOW}⚠️  无有效进程数据${NC}"
    fi

    # 警告信息
    if [ $danger_count -gt 0 ]; then
        echo -e "${RED}🔴 危险: $danger_count 个进程超过 $DANGER_THRESHOLD MB${NC}"
    fi

    if [ $warning_count -gt 0 ]; then
        echo -e "${YELLOW}⚠️  警告: $warning_count 个进程超过 $WARNING_THRESHOLD MB${NC}"
    fi

    echo -e "${BLUE}=========================================${NC}"
    echo ""

    # 每5次迭代（5分钟）显示一次趋势
    if [ $((iteration % 5)) -eq 0 ] && [ -s "${HISTORY_FILE}.summary" ]; then
        echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo -e "${BLUE}📈 5分钟趋势${NC}"
        echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

        # 显示最后5次的汇总数据
        tail -n 5 "${HISTORY_FILE}.summary" | awk '{
            printf "  [%s %s] 总内存: %.2f MB | 平均: %.2f MB | 进程数: %d\n",
                $1, $2, $3, $4, $5
        }'

        # 计算趋势
        first_total=$(tail -n 5 "${HISTORY_FILE}.summary" | head -n 1 | awk '{print $3}')
        last_total=$(tail -n 1 "${HISTORY_FILE}.summary" | awk '{print $3}')

        if [ -n "$first_total" ] && [ -n "$last_total" ]; then
            trend_value=$(echo "$last_total - $first_total" | bc)

            if (( $(echo "$trend_value > 50" | bc -l) )); then
                echo -e "${RED}  趋势: 快速增长 (+$(printf "%.2f" $trend_value) MB)${NC}"
            elif (( $(echo "$trend_value > 20" | bc -l) )); then
                echo -e "${YELLOW}  趋势: 增长 (+$(printf "%.2f" $trend_value) MB)${NC}"
            elif (( $(echo "$trend_value > -20" | bc -l) )); then
                echo -e "${GREEN}  趋势: 稳定 ($(printf "%+.2f" $trend_value) MB)${NC}"
            else
                echo -e "${GREEN}  趋势: 下降 ($(printf "%.2f" $trend_value) MB)${NC}"
            fi
        fi

        echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo ""
    fi

    # 等待60秒
    sleep 60
done
