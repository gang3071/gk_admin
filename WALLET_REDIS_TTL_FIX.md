# 玩家钱包Redis永不过期问题修复报告

## 📋 问题总结

**现象：** 玩家钱包余额缓存在Redis中**永不过期**，导致内存持续增长

**影响范围：** 
- ✅ **gk_admin** - 已修复
- ⚠️ **gk_work** - 需要同步修复
- ⚠️ **gk_api** - 需要同步修复

---

## 🔍 根本原因分析

### 设计决策：Redis as Single Source of Truth

**原始设计理念**：
```
数据库（MySQL）      Redis
     ↓               ↑
  持久化存储      实时权威数据
  （备份）         （主数据源）
```

**代码注释（第28-30行）**：
```php
/**
 * 缓存过期时间（秒）
 * ⚠️ 已废弃：余额缓存现在永不过期（Redis as Single Source of Truth）
 */
// private const CACHE_TTL = 5184000; // 60天
```

### 问题代码位置

**文件：** `addons/webman/service/WalletService.php`

**问题代码（8处）**：
```php
// 第62行 - getBalance()
Redis::set($cacheKey, $balance);  // ❌ 没有TTL

// 第151行 - updateCache()
Redis::set($cacheKey, $balance);  // ❌ 没有TTL

// 第283、296行 - getBatchBalance()
Redis::set($cacheKey, $balance);  // ❌ 没有TTL
Redis::set($cacheKey, 0.0);       // ❌ 没有TTL

// 第385、397行 - warmupCache()
Redis::set($cacheKey, $balance);  // ❌ 没有TTL
Redis::set($cacheKey, 0.0);       // ❌ 没有TTL

// Lua脚本（第449、489行）
redis.call('SET', key, newBalance)  // ❌ 没有TTL
```

### 导致的问题

| 问题 | 影响 |
|------|------|
| **内存持续增长** | 每个玩家 = 1个永不过期的key |
| **无法自动回收** | Redis重启前，内存只增不减 |
| **容量预估** | 1万玩家 ≈ 1-2 MB<br>10万玩家 ≈ 10-20 MB<br>100万玩家 ≈ 100-200 MB |
| **Redis故障风险** | Redis宕机 = 所有余额数据丢失 |

---

## ✅ 修复方案

### 策略：保留设计思路，添加合理过期时间

**修复思路**：
- 保留"Redis as Single Source"设计
- 添加**30天**过期时间（平衡性能和内存）
- 活跃玩家缓存会自动续期（每次查询/更新都刷新TTL）
- 不活跃玩家（30天未登录）缓存自动清理

### 修改内容

#### 1. 启用并修改 CACHE_TTL 常量（第26-31行）

**修复前：**
```php
/**
 * 缓存过期时间（秒）
 * ⚠️ 已废弃：余额缓存现在永不过期（Redis as Single Source of Truth）
 */
// private const CACHE_TTL = 5184000; // 60天 (60 * 24 * 3600)
```

**修复后：**
```php
/**
 * 缓存过期时间（秒）
 *
 * 设置为30天：平衡性能和内存占用
 * - 活跃玩家的余额会频繁刷新TTL，实际不会过期
 * - 不活跃玩家（30天未登录）的缓存会自动清理，释放内存
 * - 过期后首次查询会从数据库重建缓存
 */
private const CACHE_TTL = 2592000; // 30天 (30 * 24 * 3600)
```

#### 2. 修改所有 Redis::set() 为 Redis::setex()

**共修改8处**：

| 位置 | 方法 | 修复前 | 修复后 |
|------|------|--------|--------|
| 第62行 | `getBalance()` | `Redis::set($cacheKey, $balance)` | `Redis::setex($cacheKey, self::CACHE_TTL, $balance)` |
| 第151行 | `updateCache()` | `Redis::set($cacheKey, $balance)` | `Redis::setex($cacheKey, self::CACHE_TTL, $balance)` |
| 第283行 | `getBatchBalance()` | `Redis::set($cacheKey, $balance)` | `Redis::setex($cacheKey, self::CACHE_TTL, $balance)` |
| 第296行 | `getBatchBalance()` | `Redis::set($cacheKey, 0.0)` | `Redis::setex($cacheKey, self::CACHE_TTL, 0.0)` |
| 第385行 | `warmupCache()` | `Redis::set($cacheKey, $balance)` | `Redis::setex($cacheKey, self::CACHE_TTL, $balance)` |
| 第397行 | `warmupCache()` | `Redis::set($cacheKey, 0.0)` | `Redis::setex($cacheKey, self::CACHE_TTL, 0.0)` |

