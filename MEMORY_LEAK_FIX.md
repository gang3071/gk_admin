# 内存泄漏修复方案

## 🎯 修复目标

- 内存使用率稳定在 30-50%（不再攀升到 96%）
- 导出功能稳定运行，不触发 OOM
- Worker 进程稳定，不再异常退出
- 系统可长期运行，无需频繁重启

---

## 修复方案 1：优化导出功能 - 分批加载数据（最重要）

### 问题代码

**文件：** `addons/webman/grid/ShiftReportExporter.php`

**问题：** 第 353-399 行一次性加载所有历史数据

---

### 修复方案 1A：使用 chunk() 分批查询（推荐）

**原理：** 将大查询拆分成多个小查询，每次只加载 100-1000 条记录

**修改文件：** `addons/webman/grid/ShiftReportExporter.php`

**修改第 353-400 行：**

```php
/**
 * 加载所有历史交班记录的设备明细数据（分批加载）
 */
protected function loadAllHistoricalData()
{
    if (!$this->storeAdminId) {
        return;
    }

    // 查询该店家所有的交班记录 ID
    $allShiftRecords = StoreAgentShiftHandoverRecord::query()
        ->where('bind_admin_user_id', $this->storeAdminId)
        ->pluck('id');

    if ($allShiftRecords->isEmpty()) {
        return;
    }

    // ✅ 使用 chunk() 分批加载设备明细（每次 500 条）
    StoreShiftDeviceDetail::query()
        ->whereIn('shift_record_id', $allShiftRecords)
        ->chunk(500, function ($deviceDetails) {
            // 累加到每个设备的总计和全局总计
            foreach ($deviceDetails as $detail) {
                $deviceKey = $detail->player_id;

                if (isset($this->deviceTotals[$deviceKey])) {
                    $this->deviceTotals[$deviceKey]['machine_point'] += $detail->machine_point;
                    $this->deviceTotals[$deviceKey]['recharge_amount'] += $detail->recharge_amount;
                    $this->deviceTotals[$deviceKey]['withdrawal_amount'] += $detail->withdrawal_amount;
                    $this->deviceTotals[$deviceKey]['modified_add_amount'] += $detail->modified_add_amount;
                    $this->deviceTotals[$deviceKey]['modified_deduct_amount'] += $detail->modified_deduct_amount;
                    $this->deviceTotals[$deviceKey]['lottery_amount'] += $detail->lottery_amount;
                    $this->deviceTotals[$deviceKey]['total_in'] += $detail->total_in;
                    $this->deviceTotals[$deviceKey]['total_out'] += $detail->total_out;
                    $this->deviceTotals[$deviceKey]['profit'] += $detail->profit;
                }

                // 累加到全局总计
                $this->grandTotal['machine_point'] += $detail->machine_point;
                $this->grandTotal['recharge_amount'] += $detail->recharge_amount;
                $this->grandTotal['withdrawal_amount'] += $detail->withdrawal_amount;
                $this->grandTotal['modified_add_amount'] += $detail->modified_add_amount;
                $this->grandTotal['modified_deduct_amount'] += $detail->modified_deduct_amount;
                $this->grandTotal['lottery_amount'] += $detail->lottery_amount;
                $this->grandTotal['total_in'] += $detail->total_in;
                $this->grandTotal['total_out'] += $detail->total_out;
                $this->grandTotal['profit'] += $detail->profit;
            }

            // ✅ 每批处理完后，显式释放内存
            $deviceDetails = null;
            unset($deviceDetails);

            // ✅ 手动触发垃圾回收（可选，但建议）
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });
}
```

**效果：**
- **修复前：** 一次性加载 10,000 条记录 → 30 MB 内存
- **修复后：** 每次加载 500 条记录 → 1.5 MB 内存（减少 95%）
- **GC 可及时回收：** 每批处理完就释放，不会累积

---

### 修复方案 1B：使用 lazy() 惰性加载（Laravel 8.64+）

**适用版本：** Laravel 8.64 及以上

