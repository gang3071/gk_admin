# 精准定位内存泄漏 - 完整指南

**🎯 目标：** 从检测到问题 → 定位具体代码 → 修复验证

---

## 📋 完整监控体系

### 三层监控架构

```
┌─────────────────────────────────────────────────────────────┐
│  第1层：请求级别监控（MemoryTracker中间件）                  │
│  ────────────────────────────────────────────────────────── │
│  • 追踪每个请求的内存消耗                                     │
│  • 记录高内存请求（≥ 5 MB）的详细信息                        │
│  • 自动生成热点统计                                          │
│  • 输出：控制器名、参数、调用栈、优化建议                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  第2层：进程级别监控（MemoryMonitor进程）                     │
│  ────────────────────────────────────────────────────────── │
│  • 每分钟监控所有进程的内存使用                               │
│  • 计算增长率和趋势                                          │
│  • 检测到异常→自动关联第1层的高内存请求                      │
│  • 输出：问题进程PID、最可能的问题接口                        │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  第3层：热点分析工具（analyze_memory_hotspot.php）            │
│  ────────────────────────────────────────────────────────── │
│  • 生成接口内存消耗排行榜                                     │
│  • 显示最近的高内存请求                                       │
│  • 给出优化建议和优先级                                       │
│  • 输出：Top 20热点接口、优化建议、代码定位                   │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚀 快速开始（5分钟）

### Step 1: 启用请求级别监控

**编辑配置：** `config/middleware.php`

```php
<?php

use addons\webman\middleware\MemoryTracker;

return [
    '' => [
        // ... 其他中间件 ...
        MemoryTracker::class,  // ← 添加这一行
    ],
];
```

### Step 2: 重启服务

```bash
php start.php restart
```

### Step 3: 生成统计数据

访问几个页面（特别是数据量大的页面）：
- 店家后台首页
- 设备列表
- 统计报表

### Step 4: 查看分析报告

```bash
php analyze_memory_hotspot.php
```

**完成！** 您现在拥有完整的内存监控能力。

---

## 🔍 问题定位流程

### 场景1: 发现进程内存异常

**第1步：进程监控检测到问题**

```
[2026-05-28 14:30:00] 内存监控报告
⚠️ PID: 12347 | 内存: 456.78 MB | 增长: +8.30 MB/分 | 趋势: ↑↑
```

**第2步：自动分析并定位接口**

```
🔍 内存泄漏分析
PID 12347 分析:
  可能原因:
    • 中度泄漏 - 可能存在大数组累积

  🎯 定位问题接口:
    发现 15 个高内存请求
    🔴 ChannelIndexController::storeIndex - 8.50 MB
    ⚠️  StorePlayerController::index - 6.30 MB
    
    🎯 最可能的问题接口:
      → ChannelIndexController::storeIndex
      → 出现次数: 8
      → 建议: 立即检查此控制器的代码
      → 运行: php analyze_memory_hotspot.php 查看详细分析
```

**第3步：运行热点分析工具**

```bash
php analyze_memory_hotspot.php
```

输出：

```
📊 接口内存消耗排行榜（Top 20）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 #1  ChannelIndexController::storeIndex
    平均内存: 8.50 MB | 最大: 15.20 MB | 调用次数: 45
    💡 建议: 此接口内存消耗过高，需要优化
       → 检查是否一次性加载了过多列表数据
       → 建议使用分页或 lazy() 加载

🔍 最近的高内存请求（最近10条）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️  2026-05-28 14:25:33
   控制器: ChannelIndexController::storeIndex
   请求: GET /ex-admin/channel-index/storeIndex
   内存: 8.50 MB
```

**第4步：查看详细日志**

```bash
tail -f runtime/logs/webman.log | grep -A 20 "ChannelIndexController::storeIndex"
```

输出：

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️  高内存请求检测
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
时间: 2026-05-28 14:25:33
控制器: ChannelIndexController::storeIndex
请求: GET /ex-admin/channel-index/storeIndex
内存消耗: 8.50 MB
响应时间: 235.67 ms
请求参数: {"page": 1, "size": 20}
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

**第5步：定位到具体代码**

```bash
# 查看代码
cat addons/webman/controller/ChannelIndexController.php | sed -n '2048,2100p'
```

或在IDE中打开：
- 文件：`addons/webman/controller/ChannelIndexController.php`
- 方法：`storeIndex()`
- 行数：2050

**第6步：搜索问题模式**

```bash
# 搜索全量加载
grep -n "->get()" addons/webman/controller/ChannelIndexController.php

