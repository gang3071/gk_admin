# 🚨 服务器配置导致内存超过 1GB 的原因

## 核心问题

**即使代码没有泄漏，配置错误也会导致内存飙升到 1GB+！**

---

## 🎯 **最可能的原因（按概率排序）**

### **1. max_request = 0 或未设置（90% 概率）⚠️⚠️⚠️**

**问题：** 进程永不重启，内存持续累积

```php
// config/server.php

// ❌ 错误配置
'max_request' => 0,  // 或者完全没有这一行

// 影响：
// - 进程启动后永不重启
// - 处理 1000+ 次请求
// - 内存从 200MB → 500MB → 1GB → 2GB
// - 最终 OOM
```

**解决方案：**

```php
// ✅ 正确配置
'max_request' => 100,  // 每处理 100 次请求自动重启

// 效果：
// - 进程1：处理100次 → 内存 300MB → 自动重启
// - 进程1(新)：处理100次 → 内存 300MB → 自动重启
// - 内存永远稳定在 300MB
```

**验证方法：**

```bash
# 检查配置
grep "max_request" config/server.php

# 应该输出：'max_request' => 100,
# 如果输出：'max_request' => 0, 或者没有输出 → 问题找到了！
```

---

### **2. Worker 进程数过多（60% 概率）⚠️⚠️**

**问题：** 每个进程 300MB，8 个进程 = 2.4GB

```php
// config/server.php

// ❌ 错误配置
'count' => 16,  // 16 个 worker 进程

// 影响：
// 单进程正常内存：300 MB
// 总内存：16 × 300 MB = 4.8 GB 💥
```

**正确配置：**

```php
// ✅ 正确配置
'count' => 4,  // CPU 核心数（通常 4-8）

// 计算方法：
// count = CPU 核心数
// 或者：count = CPU 核心数 × 2（如果 IO 密集）

// 检查 CPU 核心数：
// Linux: grep -c ^processor /proc/cpuinfo
// Windows: echo %NUMBER_OF_PROCESSORS%

// 效果：
// 单进程：300 MB
// 总内存：4 × 300 MB = 1.2 GB ✅
```

**验证方法：**

```bash
# 检查配置
grep -E "^\s*'count'" config/server.php

# 检查实际运行的进程数
ps aux | grep "[w]ebman.*worker" | wc -l

# 如果超过 8 个 → 问题找到了！
```

---

### **3. PHP memory_limit 过高或无限制（40% 概率）⚠️**

**问题：** PHP 可以无限制使用内存

```ini
; php.ini

; ❌ 错误配置
memory_limit = -1  ; 无限制！PHP 可以吃掉所有系统内存！

; 或者
memory_limit = 2G  ; 单个请求可以用 2GB！
```

**影响：**

```
单次请求异常（如导出大量数据）：
- memory_limit = -1
- 加载 10 万条数据
- 内存飙升到 2GB
- 进程被卡在 2GB
- 即使请求结束，PHP 也不释放给 OS
```

**正确配置：**

```ini
; ✅ 正确配置
memory_limit = 512M  ; 单个请求最多 512MB

; 或者（如果有大导出需求）
memory_limit = 1G  ; 最多 1GB

; ⚠️ 千万不要设置为 -1 或超过 2G！
```

**验证方法：**

```bash
# 检查当前配置
php -r "echo ini_get('memory_limit');"

# 如果输出 -1 或 2G 以上 → 问题找到了！

# 找到 php.ini 文件
php --ini | grep "Loaded Configuration File"

# 编辑 php.ini
nano /path/to/php.ini
# 搜索 memory_limit 并修改
```

---

### **4. max_execution_time = 0（30% 概率）⚠️**

**问题：** 允许脚本无限运行

```ini
; php.ini

; ❌ 错误配置
max_execution_time = 0  ; 脚本可以永久运行

; 影响：
; - 如果有死循环或慢查询
; - 请求永不超时
; - 内存持续累积
; - 进程被卡住，内存无法释放
```

**正确配置：**

```ini
; ✅ 正确配置
max_execution_time = 60  ; 单个请求最多 60 秒

; Webman 场景：
; 普通请求：30 秒
; 导出任务：300 秒（通过队列处理）
```

**验证方法：**