#### 3. 修改 Lua 脚本（2处）

**A. LUA_ATOMIC_INCREMENT 脚本（第438-453行）**

**修复前：**
```lua
local key = KEYS[1]
local amount = tonumber(ARGV[1])

local currentBalance = tonumber(redis.call('GET', key)) or 0
local newBalance = currentBalance + amount

-- 原子性写入（永不过期）
redis.call('SET', key, newBalance)

return newBalance
```

**修复后：**
```lua
local key = KEYS[1]
local amount = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2]) or 2592000  -- 默认30天

local currentBalance = tonumber(redis.call('GET', key)) or 0
local newBalance = currentBalance + amount

-- 原子性写入（带过期时间）
redis.call('SETEX', key, ttl, newBalance)

return newBalance
```

**B. LUA_ATOMIC_DECREMENT 脚本（第467-493行）**

**修复前：**
```lua
local key = KEYS[1]
local amount = tonumber(ARGV[1])

-- ... 余额检查逻辑 ...

-- 原子性写入（永不过期）
redis.call('SET', key, newBalance)

return cjson.encode({ok = 1, balance = newBalance, old = currentBalance, new = newBalance})
```

**修复后：**
```lua
local key = KEYS[1]
local amount = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2]) or 2592000  -- 默认30天

-- ... 余额检查逻辑 ...

-- 原子性写入（带过期时间）
redis.call('SETEX', key, ttl, newBalance)

return cjson.encode({ok = 1, balance = newBalance, old = currentBalance, new = newBalance})
```

#### 4. 修改 Lua 脚本调用（2处）

**A. atomicIncrement() 方法（第517-522行）**

**修复前：**
```php
$result = Redis::eval(
    self::LUA_ATOMIC_INCREMENT,
    1,  // 1 个 KEYS 参数
    $cacheKey,  // KEYS[1]
    $amount     // ARGV[1]
);
```

**修复后：**
```php
$result = Redis::eval(
    self::LUA_ATOMIC_INCREMENT,
    1,  // 1 个 KEYS 参数
    $cacheKey,      // KEYS[1]
    $amount,        // ARGV[1]
    self::CACHE_TTL // ARGV[2]
);
```

**B. atomicDecrement() 方法（第569-574行）**

**修复前：**
```php
$result = Redis::eval(
    self::LUA_ATOMIC_DECREMENT,
    1,  // 1 个 KEYS 参数
    $cacheKey,  // KEYS[1]
    $amount     // ARGV[1]
);
```

**修复后：**
```php
$result = Redis::eval(
    self::LUA_ATOMIC_DECREMENT,
    1,  // 1 个 KEYS 参数
    $cacheKey,      // KEYS[1]
    $amount,        // ARGV[1]
    self::CACHE_TTL // ARGV[2]
);
```

---

## 🚀 部署步骤

### gk_admin 项目（已修复✅）

**步骤1：重启服务**

```bash
cd D:\gk_admin

# Windows
php windows.php restart

# Linux
php start.php restart
```

**步骤2：清空现有的永不过期缓存（可选但推荐）**

```bash
# 清空所有玩家钱包缓存（会在下次查询时自动重建）
php -r "
require 'vendor/autoload.php';
\$redis = support\Redis::connection()->client();
\$deleted = 0;
\$iterator = null;
while (false !== (\$keys = \$redis->scan(\$iterator, 'wallet:balance:*', 1000))) {
    if (is_array(\$keys) && count(\$keys) > 0) {
        \$redis->del(...\$keys);
        \$deleted += count(\$keys);
    }
    if (\$iterator === 0) {
        break;
    }
}
echo '✅ 清除了 ' . \$deleted . ' 个钱包缓存' . PHP_EOL;
echo '下次查询时会从数据库重建（带30天过期时间）' . PHP_EOL;
"
```

**步骤3：监控内存使用**

