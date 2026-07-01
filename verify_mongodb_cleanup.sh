#!/bin/bash
# MongoDB 清理验证脚本
# 用于检查 MongoDB 相关代码是否已完全清理

echo "=========================================="
echo "MongoDB 清理验证脚本"
echo "=========================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查计数器
ERRORS=0
WARNINGS=0

# 1. 检查 MongoDB 模型文件
echo "1. 检查 MongoDB 模型文件..."
if [ -d "addons/webman/model/mongo" ]; then
    echo -e "${RED}✗ MongoDB 模型目录仍然存在${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ MongoDB 模型目录已删除${NC}"
fi

# 2. 检查 MongoDB 控制器
echo ""
echo "2. 检查 MongoDB 控制器..."
MONGO_CONTROLLERS=(
    "addons/webman/controller/MachineOperationLogController.php"
    "addons/webman/controller/MachineReceiveLogController.php"
    "addons/webman/controller/ChannelMachineOperationLogController.php"
    "addons/webman/controller/LotteryAddLogController.php"
)

for controller in "${MONGO_CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        echo -e "${RED}✗ $controller 仍然存在${NC}"
        ERRORS=$((ERRORS+1))
    else
        echo -e "${GREEN}✓ $controller 已删除${NC}"
    fi
done

# 3. 检查 vendor 目录
echo ""
echo "3. 检查 vendor 目录..."
if [ -d "vendor/jenssegers/mongodb" ]; then
    echo -e "${RED}✗ vendor/jenssegers/mongodb 仍然存在${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ vendor/jenssegers/mongodb 已删除${NC}"
fi

if [ -d "vendor/mongodb" ]; then
    echo -e "${RED}✗ vendor/mongodb 仍然存在${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ vendor/mongodb 已删除${NC}"
fi

# 4. 检查代码中的引用
echo ""
echo "4. 检查代码中的 MongoDB 引用..."
MONGO_REFS=$(grep -r "MachineOperationLog\|MachineReceiveLog\|LotteryPoolAddLog" \
    --include="*.php" \
    --exclude-dir=vendor \
    --exclude-dir=.claude \
    --exclude-dir=runtime \
    . 2>/dev/null | grep -v "CLAUDE.md\|TRANSLATION_PLAN.md\|SYSTEM_MODULES.md\|MONGODB_CLEANUP_SUMMARY.md" || true)

if [ -n "$MONGO_REFS" ]; then
    echo -e "${RED}✗ 发现 MongoDB 模型引用:${NC}"
    echo "$MONGO_REFS"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ 代码中无 MongoDB 模型引用${NC}"
fi

# 5. 检查 helpers.php 中的函数
echo ""
echo "5. 检查 helpers.php 中的 MongoDB 函数..."
if grep -q "function saveMachineOperationLog\|function saveMachineReceiveLog" addons/webman/helpers.php 2>/dev/null; then
    echo -e "${RED}✗ helpers.php 中仍有 MongoDB 日志函数${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ helpers.php 中的 MongoDB 函数已删除${NC}"
fi

# 6. 检查 composer.json
echo ""
echo "6. 检查 composer.json..."
if grep -q "jenssegers/mongodb\|mongodb/mongodb" composer.json 2>/dev/null; then
    echo -e "${RED}✗ composer.json 中仍有 MongoDB 依赖${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ composer.json 中无 MongoDB 依赖${NC}"
fi

# 7. 检查已安装的包
echo ""
echo "7. 检查已安装的 Composer 包..."
if composer show 2>/dev/null | grep -q "jenssegers/mongodb\|mongodb/mongodb"; then
    echo -e "${YELLOW}⚠ Composer 仍显示 MongoDB 包已安装（可能需要运行 composer update）${NC}"
    WARNINGS=$((WARNINGS+1))
else
    echo -e "${GREEN}✓ Composer 中无 MongoDB 包${NC}"
fi

# 8. 检查配置文件
echo ""
echo "8. 检查配置文件..."

# config/database.php
if grep -q "'mongodb'" config/database.php 2>/dev/null; then
    echo -e "${RED}✗ config/database.php 中仍有 mongodb 配置${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ config/database.php 中无 mongodb 配置${NC}"
fi

# .env.example
if grep -q "MONGODB_" .env.example 2>/dev/null; then
    echo -e "${RED}✗ .env.example 中仍有 MONGODB_ 环境变量${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ .env.example 中无 MONGODB 环境变量${NC}"
fi

# addons/webman/config/database.php
if grep -q "mongo.*MachineOperationLog\|mongo.*MachineReceiveLog\|mongo.*LotteryPoolAddLog" addons/webman/config/database.php 2>/dev/null; then
    echo -e "${RED}✗ addons/webman/config/database.php 中仍有 MongoDB 模型配置${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ addons/webman/config/database.php 中无 MongoDB 模型配置${NC}"
fi

# 9. 检查权限配置
echo ""
echo "9. 检查权限配置..."

if grep -q "MachineOperationLogController\|MachineReceiveLogController\|LotteryAddLogController" config/admin_node.php 2>/dev/null; then
    echo -e "${RED}✗ config/admin_node.php 中仍有 MongoDB 控制器权限${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ config/admin_node.php 中无 MongoDB 控制器权限${NC}"
fi

if grep -q "ChannelMachineOperationLogController" config/channel_node.php 2>/dev/null; then
    echo -e "${RED}✗ config/channel_node.php 中仍有 MongoDB 控制器权限${NC}"
    ERRORS=$((ERRORS+1))
else
    echo -e "${GREEN}✓ config/channel_node.php 中无 MongoDB 控制器权限${NC}"
fi

# 总结
echo ""
echo "=========================================="
echo "验证总结"
echo "=========================================="
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ MongoDB 清理完成！所有检查通过。${NC}"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠ MongoDB 清理基本完成，但有 $WARNINGS 个警告。${NC}"
    exit 0
else
    echo -e "${RED}✗ 发现 $ERRORS 个错误和 $WARNINGS 个警告。${NC}"
    echo ""
    echo "建议执行以下操作："
    echo "1. 运行 composer update --ignore-platform-reqs 更新依赖"
    echo "2. 运行 composer dump-autoload --optimize 重新生成 autoload"
    echo "3. 检查上述错误提示，手动清理剩余的 MongoDB 引用"
    exit 1
fi