# gk_admin 项目 WalletService 整数化改造评估

**项目**：gk_admin（后台管理系统）  
**评估时间**：2026-05-10  
**对比基准**：gk_api 项目已完成整数化改造

---

## 一、项目对比

| 项目 | 文件位置 | 方法数量 | Lua 脚本 | 复杂度 |
|------|---------|---------|---------|-------|
| **gk_api** | `app/service/WalletService.php` | 11 | 3 个（INCREMENT, DECREMENT, WASH） | 🔴 高 |
| **gk_admin** | `addons/webman/service/WalletService.php` | 8 | 2 个（INCREMENT, DECREMENT） | 🟡 中 |

### 关键差异

1. **功能范围**：
   - gk_admin **没有** atomicWash()（洗分功能仅在客户端）
   - gk_admin **没有** atomicDeduct()/atomicAdd() 私有方法
   - gk_admin 仅用于后台管理员操作（人工充值、扣款）

2. **精度问题**：
   - ⚠️ Lua 脚本仍使用浮点数 + 0.01 容差
   - ⚠️ Redis 存储仍为浮点数（"元"）
   - ⚠️ 需要整数化改造

---

## 二、需要整数化的地方（8 处）

### 1️⃣ Redis 读取层（2 处）

| 方法 | 行号 | 当前逻辑 | 改造内容 |
|------|------|---------|---------|
| `getBalance()` | 52-54 | 读取浮点数 | ✅ 分 ÷ 100 → 元 |
| `getBatchBalance()` | 252-296 | 批量读取浮点数 | ✅ 分 ÷ 100 → 元 |

---

### 2️⃣ Redis 写入层（3 处）

| 方法 | 行号 | 当前逻辑 | 改造内容 |
|------|------|---------|---------|
| `updateCache()` | 151 | 写入浮点数 | ✅ 元 × 100 → 分 |
| `warmupCache()` | 385, 397 | 写入浮点数、0.0 | ✅ 元 × 100 → 分，整数 0 |
| `getBatchBalance()` 回填 | 283, 296 | 回填浮点数 | ✅ 元 × 100 → 分 |

---

### 3️⃣ Lua 脚本层（2 个脚本）

| 脚本常量 | 行号 | 当前逻辑 | 改造内容 |
|---------|------|---------|---------|
| `LUA_ATOMIC_INCREMENT` | 438-453 | 浮点数加法 | ✅ 整数加法 |
| `LUA_ATOMIC_DECREMENT` | 468-494 | 浮点数减法 + 0.01 容差 | ✅ 整数减法，移除容差 |

**关键问题**：
```lua
-- 行 476-479：浮点数精度容差（需移除）
local tolerance = 0.01
if currentBalance + tolerance < amount then
    return cjson.encode({ok = 0, error = 'insufficient_balance', ...})
end
```

---

### 4️⃣ 原子操作层（2 个方法）

| 方法 | 行号 | 改造内容 |
|------|------|---------|
| `atomicIncrement()` | 507-552 | 参数 × 100，返回 ÷ 100 |
| `atomicDecrement()` | 563-625 | 参数 × 100，返回 ÷ 100 |

---

## 三、改造清单

### ✅ Phase 1：Lua 脚本整数化（2 个）

#### 脚本 1：LUA_ATOMIC_INCREMENT（行 438-453）

**修改前**：
```lua
local currentBalance = tonumber(redis.call('GET', key)) or 0
local newBalance = currentBalance + amount  -- ❌ 浮点数加法
redis.call('SETEX', key, ttl, newBalance)
```

**修改后**：
```lua
local amountInCents = math.floor(tonumber(ARGV[1]) + 0.5)  -- 确保整数
local currentBalanceInCents = tonumber(redis.call('GET', key)) or 0
local newBalanceInCents = currentBalanceInCents + amountInCents  -- ✅ 整数加法
redis.call('SETEX', key, ttl, newBalanceInCents)
return cjson.encode({ok = 1, balance = newBalanceInCents, old = currentBalanceInCents, new = newBalanceInCents})
```