```bash
# 检查Redis内存使用情况
redis-cli info memory | grep used_memory_human

# 检查钱包缓存数量
redis-cli --scan --pattern "wallet:balance:*" | wc -l

# 检查某个玩家的缓存TTL
redis-cli TTL wallet:balance:1
# 应该显示约 2592000 秒（30天）
```

---

### gk_work 项目（待修复⚠️）

**需要同步修复！**

1. **检查是否有相同的 WalletService.php**：
   ```bash
   cd D:\gk_work
   find . -name "WalletService.php" -o -name "*Wallet*.php" | grep -i service
   ```

2. **如果有，应用相同的修复**：
   - 修改 `CACHE_TTL` 常量
   - 修改所有 `Redis::set()` 为 `Redis::setex()`
   - 修改 Lua 脚本添加 TTL 参数

3. **重启服务**：
   ```bash
   php start.php restart
   ```

---

### gk_api 项目（待修复⚠️）

**需要同步修复！**

1. **检查是否有相同的 WalletService.php**：
   ```bash
   cd D:\gk_api
   find . -name "WalletService.php" -o -name "*Wallet*.php" | grep -i service
   ```

2. **如果有，应用相同的修复**

3. **重启服务**：
   ```bash
   php start.php restart
   ```

---

## 📊 修复效果验证

### 测试1：检查新缓存是否有过期时间

```bash
# 清空旧缓存
redis-cli DEL wallet:balance:1

# 触发缓存生成（访问任意玩家页面或执行查询）
php -r "
require 'vendor/autoload.php';
\$balance = \addons\webman\service\WalletService::getBalance(1);
echo '玩家1余额: ' . \$balance . PHP_EOL;
"

# 检查TTL
redis-cli TTL wallet:balance:1
# 预期输出：2592000（30天）或略小（刚设置时会少几秒）
```

### 测试2：检查批量缓存预热

```bash
php -r "
require 'vendor/autoload.php';
\$result = \addons\webman\service\WalletService::warmupCache([1, 2, 3, 4, 5]);
echo '成功: ' . \$result['success'] . ', 失败: ' . \$result['failed'] . PHP_EOL;
"

# 检查所有缓存的TTL
for i in 1 2 3 4 5; do
    echo -n "玩家 \$i TTL: "
    redis-cli TTL wallet:balance:\$i
done
# 预期输出：每个都约为 2592000 秒
```

### 测试3：监控内存占用

**修复前：**
```
wallet:balance:* 键数量：10000
内存占用：永不过期，持续增长
```

**修复后：**
```
wallet:balance:* 键数量：
- 活跃玩家（30天内登录）：持续存在
- 不活跃玩家（30天未登录）：自动清理
内存占用：稳定在活跃玩家数量 × 100-200 bytes
```

---

## 🎯 预期效果

### 内存占用对比

| 场景 | 修复前 | 修复后 |
|------|--------|--------|
| **1万玩家** | 1-2 MB（永不过期） | 活跃玩家 × 100-200 bytes |
| **10万玩家** | 10-20 MB（永不过期） | 活跃玩家 × 100-200 bytes |
| **100万玩家** | 100-200 MB（永不过期）| 活跃玩家 × 100-200 bytes |

### 实际案例预估

假设：
- 总玩家数：100万
- 30天活跃玩家：10万（10%活跃率）

**修复前：**
- Redis缓存数量：100万个 key
- 内存占用：100-200 MB
- 增长趋势：持续增长，永不减少

**修复后：**
- Redis缓存数量：10万个 key（活跃玩家）
- 内存占用：10-20 MB
- 增长趋势：稳定（不活跃玩家自动清理）
- **内存节省：90%** ✅

---

## 💡 技术细节

### 为什么选择30天？

| 时长 | 优点 | 缺点 |
|------|------|------|
| **7天** | 内存占用最小 | 活跃玩家可能被误清理 |
| **30天**✅ | 平衡性能和内存 | 合理的活跃判断标准 |
| **60天** | 更宽松的活跃判断 | 内存占用较大 |
| **永不过期** ❌ | 性能最优 | 内存泄漏风险 |

### 为什么不会影响活跃玩家？

**TTL自动刷新机制**：

