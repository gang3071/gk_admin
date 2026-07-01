# 内存监控系统使用指南

**创建时间：** 2026-05-28  
**版本：** v1.0

---

## 📋 系统概述

**MemoryMonitor** 是一个专业的Webman进程内存监控系统，具备以下功能：

- ✅ **每分钟自动监控** - 实时跟踪所有Webman进程的内存使用情况
- ✅ **智能分析** - 自动计算内存增长率和趋势
- ✅ **泄漏诊断** - 自动识别异常增长并分析可能原因
- ✅ **修复验证** - 验证代码修复是否生效
- ✅ **趋势报告** - 每10分钟生成趋势分析和ASCII图表
- ✅ **紧急报告** - 检测到危险情况时自动生成详细报告

---

## 🚀 启动监控

### 方法1: 自动启动（推荐）

监控进程已注册到 `config/process.php`，随Webman服务自动启动。

```bash
# 重启Webman服务
php start.php restart

# 查看进程状态
php start.php status

# 应该看到 memory_monitor 进程
```

### 方法2: 单独启动（调试用）

如果只想临时启动监控：

```bash
# 启动监控
php -r "require __DIR__ . '/vendor/autoload.php'; \$monitor = new process\MemoryMonitor(); \$monitor->onWorkerStart(new Workerman\Worker());"
```

---

## 📊 监控输出说明

### 1. 每分钟监控报告

```
========================================
[2026-05-28 14:30:00] 内存监控报告
========================================
✅ PID:  12345 | 内存: 85.32 MB | 增长: +1.20 MB/分 | 趋势: ↑ | 状态: 正常 | 预估请求数: 45 | 平均: 0.7844 MB/请求
✅ PID:  12346 | 内存: 92.45 MB | 增长: +1.55 MB/分 | 趋势: ↑ | 状态: 正常 | 预估请求数: 48 | 平均: 0.8844 MB/请求
⚠️ PID:  12347 | 内存: 456.78 MB | 增长: +8.30 MB/分 | 趋势: ↑↑ | 状态: 警告 | 预估请求数: 120 | 平均: 3.3898 MB/请求
----------------------------------------
📊 汇总 | 进程数: 3 | 总内存: 634.55 MB | 平均: 211.52 MB
========================================
```

**字段说明：**

| 字段 | 说明 |
|------|------|
| **状态图标** | ✅ 正常 / ⚠️ 警告 / 🔴 危险 |
| **PID** | 进程ID |
| **内存** | 当前内存使用量（MB） |
| **增长** | 每分钟内存增长率（MB/分） |
| **趋势** | ↑↑↑ 快速上升 / ↑↑ 上升 / ↑ 缓慢上升 / ━ 稳定 / ↓ 下降 |
| **状态** | 正常（< 400 MB）/ 警告（400-800 MB）/ 危险（> 800 MB） |
| **预估请求数** | 估算已处理的请求数量 |
| **平均** | 平均每个请求的内存消耗 |

---

### 2. 异常增长警报

当检测到内存增长率超过 **10 MB/分钟** 时，会触发详细分析：

```
⚡ 检测到异常内存增长:
   PID 12347: 增长率 12.50 MB/分钟 (当前 456.78 MB)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔍 内存泄漏分析
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PID 12347 分析:
  当前内存: 456.78 MB
  增长率: 12.5 MB/分钟
  预估请求数: 120
  平均每请求: 3.39 MB
  可能原因:
    • 中度泄漏 - 可能存在大数组累积（whereIn大量ID）
    • 单次请求内存偏高 - 建议优化查询逻辑
  修复验证:
    ⚠️ 修复部分生效 - 单次请求内存偏高（3-5 MB）
    建议检查是否还有其他未优化的查询
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

### 3. 10分钟趋势报告

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📈 10分钟内存趋势分析报告
生成时间: 2026-05-28 14:40:00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PID 12345:
  运行时长: 10.5 分钟
  内存范围: 50.5 MB ~ 95.3 MB
  平均内存: 72.8 MB
  总增长: 44.8 MB
  平均增长率: 4.27 MB/分钟
  预估请求数: 315
  预计max_request触发: 5.2 分钟后
  趋势图:
   95 MB |        ████████████
   85 MB |      ████████████████
   75 MB |    ██████████████████
   65 MB |  ████████████████████
   55 MB |██████████████████████
   50 MB |██████████████████████
         +------------------------------------------------------------
          开始                                                    现在
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## 🎯 阈值设置

当前配置的阈值（可在 `process/MemoryMonitor.php` 中修改）：

| 阈值 | 默认值 | 说明 |
|------|--------|------|
| **WARNING_THRESHOLD** | 400 MB | 警告阈值 - 超过后标记为⚠️警告 |
| **DANGER_THRESHOLD** | 800 MB | 危险阈值 - 超过后标记为🔴危险并生成紧急报告 |
| **ABNORMAL_GROWTH_RATE** | 10 MB/分钟 | 异常增长率 - 超过后触发泄漏分析 |
| **HISTORY_RETENTION** | 120 分钟 | 历史数据保留时间 |

**修改示例：**

```php
// process/MemoryMonitor.php