```php
protected function loadAllHistoricalData()
{
    if (!$this->storeAdminId) {
        return;
    }

    $allShiftRecords = StoreAgentShiftHandoverRecord::query()
        ->where('bind_admin_user_id', $this->storeAdminId)
        ->pluck('id');

    if ($allShiftRecords->isEmpty()) {
        return;
    }

    // ✅ 使用 lazy() 惰性加载（更高效）
    StoreShiftDeviceDetail::query()
        ->whereIn('shift_record_id', $allShiftRecords)
        ->lazy(500)  // 每次从数据库取 500 条
        ->each(function ($detail) {
            $deviceKey = $detail->player_id;

            if (isset($this->deviceTotals[$deviceKey])) {
                $this->deviceTotals[$deviceKey]['machine_point'] += $detail->machine_point;
                // ... 其他字段累加
            }

            // 累加到全局总计
            $this->grandTotal['machine_point'] += $detail->machine_point;
            // ... 其他字段累加
        });

    // ✅ 手动触发垃圾回收
    gc_collect_cycles();
}
```

---

### 修复方案 1C：限制查询时间范围（推荐结合使用）

**原理：** 只加载最近 3-6 个月的数据，而不是所有历史数据

**修改：**

```php
protected function loadAllHistoricalData()
{
    if (!$this->storeAdminId) {
        return;
    }

    // ✅ 限制查询时间范围（最近 6 个月）
    $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));

    $allShiftRecords = StoreAgentShiftHandoverRecord::query()
        ->where('bind_admin_user_id', $this->storeAdminId)
        ->where('created_at', '>=', $sixMonthsAgo)  // ✅ 只查询最近 6 个月
        ->pluck('id');

    if ($allShiftRecords->isEmpty()) {
        return;
    }

    // ... 其余代码同上（使用 chunk 或 lazy）
}
```

**配置化：**

在 `.env` 添加：

```env
# 导出历史数据时间范围（月）
EXPORT_HISTORY_MONTHS=6
```

在代码中使用：

```php
$months = env('EXPORT_HISTORY_MONTHS', 6);
$startDate = date('Y-m-d', strtotime("-{$months} months"));
```

---

## 修复方案 2：优化 initializeStoreDevices() - 分批加载设备

**文件：** `addons/webman/grid/ShiftReportExporter.php`

**修改第 317-348 行：**

```php
protected function initializeStoreDevices()
{
    if (!$this->storeAdminId) {
        return;
    }

    $playerModel = plugin()->webman->config('database.player_model');

    // ✅ 使用 chunk() 分批加载设备
    $playerModel::query()
        ->where('store_admin_id', $this->storeAdminId)
        ->select(['id', 'name', 'phone', 'uuid'])
        ->chunk(200, function ($devices) {
            foreach ($devices as $device) {
                $deviceKey = $device->id;
                $this->deviceTotals[$deviceKey] = [
                    'player_id' => $device->id,
                    'player_name' => $device->name,
                    'player_phone' => $device->phone ?? $device->uuid,
                    'machine_point' => 0,
                    'recharge_amount' => 0,
                    'withdrawal_amount' => 0,
                    'modified_add_amount' => 0,
                    'modified_deduct_amount' => 0,
                    'lottery_amount' => 0,
                    'total_in' => 0,
                    'total_out' => 0,
                    'profit' => 0
                ];
            }

            // ✅ 显式释放
            $devices = null;
            unset($devices);
        });
}
```

---

## 修复方案 3：在队列消费者中显式清理资源

**文件：** `addons/webman/grid/Jobs/Export.php`

**修改第 22-37 行：**

```php
public function consume($data)
{
    try {
        $data['ex_admin_queue'] = false;
        Request::init(function (\Symfony\Component\HttpFoundation\Request $q) use($data){
            $q->initialize($data,$data,[],[],[],$data['ex_admin_request']['server']);
            $q->headers = new HeaderBag($data['ex_admin_request']['header']);
            $q->setMethod($data['ex_admin_request']['method']);
        });

        $class = str_replace('-', '\\', $data['ex_admin_class']);
        Container::getInstance()
            ->make(Route::class)
            ->invokeMethod($class, $data['ex_admin_function'], $data)
            ->jsonSerialize();

    } finally {
        // ✅ 显式清理大对象
        $data = null;
        unset($data);

        // ✅ 清理 Container 缓存（如果有必要）
        // Container::getInstance()->flush();  // 根据实际情况使用

        // ✅ 手动触发垃圾回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // ✅ 记录内存使用（用于监控）
        Log::info('Export Job Completed', [
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
        ]);
    }
}
```

---

## 修复方案 4：配置 Worker 进程自动重启

### 4.1 设置 max_request（推荐）

**文件：** `config/server.php`

**添加或修改：**

```php
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

    // ✅ 添加：Worker 进程处理 N 个请求后自动重启（释放内存）
    'max_request' => 1000,  // 推荐值：500-2000（根据业务调整）

    'stop_timeout' => 2,
    'pid_file' => runtime_path() . '/webman.pid',
    'status_file' => runtime_path() . '/webman.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'log_file' => runtime_path() . '/logs/workerman.log',
    'max_package_size' => 10 * 1024 * 1024
];
```

