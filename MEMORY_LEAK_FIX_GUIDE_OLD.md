# 内存泄漏修复指南

## 🔴 问题严重程度：高

**症状：** Webman Worker 进程内存随请求次数线性增长，单进程可达 2GB+

**影响：** 服务器内存耗尽、OOM崩溃、系统卡死

**紧急程度：** 立即修复

---

## 📊 数据分析

从 `php start.php status` 观察到：

| 进程ID | 请求次数 | 内存占用 | 平均每次泄漏 |
|--------|---------|---------|-------------|
| 1214   | 627次   | 2020 MB | 3.2 MB/次   |
| 1212   | 477次   | 1501 MB | 3.1 MB/次   |
| 1213   | 281次   | 927 MB  | 3.3 MB/次   |
| 1210   | 53次    | 205 MB  | 正常基线     |

**结论：** 平均每次请求泄漏 **3.2 MB**，这是典型的 **ORM 关联查询过度加载** 导致的内存累积。

---

## 🎯 根本原因

### 1. ORM 关联过度加载（主要原因）

**问题代码示例：** `StorePlayerGameLogController.php:39-46`

```php
// ❌ 错误：一次加载5层关联
$grid->model()->with([
    'player',                    // 第1层
    'machine' => function ($query) {
        return $query->with(['machineLabel']);  // 第2层嵌套
    },
    'player.channel',            // 第2层嵌套
    'machine_recording'          // 第1层
]);

// 如果列表有1000条数据，这会加载：
// 1000 players + 1000 machines + 1000 machineLabels + 1000 channels + 1000 recordings
// = 至少 5000+ 个 Eloquent 模型对象！
```

**影响：**
- 每个 Eloquent 模型对象约占用 5-10 KB 内存
- 5000个对象 = 25-50 MB 内存
- 在常驻内存模式下，这些对象不会被释放
- 多次请求累积 → 内存爆炸

### 2. 没有使用 `select()` 限制字段

```php
// ❌ 错误：加载所有字段（包括大字段）
$players = Player::query()->with('channel')->get();

// ✅ 正确：只加载需要的字段
$players = Player::query()
    ->select(['id', 'name', 'uuid', 'department_id'])
    ->with('channel:id,name,department_id')
    ->get();
```

### 3. 没有分页限制

```php
// ❌ 错误：全量查询（可能几千条）
$logs = PlayerGameLog::query()->get();

// ✅ 正确：分页或使用 chunk
$logs = PlayerGameLog::query()->paginate(50);
```

### 4. 静态变量累积（次要原因）

虽然代码中有静态变量（如 `Admin::$permissions`），但由于使用了缓存，这不是主要问题。

---

## 🛠 修复方案

### ✅ 第一步：立即止血（已完成）

**修改：** `config/server.php`

```php
// 降低 max_request 从 200 → 100
'max_request' => 100,
```

**效果：** 进程处理100次请求后自动重启，内存最多累积到 320 MB（3.2 MB × 100），可接受。

**重启服务：**
```bash
php start.php reload
```

---

### ✅ 第二步：启用内存审计（定位元凶）

**文件：** `config/middleware.php`

```php
return [
    '' => [
        AccessControl::class,
        MemoryAudit::class,  // 👈 取消注释以启用
    ],
];
```

**重启服务后，观察日志：**

```bash
tail -f runtime/logs/webman.log | grep "内存泄漏"
```

**日志示例：**

```
⚠️ 内存泄漏检测 path=/ex-admin/store-player-game-log/index memory_leaked=3.45 MB
⚠️ 内存泄漏检测 path=/ex-admin/channel-player/index memory_leaked=2.89 MB
🚨 严重内存泄漏！ path=/ex-admin/store-shift-handover-record/export memory_leaked=8.12 MB
```

**找到泄漏接口后，立即禁用中间件（恢复性能）：**

```php
// MemoryAudit::class,  // 👈 重新注释掉
```

---

### ✅ 第三步：运行自动检查脚本

```bash
php scripts/check_memory_leaks.php
```

**输出示例：**

```
🔍 开始检查 ORM 查询内存泄漏...

📁 StorePlayerGameLogController.php
   ⚠️  第 39 行: 一次加载了 5 个关联，建议减少到3个以内
      代码: $grid->model()->with(['player', 'machine' => function ($query) {

📁 ChannelPlayerController.php
   ⚠️  第 2109 行: 过度嵌套的关联加载（3层+），会加载大量数据
      代码: $grid->model()->with(['player_extend', 'machine_wallet'])->where('is_coin', 1)

============================================================
✅ 检查完成！共发现 47 个潜在问题
============================================================
```

---

### ✅ 第四步：修复高频泄漏接口

#### 示例1：StorePlayerGameLogController

**修复前：**

```php
$grid->model()->with([
    'player',
    'machine' => function ($query) {
        return $query->with(['machineLabel']);
    },
    'player.channel',
    'machine_recording'
]);
```

**修复后：**

```php
// 策略1：只加载最必要的关联
$grid->model()->with([
    'player:id,name,uuid',  // 只选择需要的字段
    'machine:id,code'       // 只选择需要的字段
]);

// 策略2：如果需要 machineLabel，使用联表查询替代关联
$grid->model()
    ->select([
        'player_game_log.*',
        'player.name as player_name',
        'machine.code as machine_code',
        'machine_label.name as machine_label_name'
    ])
    ->leftJoin('player', 'player_game_log.player_id', '=', 'player.id')
    ->leftJoin('machine', 'player_game_log.machine_id', '=', 'machine.id')
    ->leftJoin('machine_label', 'machine.label_id', '=', 'machine_label.id');
```