# 搜索大数组whereIn
grep -n "whereIn.*pluck" addons/webman/controller/ChannelIndexController.php

# 搜索循环中的查询
grep -n "foreach.*->get\|foreach.*->pluck" addons/webman/controller/ChannelIndexController.php
```

**第7步：修复代码**

根据分析建议修复（参考 MEMORY_OPTIMIZATION_GUIDE.md）

**第8步：验证修复**

```bash
# 重启服务
php start.php restart

# 访问修复的接口

# 查看日志（应该看到内存降低）
tail -f runtime/logs/webman.log | grep "ChannelIndexController::storeIndex"

# 应该看到：
[MemTrack] GET /ex-admin/channel-index/storeIndex | Controller: ChannelIndexController::storeIndex | Memory: 2.15 MB ✅
```

---

### 场景2: 主动排查潜在问题

**第1步：运行热点分析**

```bash
php analyze_memory_hotspot.php
```

**第2步：查看Top 20接口**

```
📊 接口内存消耗排行榜（Top 20）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 #1  ChannelIndexController::storeIndex
    平均内存: 8.50 MB | 最大: 15.20 MB | 调用次数: 45
    💡 建议: 此接口内存消耗过高，需要优化

⚠️  #2  StorePlayerController::index
    平均内存: 6.30 MB | 最大: 9.80 MB | 调用次数: 120
    💡 建议: 此接口内存消耗过高，需要优化

✅ #3  ChannelPlayerController::index
    平均内存: 2.15 MB | 最大: 3.20 MB | 调用次数: 89
```

**第3步：按优先级优化**

**优先级判断：**
- 🔴 **高优先级（立即处理）**: 平均 ≥ 10 MB
- ⚠️ **中优先级（尽快处理）**: 平均 7-10 MB
- 💡 **低优先级（可以排期）**: 平均 5-7 MB
- ✅ **正常**: 平均 < 5 MB

**第4步：逐个优化并验证**

---

## 📊 监控输出详解

### 1. 基础请求日志

```
[MemTrack] GET /ex-admin/channel-index/storeIndex | Controller: ChannelIndexController::storeIndex | Memory: 2.35 MB | Time: 156.23 ms
```

**字段说明：**
- `GET /ex-admin/...` - 请求方法和URL
- `Controller: XXX::method` - 控制器和方法名
- `Memory: X.XX MB` - 此请求消耗的内存
- `Time: XX.XX ms` - 响应时间

**用途：** 了解每个请求的基本性能

---

### 2. 高内存请求详情（≥ 5 MB）

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️  高内存请求检测
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
时间: 2026-05-28 14:30:00
控制器: ChannelIndexController::storeIndex
请求: GET /ex-admin/channel-index/storeIndex
内存消耗: 8.50 MB
响应时间: 235.67 ms
请求参数: {"filter": "active", "page": 1}
调用栈:
  #0 addons/webman/controller/ChannelIndexController.php:2050
  #1 vendor/webman/webman-framework/src/Route.php:156
可能原因:
  • 中度泄漏 - 可能存在大数据集加载或多次查询累积
  • 首页/列表接口 - 检查是否加载了过多数据
优化建议:
  1. 使用 lazy(500) 或 chunk(500) 替代 get()
  2. 使用 whereExists 子查询替代 whereIn 大数组
  3. 添加查询条件限制返回数据量
  4. 检查是否有N+1查询问题（使用 with() 预加载）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**用途：** 
- 定位具体的高内存接口
- 查看请求参数（已脱敏）
- 获取调用栈和代码行号
- 获得自动化的优化建议

---

### 3. 极高内存警报（≥ 10 MB）

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 极高内存请求警报！
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
时间: 2026-05-28 14:30:00
控制器: StorePlayerController::index
请求: GET /ex-admin/store-player/index
内存消耗: 15.80 MB（超过 10 MB 阈值）
响应时间: 456.78 ms

🔍 这是一个严重的内存问题！
建议立即检查此接口的代码:
  1. 查找控制器: StorePlayerController::index
  2. 检查是否有全量数据加载 (->get())
  3. 检查是否有大数组操作 (whereIn with 1000+ IDs)
  4. 检查是否有循环中的数据库查询
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📄 紧急报告已保存: runtime/logs/memory_critical_20260528143000.log
```