---

#### 脚本 2：LUA_ATOMIC_DECREMENT（行 468-494）

**修改前**：
```lua
local currentBalance = tonumber(redis.call('GET', key)) or 0
local tolerance = 0.01  -- ❌ 浮点数容差
if currentBalance + tolerance < amount then
    return cjson.encode({ok = 0, error = 'insufficient_balance', ...})
end
local newBalance = currentBalance - amount  -- ❌ 浮点数减法
```

**修改后**：
```lua
local amountInCents = math.floor(tonumber(ARGV[1]) + 0.5)
local currentBalanceInCents = tonumber(redis.call('GET', key)) or 0
-- ✅ 整数比较，无需容差
if currentBalanceInCents < amountInCents then
    return cjson.encode({ok = 0, error = 'insufficient_balance', ...})
end
local newBalanceInCents = currentBalanceInCents - amountInCents  -- ✅ 整数减法
```

---

### ✅ Phase 2：方法参数转换（2 个）

#### 方法 1：atomicIncrement()（行 519-543）

**修改前**：
```php
$result = Redis::eval(
    self::LUA_ATOMIC_INCREMENT,
    1,
    $cacheKey,  // KEYS[1]
    $amount,    // ARGV[1] ❌ 浮点数（元）
    $ttl
);
$newBalance = self::fixPrecision((float)$result);  // ❌ Lua 返回浮点数
```

**修改后**：
```php
// ✅ 元 × 100 → 分
$amountInCents = (int)round($amount * 100);

$result = Redis::eval(
    self::LUA_ATOMIC_INCREMENT,
    1,
    $cacheKey,
    $amountInCents,  // ARGV[1] ✅ 整数（分）
    $ttl
);

// ✅ Lua 返回 JSON：{ok: 1, balance: 新余额(分), ...}
$decoded = json_decode($result, true);
$newBalance = round($decoded['balance'] / 100, 2);  // ✅ 分 ÷ 100 → 元
```

---

#### 方法 2：atomicDecrement()（行 572-616）

**修改前**：
```php
$result = Redis::eval(
    self::LUA_ATOMIC_DECREMENT,
    1,
    $cacheKey,
    $amount,    // ARGV[1] ❌ 浮点数（元）
    $ttl
);
$decoded = json_decode($result, true);
$decoded['balance'] = self::fixPrecision((float)$decoded['balance']);  // ❌ 浮点数
```

**修改后**：
```php
// ✅ 元 × 100 → 分
$amountInCents = (int)round($amount * 100);

$result = Redis::eval(
    self::LUA_ATOMIC_DECREMENT,
    1,
    $cacheKey,
    $amountInCents,  // ARGV[1] ✅ 整数（分）
    $ttl
);

// ✅ 分 ÷ 100 → 元
$decoded = json_decode($result, true);
if (isset($decoded['balance'])) {
    $decoded['balance'] = round($decoded['balance'] / 100, 2);
}
if (isset($decoded['old'])) {
    $decoded['old'] = round($decoded['old'] / 100, 2);
}
```

---

### ✅ Phase 3：Redis 读写转换（5 处）

| 位置 | 行号 | 改造内容 |
|------|------|---------|
| getBalance() | 54 | `return round($cached / 100, 2);` |
| getBalance() 缓存回填 | 62 | `Redis::setex($cacheKey, ..., (int)round($balance * 100));` |
| updateCache() | 151 | `Redis::setex($cacheKey, ..., (int)round($balance * 100));` |
| getBatchBalance() | 256 | `$result[$playerId] = round($cached[$index] / 100, 2);` |
| getBatchBalance() 回填 | 283 | `Redis::setex($cacheKey, ..., (int)round($balance * 100));` |
| getBatchBalance() 零余额 | 296 | `Redis::setex($cacheKey, ..., 0);` |
| warmupCache() | 385 | `Redis::setex($cacheKey, ..., (int)round($balance * 100));` |
| warmupCache() 零余额 | 397 | `Redis::setex($cacheKey, ..., 0);` |