// 调整为更严格的阈值
const WARNING_THRESHOLD = 300;      // 300 MB警告
const DANGER_THRESHOLD = 600;       // 600 MB危险
const ABNORMAL_GROWTH_RATE = 5;     // 5 MB/分钟异常
```

---

## 📄 日志文件

### 主日志文件

```
runtime/logs/webman.log
```

包含所有监控输出，使用以下命令实时查看：

```bash
# 实时监控
tail -f runtime/logs/webman.log | grep "内存监控"

# 只看异常
tail -f runtime/logs/webman.log | grep -E "⚠️|🔴|⚡"

# 查看趋势报告
tail -f runtime/logs/webman.log | grep "趋势分析"
```

### 紧急报告文件

当检测到危险情况时，会在以下位置生成详细报告：

```
runtime/logs/memory_emergency_YYYYMMDDHHmmss.log
```

**示例：**
```
runtime/logs/memory_emergency_20260528143000.log
```

---

## 🔍 问题诊断流程

### 场景1: 修复后验证是否生效

**步骤：**

1. **部署修复代码**
   ```bash
   git pull
   php start.php restart
   ```

2. **观察监控日志（1小时）**
   ```bash
   tail -f runtime/logs/webman.log | grep "修复验证"
   ```

3. **查看验证结果**
   ```
   ✅ 修复已生效 - 单次请求内存正常（< 3 MB）
   → 成功！继续监控即可

   ⚠️ 修复部分生效 - 单次请求内存偏高（3-5 MB）
   → 部分成功，检查是否还有其他未优化的地方

   ❌ 修复未生效或存在其他泄漏源
   → 失败，需要进一步排查
   ```

---

### 场景2: 发现新的内存泄漏

**识别方法：**

1. **检查增长率**
   ```
   增长: +12.50 MB/分 | 趋势: ↑↑↑
   ```
   超过 10 MB/分钟 = 异常

2. **检查平均每请求内存**
   ```
   平均: 8.5423 MB/请求
   ```
   超过 5 MB/请求 = 单次请求内存过高

3. **查看泄漏分析**
   ```
   🔍 内存泄漏分析
   可能原因:
     • 严重泄漏 - 可能存在全量数据加载（get()）未释放
   ```

**处理步骤：**

1. 记录异常进程的PID
2. 查看该进程最近处理的请求（检查access log）
3. 定位高内存消耗的接口
4. 使用以下工具分析代码：
   ```bash
   # 查找可能的全量加载
   grep -rn "->get()->pluck(" addons/webman/controller/

   # 查找whereIn大数组
   grep -rn "whereIn.*pluck" addons/webman/controller/
   ```

---

### 场景3: 进程频繁重启

**现象：**
```
预计max_request触发: 2.3 分钟后
```

进程很快达到max_request=100，频繁重启。

**原因分析：**
- 单次请求内存过高
- 导致100次请求后累积到峰值
- 触发max_request重启机制

**解决方案：**
1. 降低单次请求内存（优化查询）
2. 或者提高max_request值（临时方案）

---

## 📈 性能优化建议

### 根据监控数据优化

| 监控数据 | 优化建议 |
|---------|---------|
| 平均 > 5 MB/请求 | 优化数据库查询，避免全量加载 |
| 增长率 > 10 MB/分 | 检查是否有静态变量累积 |
| 趋势: ↑↑↑ | 立即排查，可能存在严重泄漏 |
| 进程数过多 | 考虑减少worker进程数 |
| 总内存过高 | 考虑增加服务器内存或横向扩展 |

### 代码优化检查清单

```php
// ❌ 避免
$data = Model::query()->get();  // 全量加载
$ids = Model::query()->pluck('id')->toArray();  // 大数组
$query->whereIn('id', $ids);  // 低效查询

