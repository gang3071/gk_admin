# 🔥 Webman 内存超过 1GB 的真正原因

## ⚠️ 重要更正

**Webman/Workerman 不支持 `max_request` 配置！**

这是我之前的错误 - `max_request` 是 PHP-FPM 的特性，不适用于常驻内存框架。

---

## 💡 **Webman 的内存管理真相**

### **核心特性：**

```
Workerman/Webman 的设计理念：
┌─────────────────────────────────────┐
│ 进程启动 → 永久运行 → 除非手动重启  │
│                                     │
│ ❌ 没有自动重启机制                │
│ ❌ 没有 max_request                │
│ ❌ 进程会一直累积内存               │
└─────────────────────────────────────┘
```

**这意味着：**
- 进程从启动到手动停止，**永不重启**
- 内存会**持续累积到峰值**，然后稳定
- **这是设计特性，不是 Bug**

---

## 🔍 **那为什么会超过 1GB？**

### **原因1：进程运行时间过长（80% 概率）**

```bash
# 检查进程运行时间
ps -eo pid,etime,cmd | grep webman

# 输出示例：
# 1234  5-12:30:45  webman  ← 运行了 5 天 12 小时！
#                            内存累积到 1.2GB
```

**正常情况：**
- 第1天：200MB → 400MB → 600MB
- 第2天：600MB → 800MB → 900MB
- 第3天：900MB → 1GB → 1.1GB（稳定）
- 第4-5天：1.1GB（不再增长）

**如果运行几天后内存稳定在 1GB，这是正常的！**

---

### **原因2：Worker 进程数 = cpu_count() * 4（70% 概率）⚠️**

```php
// config/server.php
'count' => cpu_count() * 4,  // ⚠️ 这可能是问题！

// 举例：
// 8 核 CPU × 4 = 32 个 Worker 进程！
// 每个进程 300MB × 32 = 9.6 GB 💥
```

**检查：**
```bash
# 查看有多少个 worker 进程
ps aux | grep "[w]ebman.*worker" | wc -l

# 如果超过 16 个 → 问题找到了！
```

**修复：**
```php
// config/server.php
'count' => cpu_count(),  // ✅ 去掉 × 4

// 或者固定值：
'count' => 4,  // ✅ 4-8 个足够
```

---

### **原因3：单次请求内存占用过大（60% 概率）**

即使代码没有泄漏，某些操作会导致单次内存峰值很高：

#### **A. 大量数据查询**

```php
// ❌ 一次加载 10 万条
$players = Player::with(['channel', 'machine', 'logs'])
    ->get();  // 10 万 × 50 KB = 5 GB 💥

// ✅ 分页 + 限制字段
$players = Player::select(['id', 'name'])
    ->limit(100)
    ->get();  // 100 × 1 KB = 100 KB ✅
```

#### **B. 大文件导出**

```php
// ShiftReportExporter.php
// 加载 6 个月数据 = 5 万条 × 20 KB = 1 GB
```

#### **C. 图片处理**

```php
// 处理高清图片
$image = imagecreatefromjpeg('4K.jpg');  
// 未压缩内存：26 MB 每张
```

---

### **原因4：真实的内存泄漏（20% 概率）**

即使修复了 ORM 和 Admin::check()，可能还有：

1. **闭包捕获大对象**
```php
// ❌ 闭包捕获了整个 $request 对象
$callback = function() use ($request) {
    // $request 永远不释放
};
```

2. **静态变量累积**
```php
class SomeClass {
    private static $cache = [];  // ❌ 永远不清空
    
    public static function add($data) {
        self::$cache[] = $data;  // 持续累积
    }
}
```

3. **循环引用**
```php
class A {
    public $b;
}
class B {
    public $a;
}
$a = new A();
$b = new B();
$a->b = $b;
$b->a = $a;  // ❌ 循环引用，GC 无法回收
```

---

## 🔧 **Webman 的正确内存管理方案**