**用途：**
- 严重问题的即时警报
- 生成独立的紧急报告文件
- 必须立即处理

---

### 4. 进程监控自动定位

```
🔍 内存泄漏分析
PID 12347 分析:
  当前内存: 456.78 MB
  增长率: 12.5 MB/分钟
  预估请求数: 120
  平均每请求: 3.39 MB
  可能原因:
    • 中度泄漏 - 可能存在大数组累积（whereIn大量ID）

  🎯 定位问题接口:
    发现 15 个高内存请求（显示最近5个）:
      🔴 ChannelIndexController::storeIndex - 8.50 MB
      ⚠️  StorePlayerController::index - 6.30 MB
      ⚠️  ChannelIndexController::agentIndex - 5.80 MB
      ⚠️  Login::totalInfo - 5.20 MB
      ⚠️  StorePlayerController::save - 5.10 MB

    🎯 最可能的问题接口:
      → ChannelIndexController::storeIndex
      → 出现次数: 8
      → 建议: 立即检查此控制器的代码
      → 运行: php analyze_memory_hotspot.php 查看详细分析
```

**用途：**
- 进程级别监控自动关联请求级别数据
- 直接定位最可能的问题接口
- 无需手动查找，自动分析

---

### 5. 热点分析工具输出

```bash
php analyze_memory_hotspot.php
```

```
📊 接口内存消耗排行榜（Top 20）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 #1  ChannelIndexController::storeIndex
    平均内存: 8.50 MB | 最大: 15.20 MB | 调用次数: 45 | 平均时间: 256.78 ms
    💡 建议: 此接口内存消耗过高，需要优化
       → 检查是否一次性加载了过多列表数据
       → 建议使用分页或 lazy() 加载
```

**用途：**
- 全局视角查看所有接口的内存消耗
- 按平均内存排序，找出Top热点
- 获得优化建议和优先级

---

## 🎯 常见问题模式识别

### 模式1: 全量数据加载

**日志特征：**
```
内存消耗: 15.80 MB
可能原因:
  • 严重泄漏 - 可能存在全量数据加载（get()）未释放
```

**定位代码：**
```bash
grep -n "->get()" Controller.php
```

**修复方案：**
```php
// ❌ 问题代码
$data = Model::query()->get();

// ✅ 修复代码
$data = Model::query()->lazy(500);
```

---

### 模式2: 大数组whereIn

**日志特征：**
```
内存消耗: 8.50 MB
可能原因:
  • 中度泄漏 - 可能存在大数组累积（whereIn大量ID）
```

**定位代码：**
```bash
grep -n "whereIn.*pluck" Controller.php
grep -n "pluck('id')->toArray()" Controller.php
```

**修复方案：**
```php
// ❌ 问题代码
$ids = Model::query()->pluck('id')->toArray();
$query->whereIn('model_id', $ids);

// ✅ 修复代码
$query->whereExists(function($q) {
    $q->from('model')->whereColumn('model.id', 'table.model_id');
});
```

---

### 模式3: 循环中查询

**日志特征：**
```
内存消耗: 10.20 MB
响应时间: 2345.67 ms  ← 时间也很长
```

**定位代码：**
```bash
grep -n "foreach" Controller.php | grep -A 3 "->get\|->pluck\|->find"
```

**修复方案：**
```php
// ❌ 问题代码
foreach ($items as $item) {
    $related = RelatedModel::where('item_id', $item->id)->get();
    // ...
}

// ✅ 修复代码
$items = Model::with('related')->get();
foreach ($items as $item) {
    $related = $item->related;
    // ...
}
```

---

### 模式4: 导出功能未分批

**日志特征：**
```
控制器: XXXController::export
内存消耗: 25.80 MB  ← 非常高
可能原因:
  • 导出功能 - 检查是否应该使用分批导出（chunk/lazy）
```

**修复方案：**
```php
// ❌ 问题代码
$data = Model::query()->get()->toArray();
Excel::export($data);

// ✅ 修复代码
Model::query()->chunk(1000, function ($chunk) use ($excel) {
    $excel->writeChunk($chunk);
});
```

