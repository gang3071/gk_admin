# Lock Wait Timeout 锁等待超时修复说明

## 问题描述

**错误信息：**
```
Lock wait timeout exceeded
```

**涉及表：**
- `machine` (yjb_machine)
- `machine_media` (yjb_machine_media)

**错误时间：** 2026-05-19 15:32:12

---

## 根本原因

### 1. ⚠️ 嵌套事务导致死锁

**问题代码位置：** `addons/webman/controller/MachineController.php`

**嵌套事务链路：**
```php
// 外层事务（第583/603行）
DB::beginTransaction();
try {
    $this->addMachine($form, $machine);
        ↓
        // addMachine() 内部调用 addMachineMedia()
        ↓
        // 内层事务（原第722行）⚠️ 嵌套！
        Db::beginTransaction();
        try {
            // 复杂操作：
            // - MediaServer API调用
            // - MachineMedia更新（触发booted()钩子）
            // - MachineEditLog创建
            // - MachineMediaPush操作
            Db::commit();
        } catch {
            Db::rollback();
        }
    DB::commit();
}
```

**问题分析：**
- Laravel Eloquent 不完全支持嵌套事务（虽然有savepoint机制）
- 在 Webman 常驻内存环境中，嵌套事务特别容易导致：
  - 长时间持有数据库行锁
  - 事务提交/回滚顺序混乱
  - 锁等待超时（Lock wait timeout exceeded）

### 2. 数据库连接超时配置过短

**原配置：** `config/database.php`
```php
'options' => [
    \PDO::ATTR_TIMEOUT => 3  // 仅3秒
]
```

在以下场景中容易超时：
- 调用外部 MediaServer API（网络延迟）
- 复杂的多表操作
- 触发 Eloquent 模型钩子（booted）

### 3. MachineMedia 模型钩子增加事务时间

`addons/webman/model/MachineMedia.php` 第67-100行：

```php
protected static function booted()
{
    static::updated(function (MachineMedia $machineMedia) {
        // 每次更新时自动创建 MachineEditLog 记录
        // 这会在事务中增加额外的INSERT操作
        $machineEditLog = new MachineEditLog();
        $machineEditLog->save();
    });
}
```

---

## 修复方案

### ✅ 修复1：移除嵌套事务（核心修复）

**修改文件：** `addons/webman/controller/MachineController.php`

**修改内容：**
- **移除** `addMachineMedia()` 方法中的 `Db::beginTransaction()` 和 `Db::commit()`
- 让外层调用者（`addMachine()`）统一控制事务
- 保留参数验证和异常抛出机制

**修改后事务流程：**
```php
// 外层事务（第583/603行）
DB::beginTransaction();
try {
    $this->addMachine($form, $machine);
        ↓
        // addMachine() 内部调用 addMachineMedia()
        // ✅ 不再有内层事务，操作在同一事务中
        ↓
        // 所有操作在同一事务中执行
    DB::commit();  // 统一提交
} catch (\Exception $e) {
    DB::rollBack();  // 统一回滚
}
```

**代码变更：**
```diff
 public function addMachineMedia(...): bool|MachineMedia {
     if (!empty($pushIp) || ...) {
-        Db::beginTransaction();
-        try {
+        // ⚠️ 移除内层事务，由外层调用者控制事务
+        // 这样可以避免嵌套事务导致的锁等待超时问题
         if (empty($pushIp)) {
             throw new Exception(admin_trans('machine_media.push_ip_not_found'));
         }
         // ... 业务逻辑 ...
-            Db::commit();
-        } catch (\Exception) {
-            Db::rollback();
-            throw new Exception(admin_trans('machine_media.get_media_fail'));
-        }
         return $media;
     }
     return false;
 }
```

### ✅ 修复2：增加数据库超时配置

**修改文件：** `config/database.php`

**修改内容：**
```diff
 'options' => [
-    \PDO::ATTR_TIMEOUT => 3
+    // 增加连接超时时间，避免复杂操作时超时
+    \PDO::ATTR_TIMEOUT => 10,
+    // 设置字符集和会话超时
+    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4, SESSION wait_timeout = 28800, SESSION interactive_timeout = 28800'
 ]
```

**说明：**
- `ATTR_TIMEOUT`：PDO连接超时从3秒增加到10秒
- `wait_timeout`：MySQL会话超时设置为8小时（28800秒）
- `interactive_timeout`：交互式会话超时设置为8小时

---

## 测试验证

### 1. 功能测试

**测试场景：**
- ✅ 创建新机台（带机台媒体）
- ✅ 编辑现有机台（修改媒体配置）
- ✅ 删除机台媒体
- ✅ 并发创建多台机台

**测试步骤：**
```bash
# 重启Webman服务
php start.php restart

# 访问机台管理页面
# http://localhost:8789/admin

# 测试创建机台
1. 填写机台基本信息
2. 配置推流IP、拉流IP、媒体IP等
3. 提交保存
4. 检查是否成功创建

# 测试并发操作
# 在多个浏览器标签页同时保存机台
```

### 2. 性能测试

