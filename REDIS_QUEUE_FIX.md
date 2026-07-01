# Redis 队列超时永久修复方案

## 问题总结

**错误类型：** `RuntimeException: Workerman Redis Wait Timeout (600 seconds)`

**根本原因：**
1. Redis 连接超时配置过短（2.5秒）
2. 消费者进程崩溃后无自动重启
3. 队列任务可能存在阻塞或内存泄漏

---

## 修复方案 1：优化 Redis 配置（推荐）

### 1.1 增加 Redis 超时时间

**文件：** `config/redis.php`

**修改前：**
```php
return [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
        'timeout' => 2.5,           // ❌ 太短
        'read_timeout' => 2.5,      // ❌ 太短
        'persistent' => true,
        'retry_interval' => 100,
        // ...
    ],
];
```

**修改后：**
```php
return [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
        'timeout' => 10,            // ✅ 增加到 10 秒
        'read_timeout' => 30,       // ✅ 增加到 30 秒（队列任务可能较长）
        'persistent' => true,
        'retry_interval' => 100,
        
        'options' => [
            'prefix' => env('REDIS_PREFIX', ''),
            'parameters' => [
                'tcp_nodelay' => true,
            ],
        ],
    ],
];
```

**说明：**
- `timeout`: 连接超时时间，增加到 10 秒
- `read_timeout`: 读取超时时间，增加到 30 秒（适应长时间队列任务）

---

## 修复方案 2：优化消费者进程配置

### 2.1 调整进程数和重试策略

**文件：** `config/plugin/rockys/ex-admin-webman/process.php`

**当前配置：**
```php
return [
    'ex_admin_consumer' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'count' => 2,
        'constructor' => [
            'consumer_dir' => base_path() . '/addons/webman/grid/Jobs'
        ]
    ]
];
```

**优化建议：**

```php
return [
    'ex_admin_consumer' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'count' => 4, // ✅ 根据服务器 CPU 核心数调整（建议：核心数 × 1.5）
        'constructor' => [
            'consumer_dir' => base_path() . '/addons/webman/grid/Jobs'
        ]
    ]
];
```

**进程数选择：**
- **2 核 CPU**: count => 3
- **4 核 CPU**: count => 6
- **8 核 CPU**: count => 12
- **但不要超过 16**，避免过多进程导致 CPU 争抢

---

## 修复方案 3：优化 Redis 队列配置

### 3.1 调整重试策略

**文件：** `config/plugin/webman/redis-queue/redis.php`

**当前配置：**
```php
return [
    'default' => [
        'host' => sprintf(
            'redis://%s:%s',
            env('REDIS_HOST', '127.0.0.1'),
            env('REDIS_PORT', 6379)
        ),
        'options' => [
            'auth' => env('REDIS_PASSWORD', null),
            'db' => env('REDIS_DB', 0),
            'prefix' => '',
            'max_attempts' => 5,        // 重试 5 次
            'retry_seconds' => 5,       // 间隔 5 秒
        ]
    ],
];
```

**优化后：**
```php
return [
    'default' => [
        'host' => sprintf(
            'redis://%s:%s',
            env('REDIS_HOST', '127.0.0.1'),
            env('REDIS_PORT', 6379)
        ),
        'options' => [
            'auth' => env('REDIS_PASSWORD', null),
            'db' => env('REDIS_DB', 0),
            'prefix' => '',
            'max_attempts' => 3,        // ✅ 减少到 3 次（避免无意义重试）
            'retry_seconds' => 10,      // ✅ 增加到 10 秒（避免立即重试导致雪崩）
        ]
    ],
];
```

**说明：**
- `max_attempts`: 失败后重试次数，建议 3 次（太多会导致堆积）
- `retry_seconds`: 重试间隔，建议 10 秒（给系统恢复时间）

---

## 修复方案 4：添加进程监控（Supervisor）

### 4.1 安装 Supervisor

```bash
# Ubuntu/Debian
apt-get install supervisor

# CentOS
yum install supervisor
```

### 4.2 配置 Supervisor

**文件：** `/etc/supervisor/conf.d/webman.conf`

```ini
[program:webman]
command=php /www/wwwroot/admin.supergames9.com/start.php start
directory=/www/wwwroot/admin.supergames9.com
autostart=true
autorestart=true
startretries=3
startsecs=10
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwroot/admin.supergames9.com/runtime/logs/supervisor.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
stderr_logfile=/www/wwwroot/admin.supergames9.com/runtime/logs/supervisor_error.log
stderr_logfile_maxbytes=50MB
stderr_logfile_backups=10

[program:webman_check]
command=/usr/local/bin/webman_health_check.sh
directory=/www/wwwroot/admin.supergames9.com
autostart=true
autorestart=true
startretries=3
user=root
stdout_logfile=/var/log/supervisor/webman_check.log
```