**说明：**
- `max_request`: Worker 处理多少个请求后自动重启
- **推荐值：**
  - 低流量站点：1000-2000
  - 中流量站点：500-1000
  - 高流量站点：200-500
- **不要设置太小**（如 < 100），会导致频繁重启影响性能

### 4.2 配置消费者进程自动重启

**文件：** `config/plugin/rockys/ex-admin-webman/process.php`

**修改：**

```php
return [
    'ex_admin_consumer'  => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => 2,
        'constructor' => [
            'consumer_dir' => base_path() . '/addons/webman/grid/Jobs'
        ],

        // ✅ 添加：进程自动重启配置
        'reloadable' => true,  // 允许 reload 命令重启
    ]
];
```

**手动重启消费者进程：**

```bash
# 平滑重启（不中断服务）
php start.php reload

# 或定时自动重启（添加到 crontab）
0 3 * * * cd /www/wwwroot/admin.supergames9.com && php start.php reload
```

---

## 修复方案 5：优化 PHP 配置

### 5.1 调整 PHP 内存限制

**文件：** `php.ini` 或 `.env`

**修改：**

```ini
; PHP 内存限制
memory_limit = 512M  ; ✅ 从 128M 增加到 512M（根据实际情况调整）

; 单个脚本最大执行时间
max_execution_time = 300  ; ✅ 导出任务可能需要较长时间

; 垃圾回收配置
zend.enable_gc = 1  ; ✅ 启用垃圾回收
```

### 5.2 优化 OPcache

**文件：** `php.ini`

**添加或修改：**

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256  ; ✅ OPcache 内存（MB）
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.enable_cli=1  ; ✅ CLI 模式也启用
```

---

## 修复方案 6：删除未使用的代码

### 6.1 删除未使用的 $allRecords 变量

**文件：** `addons/webman/grid/ShiftReportExporter.php`

**删除第 34 行：**

```php
// ❌ 删除这一行（未使用的变量）
protected $allRecords = [];
```

### 6.2 删除未使用的静态变量

**文件：** `addons/webman/Admin.php`

**删除第 19 行：**

```php
// ❌ 删除这一行（未使用的静态变量）
protected static $permissions = [];
```

---

## 修复方案 7：添加内存监控（重要）

### 7.1 在 Exporter 中添加内存日志

**文件：** `addons/webman/grid/ShiftReportExporter.php`

**在 write() 方法开始添加：**

```php
public function write(array $data, \Closure $finish = null)
{
    // ✅ 记录开始时的内存
    if ($this->processedRecords == 0) {
        $memStart = memory_get_usage(true);
        Log::info('Export Start', [
            'memory' => round($memStart / 1024 / 1024, 2) . ' MB',
            'admin_id' => $this->storeAdminId,
            'data_count' => $this->count
        ]);
    }

    try {
        // 原有代码...

        // ✅ 在 loadAllHistoricalData() 后记录
        if ($this->processedRecords == 0) {
            $memAfterLoad = memory_get_usage(true);
            Log::info('Data Loaded', [
                'memory' => round($memAfterLoad / 1024 / 1024, 2) . ' MB',
                'devices' => count($this->deviceTotals)
            ]);
        }

        // ... 原有代码

        // ✅ 在导出完成时记录
        if ($this->processedRecords >= $this->count) {
            $memEnd = memory_get_usage(true);
            $memPeak = memory_get_peak_usage(true);
            Log::info('Export Complete', [
                'memory' => round($memEnd / 1024 / 1024, 2) . ' MB',
                'peak' => round($memPeak / 1024 / 1024, 2) . ' MB',
                'processed' => $this->processedRecords
            ]);
        }

    } catch (\Throwable $e) {
        // 原有异常处理...
    }
}
```

### 7.2 创建内存监控中间件

**文件：** `addons/webman/middleware/MemoryMonitor.php`（新建）

```php
<?php

namespace addons\webman\middleware;