---

## 四、爆机检测（无需修改）

```php
// WalletService.php:836-837
$wasCrashed = $previousBalance >= $crashAmount;
$isCrashed = $currentBalance >= $crashAmount;
```

**说明**：
- `$previousBalance` 和 `$currentBalance` 都是"元"（从 atomicIncrement/Decrement 返回）
- `$crashAmount` 是配置表中的"元"
- ✅ **无需修改**

---

## 五、修改总量统计

| 类别 | 数量 |
|------|------|
| Lua 脚本修改 | 2 个 |
| 方法修改 | 2 个（atomicIncrement, atomicDecrement） |
| Redis 读取点修改 | 2 处（getBalance, getBatchBalance） |
| Redis 写入点修改 | 5 处（updateCache, getBatchBalance 回填×2, warmupCache×2） |
| **总计** | **11 处修改** |

---

## 六、与 gk_api 对比

| 改造项 | gk_api | gk_admin | 说明 |
|--------|--------|----------|------|
| Lua 脚本 | 3 个（+WASH） | 2 个 | gk_admin 无洗分功能 |
| 方法修改 | 11 个 | 8 个 | gk_admin 功能更简单 |
| Redis 读写点 | 9 处 | 7 处 | gk_admin 无洗分相关代码 |
| 复杂度 | 🔴 高 | 🟡 中 | gk_admin 改造更简单 |

---

## 七、风险评估

| 风险 | 等级 | 缓解措施 |
|------|------|---------|
| 数据迁移不一致 | 🔴 高 | 与 gk_api 同时迁移 Redis 数据 |
| 后台手动充值错误 | 🟡 中 | 参数转换前端无需改动 |
| 爆机检测误判 | 🟢 低 | 参数都是"元"，无需改动 |

---

## 八、改造顺序

### 阶段 1：代码改造（1 天）
- [ ] 修改 LUA_ATOMIC_INCREMENT 脚本（整数化）
- [ ] 修改 LUA_ATOMIC_DECREMENT 脚本（整数化，移除容差）
- [ ] 修改 atomicIncrement() 方法（参数转换）
- [ ] 修改 atomicDecrement() 方法（参数转换）
- [ ] 修改 getBalance() 读取转换
- [ ] 修改 updateCache() 写入转换
- [ ] 修改 getBatchBalance() 读写转换
- [ ] 修改 warmupCache() 写入转换

### 阶段 2：测试验证（1 天）
- [ ] 单元测试（充值、扣款、爆机检测）
- [ ] 集成测试（后台管理员充值）
- [ ] 验证与 gk_api 的 Redis 数据兼容性

### 阶段 3：与 gk_api 同步部署
- [ ] **必须**与 gk_api 同时进行 Redis 数据迁移
- [ ] 两个项目使用同一个 Redis，必须同步切换

---

## 九、验收标准

1. ✅ 管理员充值 100 元，Redis 存储 10000 分
2. ✅ 管理员扣款 50 元，余额准确减少
3. ✅ 爆机检测准确（2000.00 元触发，1999.99 不触发）
4. ✅ 批量查询余额准确
5. ✅ 与 gk_api 的 Redis 数据一致

---

## 十、总结

**改造范围**：比 gk_api 简单（少了洗分功能）  
**改造难度**：🟡 **中等**  
**预计工期**：**2 天**（1 天改造 + 1 天测试）  
**部署方式**：**必须与 gk_api 同步**（共享 Redis）

---

**评估人员**：Claude Code  
**评估时间**：2026-05-10  
**评估状态**：✅ **可以开始改造**
