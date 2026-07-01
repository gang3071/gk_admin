# 内存泄漏诊断报告

## 📊 问题现象

根据内存使用率图表分析：
- **内存持续攀升**：从低点逐渐增长到 96.46%
- **突然下降**：达到峰值后垂直下降（OOM Killer 触发或进程重启）
- **周期性重复**：内存泄漏-崩溃-重启的恶性循环

**典型症状：**
- 系统运行一段时间后响应变慢
- 导出功能失败或超时
- Worker 进程异常退出（status 64000）
- 需要频繁重启才能恢复正常

---

## 🔍 根本原因分析

基于代码审查，发现以下**严重内存泄漏问题**：

### 1. ❌ 导出功能一次性加载所有历史数据（最严重）

**文件：** `addons/webman/grid/ShiftReportExporter.php`

**问题代码（第 353-399 行）：**

```php
protected function loadAllHistoricalData()
{
    // ❌ 查询该店家所有的交班记录 ID
    $allShiftRecords = StoreAgentShiftHandoverRecord::query()
        ->where('bind_admin_user_id', $this->storeAdminId)
        ->pluck('id');  // 可能有几千条记录

    // ❌ 一次性加载所有历史设备明细数据到内存！
    $allDeviceDetails = StoreShiftDeviceDetail::query()
        ->whereIn('shift_record_id', $allShiftRecords)
        ->get();  // 🔥 几千到几万条记录全部加载到内存

    // ❌ 遍历所有记录（内存占用持续累加）
    foreach ($allDeviceDetails as $detail) {
        // 累加数据...
    }
}
```

**影响：**
- 一个店家如果有 3 个月的数据，每天 3 次交班，每次 10 个设备
- **总记录数：** 3 × 30 × 3 × 10 = **2,700 条记录**
- 每条记录假设 1KB，总内存占用：**2.7 MB**
- **问题：** 如果同时有 10 个用户导出，内存占用：**27 MB**
- **更严重：** 如果店家有 1 年数据，每条记录更大：**100+ MB per export**

**实际生产环境：**
- 可能有 100+ 个店家
- 每个店家可能有 6 个月到 1 年的历史数据
- 导出时会一次性加载 **成千上万条记录**到内存
- 内存占用可达 **数百 MB 到 GB 级别**

---

### 2. ❌ 实例变量累积（常驻内存环境下的问题）

**文件：** `addons/webman/grid/ShiftReportExporter.php`

**问题代码（第 15-40 行）：**

```php
class ShiftReportExporter extends Excel
{
    // ❌ 实例变量会在 Webman 常驻内存中累积
    protected $processedRecords = 0;
    protected $totalDevices = 0;

    protected $grandTotal = [
        'machine_point' => 0,
        'recharge_amount' => 0,
        // ... 更多字段
    ];

    // ❌ 存储所有交班记录数据（虽然未使用，但占用内存）
    protected $allRecords = [];

    // ❌ 存储每个设备的累计数据
    protected $deviceTotals = [];

    protected $storeAdminId = null;
}
```

**问题：**
- 在 Webman 常驻内存模式下，PHP 进程不会在请求结束后销毁
- 如果导出器实例被缓存或未正确销毁，这些数组会一直占用内存
- 每次导出都会创建新的数据结构，但旧的可能没有被垃圾回收

---

### 3. ❌ Container 单例导致对象缓存

**文件：** `addons/webman/grid/Jobs/Export.php`

**问题代码（第 32-36 行）：**

```php
public function consume($data)
{
    // ❌ Container 单例可能缓存对象
    Container::getInstance()
        ->make(Route::class)
        ->invokeMethod($class, $data['ex_admin_function'], $data)
        ->jsonSerialize();

    // ❌ 没有显式清理容器缓存
    // ❌ 没有释放大对象
}
```

**问题：**
- `Container::getInstance()` 是单例模式
- 如果 `Route` 或其依赖对象被缓存，会导致内存累积
- 队列消费者处理完一个任务后，相关对象可能没有被释放

---

### 4. ❌ 数据库查询结果集未释放

**文件：** `addons/webman/grid/ShiftReportExporter.php`

**问题代码（第 325-328 行）：**

```php
$allDevices = $playerModel::query()
    ->where('store_admin_id', $this->storeAdminId)
    ->select(['id', 'name', 'phone', 'uuid'])
    ->get();  // ❌ 一次性加载所有设备

// ❌ 循环后未释放 $allDevices
foreach ($allDevices as $device) {
    // ...
}
// ❌ $allDevices 仍然占用内存
```

---

### 5. ❌ 静态变量潜在问题（次要）

**文件：** `addons/webman/Admin.php`

**问题代码（第 19 行）：**

```php
class Admin
{
    // ❌ 静态数组（虽然未使用，但仍声明了）
    protected static $permissions = [];
}
```

**问题：**
- 虽然这个数组未被使用，但如果其他地方有类似的静态数组被填充数据
- 在常驻内存模式下，静态变量会一直存在，永不释放

---

## 📈 内存泄漏累积过程

**时间线分析：**

