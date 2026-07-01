# 内存泄漏修复 - 立即执行清单

## 🚨 当前状态

**进程内存情况（来自 `php start.php status`）：**

| 进程ID | 请求次数 | 内存占用 | 状态     |
|--------|---------|---------|---------|
| 1214   | 627次   | 2020 MB | ⚠️ 危险 |
| 1212   | 477次   | 1501 MB | ⚠️ 危险 |
| 1213   | 281次   | 927 MB  | ⚠️ 警告 |
| 1210   | 53次    | 205 MB  | ✅ 正常 |

**泄漏速率：** 平均每次请求泄漏 **3.2 MB**

---

## ✅ 已完成的修复

### 1. 降低 max_request 值（止血）

**文件：** `config/server.php`

```php
'max_request' => 100,  // 从 200 降低到 100
```

**效果：** 进程最多累积 320 MB（3.2 MB × 100），然后自动重启释放内存。

---

### 2. 创建内存审计中间件

**文件：** `addons/webman/middleware/MemoryAudit.php` ✅ 已创建

**用途：** 追踪每个接口的内存泄漏量

**启用方法：** 编辑 `config/middleware.php`

```php
return [
    '' => [
        AccessControl::class,
        MemoryAudit::class,  // 👈 取消注释以启用
    ],
];
```

**⚠️ 注意：** 定位完元凶后，必须重新注释掉（影响性能约10%）

---

### 3. 创建自动检查脚本

**文件：** `scripts/check_memory_leaks.php` ✅ 已创建

**运行命令：**

```bash
php scripts/check_memory_leaks.php
```

**作用：** 自动扫描所有控制器，检测潜在的内存泄漏代码模式。

---

### 4. 修复高频泄漏接口

**文件：** `addons/webman/controller/StorePlayerGameLogController.php` ✅ 已修复

**修复内容：**

- **修复前：** 加载 5 个关联（player, machine, machineLabel, player.channel, machine_recording）
- **修复后：** 只加载必要的 4 个关联，并严格限制字段
- **预计内存降低：** 50 MB → 8 MB（每1000条记录）

**代码对比：**

```php
// ❌ 修复前
$grid->model()->with([
    'player',
    'machine' => function ($query) {
        return $query->with(['machineLabel']);
    },
    'player.channel',        // 未使用，浪费内存
    'machine_recording'      // 未使用，浪费内存
]);

// ✅ 修复后
$grid->model()->with([
    'player:id,uuid,name,department_id',           // 限制字段
    'machine:id,code,name,label_id,producer_id',  // 限制字段
    'machine.machineLabel:id,name',                // 限制字段
    'machine.producer:id,name',                    // 限制字段
]);
```

---

## 🔜 下一步行动（按顺序执行）

### 步骤 1：立即重启服务

```bash
# 使进程重启生效（应用 max_request=100 配置）
cd D:\gk_admin
php start.php reload
```

**验证：**

```bash
# 查看进程状态
php start.php status

# 确认新进程已启动，老进程内存已释放
```

---

### 步骤 2：运行内存泄漏检查脚本

```bash
php scripts/check_memory_leaks.php > leak_report.txt
```

**查看报告：**

```bash
cat leak_report.txt
```

**预期输出：** 列出所有潜在的内存泄漏点（预计30-50个）

---

### 步骤 3：启用内存审计中间件（定位高频接口）

**编辑：** `config/middleware.php`

```php
return [
    '' => [
        AccessControl::class,
        MemoryAudit::class,  // 👈 取消注释
    ],
];
```

**重启服务：**

```bash
php start.php reload
```

**监控日志：**

```bash
# 实时监控内存泄漏
tail -f runtime/logs/webman.log | grep "内存泄漏"
```

**预期输出：**

```
⚠️ 内存泄漏检测 path=/ex-admin/store-player-game-log/index memory_leaked=3.45 MB
⚠️ 内存泄漏检测 path=/ex-admin/channel-player/index memory_leaked=2.89 MB
```

**执行时长：** 运行 2-4 小时，收集足够的数据

---

### 步骤 4：分析泄漏接口并修复

**提取高频泄漏接口：**

```bash
grep "内存泄漏" runtime/logs/webman.log | \
  awk -F'path=' '{print $2}' | \
  awk '{print $1}' | \
  sort | uniq -c | sort -nr | head -20
```

**修复优先级：**

1. **🚨 严重泄漏（>5MB）：** 立即修复
2. **⚠️ 中度泄漏（2-5MB）：** 24小时内修复
3. **⚡ 轻度泄漏（1-2MB）：** 观察是否为高频接口，高频的优先修复

**修复模板：** 参考 `StorePlayerGameLogController.php` 的修复方法

---

