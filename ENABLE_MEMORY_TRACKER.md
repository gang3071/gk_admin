# 启用内存追踪中间件

**功能：** 自动追踪每个请求的内存消耗，直接定位高内存接口

---

## 🎯 启用步骤

### Step 1: 注册中间件

编辑 `config/middleware.php`，添加 MemoryTracker 中间件。

**文件位置：** `D:\gk_admin\config\middleware.php`

```php
<?php

use addons\webman\middleware\MemoryTracker; // ← 新增

return [
    '' => [
        // ... 其他中间件 ...
        
        // ✅ 添加内存追踪中间件（放在最后）
        MemoryTracker::class,
    ],
];
```

**重要：** 将 `MemoryTracker` 放在中间件列表的**最后**，这样可以准确测量整个请求的内存消耗。

---

### Step 2: 重启服务

```bash
php start.php restart
```

---

### Step 3: 验证已启用

访问任意页面后，查看日志：

```bash
tail -f runtime/logs/webman.log | grep "MemTrack"
```

应该看到类似输出：

```
[MemTrack] GET /ex-admin/channel-index/storeIndex | Controller: ChannelIndexController::storeIndex | Memory: 2.35 MB | Time: 156.23 ms
[MemTrack] POST /ex-admin/store-player/index | Controller: StorePlayerController::index | Memory: 1.87 MB | Time: 89.45 ms
```

---

## 📊 自动功能

启用后，中间件会自动：

### 1. 记录所有请求（> 1 MB）

```
[MemTrack] GET /ex-admin/... | Controller: XXX::method | Memory: X.XX MB | Time: XX.XX ms
```

### 2. 详细记录高内存请求（≥ 5 MB）

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️  高内存请求检测
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
时间: 2026-05-28 14:30:00
控制器: ChannelIndexController::storeIndex
请求: GET /ex-admin/channel-index/storeIndex
内存消耗: 8.50 MB
响应时间: 235.67 ms
请求参数: {"filter": "...", "page": 1}
调用栈:
  #0 addons/webman/controller/ChannelIndexController.php:2050
  #1 vendor/webman/webman-framework/src/Route.php:156
可能原因:
  • 中度泄漏 - 可能存在大数据集加载或多次查询累积
  • 首页/列表接口 - 检查是否加载了过多数据
优化建议:
  1. 使用 lazy(500) 或 chunk(500) 替代 get()
  2. 使用 whereExists 子查询替代 whereIn 大数组
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

### 3. 紧急警报（≥ 10 MB）

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 极高内存请求警报！
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
控制器: StorePlayerController::index
内存消耗: 15.80 MB（超过 10 MB 阈值）

🔍 这是一个严重的内存问题！
建议立即检查此接口的代码:
  1. 查找控制器: StorePlayerController::index
  2. 检查是否有全量数据加载 (->get())
  3. 检查是否有大数组操作 (whereIn with 1000+ IDs)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📄 紧急报告已保存: runtime/logs/memory_critical_20260528143000.log
```

### 4. 热点统计

自动记录到：`runtime/cache/memory_hotspot.json`

可以使用分析工具查看：

```bash
php analyze_memory_hotspot.php
```

---

## 🔧 配置阈值（可选）

如果需要调整阈值，编辑 `addons/webman/middleware/MemoryTracker.php`：

```php
class MemoryTracker implements MiddlewareInterface
{
    /**
     * 高内存请求阈值 (MB)
     */
    const HIGH_MEMORY_THRESHOLD = 5;

    /**
     * 极高内存请求阈值 (MB)
     */
    const CRITICAL_MEMORY_THRESHOLD = 10;
}
```

**建议：**
- 开发环境：HIGH = 3, CRITICAL = 8
- 生产环境：HIGH = 5, CRITICAL = 10

---

## 📊 查看统计和分析

### 方法1: 运行分析工具

```bash
php analyze_memory_hotspot.php
```

**输出示例：**

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    内存热点分析工具
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📊 接口内存消耗排行榜（Top 20）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 #1  ChannelIndexController::storeIndex
    平均内存: 8.50 MB | 最大: 15.20 MB | 调用次数: 45 | 平均时间: 256.78 ms
    💡 建议: 此接口内存消耗过高，需要优化
       → 检查是否一次性加载了过多列表数据
       → 建议使用分页或 lazy() 加载

⚠️  #2  StorePlayerController::index
    平均内存: 6.30 MB | 最大: 9.80 MB | 调用次数: 120 | 平均时间: 189.45 ms
    💡 建议: 此接口内存消耗过高，需要优化

✅ #3  ChannelPlayerController::index
    平均内存: 2.15 MB | 最大: 3.20 MB | 调用次数: 89 | 平均时间: 98.34 ms
```

### 方法2: 查看实时日志

```bash
# 只看高内存请求
tail -f runtime/logs/webman.log | grep -A 10 "高内存请求"

# 只看极高内存警报
tail -f runtime/logs/webman.log | grep -A 15 "极高内存请求警报"
```

### 方法3: 查看热点统计文件

```bash
cat runtime/cache/memory_hotspot.json | python -m json.tool
```

---

## 🎯 定位问题流程

### 场景：发现内存增长异常

1. **进程监控检测到异常**
   ```
   ⚡ 检测到异常内存增长:
      PID 12347: 增长率 12.50 MB/分钟
   ```