**性能对比：**

| 方案 | 内存占用（1000条） | SQL查询数 |
|------|------------------|----------|
| 修复前 | ~50 MB | 1 + N*3 ≈ 3001 |
| 修复后（策略1） | ~8 MB | 3 |
| 修复后（策略2） | ~3 MB | 1 |

#### 示例2：大数据导出接口

**修复前：**

```php
$records = StoreShiftHandoverRecord::query()
    ->with(['devices', 'devices.player', 'devices.machine'])
    ->get(); // 全量加载！
```

**修复后：**

```php
// 使用 chunk() 分批处理
StoreShiftHandoverRecord::query()
    ->with(['devices:id,record_id,device_name'])  // 只加载必要字段
    ->chunk(100, function ($records) use ($exporter) {
        foreach ($records as $record) {
            $exporter->writeRow($record);
        }
        
        // 手动清理已处理的对象
        unset($records);
        gc_collect_cycles();  // 强制GC
    });
```

---

### ✅ 第五步：通用修复原则

#### 1️⃣ 减少 `with()` 的使用

```php
// ❌ 避免
->with(['player', 'machine', 'channel', 'admin', 'logs'])

// ✅ 推荐：只加载真正需要的
->with(['player:id,name'])
```

#### 2️⃣ 使用 `select()` 限制字段

```php
// ❌ 避免
$players = Player::query()->get();

// ✅ 推荐
$players = Player::query()
    ->select(['id', 'name', 'uuid'])  // 不加载text、json等大字段
    ->get();
```

#### 3️⃣ 避免嵌套关联

```php
// ❌ 避免
->with('player.channel.admin')

// ✅ 推荐：拆分或用JOIN
->with(['player:id,name,channel_id', 'player.channel:id,name'])
```

#### 4️⃣ 大数据集使用 `chunk()` 或 `cursor()`

```php
// ❌ 避免
$logs = PlayerGameLog::query()->get();

// ✅ 推荐
PlayerGameLog::query()->chunk(100, function ($logs) {
    // 处理逻辑
    unset($logs);  // 手动释放
});
```

#### 5️⃣ 在处理完后手动清理

```php
foreach ($records as $record) {
    // 处理逻辑
}

// 手动清理
unset($records);
gc_collect_cycles();  // 强制垃圾回收
```

---

## 📋 修复检查清单

- [x] 降低 `max_request` 到 100（已完成）
- [ ] 启用 `MemoryAudit` 中间件，定位高频泄漏接口
- [ ] 运行 `scripts/check_memory_leaks.php` 检查脚本
- [ ] 修复所有 `memory_leaked > 5MB` 的严重接口
- [ ] 修复所有 `memory_leaked > 2MB` 的中度接口
- [ ] 重点检查以下控制器：
  - [ ] `StorePlayerGameLogController`
  - [ ] `StoreShiftHandoverRecordController::export()`
  - [ ] `ChannelPlayerController`
  - [ ] `ChannelPlayerGameLogController`
- [ ] 测试修复效果：运行7天，观察进程内存是否稳定
- [ ] 如果稳定，将 `max_request` 提升到 200-300

---

## 🔬 监控命令

### 实时监控进程内存

```bash
# 每5秒刷新一次
watch -n 5 'php start.php status | grep webman'
```

### 监控日志中的内存泄漏

```bash
tail -f runtime/logs/webman.log | grep -E "内存泄漏|memory_leaked"
```

### 查看最耗内存的接口（需启用 MemoryAudit）

```bash
grep "内存泄漏" runtime/logs/webman.log | sort -t '=' -k 4 -n -r | head -20
```

---

## 📈 预期效果

### 修复前

- 单进程处理 600 次请求 = 2 GB
- 4个进程 × 2 GB = **8 GB 总内存**
- 服务器16GB内存，2天内耗尽

### 修复后

- 单进程处理 100 次请求 = 300 MB（max_request 限制）
- 4个进程 × 300 MB = **1.2 GB 总内存**
- 内存占用降低 **85%**

---

## ⚠️ 注意事项

1. **MemoryAudit 中间件**：只在排查时启用，定位完毕后必须禁用（影响性能约10%）

2. **max_request 值**：
   - 100 = 激进（内存占用低，但重启频繁）
   - 200 = 平衡（推荐）
   - 500 = 宽松（需确保泄漏已修复）

3. **不要一次性修复所有接口**：
   - 先修复高频泄漏接口（>5MB）
   - 观察1-2天
   - 再修复中度泄漏接口（2-5MB）

4. **测试修复效果**：
   ```bash
   # 重启服务
   php start.php restart

   # 观察24小时
   watch -n 60 'php start.php status'

   # 如果内存稳定在 300-500 MB，说明修复成功
   ```

---

## 📞 紧急联系

如果修复后仍有内存泄漏：

1. 导出完整日志：`grep "内存泄漏" runtime/logs/webman.log > memory_leak_report.log`
2. 运行检查脚本：`php scripts/check_memory_leaks.php > leak_check.txt`
3. 附上以上文件反馈

---

**最后更新：** 2026-05-16

**版本：** v1.0

**状态：** 🔴 进行中
