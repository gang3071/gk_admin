#!/bin/bash
###############################################################################
# 服务器配置检查脚本
# 用于诊断为什么 Webman 进程内存会超过 1GB
###############################################################################

echo "======================================================================"
echo "  Webman 服务器配置诊断工具"
echo "======================================================================"
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

###############################################################################
# 1. PHP 配置检查
###############################################################################
echo "1. PHP 配置检查"
echo "----------------------------------------------------------------------"

# memory_limit
MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
echo -e "   memory_limit: ${YELLOW}${MEMORY_LIMIT}${NC}"

if [[ "$MEMORY_LIMIT" == "-1" ]]; then
    echo -e "   ${RED}⚠️  警告：无限制内存！PHP 可以使用所有系统内存！${NC}"
    echo "   建议：设置为 512M 或 1G"
elif [[ "${MEMORY_LIMIT//[^0-9]/}" -gt 1024 ]]; then
    echo -e "   ${YELLOW}⚠️  警告：memory_limit 过高（${MEMORY_LIMIT}）${NC}"
    echo "   建议：降低到 512M"
else
    echo -e "   ${GREEN}✅ 正常${NC}"
fi

# max_execution_time
MAX_EXEC=$(php -r "echo ini_get('max_execution_time');")
echo -e "   max_execution_time: ${YELLOW}${MAX_EXEC}s${NC}"

if [[ "$MAX_EXEC" == "0" ]] || [[ "$MAX_EXEC" -gt 300 ]]; then
    echo -e "   ${YELLOW}⚠️  可能导致长时间运行的请求占用大量内存${NC}"
fi

# Opcache 配置
OPCACHE_ENABLED=$(php -r "echo ini_get('opcache.enable');")
echo -e "   opcache.enable: ${YELLOW}${OPCACHE_ENABLED}${NC}"

if [[ "$OPCACHE_ENABLED" != "1" ]]; then
    echo -e "   ${YELLOW}⚠️  Opcache 未启用，可能导致重复加载代码${NC}"
fi

OPCACHE_MEM=$(php -r "echo ini_get('opcache.memory_consumption');")
echo -e "   opcache.memory_consumption: ${YELLOW}${OPCACHE_MEM}${NC}"

if [[ "${OPCACHE_MEM//[^0-9]/}" -gt 512 ]]; then
    echo -e "   ${YELLOW}⚠️  Opcache 内存过大${NC}"
fi

echo ""

###############################################################################
# 2. Webman 配置检查
###############################################################################
echo "2. Webman 配置检查"
echo "----------------------------------------------------------------------"

# 检查 config/server.php
if [ -f "config/server.php" ]; then
    # worker 进程数
    WORKER_COUNT=$(grep -oP "count'\s*=>\s*\K\d+" config/server.php | head -1)
    echo -e "   worker count: ${YELLOW}${WORKER_COUNT:-未设置}${NC}"

    if [[ "$WORKER_COUNT" -gt 8 ]]; then
        echo -e "   ${YELLOW}⚠️  进程数过多！${NC}"
        echo "   ${WORKER_COUNT} 个进程 × 1GB = ${WORKER_COUNT}GB 总内存占用"
        echo "   建议：设置为 CPU 核心数（通常 4-8）"
    fi

    # max_request
    MAX_REQUEST=$(grep -oP "max_request'\s*=>\s*\K\d+" config/server.php | head -1)
    echo -e "   max_request: ${YELLOW}${MAX_REQUEST:-未设置}${NC}"

    if [[ -z "$MAX_REQUEST" ]] || [[ "$MAX_REQUEST" == "0" ]]; then
        echo -e "   ${RED}❌ 未设置或为 0！进程永不重启！${NC}"
        echo "   这是导致内存超过 1GB 的主要原因！"
        echo "   建议：设置为 100"
    elif [[ "$MAX_REQUEST" -gt 500 ]]; then
        echo -e "   ${YELLOW}⚠️  max_request 过高（${MAX_REQUEST}）${NC}"
        echo "   建议：降低到 100-200"
    else
        echo -e "   ${GREEN}✅ 正常${NC}"
    fi

else
    echo -e "   ${RED}❌ config/server.php 不存在${NC}"
fi

echo ""

###############################################################################
# 3. 系统资源检查
###############################################################################
echo "3. 系统资源检查"
echo "----------------------------------------------------------------------"

# 总内存
TOTAL_MEM=$(free -h | awk '/^Mem:/{print $2}')
echo -e "   系统总内存: ${YELLOW}${TOTAL_MEM}${NC}"

# 可用内存
AVAILABLE_MEM=$(free -h | awk '/^Mem:/{print $7}')
echo -e "   可用内存: ${YELLOW}${AVAILABLE_MEM}${NC}"

# Swap 使用
SWAP_USED=$(free -h | awk '/^Swap:/{print $3}')
echo -e "   Swap 使用: ${YELLOW}${SWAP_USED}${NC}"

if [[ "${SWAP_USED//[^0-9]/}" -gt 1 ]]; then
    echo -e "   ${RED}⚠️  警告：正在使用 Swap！性能会严重下降！${NC}"