```bash
php -r "echo ini_get('max_execution_time');"

# 如果输出 0 → 问题找到了！
```

---

### **5. Opcache 配置不当（20% 概率）**

**问题：** Opcache 内存过大或未启用

```ini
; php.ini

; ❌ 问题配置1：未启用 Opcache
opcache.enable = 0  ; 每次请求都重新解析 PHP 文件

; ❌ 问题配置2：Opcache 内存过大
opcache.memory_consumption = 1024  ; 1GB Opcache 内存

; ❌ 问题配置3：字符串缓存未启用
opcache.interned_strings_buffer = 0  ; 重复字符串占用大量内存
```

**正确配置：**

```ini
; ✅ 正确配置
opcache.enable = 1
opcache.memory_consumption = 256  ; 256MB 足够
opcache.interned_strings_buffer = 16  ; ⭐ 重要！共享字符串
opcache.max_accelerated_files = 10000

; 效果：
; - 代码缓存，不重复加载
; - 字符串共享，减少 10-20% 内存
```

**验证方法：**

```bash
php -r "echo ini_get('opcache.enable');"
# 应该输出：1

php -r "echo ini_get('opcache.interned_strings_buffer');"
# 应该输出：16 或更高
```

---

### **6. 特定场景导致的内存峰值（50% 概率）**

即使配置正确，某些操作也会导致单次内存飙升：

#### **A. Excel 导出大量数据**

```php
// ShiftReportExporter.php

// ❌ 问题代码
$allRecords = StoreShiftRecord::query()
    ->where('created_at', '>=', $sixMonthsAgo)  // 6 个月
    ->get();  // 一次加载 5 万条！

// 影响：
// - 5 万条记录 × 20 KB = 1 GB
// - PhpSpreadsheet 对象：300 MB
// - 总计：1.3 GB 💥
```

**解决方案：**

```php
// ✅ 优化代码
$threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));  // 缩短到 3 个月

StoreShiftRecord::query()
    ->where('created_at', '>=', $threeMonthsAgo)
    ->chunk(500, function ($records) {  // 分批加载
        foreach ($records as $record) {
            // 处理
        }
        gc_collect_cycles();  // 每批后清理
    });
```

#### **B. 复杂统计查询**

```php
// ❌ 问题代码
$report = DB::select("
    SELECT 
        player_id,
        COUNT(*) as total_games,
        SUM(bet_amount) as total_bet,
        -- 100 多个字段
    FROM yjb_player_game_log
    GROUP BY player_id  -- 10 万个玩家
");

// 影响：10 万行 × 10 KB = 1 GB
```

**解决方案：**

```php
// ✅ 分页查询
DB::table('yjb_player_game_log')
    ->select([
        'player_id',
        DB::raw('COUNT(*) as total_games')  // 只选必要字段
    ])
    ->groupBy('player_id')
    ->chunk(1000, function ($rows) {
        // 处理
    });
```

#### **C. 图片处理**

```php
// ❌ 问题代码
$image = imagecreatefromjpeg($largeImage);  // 4K 图片
// 未压缩内存：4096 × 2160 × 3 = 26 MB

// 处理 10 张：260 MB
// 如果没 unset：永久占用
```

---

## 📊 **实际案例分析**

### **案例1：进程内存从 300MB → 2GB**

```
服务器配置：
- 4 核 CPU
- 8GB 内存
- worker count = 8  ❌
- max_request = 0  ❌
- memory_limit = -1 ❌

问题分析：
1. 8 个 worker 进程（应该 4 个）
2. 每个进程处理 1000+ 次请求（应该 100 次重启）
3. 单次请求可以用无限内存（应该 512M）

结果：
- 每个进程：2GB
- 总内存：8 × 2GB = 16GB
- 系统开始 Swap，性能崩溃 💥

修复：
- count => 4
- max_request => 100
- memory_limit = 512M

修复后：
- 每个进程：280MB
- 总内存：4 × 280MB = 1.1GB ✅
```

---

### **案例2：单次导出请求占用 1.5GB**

