#!/bin/bash
# 机台在线状态诊断脚本
# 用法: bash diagnose_machine_status.sh

echo "=========================================="
echo "机台在线状态诊断工具"
echo "=========================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检测当前目录
if [ ! -f ".env" ]; then
    echo -e "${RED}✗ 错误：未找到 .env 文件${NC}"
    echo "  请在 gk_admin 项目根目录运行此脚本"
    exit 1
fi

echo -e "${GREEN}✓ 当前目录：$(pwd)${NC}"
echo ""

# === 步骤1：检查 GK_WORK_API_URL 配置 ===
echo "【步骤1】检查 GK_WORK_API_URL 配置"
echo "----------------------------------------"

if grep -q "GK_WORK_API_URL" .env; then
    GK_WORK_URL=$(grep "^GK_WORK_API_URL=" .env | cut -d'=' -f2)
    echo -e "${GREEN}✓ 找到配置：GK_WORK_API_URL=$GK_WORK_URL${NC}"

    # 提取 IP 和端口
    GK_WORK_HOST=$(echo $GK_WORK_URL | sed 's|http://||' | sed 's|https://||' | cut -d':' -f1)
    GK_WORK_PORT=$(echo $GK_WORK_URL | sed 's|http://||' | sed 's|https://||' | cut -d':' -f2 | cut -d'/' -f1)

    echo "  主机: $GK_WORK_HOST"
    echo "  端口: $GK_WORK_PORT"
else
    echo -e "${RED}✗ 未找到 GK_WORK_API_URL 配置${NC}"
    echo "  请在 .env 中添加："
    echo "  GK_WORK_API_URL=http://IP:PORT"
    exit 1
fi
echo ""

# === 步骤2：测试网络连通性 ===
echo "【步骤2】测试网络连通性"
echo "----------------------------------------"

# Ping 测试
echo -n "Ping $GK_WORK_HOST ... "
if ping -c 1 -W 2 $GK_WORK_HOST &> /dev/null; then
    echo -e "${GREEN}✓ 可达${NC}"
else
    echo -e "${YELLOW}⚠ 无法 ping 通（可能禁 ping，不影响 HTTP）${NC}"
fi

# 端口测试
echo -n "端口 $GK_WORK_PORT 连通性测试 ... "
if timeout 3 bash -c "cat < /dev/null > /dev/tcp/$GK_WORK_HOST/$GK_WORK_PORT" 2>/dev/null; then
    echo -e "${GREEN}✓ 端口开放${NC}"
else
    echo -e "${RED}✗ 端口无法连接${NC}"
    echo ""
    echo -e "${YELLOW}可能的原因：${NC}"
    echo "  1. gk_work 服务未启动"
    echo "  2. 防火墙阻止"
    echo "  3. 端口配置错误"
    echo ""
    echo "建议操作："
    echo "  SSH 到 gk_work 服务器，执行："
    echo "  cd /www/wwwroot/gk_work"
    echo "  php start.php status"
    exit 1
fi
echo ""

# === 步骤3：测试 API 接口 ===
echo "【步骤3】测试 API 接口"
echo "----------------------------------------"

# 测试 Gateway 信息接口
echo -n "测试 GET /api/admin/machine/gateway-info ... "
RESPONSE=$(curl -s -w "\n%{http_code}" --connect-timeout 5 "$GK_WORK_URL/api/admin/machine/gateway-info" 2>/dev/null)
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ HTTP $HTTP_CODE${NC}"
    echo "  响应: $(echo $BODY | cut -c1-80)..."
else
    echo -e "${RED}✗ HTTP $HTTP_CODE${NC}"
    echo "  响应: $BODY"
fi
echo ""

# 测试机台在线检查接口
echo -n "测试 POST /api/admin/machine/check-online ... "
RESPONSE=$(curl -s -w "\n%{http_code}" --connect-timeout 5 \
    -X POST "$GK_WORK_URL/api/admin/machine/check-online" \
    -H "Content-Type: application/json" \
    -d '{"machine_id":1}' 2>/dev/null)
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ HTTP $HTTP_CODE${NC}"

    # 解析响应
    CODE=$(echo $BODY | grep -o '"code":[0-9]*' | cut -d':' -f2)
    if [ "$CODE" = "200" ]; then
        echo -e "  ${GREEN}✓ API 返回成功${NC}"
        echo "  响应: $BODY"
    else
        echo -e "  ${YELLOW}⚠ API 返回错误码: $CODE${NC}"
        echo "  响应: $BODY"
    fi
else
    echo -e "${RED}✗ HTTP $HTTP_CODE${NC}"
    echo "  响应: $BODY"
fi
echo ""

# === 步骤4：检查本地服务状态 ===
echo "【步骤4】检查本地服务状态"
echo "----------------------------------------"

echo -n "gk_admin Webman 进程 ... "
if pgrep -f "gk_admin.*start.php" > /dev/null; then
    echo -e "${GREEN}✓ 运行中${NC}"
    ADMIN_PID=$(pgrep -f "gk_admin.*start.php" | head -1)
    echo "  PID: $ADMIN_PID"
else
    echo -e "${RED}✗ 未运行${NC}"
fi
echo ""

# === 步骤5：检查日志 ===
echo "【步骤5】检查最近的错误日志"
echo "----------------------------------------"

if [ -f "runtime/logs/webman.log" ]; then
    echo "最近 5 条包含 'MachineApi' 的日志："
    grep -i "MachineApi" runtime/logs/webman.log | tail -5 || echo "  (无相关日志)"
else
    echo -e "${YELLOW}⚠ 日志文件不存在${NC}"
fi
echo ""

# === 总结 ===
echo "=========================================="
echo "诊断总结"
echo "=========================================="

if [ "$HTTP_CODE" = "200" ] && [ "$CODE" = "200" ]; then
    echo -e "${GREEN}✓ 所有检查通过！${NC}"
    echo ""
    echo "配置正确，API 可用。"
    echo ""
    echo "如果机台列表仍显示离线，可能原因："
    echo "  1. 机台实际未连接到 Gateway Worker"
    echo "  2. 浏览器缓存，尝试刷新页面（Ctrl+F5）"
    echo "  3. WebSocket 连接问题，检查 WS_URL 配置"
    echo ""
    echo "建议操作："
    echo "  1. 清除浏览器缓存并刷新页面"
    echo "  2. 检查 gk_work 的 Gateway Worker 状态："
    echo "     ssh gk_work_server"
    echo "     cd /www/wwwroot/gk_work"
    echo "     php start.php status | grep -i gateway"
else
    echo -e "${RED}✗ 发现问题！${NC}"
    echo ""
    echo "请根据上述检查结果修复问题。"
    echo ""
    echo "常见修复方法："
    echo ""
    echo "1. 如果 gk_work 在其他服务器，修改 .env："
    echo "   GK_WORK_API_URL=http://实际IP:8788"
    echo ""
    echo "2. 如果 gk_work 未启动，SSH 到 gk_work 服务器："
    echo "   cd /www/wwwroot/gk_work"
    echo "   php start.php start -d"
    echo ""
    echo "3. 修改配置后，重启 gk_admin："
    echo "   cd $(pwd)"
    echo "   php start.php restart"
fi

echo ""
echo "=========================================="
echo "详细文档: MACHINE_ONLINE_STATUS_FIX.md"
echo "=========================================="
