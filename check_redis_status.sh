#!/bin/bash
# Redis 服务状态诊断脚本

echo "====================================="
echo "Redis 服务状态诊断"
echo "====================================="
echo ""

# 1. 检查 Redis 进程
echo "1. 检查 Redis 进程是否运行..."
if ps aux | grep -v grep | grep redis-server > /dev/null; then
    echo "✅ Redis 进程正在运行"
    ps aux | grep redis-server | grep -v grep
else
    echo "❌ Redis 进程未运行！"
    echo "   请执行: systemctl start redis 或 service redis-server start"
fi
echo ""

# 2. 检查 Redis 端口
echo "2. 检查 Redis 端口 6379 是否监听..."
if netstat -tlnp 2>/dev/null | grep :6379 > /dev/null || ss -tlnp 2>/dev/null | grep :6379 > /dev/null; then
    echo "✅ Redis 端口 6379 正在监听"
    netstat -tlnp 2>/dev/null | grep :6379 || ss -tlnp 2>/dev/null | grep :6379
else
    echo "❌ Redis 端口 6379 未监听！"
fi
echo ""

# 3. 测试 Redis 连接
echo "3. 测试 Redis 连接..."
if redis-cli -h 127.0.0.1 -p 6379 ping 2>/dev/null | grep -q PONG; then
    echo "✅ Redis 连接成功 (PONG)"
else
    echo "❌ Redis 连接失败！"
    echo "   请检查 Redis 配置: /etc/redis/redis.conf"
    echo "   确保 bind 127.0.0.1 未被注释"
fi
echo ""

# 4. 检查 Redis 队列
echo "4. 检查 Redis 队列长度..."
QUEUE_LENGTH=$(redis-cli -h 127.0.0.1 -p 6379 llen ex-admin-grid-export 2>/dev/null)
if [ -n "$QUEUE_LENGTH" ]; then
    echo "✅ 队列 'ex-admin-grid-export' 长度: $QUEUE_LENGTH"
    if [ "$QUEUE_LENGTH" -gt 100 ]; then
        echo "⚠️  警告：队列堆积过多（$QUEUE_LENGTH），可能有任务阻塞"
    fi
else
    echo "⚠️  无法查询队列信息"
fi
echo ""

# 5. 检查 Webman 队列消费者进程
echo "5. 检查 Webman 队列消费者进程..."
if ps aux | grep -v grep | grep ex_admin_consumer > /dev/null; then
    echo "✅ 队列消费者进程正在运行"
    ps aux | grep ex_admin_consumer | grep -v grep
else
    echo "❌ 队列消费者进程未运行！"
    echo "   请重启 Webman: php start.php restart"
fi
echo ""

# 6. 检查最近的错误日志
echo "6. 检查最近的 Redis 错误（最近 10 条）..."
if [ -f runtime/logs/workerman.log ]; then
    echo "--- Redis 相关错误 ---"
    grep -i "redis.*error\|redis.*exception\|redis.*timeout" runtime/logs/workerman.log | tail -10
    echo ""
else
    echo "⚠️  日志文件不存在: runtime/logs/workerman.log"
fi

echo "====================================="
echo "诊断完成"
echo "====================================="