---

## 📈 优化效果验证

### 修复前

```
[MemTrack] GET /ex-admin/store-player/index | Memory: 15.80 MB | Time: 456.78 ms

⚠️  高内存请求检测
控制器: StorePlayerController::index
内存消耗: 15.80 MB
```

### 修复后

```
[MemTrack] GET /ex-admin/store-player/index | Memory: 2.15 MB | Time: 89.45 ms ✅

# 不再触发高内存警报
```

**改善：**
- 内存: 15.80 MB → 2.15 MB (↓ 86%)
- 时间: 456.78 ms → 89.45 ms (↓ 80%)

---

## 🛠️ 实用命令集合

### 监控命令

```bash
# 实时查看所有请求
tail -f runtime/logs/webman.log | grep "MemTrack"

# 只看高内存请求
tail -f runtime/logs/webman.log | grep -A 15 "高内存请求"

# 只看某个控制器
tail -f runtime/logs/webman.log | grep "ChannelIndexController"

# 热点分析
php analyze_memory_hotspot.php

# 进程监控
tail -f runtime/logs/webman.log | grep "内存监控"
```

### 分析命令

```bash
# 查找全量加载
find addons/webman/controller -name "*.php" -exec grep -l "->get()" {} \;

# 查找whereIn大数组
find addons/webman/controller -name "*.php" -exec grep -l "whereIn.*pluck" {} \;

# 查找循环中的查询
find addons/webman/controller -name "*.php" -exec grep -l "foreach.*->get" {} \;

# 查看热点统计
cat runtime/cache/memory_hotspot.json | python -m json.tool

# 查看紧急报告
ls -lht runtime/logs/memory_critical_*.log | head -n 5
```

### 清理命令

```bash
# 重置热点统计
rm runtime/cache/memory_hotspot.json

# 清理紧急报告（谨慎）
rm runtime/logs/memory_critical_*.log

# 清理日志（保留最近7天）
find runtime/logs -name "*.log" -mtime +7 -delete
```

---

## ✅ 最佳实践

### 1. 开发阶段

- ✅ 始终启用MemoryTracker中间件
- ✅ 每完成一个功能，运行 `php analyze_memory_hotspot.php`
- ✅ 确保所有接口平均内存 < 5 MB

### 2. 测试阶段

- ✅ 压力测试前启用完整监控
- ✅ 记录测试前后的内存数据
- ✅ 验证修复效果

### 3. 生产阶段

- ✅ 保持MemoryTracker启用（开销极小）
- ✅ 定期运行热点分析（每周一次）
- ✅ 监控进程内存趋势
- ✅ 设置告警阈值

### 4. 优化顺序

1. **先优化Top 5热点接口** - 80/20法则，优先处理影响最大的
2. **再处理极高内存请求** - 看紧急报告
3. **最后全面优化** - 所有接口 < 5 MB

---

## 📞 总结

### 完整工具链

| 工具 | 用途 | 何时使用 |
|------|------|---------|
| **MemoryTracker中间件** | 请求级别监控 | 始终启用 |
| **MemoryMonitor进程** | 进程级别监控 | 始终运行 |
| **analyze_memory_hotspot.php** | 热点分析 | 定期运行 |
| **日志查看** | 实时监控 | 排查问题时 |

### 定位问题的三个入口

**入口1: 从进程监控发现**
```
进程内存异常 → 自动定位接口 → 查看详细日志 → 修复代码
```

**入口2: 从热点分析发现**
```
运行分析工具 → 查看Top热点 → 查看详细日志 → 修复代码
```

**入口3: 从实时日志发现**
```
查看日志 → 发现高内存警报 → 定位代码 → 修复
```

### 关键命令

```bash
# 启用监控（只需一次）
编辑 config/middleware.php 添加 MemoryTracker::class
php start.php restart

# 日常监控
tail -f runtime/logs/webman.log | grep -E "MemTrack|高内存"

# 定期分析
php analyze_memory_hotspot.php

# 问题排查
tail -f runtime/logs/webman.log | grep "控制器名"
```

---

**准备好了吗？立即启用完整监控！** 🎯

查看详细指南：[ENABLE_MEMORY_TRACKER.md](./ENABLE_MEMORY_TRACKER.md)