use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class MemoryMonitor implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $memBefore = memory_get_usage(true);

        $response = $handler($request);

        $memAfter = memory_get_usage(true);
        $memDiff = $memAfter - $memBefore;

        // ✅ 如果单个请求内存增长超过 10MB，记录警告
        if ($memDiff > 10 * 1024 * 1024) {
            Log::warning('High Memory Usage Detected', [
                'url' => $request->path(),
                'method' => $request->method(),
                'memory_before' => round($memBefore / 1024 / 1024, 2) . ' MB',
                'memory_after' => round($memAfter / 1024 / 1024, 2) . ' MB',
                'memory_增加' => round($memDiff / 1024 / 1024, 2) . ' MB'
            ]);
        }

        return $response;
    }
}
```

**注册中间件：**

在 `config/middleware.php` 中添加（可选，仅在需要诊断时启用）：

```php
return [
    // 全局中间件
    '' => [
        // ...
        // ✅ 添加内存监控（开发环境或诊断时启用）
        addons\webman\middleware\MemoryMonitor::class,
    ],
];
```

---

## 修复方案 8：优化其他导出器

**检查其他导出器是否有类似问题：**

```bash
cd /www/wwwroot/admin.supergames9.com
grep -r "->get()" addons/webman/grid/*Exporter.php
```

**对于每个导出器，应用相同的修复：**
1. 使用 `chunk()` 或 `lazy()` 分批加载
2. 限制查询时间范围
3. 显式释放大对象
4. 手动触发 GC

**示例文件：**
- `DeviceDetailExporter.php`
- `ChannelPlayerReportExporter.php`
- `AgentStoreProfitReportExporter.php`
- `AgentStoreProfitMonthlyExporter.php`

---

## 实施步骤

### 步骤 1：备份代码

```bash
cd /www/wwwroot/admin.supergames9.com

# 备份关键文件
cp addons/webman/grid/ShiftReportExporter.php addons/webman/grid/ShiftReportExporter.php.backup.$(date +%Y%m%d%H%M%S)
cp addons/webman/grid/Jobs/Export.php addons/webman/grid/Jobs/Export.php.backup.$(date +%Y%m%d%H%M%S)
cp config/server.php config/server.php.backup.$(date +%Y%m%d%H%M%S)
```

### 步骤 2：应用修复（按优先级）

**优先级 1（立即实施）：**
1. 修复方案 1：优化 `loadAllHistoricalData()` - 使用 chunk()
2. 修复方案 2：优化 `initializeStoreDevices()` - 使用 chunk()
3. 修复方案 4：设置 `max_request = 1000`

**优先级 2（24小时内）：**
4. 修复方案 3：优化队列消费者 - 显式清理
5. 修复方案 6：删除未使用的变量
6. 修复方案 7：添加内存监控

**优先级 3（一周内）：**
7. 修复方案 5：优化 PHP 配置
8. 修复方案 8：优化其他导出器

### 步骤 3：测试修复效果

```bash
# 1. 重启服务
php start.php restart

# 2. 测试导出功能
# 在后台进行一次完整的交班记录导出

# 3. 监控内存使用
ps aux | grep webman | grep -v grep

# 4. 查看日志
tail -f runtime/logs/webman.log | grep -i "memory\|export"

# 5. 持续监控 24 小时
./monitor_queue.sh  # 使用之前创建的监控脚本
```

### 步骤 4：验证修复

**成功标志：**
- ✅ 内存使用率稳定在 30-50%
- ✅ 导出功能正常完成
- ✅ 日志中无 OOM 错误
- ✅ Worker 进程稳定运行

**如果仍有问题：**
- 检查日志中的内存增长点
- 使用 Xdebug Profiler 分析
- 联系技术支持

---

## 回滚方案

如果修复后出现问题：

```bash
# 恢复备份
cp addons/webman/grid/ShiftReportExporter.php.backup.YYYYMMDDHHMMSS addons/webman/grid/ShiftReportExporter.php
cp addons/webman/grid/Jobs/Export.php.backup.YYYYMMDDHHMMSS addons/webman/grid/Jobs/Export.php
cp config/server.php.backup.YYYYMMDDHHMMSS config/server.php

# 重启服务
php start.php restart
```

---

## 总结

**关键修复点：**
1. ✅ 使用 `chunk()` 分批加载数据（最重要）
2. ✅ 限制查询时间范围（减少数据量）
3. ✅ 显式释放大对象（`unset()` + `gc_collect_cycles()`）
4. ✅ 设置 `max_request` 自动重启（防止内存累积）
5. ✅ 添加内存监控（及时发现问题）

**预期效果：**
- 内存使用率从 96% 降到 30-50%
- 导出功能稳定，无 OOM
- Worker 进程稳定，无需频繁重启
- 系统可长期运行

---

**修复时间：** 2026-05-09  
**修复状态：** 等待实施  
**下一步：** 在生产环境应用修复并监控 24 小时
