# Redis 队列超时问题 - 修复总结

## 问题描述

**错误信息：**
```
RuntimeException: Workerman Redis Wait Timeout (600 seconds)
worker[plugin.rockys.ex-admin-webman.ex_admin_consumer:165358] exit with status 64000
```

**影响范围：**
- ExAdmin 消费者进程崩溃
- 队列任务无法处理
- 后台导出、异步任务失败

---

## 已实施修复

### 1. ✅ 优化 Redis 超时配置

**文件：** `config/redis.php`

**修改内容：**
```php
'timeout' => 10,          // 2.5s → 10s
'read_timeout' => 30,     // 2.5s → 30s
```

**效果：** 避免长时间队列任务导致的超时错误

---

### 2. ✅ 优化队列重试策略

**文件：** `config/plugin/webman/redis-queue/redis.php`

**修改内容：**
```php
'max_attempts' => 3,      // 5 → 3（减少无意义重试）
'retry_seconds' => 10,    // 5s → 10s（避免立即重试）
```

**效果：** 减少队列堆积，避免雪崩效应

---

### 3. ✅ 创建监控和维护脚本

**新增文件：**

1. **`REDIS_QUEUE_DIAGNOSTIC.md`** - 诊断指南
   - 快速排查问题步骤
   - 日志分析方法
   - 临时修复方案

2. **`REDIS_QUEUE_FIX.md`** - 永久修复方案
   - 详细配置优化
   - Supervisor 监控配置
   - 队列任务代码优化建议

3. **`restart_queue.sh`** - 快速重启脚本
   - 自动检查 Redis 连接
   - 检查队列长度
   - 验证进程启动

4. **`monitor_queue.sh`** - 队列监控脚本
   - 实时监控队列状态
   - 检查进程健康
   - 错误告警

---

## 生产环境部署步骤

### 立即执行（修复当前问题）

```bash
# 1. SSH 登录生产服务器
ssh user@agent.supergames9.com

# 2. 进入项目目录
cd /www/wwwroot/admin.supergames9.com

# 3. 备份当前配置
cp config/redis.php config/redis.php.backup.$(date +%Y%m%d%H%M%S)
cp config/plugin/webman/redis-queue/redis.php config/plugin/webman/redis-queue/redis.php.backup.$(date +%Y%m%d%H%M%S)

# 4. 同步本地修复后的配置文件到服务器
# （使用 FTP/SFTP 或 Git 拉取最新代码）

# 5. 重启 Webman 服务
php start.php restart

# 6. 验证进程状态
php start.php status
ps aux | grep ex_admin_consumer

# 7. 监控日志（确认无超时错误）
tail -f runtime/logs/webman.log
```

### 后续优化（推荐）

```bash
# 1. 设置脚本权限
chmod +x restart_queue.sh
chmod +x monitor_queue.sh

# 2. 运行监控检查
./monitor_queue.sh

# 3. 安装 Supervisor（进程守护）
apt-get install supervisor

# 4. 配置 Supervisor（参考 REDIS_QUEUE_FIX.md）
nano /etc/supervisor/conf.d/webman.conf

# 5. 启动 Supervisor
supervisorctl reread
supervisorctl update
supervisorctl start all
```

---

## 验证修复效果

### 检查清单

- [ ] Redis 连接正常（`redis-cli ping` 返回 PONG）
- [ ] 消费者进程运行中（2 个进程，根据配置）
- [ ] 队列长度稳定（不持续增长）
- [ ] 无超时错误（日志中无 "Timeout" 错误）
- [ ] 无进程崩溃（无 "exit with status 64000" 错误）

### 验证命令

```bash
# 1. 检查 Redis
redis-cli ping

# 2. 检查进程
ps aux | grep ex_admin_consumer | grep -v grep

# 3. 检查队列长度
redis-cli -n 0 LLEN "{redis-queue}-default-queue"

# 4. 检查最近错误
tail -100 runtime/logs/webman.log | grep -i "timeout\|64000"

# 5. 运行监控脚本
./monitor_queue.sh
```

---

## 预期效果

**修复前：**
- ❌ 消费者进程频繁崩溃
- ❌ Redis 超时错误（每 10 分钟一次）
- ❌ 队列任务堆积
- ❌ 导出功能失败

**修复后：**
- ✅ 消费者进程稳定运行
- ✅ 无 Redis 超时错误
- ✅ 队列任务正常处理
- ✅ 导出功能正常