### 4.3 创建健康检查脚本

**文件：** `/usr/local/bin/webman_health_check.sh`

```bash
#!/bin/bash
# Webman 进程健康检查脚本

PROJECT_DIR="/www/wwwroot/admin.supergames9.com"
LOG_FILE="/var/log/webman_health_check.log"
ADMIN_EMAIL="admin@example.com"

# 检查消费者进程数量
check_consumer_processes() {
    CONSUMER_COUNT=$(ps aux | grep -c "ex_admin_consumer")
    EXPECTED_COUNT=2  # 根据 process.php 中的 count 配置
    
    if [ $CONSUMER_COUNT -lt $EXPECTED_COUNT ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - 警告：消费者进程数量异常 ($CONSUMER_COUNT/$EXPECTED_COUNT)" >> $LOG_FILE
        echo "消费者进程数量异常：$CONSUMER_COUNT/$EXPECTED_COUNT" | mail -s "Webman 进程告警" $ADMIN_EMAIL
        
        # 尝试重启
        cd $PROJECT_DIR
        php start.php restart
    fi
}

# 检查 Redis 连接
check_redis_connection() {
    if ! redis-cli ping > /dev/null 2>&1; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - 错误：Redis 连接失败" >> $LOG_FILE
        echo "Redis 连接失败，请立即检查 Redis 服务器" | mail -s "Redis 连接告警" $ADMIN_EMAIL
    fi
}

# 检查队列堆积
check_queue_length() {
    QUEUE_LENGTH=$(redis-cli -n 0 LLEN "{redis-queue}-default-queue" 2>/dev/null || echo 0)
    
    if [ $QUEUE_LENGTH -gt 1000 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - 警告：队列堆积 $QUEUE_LENGTH 个任务" >> $LOG_FILE
        echo "Redis 队列堆积严重：$QUEUE_LENGTH 个任务待处理" | mail -s "队列堆积告警" $ADMIN_EMAIL
    fi
}

# 检查错误日志
check_error_log() {
    ERROR_COUNT=$(tail -100 $PROJECT_DIR/runtime/logs/webman.log | grep -c "RuntimeException\|Fatal error\|exit with status 64000")
    
    if [ $ERROR_COUNT -gt 5 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - 警告：检测到 $ERROR_COUNT 个严重错误" >> $LOG_FILE
        tail -20 $PROJECT_DIR/runtime/logs/webman.log | mail -s "Webman 错误告警" $ADMIN_EMAIL
    fi
}

# 主循环
while true; do
    check_consumer_processes
    check_redis_connection
    check_queue_length
    check_error_log
    
    # 每 5 分钟检查一次
    sleep 300
done
```

**设置权限：**
```bash
chmod +x /usr/local/bin/webman_health_check.sh
```

### 4.4 启动 Supervisor

```bash
# 重新加载配置
supervisorctl reread
supervisorctl update

# 启动监控
supervisorctl start webman
supervisorctl start webman_check

# 查看状态
supervisorctl status
```

---

## 修复方案 5：优化队列任务代码

### 5.1 检查现有队列任务

**目录：** `addons/webman/grid/Jobs/`

**需要优化的点：**

1. **避免内存泄漏：**
```php
// ❌ 错误示例
public function consume($data) {
    $players = Player::all(); // 一次性加载所有数据，内存溢出
    foreach ($players as $player) {
        // 处理...
    }
}

// ✅ 正确示例
public function consume($data) {
    Player::chunk(100, function ($players) {
        foreach ($players as $player) {
            // 处理...
        }
    });
}
```

2. **添加超时控制：**
```php
// ✅ 添加最大执行时间
public function consume($data) {
    set_time_limit(300); // 5 分钟超时
    
    try {
        // 任务逻辑
    } catch (\Exception $e) {
        Log::error('Queue task failed: ' . $e->getMessage());
        throw $e; // 重新抛出，触发重试机制
    }
}
```

3. **使用数据库事务：**
```php
public function consume($data) {
    Db::beginTransaction();
    try {
        // 数据库操作
        Db::commit();
    } catch (\Exception $e) {
        Db::rollBack();
        Log::error('Transaction failed: ' . $e->getMessage());
        throw $e;
    }
}
```

### 5.2 添加任务监控

在队列任务中添加执行时间记录：