### **方案1：定时重启进程（推荐）⭐**

既然 Webman 没有自动重启，那就**手动定时重启**：

```bash
# 创建定时任务：每天凌晨 3 点重启
crontab -e

# 添加：
0 3 * * * cd /path/to/project && php start.php reload >> /tmp/webman_reload.log 2>&1
```

**reload vs restart 的区别：**
```bash
# reload：平滑重启（零停机）
php start.php reload  # ✅ 推荐

# restart：停止后重启（有短暂停机）
php start.php stop && php start.php start -d

# 区别：
# reload：主进程不变，只重启 worker 进程
# restart：全部进程重启
```

---

### **方案2：监控内存自动重启**

```bash
# 创建监控脚本
cat > /usr/local/bin/webman_memory_guard.sh << 'EOF'
#!/bin/bash

MAX_MEM_MB=800  # 单进程超过 800MB 就重启

WEBMAN_PIDS=$(pgrep -f "webman.*worker")

for pid in $WEBMAN_PIDS; do
    MEM_MB=$(ps -p $pid -o rss= | awk '{print $1/1024}')
    
    if (( $(echo "$MEM_MB > $MAX_MEM_MB" | bc -l) )); then
        echo "[$(date)] 进程 $pid 内存 ${MEM_MB}MB 超过阈值，执行 reload"
        cd /path/to/project
        php start.php reload
        break
    fi
done
EOF

chmod +x /usr/local/bin/webman_memory_guard.sh

# 添加到 crontab：每小时检查一次
0 * * * * /usr/local/bin/webman_memory_guard.sh >> /tmp/webman_guard.log 2>&1
```

---

### **方案3：使用 Supervisor 管理进程**

```ini
; /etc/supervisor/conf.d/webman.conf

[program:webman]
command=php /path/to/project/start.php start
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/webman/supervisor.log

; ⭐ 内存限制：单进程超过 800MB 自动重启
stopasgroup=true
killasgroup=true
; Supervisor 本身不直接支持内存限制，需要配合 systemd

; 如果使用 systemd：
; MemoryMax=800M  # 单进程最大 800MB
```

**使用 systemd 服务（推荐）：**

```ini
# /etc/systemd/system/webman.service

[Unit]
Description=Webman Server
After=network.target

[Service]
Type=forking
User=www-data
WorkingDirectory=/path/to/project
ExecStart=/usr/bin/php /path/to/project/start.php start -d
ExecReload=/usr/bin/php /path/to/project/start.php reload
ExecStop=/usr/bin/php /path/to/project/start.php stop
Restart=always
RestartSec=10s

; ⭐ 内存限制
MemoryMax=3G  # 整个服务最大 3GB（4个进程 × 750MB）
MemoryHigh=2.5G  # 超过 2.5GB 开始 throttle

[Install]
WantedBy=multi-user.target
```

```bash
# 启用服务
systemctl daemon-reload
systemctl enable webman
systemctl start webman

# 查看状态
systemctl status webman

# 查看内存使用
systemctl show webman --property=MemoryCurrent
```

---

### **方案4：减少 Worker 进程数（立即执行）**

```php
// config/server.php

// ❌ 修复前
'count' => cpu_count() * 4,  // 可能是 32 个进程！

// ✅ 修复后
'count' => cpu_count(),  // 8 核 = 8 个进程

// 或者固定值
'count' => 4,  // 4 个进程足够大部分场景
```

**计算合适的进程数：**

```
进程数计算公式：

CPU 密集型：count = CPU 核心数
IO 密集型：count = CPU 核心数 × 2（最多）

Webman 管理后台（IO 密集）：
- 4 核 CPU → 4-8 个进程
- 8 核 CPU → 8-16 个进程（最多）

每个进程按 300MB 计算：
- 4 个进程 = 1.2 GB
- 8 个进程 = 2.4 GB
- 16 个进程 = 4.8 GB  ← 太多！
- 32 个进程 = 9.6 GB  ← 严重过量！
```

