# 🔬 PHP 常驻进程内存不释放的真相

## ⚠️ 核心问题

**即使代码完全正确，PHP 常驻进程的内存也不会降低！**

这不是 Bug，这是 PHP 的设计特性。

---

## 📚 PHP 内存管理机制详解

### 1. **PHP 的内存分配策略**

```
PHP 内存管理层次：
┌─────────────────────────────────────────┐
│ Level 1: 操作系统内存                    │  ← OS 管理
│  ↓ malloc() / mmap()                    │
├─────────────────────────────────────────┤
│ Level 2: PHP 内存池 (Zend Memory Pool)  │  ← PHP 管理
│  ↓ emalloc() / efree()                  │
├─────────────────────────────────────────┤
│ Level 3: 变量存储                        │  ← 你的代码
│  $var = ...                             │
└─────────────────────────────────────────┘
```

**关键点：**
- PHP 从 OS 申请内存后，**放入自己的内存池**
- `unset($var)` 只是**归还给内存池**，不归还给 OS
- PHP 内存池会**复用**这些内存，但**不释放**给 OS

---

### 2. **为什么这样设计？**

#### **性能优化考虑：**

```php
// 场景：处理1000次请求

// 方案A（PHP 当前方案）：
for ($i = 0; $i < 1000; $i++) {
    // 第1次：向 OS 申请 10MB  → 耗时 0.5ms
    // 第2-1000次：从内存池复用 → 耗时 0.01ms
    // 总耗时：0.5 + (999 × 0.01) = 10.49ms ✅
}

// 方案B（每次归还给 OS）：
for ($i = 0; $i < 1000; $i++) {
    // 每次：申请 10MB → 使用 → 释放 → 耗时 0.5ms
    // 总耗时：1000 × 0.5 = 500ms ❌ 慢了47倍！
}
```

**结论：** 内存复用比频繁申请/释放快 **47 倍**！

---

### 3. **Webman 常驻内存的特殊性**

#### **传统 PHP-FPM：**
```
请求进入 → 启动进程 → 处理 → 关闭进程 → 内存归还OS
        ↑______________________________↑
           每次请求都是全新的进程
```

#### **Webman (常驻内存)：**
```
启动进程 → [请求1] → [请求2] → ... → [请求N] → ...
     ↑________________________________________________
                 同一个进程处理所有请求
                 内存持续累积到峰值
```

**这意味着：**
- 第1次请求：进程内存从 50MB → 200MB
- 第2次请求：复用内存，可能 200MB → 250MB
- 第3次请求：复用内存，可能 250MB → 280MB
- ...
- 第100次请求：280MB（不再增长，但也不降低）
- **峰值锁定：280MB 成为进程的"最低内存"**

---

## 🔍 **如何判断是泄漏 vs 正常不释放？**

### **真正泄漏的特征：**

```bash
# 1. 内存持续增长，没有上限
进程1: 100MB → 200MB → 400MB → 800MB → 1.6GB → 3.2GB → 💥 OOM

# 2. 处理相同数量请求，内存一直增长
100次请求: 300MB
200次请求: 600MB
300次请求: 900MB  ← 线性增长！
```

### **正常不释放的特征：**

```bash
# 1. 内存增长后稳定
进程1: 100MB → 200MB → 280MB → 280MB → 280MB → 280MB ✅

# 2. 处理更多请求，内存不再增长
100次请求: 280MB
200次请求: 280MB  ← 稳定！
300次请求: 280MB  ← 稳定！
```

---

## 🎯 **验证方法**

### **方法1：连续请求测试**

```bash
# 在生产服务器上执行

# 记录初始内存
php start.php status > mem_before.txt

# 发送100次请求
for i in {1..100}; do
  curl -s http://localhost:8789/ex-admin/channel-player/index \
    -H "Cookie: ex_admin_token=..." > /dev/null
  sleep 0.1
done

# 记录第一次后的内存
php start.php status > mem_after_100.txt

# 再发送100次请求
for i in {1..100}; do
  curl -s http://localhost:8789/ex-admin/channel-player/index \
    -H "Cookie: ex_admin_token=..." > /dev/null
  sleep 0.1
done

# 记录第二次后的内存
php start.php status > mem_after_200.txt

# 对比
diff mem_after_100.txt mem_after_200.txt
```

**判断：**
- 如果 `mem_after_100.txt` 和 `mem_after_200.txt` **内存相同** → ✅ 正常
- 如果内存**继续增长** → ❌ 有泄漏

---

### **方法2：内存峰值监控**

```bash
# 创建监控脚本
cat > /tmp/monitor_mem.sh << 'EOF'
#!/bin/bash
for i in {1..60}; do
  echo "=== $(date '+%Y-%m-%d %H:%M:%S') ==="
  php start.php status | grep webman | awk '{print $2, $7}'
  sleep 60
done
EOF

chmod +x /tmp/monitor_mem.sh

# 后台运行监控（1小时）
nohup /tmp/monitor_mem.sh > mem_monitor.log 2>&1 &

# 1小时后查看
cat mem_monitor.log
```

