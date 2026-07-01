# Redis 队列超时问题诊断指南

## 错误日志分析

```
2026-05-07 11:31:35 worker[plugin.rockys.ex-admin-webman.ex_admin_consumer:165358] exit with status 64000
2026-05-07 11:41:37 RuntimeException: Workerman Redis Wait Timeout (600 seconds)
```

**问题根源：**
1. ExAdmin 消费者进程崩溃（状态码 64000）
2. Redis 队列等待超时（600秒）
3. 进程无法正常处理队列任务

---

## 诊断步骤

### 1. 检查 Redis 服务状态

```bash
# 检查 Redis 是否运行
redis-cli ping
# 期望输出: PONG

# 检查 Redis 连接数
redis-cli info clients
# 查看 connected_clients 数量

# 检查 Redis 内存使用
redis-cli info memory
# 查看 used_memory_human

# 检查队列长度
redis-cli -n 0 llen "{redis-queue}-default-queue"
redis-cli -n 0 llen "{redis-queue}-default-delay"
```

### 2. 检查进程状态

```bash
# 在生产服务器上执行
cd /www/wwwroot/admin.supergames9.com

# 查看进程状态
php start.php status

# 查看消费者进程（应该有 2 个）
ps aux | grep ex_admin_consumer

# 查看进程内存占用
ps aux | grep webman | sort -k 4 -r | head -20
```

### 3. 查看错误日志

```bash
# 查看最近的错误
tail -100 runtime/logs/webman.log | grep -i "error\|exception\|timeout"

# 查看消费者相关错误
tail -100 runtime/logs/webman.log | grep -i "consumer\|queue"

# 查看内存相关错误
tail -100 runtime/logs/webman.log | grep -i "memory\|fatal"
```

### 4. 检查队列任务堆积

```bash
# 连接 Redis
redis-cli -n 0

# 查看所有队列键
KEYS "*redis-queue*"

# 查看队列长度
LLEN "{redis-queue}-default-queue"
LLEN "{redis-queue}-default-delay"
LLEN "{redis-queue}-default-failed"

# 查看失败任务
LRANGE "{redis-queue}-default-failed" 0 10
```

---

## 临时修复方案

### 方案 1：重启服务（立即生效）

```bash
cd /www/wwwroot/admin.supergames9.com

# 重启 Webman（包括所有进程）
php start.php restart

# 验证进程启动成功
php start.php status

# 检查消费者进程
ps aux | grep ex_admin_consumer
# 应该看到 2 个消费者进程
```

### 方案 2：清理堆积队列（谨慎使用）

```bash
# ⚠️ 注意：这会删除所有未处理的队列任务！

# 连接 Redis
redis-cli -n 0

# 删除堆积的队列（确认后执行）
DEL "{redis-queue}-default-queue"
DEL "{redis-queue}-default-delay"

# 重启服务
cd /www/wwwroot/admin.supergames9.com
php start.php restart
```

### 方案 3：增加进程数（如果任务量大）

编辑 `config/plugin/rockys/ex-admin-webman/process.php`：

```php
return [
    'ex_admin_consumer' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'count' => 4, // 从 2 增加到 4
        'constructor' => [
            'consumer_dir' => base_path() . '/addons/webman/grid/Jobs'
        ]
    ]
];
```

然后重启：
```bash
php start.php restart
```

---

## 永久修复方案

已创建修复文件：
- `REDIS_QUEUE_FIX.md` - 包含配置优化方案
- 需要修改 Redis 超时配置
- 需要优化队列任务代码

---

## 监控建议

### 1. 设置进程监控

使用 Supervisor 监控进程自动重启：

```ini
# /etc/supervisor/conf.d/webman.conf
[program:webman]
command=php /www/wwwroot/admin.supergames9.com/start.php start
directory=/www/wwwroot/admin.supergames9.com
autostart=true
autorestart=true
startretries=3
user=www-data
redirect_stderr=true
stdout_logfile=/www/wwwroot/admin.supergames9.com/runtime/logs/supervisor.log
```

### 2. 添加队列监控脚本

```bash
#!/bin/bash
# /usr/local/bin/check_redis_queue.sh

QUEUE_LENGTH=$(redis-cli -n 0 LLEN "{redis-queue}-default-queue")

if [ $QUEUE_LENGTH -gt 1000 ]; then
    echo "警告：Redis 队列堆积 $QUEUE_LENGTH 个任务！" | mail -s "Redis 队列告警" admin@example.com
fi
```

### 3. 添加 Cron 定时检查

```cron
# 每 5 分钟检查一次队列长度
*/5 * * * * /usr/local/bin/check_redis_queue.sh
```

---

## 常见问题排查

### Q1: 消费者进程不断崩溃

**原因：** 
- 队列任务代码有 Bug，导致内存溢出
- 数据库查询未释放连接

**解决：**
```bash
# 查看具体错误
tail -f runtime/logs/webman.log

# 检查最近执行的任务
redis-cli -n 0 LRANGE "{redis-queue}-default-failed" 0 10
```

### Q2: 队列任务处理缓慢

**原因：**
- 消费者进程数太少
- 任务逻辑复杂，耗时长

**解决：**
- 增加 `count` 参数（消费者进程数）
- 优化任务代码，避免阻塞操作

### Q3: Redis 连接频繁超时

**原因：**
- `timeout` 配置太短（2.5秒）
- Redis 服务器性能问题

**解决：**
- 增加 timeout 配置（见 `REDIS_QUEUE_FIX.md`）
- 升级 Redis 服务器配置

---

## 相关文件

- `config/redis.php` - Redis 基本配置
- `config/plugin/webman/redis-queue/redis.php` - 队列 Redis 配置
- `config/plugin/rockys/ex-admin-webman/process.php` - 消费者进程配置
- `addons/webman/grid/Jobs/` - 队列任务代码目录

---

## 生产环境快速修复清单

**立即执行：**
```bash
# 1. 检查 Redis 状态
redis-cli ping

# 2. 重启 Webman 服务
cd /www/wwwroot/admin.supergames9.com
php start.php restart

# 3. 验证进程启动
php start.php status
ps aux | grep ex_admin_consumer

# 4. 实时监控日志
tail -f runtime/logs/webman.log
```

**如果问题持续：**
1. 检查 `REDIS_QUEUE_FIX.md` 中的配置优化方案
2. 联系数据库管理员检查 Redis 服务器
3. 分析队列任务代码，寻找性能瓶颈