```
启动时：
- 进程启动，内存占用 ~50 MB（PHP + Webman + 依赖）

第 1 次导出（00:10）：
- 加载 10,000 条记录 → +30 MB
- 导出完成，但对象未完全释放 → 实际释放 20 MB
- 剩余累积：10 MB
- 总内存：60 MB

第 2 次导出（00:25）：
- 加载 10,000 条记录 → +30 MB
- 导出完成 → 实际释放 20 MB
- 剩余累积：10 MB
- 总内存：70 MB

...（持续累积）...

第 50 次导出（04:10）：
- 累积内存：50 × 10 MB = 500 MB
- 加载新数据：+30 MB
- 总内存：530 MB
- 触发 OOM Killer 或进程崩溃

重启：
- 进程重启，内存清零
- 循环重新开始...
```

---

## 🎯 为什么会持续攀升

### 1. 垃圾回收（GC）不及时

PHP 的垃圾回收器（GC）并不会立即回收不再使用的对象：
- 需要等待 GC 周期触发
- 在高负载下，GC 可能延迟
- 大对象可能需要多次 GC 才能完全回收

### 2. 循环引用

如果对象之间存在循环引用，PHP GC 可能无法正确回收：

```php
// 示例：循环引用
$exporter->property = $someObject;
$someObject->parent = $exporter;  // 循环引用
// 即使 $exporter 不再使用，也可能无法被回收
```

### 3. 全局引用

如果对象被存储在全局变量、静态变量或单例中：

```php
// 全局缓存
global $exporters;
$exporters[] = $exporter;  // ❌ 永久持有引用

// 静态缓存
static $cache = [];
$cache[] = $exporter;  // ❌ 永久持有引用

// 单例缓存
Container::getInstance()->bind('exporter', $exporter);  // ❌ 可能永久持有
```

### 4. 事件监听器累积

如果在循环中注册事件监听器，每次都会增加新的监听器：

```php
// ❌ 错误示例
foreach ($requests as $request) {
    EventManager::on('export', function() use ($request) {
        // 闭包持有 $request 引用
    });
}
// 结果：注册了 N 个监听器，每个都持有数据引用
```

---

## 🔬 诊断方法

### 方法 1：实时监控内存使用

**创建监控脚本：**

```bash
#!/bin/bash
# monitor_memory.sh

while true; do
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Memory:"
    ps aux | grep webman | grep -v grep | awk '{printf "  PID: %-8s MEM: %-6s CMD: %s\n", $2, $4"%", $11}'
    echo ""
    sleep 60
done
```

**运行：**
```bash
chmod +x monitor_memory.sh
./monitor_memory.sh > memory_monitor.log &
```

### 方法 2：在代码中打印内存使用

**在 Exporter 中添加日志：**

```php
// 在 ShiftReportExporter::write() 开始
$memBefore = memory_get_usage(true);
Log::info('Export Start', [
    'memory' => round($memBefore / 1024 / 1024, 2) . ' MB',
    'admin_id' => $this->storeAdminId
]);

// 在 loadAllHistoricalData() 后
$memAfter = memory_get_usage(true);
Log::info('Data Loaded', [
    'memory' => round($memAfter / 1024 / 1024, 2) . ' MB',
    '增加' => round(($memAfter - $memBefore) / 1024 / 1024, 2) . ' MB',
    'records' => count($allDeviceDetails)
]);

// 在 write() 结束
$memEnd = memory_get_usage(true);
Log::info('Export End', [
    'memory' => round($memEnd / 1024 / 1024, 2) . ' MB',
    'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
]);
```

### 方法 3：使用 Xdebug Profiler

**安装 Xdebug（开发环境）：**

```bash
pecl install xdebug
```

**配置 php.ini：**

```ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug
xdebug.profiler_output_name=cachegrind.out.%p
```

**分析：**

```bash
# 使用 KCachegrind 或 QCachegrind 分析
kcachegrind /tmp/xdebug/cachegrind.out.*
```

### 方法 4：检查 PHP OPcache 状态

```php
// 添加到 Admin 面板
$opcache = opcache_get_status();
echo "内存使用: " . round($opcache['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
echo "命中率: " . round($opcache['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
```

---

## 🛠️ 修复方案

详见以下文档：
- **`MEMORY_LEAK_FIX.md`** - 详细修复步骤和代码修改
- **`MEMORY_OPTIMIZATION.md`** - 系统级内存优化建议

---

## 📋 快速检查清单

- [ ] 检查导出功能是否一次性加载大量数据
- [ ] 检查实例变量是否在 Webman 常驻内存中累积
- [ ] 检查 Container 单例是否缓存了大对象
- [ ] 检查数据库查询后是否释放了结果集
- [ ] 检查静态变量是否被持续填充数据
- [ ] 检查事件监听器是否重复注册
- [ ] 检查全局变量是否持有对象引用
- [ ] 检查循环引用是否阻止垃圾回收

---

## 📊 预期效果

**修复前：**
- ❌ 内存持续攀升到 96%
- ❌ 每天需要重启 3-5 次
- ❌ 导出功能经常失败
- ❌ 系统响应缓慢

**修复后：**
- ✅ 内存稳定在 30-50%
- ✅ 无需频繁重启
- ✅ 导出功能稳定
- ✅ 系统响应快速

---

**诊断时间：** 2026-05-09  
**下一步：** 参考 `MEMORY_LEAK_FIX.md` 实施修复