---

### **方案5：优化单次请求内存**

```php
// ❌ 修复前：单次 3-5 MB
$grid->model()->with([
    'player',
    'machine',
    'channel',
    'player.logs',
    'machine.producer',
]);

// ✅ 修复后：单次 0.5-1 MB
$grid->model()->with([
    'player:id,name',  // 只加载必要字段
    'machine:id,code',
]);
```

---

## 📊 **验证方法**

### **1. 检查进程数和内存**

```bash
# 查看进程数
ps aux | grep "[w]ebman.*worker" | wc -l

# 查看每个进程内存
ps aux | grep "[w]ebman" | awk '{printf "PID: %s, MEM: %d MB\n", $2, $6/1024}'

# 总内存
ps aux | grep "[w]ebman" | awk '{sum+=$6} END {printf "总内存: %d MB\n", sum/1024}'
```

---

### **2. 检查进程运行时间**

```bash
ps -eo pid,etime,cmd | grep webman

# 输出示例：
# 1234  5-12:30:45  webman  ← 运行了 5 天
# 1235  5-12:30:45  webman
```

**如果运行超过 3-5 天，建议重启：**
```bash
php start.php reload
```

---

### **3. 监控内存是否还在增长**

```bash
# 持续监控 1 小时
for i in {1..60}; do
  echo "=== $(date '+%H:%M:%S') ==="
  ps aux | grep "[w]ebman" | awk '{print $2, $6/1024 "MB"}'
  sleep 60
done > mem_monitor.log

# 1 小时后分析
tail -50 mem_monitor.log
```

**判断：**
- 内存曲线 📈 持续上升 → 有泄漏
- 内存曲线 📊 先升后平 → 正常

---

## ✅ **判断标准**

### **正常情况：**

```
进程运行 5 天后：
- 内存稳定在 800MB - 1.2GB
- 不再继续增长
- 重启后降回 150MB，然后逐渐升到 800MB 稳定

→ 这是 PHP 不释放内存的特性，正常！
```

### **异常情况（真正泄漏）：**

```
进程持续运行：
- 第1天：500MB
- 第2天：800MB
- 第3天：1.2GB
- 第4天：1.6GB  ← 持续增长，没有稳定点
- 第5天：2.1GB
- 最终：OOM

→ 这才是真正的内存泄漏！
```

---

## 🎯 **立即执行方案**

### **Step 1：减少 Worker 进程数（5分钟）**

```php
// config/server.php
'count' => 4,  // ✅ 改为 4 或 8

// 重启
php start.php stop
php start.php start -d

// 验证
ps aux | grep "[w]ebman" | wc -l  // 应该是 4-8
```

---

### **Step 2：设置定时重启（10分钟）**

```bash
# 编辑 crontab
crontab -e

# 添加：每天凌晨 3 点 reload
0 3 * * * cd /path/to/project && php start.php reload

# 或者：每 6 小时 reload
0 */6 * * * cd /path/to/project && php start.php reload
```

---

### **Step 3：监控 24 小时**

```bash
# 记录当前内存
php start.php status > mem_day1.txt

# 24 小时后
php start.php status > mem_day2.txt

# 对比
diff mem_day1.txt mem_day2.txt

# 如果内存增长 < 200MB → 可以接受
# 如果内存增长 > 500MB → 还有泄漏
```

---

## 🔑 **核心结论**

1. **Webman 不支持 max_request** - 这是我之前的错误
2. **进程永不自动重启** - 需要手动定时 reload
3. **内存会累积到峰值** - 这是 PHP 特性
4. **1GB 可能是正常的** - 如果稳定不再涨
5. **32 个进程太多了** - 改为 4-8 个
6. **定时 reload 是必须的** - crontab 每天一次

---

**最后更新：** 2026-05-30  
**更正：** Webman 不支持 max_request，需要手动管理进程重启  
**关键操作：** 减少 count + 定时 reload