**判断：**
- 如果内存曲线是 **📈 持续上升** → 泄漏
- 如果内存曲线是 **📊 先升后平** → 正常

---

## 🔧 **解决方案**

### **方案1：接受现实 + 自动重启（推荐）**

**既然 PHP 不释放内存，那就让进程定期重启！**

```php
// config/server.php

'max_request' => 100,  // 每个进程处理100次请求后自动重启 ✅
```

**效果：**
```
进程1: 处理100次 → 内存280MB → 自动重启
进程1(新): 处理100次 → 内存280MB → 自动重启
进程1(新): 处理100次 → 内存280MB → 自动重启
...
内存永远稳定在 280MB ✅
```

---

### **方案2：手动触发 GC（效果有限）**

```php
// addons/webman/middleware/GarbageCollector.php

namespace addons\webman\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class GarbageCollector implements MiddlewareInterface
{
    private static $requestCount = 0;

    public function process(Request $request, callable $handler): Response
    {
        $response = $handler($request);

        // 每10次请求触发一次GC
        self::$requestCount++;
        if (self::$requestCount % 10 === 0) {
            gc_collect_cycles();
        }

        return $response;
    }
}
```

**注意：** GC 只能回收**循环引用**，无法让 PHP 归还内存给 OS！

---

### **方案3：启用 Opcache 共享内存（推荐）**

```ini
; php.ini

; Opcache 优化
opcache.enable=1
opcache.memory_consumption=256  ; Opcache 专用内存
opcache.interned_strings_buffer=16  ; 字符串缓存 ✅ 重要！
opcache.max_accelerated_files=10000

; 共享字符串池 - 减少重复字符串占用
opcache.save_comments=0
opcache.fast_shutdown=1
```

**效果：**
- 重复的字符串（如SQL、配置）共享存储
- 减少 10-20% 内存占用

---

### **方案4：降低单次请求内存（治本）**

```php
// ✅ 限制查询字段
$grid->model()->select(['id', 'name', 'status']);

// ✅ 限制关联字段
$grid->model()->with([
    'player:id,name',  // 只加载2个字段
    'machine:id,code'  // 只加载2个字段
]);

// ✅ 分页限制
$grid->model()->limit(50);  // 不要一次加载500条

// ✅ 避免 N+1
$grid->model()->with(['relation']);  // 预加载
```

**效果：**
- 单次请求从 3MB → 0.5MB
- 峰值内存从 600MB → 200MB

---

## 📊 **实际案例对比**

### **修复前（有真实泄漏）：**

```
时间    进程内存
00:00   150 MB  (启动)
01:00   400 MB  (100次请求)
02:00   800 MB  (200次请求)  ← 持续增长
03:00   1.6 GB  (300次请求)  ← 持续增长
04:00   💥 OOM
```

### **修复后（只是不释放，无泄漏）：**

```
时间    进程内存
00:00   150 MB  (启动)
01:00   320 MB  (100次请求)
02:00   320 MB  (200次请求)  ← 稳定！
03:00   320 MB  (300次请求)  ← 稳定！
04:00   320 MB  (400次请求)  ← 稳定！
...
自动重启（max_request=100）
00:00   150 MB  (新进程)
01:00   320 MB  ← 循环往复
```

---

## ✅ **最终建议**

### **立即执行：**

1. **验证是否还有真实泄漏**
   ```bash
   # 在生产执行上面的"连续请求测试"
   # 如果第二个100次请求内存不增长 → 只是不释放
   ```

2. **确保 max_request 已设置**
   ```php
   // config/server.php
   'max_request' => 100,  // 必须设置！
   ```

3. **监控峰值是否稳定**
   ```bash
   # 持续监控1小时，看内存是否稳定
   watch -n 60 'php start.php status'
   ```

### **如果内存稳定（只是不释放）：**

✅ **这是正常的！不需要再修复！**

- PHP 常驻进程本来就会"锁定"在峰值内存
- 只要不继续增长，就没问题
- `max_request=100` 会定期重启进程
- 这是 Webman/Swoole/Workerman 的正常行为

### **如果内存还在增长：**

❌ **还有真实泄漏，需要继续排查！**

执行诊断脚本找出泄漏源：
```bash
php scripts/memory_leak_detector.php
```

---

## 🎓 **深入阅读**

- [PHP Manual: Memory Management](https://www.php.net/manual/en/internals2.memory.management.php)
- [Zend Memory Manager Internals](https://www.phpinternalsbook.com/php7/memory_management/zend_memory_manager.html)
- [Why PHP doesn't release memory back to OS](https://stackoverflow.com/questions/10284364/why-doesnt-php-release-memory-when-leaving-scope)

---

**最后更新：** 2026-05-30  
**适用场景：** Webman / Swoole / Workerman 等常驻内存框架  
**核心结论：** 内存不释放是特性，不是 Bug。用 max_request 解决。