2. **自动分析定位问题接口**
   ```
   🎯 定位问题接口:
      发现 15 个高内存请求
      🔴 ChannelIndexController::storeIndex - 8.50 MB
      ⚠️  StorePlayerController::index - 6.30 MB
      
      🎯 最可能的问题接口:
        → ChannelIndexController::storeIndex
        → 出现次数: 8
        → 建议: 立即检查此控制器的代码
   ```

3. **运行热点分析工具**
   ```bash
   php analyze_memory_hotspot.php
   ```

4. **定位到具体代码**
   ```
   控制器: ChannelIndexController::storeIndex
   文件: addons/webman/controller/ChannelIndexController.php
   行数: 2048-2188
   ```

5. **检查代码并修复**
   ```bash
   # 查看文件
   cat addons/webman/controller/ChannelIndexController.php | sed -n '2048,2188p'
   
   # 搜索问题模式
   grep -n "->get()" addons/webman/controller/ChannelIndexController.php
   grep -n "whereIn.*pluck" addons/webman/controller/ChannelIndexController.php
   ```

6. **验证修复效果**
   - 重启服务后，再次访问接口
   - 查看日志，确认内存降低
   - 运行 `php analyze_memory_hotspot.php` 查看新的统计

---

## 📈 性能影响

**内存追踪中间件的开销：**

- 每个请求增加：**< 0.1 MB**
- 响应时间增加：**< 1 ms**
- 影响：**可忽略不计**

**建议：**
- 开发环境：始终启用
- 测试环境：始终启用
- 生产环境：可选启用（开销极小，建议启用）

---

## ⚠️ 注意事项

### 1. 日志文件增长

启用后日志会增多，建议配置日志轮转：

```php
// config/log.php
'channels' => [
    'default' => [
        'handler' => RotatingFileHandler::class,
        'constructor' => [
            runtime_path() . '/logs/webman.log',
            7,  // 保留7天
            Logger::DEBUG,
        ],
    ],
],
```

### 2. 热点统计文件大小

热点统计文件自动限制在 Top 50，不会无限增长。

如需重置统计：

```bash
rm runtime/cache/memory_hotspot.json
```

### 3. 敏感信息过滤

中间件会自动过滤以下参数：
- password
- token
- secret
- key
- api_key
- access_token

这些参数在日志中显示为 `***REDACTED***`

---

## 🔧 停用中间件

如需临时停用（不推荐）：

**方法1: 注释配置**

编辑 `config/middleware.php`：

```php
return [
    '' => [
        // MemoryTracker::class,  // ← 注释掉
    ],
];
```

**方法2: 提高阈值**

编辑 `addons/webman/middleware/MemoryTracker.php`：

```php
const HIGH_MEMORY_THRESHOLD = 999;     // 设置为极高值
const CRITICAL_MEMORY_THRESHOLD = 9999;
```

---

## 📞 故障排除

### Q: 中间件未生效？

**检查：**

```bash
# 1. 确认配置文件
cat config/middleware.php | grep MemoryTracker

# 2. 确认服务已重启
php start.php status

# 3. 查看日志
tail -f runtime/logs/webman.log | grep MemTrack
```

### Q: 看不到 MemTrack 日志？

**原因：** 请求内存 < 1 MB，不会记录

**解决：** 访问一些数据量大的页面（如列表页）

### Q: 热点统计文件不存在？

**原因：** 还没有请求被处理

**解决：** 多访问几次页面后再查看

---

## 📊 与进程监控的配合

**完整的监控体系：**

```
┌─────────────────────────────────────────────────┐
│  1. MemoryTracker 中间件（请求级别）              │
│     → 追踪每个请求的内存                         │
│     → 记录高内存请求详情                         │
│     → 生成热点统计                              │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  2. MemoryMonitor 进程（进程级别）                │
│     → 监控进程总内存                             │
│     → 检测异常增长                              │
│     → 自动查找高内存请求（关联步骤1）             │
│     → 定位问题接口                              │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  3. analyze_memory_hotspot.php（分析工具）       │
│     → 生成热点排行榜                             │
│     → 显示优化建议                              │
│     → 查看紧急报告                              │
└─────────────────────────────────────────────────┘
```

**工作流程：**

1. 用户访问页面 → MemoryTracker 记录内存
2. 如果内存高 → 自动记录详细信息
3. MemoryMonitor 检测到进程内存异常 → 自动查找最近的高内存请求
4. 开发者运行 `php analyze_memory_hotspot.php` → 查看完整分析

---

## ✅ 总结

**启用后的效果：**

- ✅ 自动追踪每个请求的内存消耗
- ✅ 高内存请求自动记录详情（控制器、参数、调用栈）
- ✅ 极高内存请求触发警报并生成报告
- ✅ 自动统计热点接口（Top 50）
- ✅ 配合进程监控，直接定位问题代码
- ✅ 零配置，启用即用

**推荐配置：**

```
开发环境: 始终启用（HIGH=3, CRITICAL=8）
生产环境: 始终启用（HIGH=5, CRITICAL=10）
```

---

**准备好了吗？立即启用内存追踪！** 🚀

```bash
# 1. 编辑 config/middleware.php，添加 MemoryTracker::class
# 2. 重启服务
php start.php restart

# 3. 访问几个页面，生成统计数据

# 4. 运行分析工具
php analyze_memory_hotspot.php
```