```php
use support\Log;

class MyQueueJob {
    public function consume($data) {
        $startTime = microtime(true);
        
        try {
            // 任务逻辑
            
            $duration = microtime(true) - $startTime;
            Log::info('Queue task completed', [
                'job' => __CLASS__,
                'duration' => round($duration, 2) . 's',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            Log::error('Queue task failed', [
                'job' => __CLASS__,
                'duration' => round($duration, 2) . 's',
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}
```

---

## 修复方案 6：Redis 服务器优化

### 6.1 调整 Redis 配置

**文件：** `/etc/redis/redis.conf`（生产服务器）

```conf
# 增加最大内存
maxmemory 2gb

# 设置内存淘汰策略
maxmemory-policy allkeys-lru

# 增加最大连接数
maxclients 10000

# 启用持久化（可选）
save 900 1
save 300 10
save 60 10000

# 增加超时时间
timeout 300

# 优化性能
tcp-backlog 511
tcp-keepalive 300
```

**重启 Redis：**
```bash
systemctl restart redis
```

---

## 实施步骤

### 步骤 1：备份配置（必须）

```bash
cd /www/wwwroot/admin.supergames9.com

# 备份配置文件
cp config/redis.php config/redis.php.backup
cp config/plugin/rockys/ex-admin-webman/process.php config/plugin/rockys/ex-admin-webman/process.php.backup
cp config/plugin/webman/redis-queue/redis.php config/plugin/webman/redis-queue/redis.php.backup
```

### 步骤 2：应用修复（按顺序）

```bash
# 1. 修改 Redis 配置（修复方案 1）
nano config/redis.php
# 修改 timeout 和 read_timeout

# 2. 优化消费者进程配置（修复方案 2）
nano config/plugin/rockys/ex-admin-webman/process.php
# 调整 count 参数

# 3. 优化队列配置（修复方案 3）
nano config/plugin/webman/redis-queue/redis.php
# 调整重试策略

# 4. 重启服务
php start.php restart

# 5. 验证进程启动
php start.php status
ps aux | grep ex_admin_consumer
```

### 步骤 3：安装监控（可选但推荐）

```bash
# 安装 Supervisor（修复方案 4）
apt-get install supervisor

# 配置 Supervisor
nano /etc/supervisor/conf.d/webman.conf
# 粘贴配置内容

# 创建健康检查脚本
nano /usr/local/bin/webman_health_check.sh
# 粘贴脚本内容
chmod +x /usr/local/bin/webman_health_check.sh

# 启动 Supervisor
supervisorctl reread
supervisorctl update
supervisorctl start all
```

### 步骤 4：验证修复

```bash
# 监控日志
tail -f runtime/logs/webman.log

# 检查队列处理
redis-cli -n 0 LLEN "{redis-queue}-default-queue"

# 验证没有超时错误
tail -100 runtime/logs/webman.log | grep -i "timeout"
```

---

## 回滚方案

如果修复后出现问题：

```bash
# 恢复配置
cp config/redis.php.backup config/redis.php
cp config/plugin/rockys/ex-admin-webman/process.php.backup config/plugin/rockys/ex-admin-webman/process.php
cp config/plugin/webman/redis-queue/redis.php.backup config/plugin/webman/redis-queue/redis.php

# 重启服务
php start.php restart
```

---

## 效果验证

**修复成功的标志：**
1. ✅ 进程不再异常退出（没有 status 64000 错误）
2. ✅ 没有 Redis 超时错误
3. ✅ 队列任务正常消费（队列长度稳定）
4. ✅ 日志中没有重复错误

**监控指标：**
```bash
# 1. 检查进程稳定性（每小时检查一次）
ps aux | grep ex_admin_consumer

# 2. 检查队列长度（应该保持较低）
redis-cli -n 0 LLEN "{redis-queue}-default-queue"

# 3. 检查失败任务数（应该为 0 或很少）
redis-cli -n 0 LLEN "{redis-queue}-default-failed"

# 4. 监控错误日志（应该没有超时错误）
tail -100 runtime/logs/webman.log | grep -i "timeout\|64000"
```

---

## 总结

**关键修复点：**
1. 增加 Redis 超时配置（2.5s → 10s/30s）
2. 调整消费者进程数（根据 CPU 核心）
3. 优化重试策略（max_attempts: 3, retry_seconds: 10）
4. 添加 Supervisor 进程监控
5. 优化队列任务代码（避免内存泄漏）

**预期效果：**
- ✅ 消除 Redis 超时错误
- ✅ 进程自动重启（Supervisor）
- ✅ 队列任务稳定处理
- ✅ 系统稳定性大幅提升

**后续建议：**
1. 定期检查队列长度（设置告警阈值）
2. 分析慢任务，优化代码
3. 监控 Redis 内存使用
4. 定期清理失败任务
