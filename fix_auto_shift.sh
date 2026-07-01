#!/bin/bash
# ============================================
# 自动交班多条记录问题一键修复脚本 (Linux/Mac)
# ============================================

set -e  # 遇到错误立即退出

echo ""
echo "========================================"
echo "  自动交班配置表修复工具"
echo "========================================"
echo ""
echo "此脚本将修复以下问题："
echo "  1. 清理重复的自动交班配置记录"
echo "  2. 修复错误的唯一索引设计"
echo "  3. 确保每个店家只有一条配置"
echo ""
echo "修复前会自动备份数据，请放心运行。"
echo ""
read -p "按 Enter 继续，或按 Ctrl+C 取消..."

# 检查 Phinx 是否可用
echo ""
echo "[检查] 检测 Phinx 迁移工具..."
if [ -f "vendor/bin/phinx" ]; then
    echo "[成功] Phinx 可用"
    USE_PHINX=true
else
    echo "[警告] Phinx 不可用，将使用 SQL 脚本方式"
    USE_PHINX=false
fi

# 方式 1: 使用 Phinx 迁移
if [ "$USE_PHINX" = true ]; then
    echo ""
    echo "========================================"
    echo "  方式 1: 使用 Phinx 迁移 (推荐)"
    echo "========================================"
    echo ""
    echo "正在执行迁移..."

    if vendor/bin/phinx migrate; then
        echo ""
        echo "[成功] Phinx 迁移执行完成！"
        MIGRATION_SUCCESS=true
    else
        echo ""
        echo "[错误] Phinx 迁移执行失败"
        echo "请查看上面的错误信息。"
        echo ""
        read -p "是否尝试使用 SQL 脚本方式修复？(y/n): " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            USE_PHINX=false
        else
            exit 1
        fi
    fi
fi

# 方式 2: 使用 SQL 脚本
if [ "$USE_PHINX" = false ]; then
    echo ""
    echo "========================================"
    echo "  方式 2: 使用 SQL 脚本"
    echo "========================================"
    echo ""
    echo "请选择执行方式："
    echo "  1. 使用 MySQL 命令行工具"
    echo "  2. 手动复制 SQL 执行"
    echo ""
    read -p "请选择 (1/2): " -n 1 -r
    echo ""

    if [[ $REPLY == "1" ]]; then
        # 使用 MySQL 命令行
        echo ""
        read -p "数据库主机 (默认: localhost): " DB_HOST
        DB_HOST=${DB_HOST:-localhost}

        read -p "数据库名 (默认: yjb_platform): " DB_NAME
        DB_NAME=${DB_NAME:-yjb_platform}

        read -p "数据库用户名 (默认: root): " DB_USER
        DB_USER=${DB_USER:-root}

        read -sp "数据库密码: " DB_PASS
        echo ""

        echo ""
        echo "正在执行 SQL 脚本..."

        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/fixes/fix_auto_shift_unique_index.sql; then
            echo "[成功] SQL 脚本执行完成！"
            MIGRATION_SUCCESS=true
        else
            echo "[错误] SQL 脚本执行失败"
            echo "请检查数据库连接信息是否正确。"
            exit 1
        fi
    else
        # 手动执行
        echo ""
        echo "========================================"
        echo "  手动执行指南"
        echo "========================================"
        echo ""
        echo "请按照以下步骤操作："
        echo ""
        echo "1. 打开 phpMyAdmin 或 Navicat"
        echo "2. 选择数据库"
        echo "3. 打开 SQL 窗口"
        echo "4. 复制以下文件的内容："
        echo "   database/fixes/fix_auto_shift_unique_index.sql"
        echo "5. 粘贴并执行"
        echo ""
        echo "文件路径: $(pwd)/database/fixes/fix_auto_shift_unique_index.sql"
        echo ""
        read -p "按 Enter 键结束..."
        exit 0
    fi
fi

# 验证修复结果
if [ "$MIGRATION_SUCCESS" = true ]; then
    echo ""
    echo "========================================"
    echo "  验证修复结果"
    echo "========================================"
    echo ""
    echo "请手动验证以下内容："
    echo ""
    echo "1. 登录店家账号"
    echo "2. 进入\"店家中心\""
    echo "3. 检查自动交班状态是否显示\"已禁用\""
    echo "4. 点击\"手动交班\"按钮"
    echo "5. 应该能够正常打开交班表单"
    echo ""
    echo "如果仍然无法手动交班，请运行以下 SQL 验证："
    echo ""
    echo "  SELECT * FROM yjb_store_auto_shift_config WHERE is_enabled = 1;"
    echo "  （应该返回空结果集，或者只包含确实需要启用自动交班的记录）"
    echo ""
fi

echo ""
echo "========================================"
echo "  修复完成"
echo "========================================"
echo ""
echo "感谢使用！"
echo ""