fi

echo ""

###############################################################################
# 4. 当前进程状态
###############################################################################
echo "4. 当前 Webman 进程状态"
echo "----------------------------------------------------------------------"

if command -v php &> /dev/null && [ -f "start.php" ]; then
    php start.php status 2>/dev/null | grep -E "webman|worker" || echo "   服务未运行"
else
    ps aux | grep "[w]ebman" | awk '{printf "   PID: %s, MEM: %d MB, CPU: %s%%\n", $2, $6/1024, $3}'
fi

echo ""

###############################################################################
# 5. 进程内存分布
###############################################################################
echo "5. 进程内存详细分析"
echo "----------------------------------------------------------------------"

WEBMAN_PIDS=$(pgrep -f "webman" 2>/dev/null)

if [ -n "$WEBMAN_PIDS" ]; then
    TOTAL_MEM=0
    MAX_MEM=0
    COUNT=0

    while IFS= read -r pid; do
        MEM=$(ps -p $pid -o rss= 2>/dev/null)
        if [ -n "$MEM" ]; then
            MEM_MB=$((MEM / 1024))
            TOTAL_MEM=$((TOTAL_MEM + MEM_MB))
            COUNT=$((COUNT + 1))

            if [ $MEM_MB -gt $MAX_MEM ]; then
                MAX_MEM=$MEM_MB
            fi

            if [ $MEM_MB -gt 1024 ]; then
                echo -e "   ${RED}PID $pid: ${MEM_MB} MB ⚠️  超过 1GB！${NC}"
            elif [ $MEM_MB -gt 500 ]; then
                echo -e "   ${YELLOW}PID $pid: ${MEM_MB} MB${NC}"
            else
                echo -e "   ${GREEN}PID $pid: ${MEM_MB} MB${NC}"
            fi
        fi
    done <<< "$WEBMAN_PIDS"

    echo ""
    echo "   总计："
    echo "   - 进程数: $COUNT"
    echo "   - 总内存: ${TOTAL_MEM} MB"
    echo "   - 平均内存: $((TOTAL_MEM / COUNT)) MB"
    echo "   - 最大内存: ${MAX_MEM} MB"

    if [ $MAX_MEM -gt 1024 ]; then
        echo -e "   ${RED}❌ 有进程超过 1GB！需要立即处理！${NC}"
    fi
else
    echo "   ℹ️  Webman 服务未运行"
fi

echo ""

###############################################################################
# 6. 诊断建议
###############################################################################
echo "======================================================================"
echo "  诊断建议"
echo "======================================================================"
echo ""

# 判断主要问题
ISSUES=()

if [[ -z "$MAX_REQUEST" ]] || [[ "$MAX_REQUEST" == "0" ]]; then
    ISSUES+=("max_request 未设置或为 0")
fi

if [[ "$MAX_REQUEST" -gt 500 ]]; then
    ISSUES+=("max_request 过高 ($MAX_REQUEST)")
fi

if [[ "$WORKER_COUNT" -gt 8 ]]; then
    ISSUES+=("worker 进程数过多 ($WORKER_COUNT)")
fi

if [[ "$MEMORY_LIMIT" == "-1" ]]; then
    ISSUES+=("PHP memory_limit 无限制")
fi

if [[ "${MEMORY_LIMIT//[^0-9]/}" -gt 1024 ]]; then
    ISSUES+=("PHP memory_limit 过高 ($MEMORY_LIMIT)")
fi

if [ ${#ISSUES[@]} -eq 0 ]; then
    echo -e "${GREEN}✅ 配置看起来正常${NC}"
    echo ""
    echo "如果内存还是超过 1GB，可能是："
    echo "1. 代码中仍有内存泄漏"
    echo "2. 某些特定请求占用过大"
    echo "3. 运行诊断脚本：php scripts/memory_leak_detector.php"
else
    echo -e "${RED}发现以下问题：${NC}"
    echo ""
    for issue in "${ISSUES[@]}"; do
        echo "   ❌ $issue"
    done
    echo ""
    echo "建议修复方案："
    echo ""

    if [[ -z "$MAX_REQUEST" ]] || [[ "$MAX_REQUEST" == "0" ]] || [[ "$MAX_REQUEST" -gt 500 ]]; then
        echo "1. 设置 max_request"
        echo "   编辑 config/server.php："
        echo "   'max_request' => 100,"
        echo ""
    fi

    if [[ "$WORKER_COUNT" -gt 8 ]]; then
        echo "2. 降低 worker 进程数"
        echo "   编辑 config/server.php："
        echo "   'count' => 4,  // 或者你的 CPU 核心数"
        echo ""
    fi

    if [[ "$MEMORY_LIMIT" == "-1" ]] || [[ "${MEMORY_LIMIT//[^0-9]/}" -gt 1024 ]]; then
        echo "3. 限制 PHP 内存"
        echo "   编辑 php.ini："
        echo "   memory_limit = 512M"
        echo ""
    fi

    echo "修复后重启服务："
    echo "   php start.php stop"
    echo "   php start.php start -d"
fi

echo ""
echo "======================================================================"