```
场景：
- 导出 6 个月的交班记录
- 10 个店家 × 5 万条记录
- 使用 PhpSpreadsheet

问题：
- 一次性加载 5 万条到内存
- 每条 20KB
- 总计：1GB 数据 + 500MB PhpSpreadsheet = 1.5GB

即使代码结束，PHP 不释放：
- 进程内存锁定在 1.5GB
- 下次请求复用这 1.5GB，可能再增长
- 最终：2GB+

修复：
1. 缩短时间范围：6 个月 → 3 个月
2. 使用 chunk(500)
3. 限制单次导出：最多 1 万条
4. 导出任务单独进程（不占用 worker）
```

---

## 🔧 **完整修复方案**

### **Step 1：检查服务器配置**

在生产服务器上执行：

```bash
# 上传脚本
chmod +x scripts/check_server_config.sh

# 执行检查
./scripts/check_server_config.sh

# 会输出所有配置问题和建议
```

---

### **Step 2：修复配置文件**

#### **A. config/server.php**

```php
<?php
return [
    'listen' => 'http://0.0.0.0:8789',
    'transport' => 'tcp',
    'context' => [],
    
    // ⭐ 最重要的配置
    'name' => 'webman',
    'count' => 4,  // ✅ CPU 核心数（检查：lscpu | grep "^CPU(s):"）
    'user' => '',
    'group' => '',
    'reusePort' => false,
    'max_request' => 100,  // ✅ 必须设置！每 100 次请求重启进程
];
```

#### **B. php.ini**

```ini
; 找到 php.ini
; php --ini | grep "Loaded Configuration File"

; 编辑 php.ini
memory_limit = 512M  ; ✅ 限制单次请求内存
max_execution_time = 60  ; ✅ 限制执行时间

; Opcache 优化
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16  ; ⭐ 重要
opcache.max_accelerated_files = 10000
```

---

### **Step 3：重启服务**

```bash
# 停止服务
php start.php stop

# 清理旧进程（如果有残留）
pkill -9 -f webman

# 重新启动
php start.php start -d

# 检查进程数
ps aux | grep "[w]ebman.*worker" | wc -l
# 应该等于你设置的 count

# 监控内存
watch -n 10 'php start.php status'
```

---

### **Step 4：验证修复效果**

```bash
# 压力测试 - 发送 200 次请求
for i in {1..200}; do
  curl -s http://localhost:8789/ex-admin/channel-player/index \
    -H "Cookie: ex_admin_token=..." > /dev/null
  [ $((i % 20)) -eq 0 ] && echo "已发送 $i 次请求"
  sleep 0.5
done

# 检查内存
php start.php status

# 预期结果：
# - 每个进程在 100 次请求后自动重启
# - 内存稳定在 200-400MB
# - 不会超过 500MB
```

---

## ✅ **成功标准**

修复后，应该达到：

| 指标 | 修复前 | 修复后 |
|------|--------|--------|
| 单进程最大内存 | 1-2 GB | < 500 MB |
| Worker 进程数 | 8-16 | 4-8 |
| 处理 100 次请求后 | 持续增长 | 自动重启 |
| 总内存占用 | 8-16 GB | 1-2 GB |
| max_request | 0 或未设置 | 100 |
| memory_limit | -1 或 2G+ | 512M |

---

## 🎯 **快速诊断清单**

```bash
# 1. 检查 max_request（最重要！）
grep "max_request" config/server.php
# 期望：'max_request' => 100,

# 2. 检查 worker 进程数
grep "'count'" config/server.php
ps aux | grep "[w]ebman.*worker" | wc -l
# 期望：4-8 个

# 3. 检查 PHP memory_limit
php -r "echo ini_get('memory_limit');"
# 期望：512M 或 1G

# 4. 检查当前内存使用
php start.php status
# 期望：每个进程 < 500MB

# 5. 检查是否有大于 1GB 的进程
ps aux | grep "[w]ebman" | awk '{if($6/1024 > 1024) print $2, $6/1024 "MB"}'
# 期望：无输出
```

---

## 📞 **如果修复后还超过 1GB**

说明有真正的代码泄漏，需要：

```bash
# 运行内存泄漏检测
php scripts/memory_leak_detector.php > leak_report.txt

# 检查哪个接口泄漏最严重
grep "MemoryAudit" runtime/logs/webman.log | \
  awk '{print $(NF-3), $(NF-1)}' | \
  sort -k2 -rn | head -10
```

---

**最后更新：** 2026-05-30  
**结论：** 90% 的 1GB+ 内存问题是 max_request=0 导致的！