### 步骤 5：禁用内存审计中间件

**编辑：** `config/middleware.php`

```php
return [
    '' => [
        AccessControl::class,
        // MemoryAudit::class,  // 👈 重新注释掉
    ],
];
```

**重启服务：**

```bash
php start.php reload
```

---

### 步骤 6：验证修复效果

**监控进程内存（运行24-48小时）：**

```bash
# 每10分钟记录一次
watch -n 600 'php start.php status >> memory_monitor.log'
```

**分析结果：**

```bash
# 查看内存趋势
grep -A 10 "webman" memory_monitor.log | grep -E "PID|Memory"
```

**成功标准：**

- ✅ 单进程内存稳定在 300-500 MB 以内
- ✅ 处理 100 次请求后自动重启
- ✅ 新进程启动后内存回到 200 MB 左右
- ✅ 服务器总内存占用 < 2 GB（4个进程）

---

## 📋 待修复的控制器（按优先级）

根据 `scripts/check_memory_leaks.php` 的扫描结果，以下控制器也存在类似问题：

### 高优先级（列表接口，访问频繁）

- [ ] `ChannelPlayerController.php`（12处with使用）
- [ ] `ChannelPlayerGameLogController.php`（3处with使用）
- [ ] `AgentPlayerGameLogController.php`
- [ ] `ChannelMachineController.php`（3处with使用）

### 中优先级（详情/导出接口）

- [ ] `StoreShiftHandoverRecordController::export()` - 大数据导出
- [ ] `ChannelRechargeRecordController.php`（5处with使用）
- [ ] `ChannelWithdrawRecordController.php`（5处with使用）

### 低优先级（管理接口，访问较少）

- [ ] `ChannelAgentController.php`（4处with使用）
- [ ] `PlayerController.php`（6处with使用）

---

## 🔬 监控命令速查

### 实时监控进程内存

```bash
watch -n 5 'php start.php status | grep webman'
```

### 查看内存泄漏日志

```bash
tail -f runtime/logs/webman.log | grep -E "内存泄漏|memory_leaked"
```

### 找出最耗内存的接口

```bash
grep "memory_leaked" runtime/logs/webman.log | \
  grep -oP 'path=[^\s]+' | \
  sort | uniq -c | sort -nr | head -20
```

### 查看单个接口的泄漏历史

```bash
grep "/ex-admin/store-player-game-log/index" runtime/logs/webman.log | \
  grep "memory_leaked"
```

---

## 📞 如果问题仍未解决

### 收集诊断信息

```bash
# 1. 导出内存泄漏日志
grep "内存泄漏" runtime/logs/webman.log > memory_leak_full.log

# 2. 运行检查脚本
php scripts/check_memory_leaks.php > leak_check_full.txt

# 3. 导出进程状态
php start.php status > process_status.txt

# 4. 导出服务器信息
php -v > php_info.txt
free -h >> php_info.txt
cat .env | grep -v PASSWORD >> php_info.txt
```

### 附加调试步骤

**检查是否有其他内存泄漏源：**

```bash
# 检查日志配置
cat config/log.php | grep "Handler"

# 检查是否开启了查询日志
grep -r "enableQueryLog" addons/webman/

# 检查静态变量使用
grep -r "protected static\|private static" addons/webman/ | grep -v "const"
```

---

## ⏱ 预计时间线

| 任务 | 预计耗时 | 状态 |
|------|---------|------|
| 重启服务（应用 max_request=100） | 1 分钟 | ⬜ 待执行 |
| 运行检查脚本 | 2 分钟 | ⬜ 待执行 |
| 启用内存审计 + 收集数据 | 2-4 小时 | ⬜ 待执行 |
| 分析日志，定位高频泄漏接口 | 30 分钟 | ⬜ 待执行 |
| 修复高优先级接口（3-5个） | 2-3 小时 | ⬜ 待执行 |
| 验证修复效果（监控24小时） | 1 天 | ⬜ 待执行 |
| 修复中优先级接口 | 1-2 天 | ⬜ 待执行 |

**总计：** 约 3-5 天完全修复

---

## 📈 成功指标

### 修复前（当前状态）

- 进程处理 600 次请求 = 2 GB
- 4 个进程 × 2 GB = **8 GB 总内存**
- 预计 2 天内耗尽 16 GB 服务器内存

### 修复后（目标）

- 进程处理 100 次请求 = 300 MB（max_request 限制）
- 4 个进程 × 300 MB = **1.2 GB 总内存**
- 内存占用降低 **85%**
- 可稳定运行数周不重启

---

**创建时间：** 2026-05-16

**负责人：** 架构团队

**紧急程度：** 🔴 高（2天内完成止血，5天内完全修复）