---

## 持续监控

### 日常监控命令

```bash
# 1. 每天检查队列状态（建议早晚各一次）
cd /www/wwwroot/admin.supergames9.com
./monitor_queue.sh

# 2. 检查队列长度
redis-cli -n 0 LLEN "{redis-queue}-default-queue"
# 正常: < 100
# 警告: 100-1000
# 严重: > 1000

# 3. 检查失败任务
redis-cli -n 0 LLEN "{redis-queue}-default-failed"
# 应该为 0 或很少

# 4. 检查进程数
ps aux | grep ex_admin_consumer | grep -v grep | wc -l
# 应该为 2（根据 process.php 配置）
```

### 告警阈值

设置以下告警（建议使用监控系统如 Zabbix、Prometheus）：

1. **队列长度 > 1000**：立即检查
2. **失败任务 > 10**：查看失败原因
3. **消费者进程 < 2**：立即重启
4. **超时错误 > 5/小时**：检查 Redis 和任务代码

---

## 常见问题处理

### Q1: 重启后问题依然存在

**检查步骤：**
```bash
# 1. 确认配置文件已更新
grep "timeout" config/redis.php
# 应该显示: 'timeout' => 10,

# 2. 确认进程使用新配置
php start.php reload  # 或 restart

# 3. 清理 OPcache（如果启用）
redis-cli FLUSHALL  # ⚠️ 谨慎使用，会清空 Redis 所有数据
```

### Q2: 队列持续堆积

**可能原因：**
- 任务处理速度 < 任务生成速度
- 任务代码有 Bug，处理失败

**解决方案：**
```bash
# 1. 增加消费者进程数
nano config/plugin/rockys/ex-admin-webman/process.php
# 修改 'count' => 4,  # 从 2 增加到 4

# 2. 重启服务
php start.php restart

# 3. 分析失败任务
redis-cli -n 0 LRANGE "{redis-queue}-default-failed" 0 10

# 4. 检查任务代码
# 查看 addons/webman/grid/Jobs/ 目录下的队列任务
```

### Q3: 进程频繁崩溃

**可能原因：**
- 内存溢出
- 任务代码有致命错误

**解决方案：**
```bash
# 1. 检查内存使用
ps aux | grep webman | sort -k 4 -r | head -10

# 2. 查看崩溃日志
tail -100 runtime/logs/webman.log | grep -i "fatal\|memory"

# 3. 增加 PHP 内存限制
nano .env
# 添加或修改: memory_limit=512M

# 4. 优化任务代码（参考 REDIS_QUEUE_FIX.md 第 5 节）
```

---

## 回滚方案

如果修复后出现新问题：

```bash
# 1. 恢复配置文件
cp config/redis.php.backup.YYYYMMDDHHMMSS config/redis.php
cp config/plugin/webman/redis-queue/redis.php.backup.YYYYMMDDHHMMSS config/plugin/webman/redis-queue/redis.php

# 2. 重启服务
php start.php restart

# 3. 通知团队
echo "已回滚 Redis 队列配置，请检查原始问题"
```

---

## 相关文档

1. **`REDIS_QUEUE_DIAGNOSTIC.md`** - 诊断指南
   - 问题排查步骤
   - 临时修复方案
   - 日志分析

2. **`REDIS_QUEUE_FIX.md`** - 永久修复方案
   - 详细配置说明
   - Supervisor 配置
   - 代码优化建议

3. **`restart_queue.sh`** - 重启脚本
   - 自动化重启流程
   - 健康检查

4. **`monitor_queue.sh`** - 监控脚本
   - 实时状态监控
   - 问题告警

---

## 技术支持

**遇到问题请按以下顺序排查：**

1. 运行监控脚本：`./monitor_queue.sh`
2. 查看诊断文档：`REDIS_QUEUE_DIAGNOSTIC.md`
3. 查看日志：`tail -100 runtime/logs/webman.log`
4. 检查 Redis：`redis-cli ping` 和 `redis-cli info`

**紧急情况联系：**
- 系统管理员
- DBA（数据库/Redis 问题）
- 开发团队（代码问题）

---

**修复时间：** 2026-05-09

**修复状态：** ✅ 配置已优化，等待生产环境部署验证

**后续计划：**
1. 部署到生产环境
2. 监控 24 小时
3. 根据效果调整参数
4. 安装 Supervisor 进程守护
