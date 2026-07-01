#!/bin/bash
# Redis 队列修复 - 快速重启脚本

echo "====================================="
echo "Redis 队列超时修复 - 重启服务"
echo "====================================="
echo ""

# 切换到项目目录
cd /www/wwwroot/admin.supergames9.com

# 1. 检查 Redis 连接
echo "[1/6] 检查 Redis 连接..."
if redis-cli ping > /dev/null 2>&1; then
    echo "✅ Redis 连接正常"
else
    echo "❌ Redis 连接失败！请检查 Redis 服务器"
    exit 1
fi

# 2. 检查队列长度
echo ""
echo "[2/6] 检查队列长度..."
QUEUE_LENGTH=$(redis-cli -n 0 LLEN "{redis-queue}-default-queue" 2>/dev/null || echo 0)
echo "当前队列长度: $QUEUE_LENGTH"

if [ $QUEUE_LENGTH -gt 5000 ]; then
    echo "⚠️  警告：队列堆积严重！建议清理队列后再重启"
    read -p "是否继续重启？(y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "已取消重启"
        exit 1
    fi
fi

# 3. 停止 Webman
echo ""
echo "[3/6] 停止 Webman 服务..."
php start.php stop
sleep 2

# 4. 清理僵尸进程
echo ""
echo "[4/6] 清理僵尸进程..."
pkill -f "webman" 2>/dev/null || true
sleep 1

# 5. 启动 Webman
echo ""
echo "[5/6] 启动 Webman 服务..."
php start.php start -d

# 等待进程启动
sleep 3

# 6. 验证进程状态
echo ""
echo "[6/6] 验证进程状态..."
php start.php status

echo ""
echo "检查消费者进程..."
CONSUMER_COUNT=$(ps aux | grep -c "ex_admin_consumer")
echo "消费者进程数: $CONSUMER_COUNT"

if [ $CONSUMER_COUNT -ge 2 ]; then
    echo "✅ 消费者进程启动成功"
else
    echo "❌ 消费者进程启动失败！"
    exit 1
fi

echo ""
echo "====================================="
echo "✅ 重启完成！"
echo "====================================="
echo ""
echo "后续监控命令："
echo "  查看实时日志: tail -f runtime/logs/webman.log"
echo "  查看进程状态: php start.php status"
echo "  查看队列长度: redis-cli -n 0 LLEN \"{redis-queue}-default-queue\""
echo ""
