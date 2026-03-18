#!/bin/bash
# ========================================
# 设备管理功能数据库迁移脚本 (Linux/Mac)
# ========================================

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo
echo "========================================"
echo "设备管理功能 - 数据库迁移"
echo "========================================"
echo

# 切换到项目根目录
cd "$(dirname "$0")/../.."
echo "当前目录: $(pwd)"
echo

# 显示菜单
show_menu() {
    echo "请选择迁移方式:"
    echo
    echo "[1] 使用 Phinx 执行迁移 (推荐)"
    echo "[2] 使用 SQL 文件执行迁移"
    echo "[3] 查看迁移状态"
    echo "[4] 回滚迁移"
    echo "[0] 退出"
    echo
}

# Phinx 迁移
phinx_migrate() {
    echo
    echo "========================================"
    echo "执行 Phinx 迁移..."
    echo "========================================"
    echo

    php vendor/bin/phinx migrate

    if [ $? -eq 0 ]; then
        echo
        echo -e "${GREEN}✅ 迁移执行成功！${NC}"
        echo
        echo "下一步:"
        echo "1. 在后台添加菜单: 设备管理"
        echo "2. 阅读文档: docs/device_management_quick_start.md"
    else
        echo
        echo -e "${RED}❌ 迁移执行失败！请检查错误信息。${NC}"
    fi
}

# SQL 迁移
sql_migrate() {
    echo
    echo "========================================"
    echo "使用 SQL 文件执行迁移"
    echo "========================================"
    echo

    read -p "MySQL 用户名: " db_user
    read -p "数据库名称: " db_name

    echo
    echo "正在执行 SQL 文件..."
    mysql -u "$db_user" -p "$db_name" < database/migrations/create_device_tables.sql

    if [ $? -eq 0 ]; then
        echo
        echo -e "${GREEN}✅ SQL 执行成功！${NC}"
        echo
        echo "下一步:"
        echo "1. 在后台添加菜单: 设备管理"
        echo "2. 阅读文档: docs/device_management_quick_start.md"
    else
        echo
        echo -e "${RED}❌ SQL 执行失败！请检查错误信息。${NC}"
    fi
}

# 查看状态
phinx_status() {
    echo
    echo "========================================"
    echo "查看迁移状态..."
    echo "========================================"
    echo

    php vendor/bin/phinx status
}

# 回滚迁移
phinx_rollback() {
    echo
    echo "========================================"
    echo "回滚迁移"
    echo "========================================"
    echo
    echo -e "${YELLOW}⚠️  警告：回滚将删除设备管理相关表和数据！${NC}"
    echo

    read -p "确认回滚? (yes/no): " confirm

    if [ "$confirm" = "yes" ]; then
        php vendor/bin/phinx rollback -t 20260316120000
        echo
        echo -e "${GREEN}✅ 回滚完成！${NC}"
    else
        echo
        echo "已取消回滚。"
    fi
}

# 主循环
while true; do
    show_menu
    read -p "请输入选项 (0-4): " choice

    case $choice in
        1)
            phinx_migrate
            break
            ;;
        2)
            sql_migrate
            break
            ;;
        3)
            phinx_status
            break
            ;;
        4)
            phinx_rollback
            break
            ;;
        0)
            echo
            echo "退出脚本。"
            break
            ;;
        *)
            echo
            echo -e "${RED}❌ 无效选项！请重新选择。${NC}"
            echo
            ;;
    esac
done

echo
echo "========================================"
echo "脚本执行完毕"
echo "========================================"
echo