// ✅ 推荐
$data = Model::query()->lazy(500);  // 惰性加载
$query->whereExists(function($q) {  // 子查询
    $q->from('table')->whereColumn(...);
});
```

---

## 🛠️ 手动监控脚本

如果需要手动监控（不使用后台进程），可以使用以下脚本：

```bash
#!/bin/bash
# monitor_memory.sh - 手动内存监控脚本

echo "=== Webman 内存监控 ==="
echo "按 Ctrl+C 停止"
echo ""

while true; do
    echo "========================================="
    echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "========================================="

    if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" ]]; then
        # Windows
        wmic process where "name='php.exe'" get ProcessId,WorkingSetSize | awk 'NR>1 && $2 {printf "PID: %-8s 内存: %.2f MB\n", $1, $2/1024/1024}'
    else
        # Linux
        ps aux | grep webman | grep -v grep | awk '{printf "PID: %-8s 内存: %.2f MB (%s%%)\n", $2, $6/1024, $4}'
    fi

    echo ""
    sleep 60
done
```

**使用方法：**

```bash
# Windows Git Bash / Linux
chmod +x monitor_memory.sh
./monitor_memory.sh

# 或者直接用bash
bash monitor_memory.sh
```

---

## 🔧 常见问题

### Q1: 监控进程没有启动？

**检查步骤：**

```bash
# 1. 确认进程配置
cat config/process.php | grep memory_monitor

# 2. 查看进程状态
php start.php status

# 3. 查看启动日志
tail -n 50 runtime/logs/webman.log | grep "内存监控"
```

如果没有看到 "内存监控进程启动"，执行：

```bash
php start.php restart
```

---

### Q2: 日志太多，如何筛选？

**推荐过滤命令：**

```bash
# 只看警告和危险
tail -f runtime/logs/webman.log | grep -E "⚠️|🔴"

# 只看汇总统计
tail -f runtime/logs/webman.log | grep "📊 汇总"

# 只看异常增长
tail -f runtime/logs/webman.log | grep "⚡ 检测到异常"

# 只看修复验证
tail -f runtime/logs/webman.log | grep "修复验证"

# 只看趋势报告
tail -f runtime/logs/webman.log | grep -A 20 "趋势分析报告"
```

---

### Q3: Windows下无法获取进程信息？

**解决方案：**

监控代码已兼容Windows，使用 `wmic` 命令。如果仍有问题：

```bash
# 测试wmic命令
wmic process where "name='php.exe'" get ProcessId,WorkingSetSize

# 如果不工作，以管理员权限运行
```

---

### Q4: 如何临时停止监控？

```bash
# 方法1: 修改config/process.php，注释掉memory_monitor
# 然后重启
php start.php restart

# 方法2: 查找并kill监控进程
ps aux | grep MemoryMonitor
kill <pid>
```

---

## 📞 支持

如果遇到问题：

1. 查看日志: `runtime/logs/webman.log`
2. 查看紧急报告: `runtime/logs/memory_emergency_*.log`
3. 检查进程状态: `php start.php status`
4. 联系开发团队并提供：
   - 监控日志片段
   - 进程状态截图
   - 服务器配置（内存、CPU、进程数）

---

## 📊 监控效果验证

**修复前后对比（预期）：**

| 指标 | 修复前 | 修复后 | 改善 |
|------|--------|--------|------|
| 平均每请求 | 10-15 MB | 1.5-2 MB | ↓ 85% |
| 增长率 | 13 MB/分 | 2 MB/分 | ↓ 85% |
| 100次请求后 | 1.38 GB | 250 MB | ↓ 82% |
| 触发max_request | 10分钟 | 50分钟 | ↑ 400% |

**成功标准：**

- ✅ 平均每请求 < 3 MB
- ✅ 增长率 < 5 MB/分钟
- ✅ 100次请求后 < 400 MB
- ✅ 趋势图稳定（━）或缓慢上升（↑）

---

**文档版本：** v1.0  
**最后更新：** 2026-05-28  
**维护者：** Claude (Staff Engineer)
