# 服务器配置更新 - 内存优化

## 需要手动修改的配置文件

### 1. config/server.php - 添加 Worker 自动重启

**文件位置：** `D:\gk_admin\config\server.php`

**在文件中找到并添加 `max_request` 配置：**

```php
<?php
return [
    'listen' => env('APP_HOST', '0.0.0.0') . ':' . env('APP_PORT', 8789),
    'transport' => 'tcp',
    'context' => [],
    'name' => 'webman',
    'count' => cpu_count() * 2,
    'user' => '',
    'group' => '',
    'reusePort' => false,
    'event_loop' => '',

    // ✅ 添加这一行：Worker 进程处理 N 个请求后自动重启（释放内存）
    'max_request' => 1000,

    'stop_timeout' => 2,
    'pid_file' => runtime_path() . '/webman.pid',
    'status_file' => runtime_path() . '/webman.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'log_file' => runtime_path() . '/logs/workerman.log',
    'max_package_size' => 10 * 1024 * 1024
];
```

**配置说明：**
- `max_request: 1000` 表示 Worker 进程处理 1000 个请求后自动重启
- **建议值：**
  - 低流量：1000-2000
  - 中流量：500-1000
  - 高流量：200-500
- **不要设置太小**（< 100）会导致频繁重启影响性能

---

### 2. .env - 添加导出历史数据时间范围配置

**文件位置：** `D:\gk_admin\.env`

**在文件末尾添加：**

```env
# ========== 内存优化配置 ==========

# 导出历史数据时间范围（月）
# 默认只导出最近6个月的数据，避免内存溢出
EXPORT_HISTORY_MONTHS=6

# PHP 内存限制（如果需要调整）
# memory_limit=512M
```

---

### 3. php.ini - 优化 PHP 配置（可选）

**文件位置：** `/etc/php/8.0/cli/php.ini` 或 `/etc/php.ini`（根据系统而定）

**找到并修改以下配置：**

```ini
; ========== 内存配置 ==========
memory_limit = 512M  ; 从 128M 增加到 512M（根据实际情况调整）

; ========== 执行时间 ==========
max_execution_time = 300  ; 导出任务可能需要较长时间

; ========== 垃圾回收 ==========
zend.enable_gc = 1  ; 启用垃圾回收（默认已启用）

; ========== OPcache 优化 ==========
[opcache]
opcache.enable=1
opcache.memory_consumption=256  ; OPcache 内存（MB）
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.enable_cli=1  ; CLI 模式也启用
```

**修改后重启 PHP-FPM（如果使用）：**

```bash
# Ubuntu/Debian
systemctl restart php8.0-fpm

# CentOS
systemctl restart php-fpm
```

---

## 生产环境部署步骤

### 步骤 1：备份配置文件

```bash
cd /www/wwwroot/admin.supergames9.com

# 备份 server.php
cp config/server.php config/server.php.backup.$(date +%Y%m%d%H%M%S)

# 备份 .env
cp .env .env.backup.$(date +%Y%m%d%H%M%S)
```

### 步骤 2：同步代码到生产环境

**方法 A：使用 Git（推荐）**

```bash
# 在生产服务器上
cd /www/wwwroot/admin.supergames9.com
git pull origin master

# 或者拉取特定分支
git pull origin release/1.0.0.1
```

**方法 B：使用 FTP/SFTP**

上传以下修改后的文件：
- `addons/webman/grid/ShiftReportExporter.php`
- `addons/webman/grid/Jobs/Export.php`
- `addons/webman/Admin.php`

### 步骤 3：修改 config/server.php

```bash
# 编辑文件
nano config/server.php

# 添加这一行（在 'event_loop' 后面）：
#     'max_request' => 1000,

# 保存并退出（Ctrl+O, Enter, Ctrl+X）
```

### 步骤 4：修改 .env

```bash
# 编辑文件
nano .env

# 在文件末尾添加：
# EXPORT_HISTORY_MONTHS=6

# 保存并退出
```

### 步骤 5：重启 Webman 服务

```bash
# 停止服务
php start.php stop

# 等待 2 秒
sleep 2

# 启动服务（daemon 模式）
php start.php start -d

# 验证进程状态
php start.php status
```