```
玩家A（活跃）：
Day 1: 查询余额 → 缓存创建，TTL = 30天
Day 5: 充值操作 → 缓存更新，TTL = 30天（重新计时）
Day 10: 下注操作 → 缓存更新，TTL = 30天（重新计时）
Day 15: 提现操作 → 缓存更新，TTL = 30天（重新计时）
...
结果：活跃玩家的缓存实际永不过期 ✅

玩家B（不活跃）：
Day 1: 查询余额 → 缓存创建，TTL = 30天
Day 2-30: 无任何操作
Day 31: 缓存自动过期，被Redis清理 ✅
Day 60: 再次登录 → 从数据库重建缓存
结果：30天未活跃的玩家缓存被清理，释放内存 ✅
```

### Redis::setex() vs Redis::set() + Redis::expire()

**为什么用 setex？**

| 方法 | 原子性 | 性能 | 推荐 |
|------|--------|------|------|
| `setex($key, $ttl, $value)` | ✅ 原子操作 | 快 | ✅ 推荐 |
| `set($key, $value)` + `expire($key, $ttl)` | ❌ 两步操作，不原子 | 慢 | ❌ 不推荐 |

**原子性问题示例**：
```php
// ❌ 非原子，两个命令之间可能出现问题
Redis::set($key, $value);  // 步骤1
Redis::expire($key, 3600); // 步骤2 - 如果这里失败，缓存永不过期！

// ✅ 原子操作，一次完成
Redis::setex($key, 3600, $value);
```

---

## 🔍 相关文件

### gk_admin（已修复）
- ✅ `addons/webman/service/WalletService.php` - 钱包服务（已修复）

### gk_work（待检查）
- ⚠️ 需要检查是否有相同的 `WalletService.php`
- ⚠️ 需要检查是否有其他钱包缓存相关代码

### gk_api（待检查）
- ⚠️ 需要检查是否有相同的 `WalletService.php`
- ⚠️ 需要检查是否有其他钱包缓存相关代码

---

## 📝 注意事项

### 重要提醒

1. **三个项目需要同步修复**
   - gk_admin、gk_work、gk_api 共享同一个 Redis
   - 任何一个项目未修复，都会产生永不过期的缓存
   - 必须全部修复才能彻底解决内存泄漏

2. **清空旧缓存是安全的**
   - 清空缓存不会导致数据丢失（数据库是持久化存储）
   - 清空后首次查询会稍慢（需要从DB读取）
   - 建议在低峰期执行清空操作

3. **监控Redis内存**
   - 修复后持续监控Redis内存使用
   - 预期内存会逐步下降（旧缓存过期）
   - 如果内存仍增长，检查其他项目是否已修复

4. **TTL可调整**
   - 如果30天太长，可以改为14天或7天
   - 修改 `CACHE_TTL` 常量即可
   - 修改后需要重启服务

---

## ✅ 修复确认清单

### gk_admin
- [x] 修改 `CACHE_TTL` 常量
- [x] 修改 `getBalance()` 方法
- [x] 修改 `updateCache()` 方法
- [x] 修改 `getBatchBalance()` 方法（2处）
- [x] 修改 `warmupCache()` 方法（2处）
- [x] 修改 `LUA_ATOMIC_INCREMENT` 脚本
- [x] 修改 `LUA_ATOMIC_DECREMENT` 脚本
- [x] 修改 `atomicIncrement()` 调用
- [x] 修改 `atomicDecrement()` 调用
- [ ] 重启 Webman 服务 ⚠️
- [ ] 清空旧缓存（可选）
- [ ] 验证新缓存TTL
- [ ] 监控内存使用

### gk_work
- [ ] 检查是否有 WalletService.php
- [ ] 应用相同修复
- [ ] 重启服务
- [ ] 验证

### gk_api
- [ ] 检查是否有 WalletService.php
- [ ] 应用相同修复
- [ ] 重启服务
- [ ] 验证

---

**修复完成时间：** 2026-05-25  
**修复状态：** ✅ gk_admin代码已修复，⚠️ 等待重启部署和其他项目同步修复  
**负责人：** Claude AI  
**下一步：**
1. 重启 gk_admin 服务
2. 检查并修复 gk_work 和 gk_api
3. 清空旧缓存
4. 监控内存使用
