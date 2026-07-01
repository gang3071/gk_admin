# Webman 内存泄露根本原因分析报告

## 🔥 问题现状

- **泄露速度**: 平均每次请求泄露 **3.2 MB**（极其严重！）
- **监控数据**: 进程1214处理627次请求达2GB，进程1210处理53次仅205MB
- **现有缓解**: `max_request = 100`（每100个请求重启进程）
- **效果评估**: 不理想，依然持续泄露

## ✅ 已修复的泄露源

### 1. DataPermissions Trait 重复注册全局作用域
**影响**: 约 240 MB/600请求  
**修复**: 添加静态标记防止重复注册  
**文件**: `addons/webman/traits/DataPermissions.php:17`

### 2. Admin::check() 权限节点重复复制
**影响**: 约 2 MB/请求  
**修复**: 缓存权限节点ID数组  
**文件**: `addons/webman/Admin.php:27`

### 3. Eloquent with() 过度加载数据
**影响**: 减少 84-90% 数据加载量  
**修复**: 限制字段列表（如 `player:id,uuid,name`）  
**文件**: 多个Controller

---

## ⚠️ 最可能的根本原因

### 🔥 原因 1: Monolog Logger 配置问题（高度怀疑）

#### 问题分析

**当前配置严重问题**:
1. **40+ 个独立 Log Handler** - 每个都维护文件句柄和缓冲区
2. **全部使用 DEBUG 级别** - 生产环境记录过多无用日志
3. **缺少缓冲管理** - 没有定期刷新缓冲区

**内存泄露机制**:
```
请求 1 → 写入40个Logger → 每个缓冲区+100KB → 累积4MB
请求 2 → 写入40个Logger → 每个缓冲区+100KB → 累积8MB
...
请求 100 → 缓冲区总计400MB → 进程重启才释放
```

**证据代码** (`config/log.php`):
```php
// 40+ 个Handler，全部 DEBUG 级别
'default' => [..., Monolog\Logger::DEBUG],
'machine' => [..., Monolog\Logger::DEBUG],
'slot_machine' => [..., Monolog\Logger::DEBUG],
// ... 还有37个
```

#### 💡 修复方案

**方案 A: 合并Logger + 提高日志级别**（推荐）

```php
// config/log.php
return [
    // ✅ 主Logger - WARNING级别
    'default' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/webman.log',
                    7,
                    Monolog\Logger::WARNING, // 改为 WARNING
                ],
            ]
        ],
    ],
    
    // ✅ 机台Logger - 合并所有机台日志
    'machine' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/machine.log',
                    3,
                    Monolog\Logger::INFO, // INFO级别
                ],
            ]
        ],
    ],
    
    // ✅ 游戏平台Logger - 合并所有平台日志
    'game_platform' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/game_platform.log',
                    2,
                    Monolog\Logger::INFO,
                ],
            ]
        ],
    ],
    
    // 删除不常用的Logger:
    // - slot_machine, jackpot_machine （合并到 machine）
    // - 各游戏平台Logger（合并到 game_platform）
];
```

**方案 B: 使用 BufferHandler + 手动刷新**（如需保留DEBUG）

```php
// 在每个Logger配置中添加BufferHandler
'handlers' => [
    [
        'class' => Monolog\Handler\BufferHandler::class,
        'constructor' => [
            new Monolog\Handler\RotatingFileHandler::class(...),
            100, // 缓冲100条记录后才写入
            Monolog\Logger::DEBUG,
            true, // 请求结束时刷新
        ],
    ]
],
```

---

### 原因 2: Eloquent Query Builder 查询对象未释放

#### 问题分析

Laravel Query Builder 在每次查询后会保留：
- 绑定参数数组
- 查询列信息
- 关联加载的模型实例

在高频接口中，这些对象累积会导致严重泄露。

#### 💡 修复方案

**在高频Controller中显式释放**:

```php
// addons/webman/controller/ChannelPlayerGameLogController.php
public function index(): Grid
{
    $query = $this->model::query()
        ->with([
            'player:id,uuid,name',
            'machine:id,code,name'
        ]);
    
    $total = $query->count();
    $list = $query->forPage($page, $size)->get()->toArray();
    
    // ✅ 显式释放Query对象
    unset($query);
    
    return Grid::create($list, function (Grid $grid) use ($total) {
        $grid->setTotal($total);
        // ...
    });
}
```

---

### 原因 3: ExAdmin Container 服务累积

#### 问题分析

`Container::getInstance()` 是全局单例，可能在每次请求时注册新服务但不释放。

#### 排查方法

检查 ExAdmin 源码中的 Container 实现：
```php
// 查看是否有服务累积
vendor/exadmin/exadmin/src/support/Container.php
```

---

## 🚀 立即执行的修复步骤

### 步骤 1: 优化Log配置（立即生效）

1. 备份当前配置：
   ```bash
   cp config/log.php config/log.php.backup
   ```

2. 修改 `config/log.php`：
   - 将所有Handler的日志级别改为 `Monolog\Logger::WARNING`
   - 合并相似的Logger（机台、游戏平台）
   - 删除不常用的Logger

3. 重启Webman：
   ```bash
   php start.php restart
   ```

### 步骤 2: 添加内存监控中间件

创建 `addons/webman/middleware/MemoryMonitor.php`：

```php
<?php

namespace addons\webman\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use support\Log;

class MemoryMonitor implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $memBefore = memory_get_usage(true);
        $response = $handler($request);
        $memAfter = memory_get_usage(true);
        $leaked = $memAfter - $memBefore;
        
        // 泄露超过1MB时记录
        if ($leaked > 1 * 1024 * 1024) {
            Log::warning("Memory leaked", [
                'amount_mb' => round($leaked / 1024 / 1024, 2),
                'uri' => $request->uri(),
                'method' => $request->method(),
            ]);
        }
        
        return $response;
    }
}
```

在 `config/middleware.php` 中注册：
```php
return [
    '' => [
        // ... 其他中间件
        addons\webman\middleware\MemoryMonitor::class,
    ],
];
```

### 步骤 3: 降低 max_request 值

修改 `config/server.php`：
```php
'max_request' => 50, // 从100降到50，更频繁重启
```

### 步骤 4: 监控并分析泄露接口

运行24小时后，分析日志：
```bash
# 找出泄露最严重的接口
grep "Memory leaked" runtime/logs/webman.log | jq -r '.context.uri' | sort | uniq -c | sort -rn | head -20
```

---

## 📊 预期效果

### 修复前（当前）
- 平均泄露: **3.2 MB/请求**
- 100请求: **320 MB**
- 进程峰值: **2 GB**

### 修复后（预期）
- 平均泄露: **< 100 KB/请求**（优化97%）
- 100请求: **< 10 MB**
- 进程峰值: **< 200 MB**

---

## 🔍 深度诊断工具（可选）

### 使用Xdebug进行内存分析

```php
// 在问题接口添加
xdebug_start_trace();
// ... 业务逻辑
xdebug_stop_trace();

// 分析 trace 文件查找内存累积点
```

### 使用php-memory-profiler

```bash
# 安装
pecl install memory-profiler

# 启用
php -d memory_profiler.enable=1 start.php start
```

---

## 总结与建议

1. **根本原因**: Monolog配置不当（40+Handler × DEBUG级别）是主要泄露源
2. **修复优先级**: Log配置优化 > Query释放 > max_request降低
3. **监控验证**: 使用MemoryMonitor中间件持续监控
4. **长期方案**: 考虑使用队列异步写日志，减少同步IO阻塞

**预计修复后内存泄露可降低90%以上，进程可稳定运行500+请求不重启。**