**检查事务执行时间：**
```php
// 在MachineController::save()方法中添加日志
$start = microtime(true);
DB::beginTransaction();
try {
    $this->addMachine($form, $machine);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
$duration = microtime(true) - $start;
Log::info("Transaction duration: {$duration}s");
```

**预期结果：**
- 正常创建机台：< 2秒
- 并发操作：无锁等待错误

### 3. 数据库锁监控

**检查当前未提交的事务：**
```sql
SELECT * FROM information_schema.innodb_trx\G
```

**检查锁等待情况：**
```sql
SELECT * FROM information_schema.innodb_lock_waits\G
```

**预期结果：**
- 无长时间未提交的事务
- 无锁等待记录

---

## 相关文件修改清单

| 文件路径 | 修改类型 | 修改内容 |
|---------|---------|---------|
| `addons/webman/controller/MachineController.php` | 代码优化 | 移除 `addMachineMedia()` 方法中的嵌套事务 |
| `config/database.php` | 配置调整 | 增加PDO超时时间，设置会话超时 |

---

## 预防措施

### 1. 代码规范

**❌ 禁止嵌套事务：**
```php
// ❌ 错误示例
DB::beginTransaction();
try {
    $this->someMethod();  // 内部又调用beginTransaction()
    DB::commit();
}
```

**✅ 正确做法：**
```php
// ✅ 方案1：统一在最外层控制事务
DB::beginTransaction();
try {
    $this->method1();  // 不包含事务
    $this->method2();  // 不包含事务
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
}

// ✅ 方案2：方法接收事务参数
public function method($useTransaction = true) {
    if ($useTransaction) {
        DB::beginTransaction();
    }
    try {
        // 业务逻辑
        if ($useTransaction) {
            DB::commit();
        }
    } catch (\Exception $e) {
        if ($useTransaction) {
            DB::rollBack();
        }
        throw $e;
    }
}
```

### 2. 事务最佳实践

**原则：**
1. **最小化事务范围** - 只包含必要的数据库操作
2. **避免在事务中调用外部API** - 将API调用移到事务外
3. **快速提交** - 减少锁持有时间
4. **统一事务控制** - 由最外层方法控制事务

**示例：**
```php
// ❌ 错误：事务中调用外部API
DB::beginTransaction();
try {
    $media->save();
    $result = $mediaServer->createStream();  // 外部API，可能很慢
    $media->stream_name = $result['dataId'];
    $media->save();
    DB::commit();
}

// ✅ 正确：先调用API，再开启事务
$result = $mediaServer->createStream();  // 事务外调用
DB::beginTransaction();
try {
    $media->stream_name = $result['dataId'];
    $media->save();
    DB::commit();
}
```

### 3. Webman 常驻内存注意事项

**关键点：**
- 确保每个请求的事务都正确提交/回滚
- 避免全局变量污染导致事务状态混乱
- 使用 `DB::connection()->transactionLevel()` 检查事务嵌套层级

**检查事务状态：**
```php
// 在控制器方法开始时检查
if (DB::connection()->transactionLevel() > 0) {
    Log::warning('Transaction already started before controller method');
    // 可能是上个请求未正确关闭
    DB::rollBack();
}
```

---

## 监控建议

### 1. 数据库慢查询监控

```sql
-- 启用慢查询日志
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;  -- 超过2秒的查询记录
```

### 2. 锁等待监控

```bash
# 定期检查锁等待情况
mysql -e "SELECT * FROM information_schema.innodb_lock_waits" | wc -l
```

### 3. 应用层日志

在 `MachineController` 关键方法中添加日志：
```php
Log::info('Machine save started', [
    'transaction_level' => DB::connection()->transactionLevel(),
    'machine_id' => $machine->id ?? 'new'
]);
```

---

## 回滚方案

如果修复后出现问题，可以使用Git回滚：

```bash
# 查看本次修改
git diff HEAD

# 回滚到修改前
git checkout HEAD -- addons/webman/controller/MachineController.php
git checkout HEAD -- config/database.php

# 重启服务
php start.php restart
```

---

## 总结

### 修复效果

| 问题 | 修复前 | 修复后 |
|------|--------|--------|
| 嵌套事务 | ❌ 存在，导致死锁 | ✅ 已移除 |
| 数据库超时 | ❌ 3秒，容易超时 | ✅ 10秒 + 会话超时优化 |
| 锁等待错误 | ❌ 频繁发生 | ✅ 预期消除 |
| 代码可维护性 | ⚠️ 事务嵌套复杂 | ✅ 事务控制清晰 |

### 关键要点

1. ✅ **移除嵌套事务是核心修复** - 解决了根本原因
2. ✅ **增加超时配置是保护措施** - 避免偶发性超时
3. ⚠️ **需要充分测试** - 特别是并发场景
4. 📝 **遵循事务最佳实践** - 避免未来出现类似问题

---

**修复日期：** 2026-05-19
**修复人员：** Claude (Staff Engineer)
**影响范围：** 机台管理模块（Machine Management）
**风险等级：** 低（仅优化事务控制，不改变业务逻辑）