### 步骤 6：验证修复效果

```bash
# 1. 检查进程是否正常启动
ps aux | grep webman | grep -v grep

# 2. 检查内存使用
ps aux | grep webman | awk '{sum+=$4} END {print "Webman 总内存占用: " sum "%"}'

# 3. 测试导出功能
# 在后台执行一次交班记录导出

# 4. 监控日志
tail -f runtime/logs/webman.log | grep -i "memory\|export"
```

---

## 监控内存使用

### 实时监控脚本

**创建监控脚本：** `monitor_memory_realtime.sh`

```bash
#!/bin/bash
# 实时监控 Webman 内存使用

echo "实时监控 Webman 内存使用（每5秒刷新）"
echo "按 Ctrl+C 停止"
echo ""

while true; do
    clear
    echo "====================================="
    echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "====================================="
    echo ""

    # Webman 进程列表
    echo "Webman 进程内存使用:"
    ps aux | grep webman | grep -v grep | awk '{printf "  PID: %-8s MEM: %-8s CPU: %-6s CMD: %s\n", $2, $4"%", $3"%", $11}'

    echo ""

    # 总内存占用
    TOTAL_MEM=$(ps aux | grep webman | grep -v grep | awk '{sum+=$4} END {print sum}')
    echo "总内存占用: ${TOTAL_MEM}%"

    # 内存警告
    if (( $(echo "$TOTAL_MEM > 50" | bc -l) )); then
        echo "⚠️  警告：内存占用过高！"
    elif (( $(echo "$TOTAL_MEM > 30" | bc -l) )); then
        echo "⚠️  注意：内存占用较高"
    else
        echo "✅ 内存占用正常"
    fi

    echo ""
    echo "====================================="

    sleep 5
done
```

**使用：**

```bash
chmod +x monitor_memory_realtime.sh
./monitor_memory_realtime.sh
```

### 定时检查脚本

**添加到 Crontab：**

```bash
# 编辑 crontab
crontab -e

# 添加：每小时检查一次内存使用
0 * * * * cd /www/wwwroot/admin.supergames9.com && ps aux | grep webman | grep -v grep | awk '{sum+=$4} END {if(sum>50) print "$(date) - 内存占用过高: " sum "%"}' >> runtime/logs/memory_check.log
```

---

## 回滚方案

如果修复后出现问题，按以下步骤回滚：

```bash
cd /www/wwwroot/admin.supergames9.com

# 1. 恢复配置文件
cp config/server.php.backup.YYYYMMDDHHMMSS config/server.php
cp .env.backup.YYYYMMDDHHMMSS .env

# 2. 恢复代码（如果使用 Git）
git reset --hard HEAD~1

# 或者恢复单个文件
git checkout HEAD~1 -- addons/webman/grid/ShiftReportExporter.php
git checkout HEAD~1 -- addons/webman/grid/Jobs/Export.php
git checkout HEAD~1 -- addons/webman/Admin.php

# 3. 重启服务
php start.php restart

# 4. 验证
php start.php status
```

---

## 验证清单

**部署后检查：**

- [ ] config/server.php 已添加 `max_request` 配置
- [ ] .env 已添加 `EXPORT_HISTORY_MONTHS` 配置
- [ ] Webman 服务成功重启
- [ ] 所有 Worker 进程正常运行
- [ ] 消费者进程正常运行（2个）
- [ ] 导出功能正常工作
- [ ] 内存使用率低于 50%

**持续监控（24小时）：**

- [ ] 内存使用率保持稳定
- [ ] 无 OOM 错误
- [ ] 无进程崩溃
- [ ] 导出功能稳定
- [ ] 日志中有内存监控记录

---

## 预期效果

**修复前：**
- ❌ 内存持续攀升到 96%
- ❌ 每天需要重启 3-5 次
- ❌ 导出功能频繁失败
- ❌ Worker 进程异常退出

**修复后：**
- ✅ 内存稳定在 30-50%
- ✅ 无需频繁重启（可运行数周）
- ✅ 导出功能稳定
- ✅ Worker 进程稳定运行

---

**配置更新时间：** 2026-05-09  
**状态：** 等待生产环境部署  
**负责人：** 系统管理员
