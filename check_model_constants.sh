#!/bin/bash
# 检查两个项目的模型常量是否一致

ADMIN_DIR="D:/gk_admin/addons/webman/model"
API_DIR="D:/gk_api/app/model"

echo "=== 检查模型常量一致性 ==="
echo ""

# 重点检查的模型
MODELS=(
    "Player.php"
    "PlayerRechargeRecord.php"
    "PlayerWithdrawRecord.php"
    "AdminUser.php"
    "Channel.php"
    "Machine.php"
    "LotteryTicket.php"
    "LotteryTicketActivity.php"
    "LotteryTicketRecord.php"
)

for model in "${MODELS[@]}"; do
    echo "=== $model ==="

    if [ -f "$ADMIN_DIR/$model" ] && [ -f "$API_DIR/$model" ]; then
        admin_consts=$(grep "const.*=" "$ADMIN_DIR/$model" | grep -E "STATUS_|TYPE_|SOURCE_" | wc -l)
        api_consts=$(grep "const.*=" "$API_DIR/$model" | grep -E "STATUS_|TYPE_|SOURCE_" | wc -l)

        echo "  gk_admin: $admin_consts 个常量"
        echo "  gk_api: $api_consts 个常量"

        if [ $admin_consts -ne $api_consts ]; then
            echo "  ⚠️ 数量不一致"
        fi

        # 显示具体差异
        diff -u <(grep "const.*=" "$ADMIN_DIR/$model" | grep -E "STATUS_|TYPE_|SOURCE_") \
                <(grep "const.*=" "$API_DIR/$model" | grep -E "STATUS_|TYPE_|SOURCE_") 2>/dev/null || true
    else
        echo "  ❌ 模型文件不存在"
    fi
    echo ""
done
