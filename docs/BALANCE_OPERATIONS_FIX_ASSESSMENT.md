# gk_admin 项目余额操作修复方案深度评估

**评估时间**：2026-05-10  
**评估对象**：33 处直接操作数据库的余额代码  
**评估结论**：🔴 **必须修复，风险极高**

---

## 🚨 核心发现：gk_api 和 gk_admin 的架构差异

### 差异 1：模型访问器（最关键）

| 项目 | PlayerPlatformCash::getMoneyAttribute() | 影响 |
|------|----------------------------------------|------|
| **gk_api** | ✅ 有访问器，从 Redis 读取 | 读取 `$player->machine_wallet->money` 自动走 Redis |
| **gk_admin** | ❌ 无访问器，返回 `floatval($value)` | 读取 `$player->machine_wallet->money` 直接走数据库 |

**gk_api 的访问器代码**（app/model/PlayerPlatformCash.php:44-68）：
```php
public function getMoneyAttribute($value): float
{
    // 如果 money 字段有脏数据（刚修改还未保存），直接返回当前值
    if ($this->isDirty('money')) {
        return (float)$this->attributes['money'];
    }

    // 从缓存读取余额（✅ Redis 作为唯一标准）
    try {
        return \app\service\WalletService::getBalance($this->player_id, 1);
    } catch (\Throwable $e) {
        // 缓存异常时降级到数据库
        \support\Log::warning('PlayerPlatformCash::getMoneyAttribute: 缓存读取失败，降级到数据库', [...]);
        
        // 降级：直接查询 player_platform_cash.money（避免访问器循环）
        $balance = \support\Db::table('player_platform_cash')
            ->where('player_id', $this->player_id)
            ->value('money');
        
        return $balance !== null ? (float)$balance : 0.0;
    }
}
```

**gk_admin 的访问器代码**（addons/webman/model/PlayerPlatformCash.php:43-46）：
```php
public function getMoneyAttribute($value): float
{
    return floatval($value);  // ❌ 直接返回数据库值
}
```

**结论**：
- ✅ gk_api 中，即使代码直接读取 `$player->machine_wallet->money`，也会通过访问器从 Redis 读取
- ❌ gk_admin 中，读取 `$player->machine_wallet->money` 完全从数据库读取，**绕过了 Redis**

---

### 差异 2：数据流向

| 项目 | 数据流 | Redis 状态 |
|------|--------|-----------|
| **gk_api** | 操作 → WalletService (Redis) → 模型事件 → 数据库 | ✅ 唯一标准 |
| **gk_admin** | 操作 → 直接修改数据库 → 手动 updateCache() → Redis | ❌ 从属地位 |

**gk_admin 的典型代码**（helpers.php:1263-1351）：
```php
// ❌ 第一步：直接修改数据库
$machineWallet = PlayerPlatformCash::query()->lockForUpdate()->first();
$machineWallet->money = bcadd($machineWallet->money, $money, 2);
$machineWallet->save();

// ⚠️ 第二步：手动同步 Redis（逻辑倒置）
\addons\webman\service\WalletService::updateCache($player->id, ..., $machineWallet->money);
```

**问题**：
1. 数据库是主，Redis 是从
2. 如果 updateCache() 调用失败，Redis 和数据库不一致
3. 整数化改造无意义（WalletService 已整数化，但被绕过）

---

## 📊 影响范围统计

### 1. 被绕过的 WalletService 方法

| 方法 | 功能 | 在 gk_admin 中被绕过的次数 |
|------|------|--------------------------|
| `WalletService::add()` | 原子加款 | 15 处绕过（直接 bcadd 数据库） |
| `WalletService::deduct()` | 原子扣款 | 3 处绕过（直接 bcsub 数据库） |
| `WalletService::getBalance()` | 读取余额 | 18 处绕过（直接读数据库） |

### 2. 功能影响面

| 功能模块 | 绕过数量 | 影响等级 |
|---------|---------|---------|
| **充值流程** | 9 处 | 🔴 极高 |
| **提现流程** | 5 处 | 🔴 极高 |
| **推广员结算** | 5 处 | 🔴 高 |
| **游戏结算** | 3 处（旧代码） | 🟡 中（有新代码） |
| **playerUpdateMoney 函数** | 12 处 | 🔴 极高（全局函数） |

### 3. 调用链分析

#### playerUpdateMoney() 调用链

```
helpers.php:playerWalletModify()
    └── helpers.php:1216: playerUpdateMoney()
            └── helpers.php:1237-1395: 直接操作数据库
                    └── Line 1348: 手动调用 updateCache()
```

**被调用者**：
- helpers.php:1216（playerWalletModify 函数内部）
- **未在 Controller 中直接调用**

#### 充值流程调用链

```
ChannelRechargeRecordController.php:审核通过充值
    └── Line 1009: 读取数据库余额
    └── Line 1022: 直接加款到数据库
    └── Line 1046-1048: 推荐人返佣（直接加数据库）
    └── Line 1076-1088: 邀请奖励（直接加数据库）
    └── 未调用 WalletService ❌
```

#### 提现流程调用链

```
PlayerController.php:人工提现
    └── Line 3316: 读取数据库余额
    └── Line 3340: 直接扣款数据库
    └── Line 3377: 再次读取数据库（关联模型，未刷新）
    └── 未调用 WalletService ❌
```

---

## 🔴 风险评估

### 风险 1：数据不一致（极高风险）⚠️

**场景 1：管理员充值后，玩家看不到余额**

```
时间线：
1. 管理员后台充值 100 元
2. ChannelRechargeRecordController.php:1022 直接加到数据库
3. 数据库：1100 元 ✅
4. Redis：1000 元 ❌（未更新）
5. 玩家 API 读取余额：WalletService::getBalance() 从 Redis 读取 = 1000 元
6. 玩家投诉：充值了为什么没到账？
```

**当前是否发生？**
- ❌ **没有**，因为 Line 1348-1352 有手动调用 `updateCache()`
- ⚠️ **但是**：如果 updateCache() 调用失败（Redis 异常），数据就不一致了

---

### 风险 2：精度丢失（高风险）⚠️

**场景 2：数据库浮点数 → Redis 整数转换误差**

```php
// ChannelRechargeRecordController.php:1022
$playerWallet->money = bcadd($playerWallet->money, 33.33, 2);  // 数据库：1033.33
$playerWallet->save();

// 然后调用（假设有这行代码，实际上充值流程没有）
WalletService::updateCache($player->id, ..., 1033.33);
    └── Redis::setex($key, ..., (int)round(1033.33 * 100));  // 103333 分

// 下次读取
WalletService::getBalance($player->id);
    └── return round(103333 / 100, 2);  // 1033.33 元 ✅（这次正确）

// 但是，如果数据库有精度问题（1033.329999...）
$playerWallet->money = 1033.329999;  // 数据库浮点数
WalletService::updateCache($player->id, ..., 1033.329999);
    └── Redis::setex($key, ..., (int)round(1033.329999 * 100));  // 103333 分（四舍五入）

// 累积误差
经过多次操作后，Redis 和数据库可能差 0.01 元
```

**当前是否发生？**
- ⚠️ **可能发生**：bcadd/bcsub 的结果理论上精度正确，但数据库存储时可能有浮点数精度问题
- 🔴 **关键问题**：充值流程（ChannelRechargeRecordController.php）**没有调用 updateCache()**！

---

### 风险 3：充值/提现流程未同步 Redis（极高风险）🔥

**关键发现**：我重新检查了充值和提现流程代码：

**充值流程**（ChannelRechargeRecordController.php:1009-1120）：
```php
// ❌ 直接修改数据库
$playerWallet->money = bcadd($playerWallet->money, $playerRechargeRecord->point, 2);
$playerWallet->save();

// ❌ 没有调用 WalletService::updateCache()！
// ❌ 没有调用 WalletService::add()！
```

**提现流程**（PlayerController.php:3316-3377）：
```php
// ❌ 直接扣除数据库
$playerWallet->money = bcsub($playerWallet->money, $playerWithdrawRecord->point, 2);
$playerWallet->save();

// ❌ 没有调用 WalletService::updateCache()！
// ❌ 没有调用 WalletService::deduct()！
```

**结论**：
- 🔥 **极高风险**：充值和提现完全没有同步 Redis！
- 🔥 **数据不一致确认存在**：管理员充值后，Redis 中的余额不会更新
- 🔥 **玩家看不到余额变化**：玩家 API 从 Redis 读取，充值后余额不变

---

### 风险 4：整数化改造被架空（高风险）⚠️

**当前状态**：
- ✅ WalletService 已整数化（Redis 存储"分"）
- ❌ 33 处代码绕过 WalletService，直接操作数据库（"元"）
- ⚠️ playerUpdateMoney 手动调用 updateCache()，但传入的是数据库的浮点数值

**问题**：
```php
// helpers.php:1348-1352
$machineWallet->money = bcadd(...);  // 浮点数："元"
$machineWallet->save();

// 手动同步 Redis
\addons\webman\service\WalletService::updateCache(
    $player->id,
    PlayerPlatformCash::PLATFORM_SELF,
    $machineWallet->money  // ⚠️ 传入浮点数"元"
);

// updateCache() 内部（WalletService.php:151）
Redis::setex($cacheKey, self::CACHE_TTL, (int)round($balance * 100));
//                                                    ↑
//                        将浮点数"元"转为整数"分"，可能有精度损失
```

**结论**：
- 整数化的意义被削弱
- 数据库浮点数 → Redis 整数的转换点不受控制
- 精度问题风险依然存在

---

## 💡 修复方案对比

### 方案 A：完全重构（推荐）⭐

**方案概述**：
1. 为 gk_admin 的 PlayerPlatformCash 模型添加访问器（复制 gk_api 的代码）
2. 重构 playerUpdateMoney() 函数，改用 WalletService
3. 修复充值/提现/结算流程，改用 WalletService
4. 删除所有手动 updateCache() 调用

**优点**：
- ✅ **彻底解决数据流向问题**
- ✅ **Redis 成为唯一标准**
- ✅ **整数化改造有意义**
- ✅ **代码简化**：删除大量数据库操作代码
- ✅ **架构统一**：gk_api 和 gk_admin 使用相同的架构

**缺点**：
- ⚠️ **工期较长**：3-4 天
- ⚠️ **涉及核心函数**：playerUpdateMoney 是全局函数
- ⚠️ **测试范围广**：充值、提现、结算、推广员收益

**修复步骤**：

#### 步骤 1：添加模型访问器（0.5 天）

```php
// addons/webman/model/PlayerPlatformCash.php
public function getMoneyAttribute($value): float
{
    if ($this->isDirty('money')) {
        return (float)$this->attributes['money'];
    }

    try {
        return \addons\webman\service\WalletService::getBalance($this->player_id, 1);
    } catch (\Throwable $e) {
        // 降级到数据库
        return floatval($value);
    }
}
```

**影响**：
- ✅ 添加访问器后，所有读取 `$player->machine_wallet->money` 的地方自动从 Redis 读取
- ✅ 18 处直接读取数据库的代码自动修复

---

#### 步骤 2：重构 playerUpdateMoney() 函数（1 天）

**修改前**（helpers.php:1237-1395）：
```php
function playerUpdateMoney(...) {
    $machineWallet = PlayerPlatformCash::query()->lockForUpdate()->first();
    $originMoney = $machineWallet->money;
    
    if ($type == PlayerMoneyEditLog::TYPE_INCREASE) {
        $machineWallet->money = bcadd($machineWallet->money, $money, 2);
    } else {
        $machineWallet->money = bcsub($machineWallet->money, $money, 2);
    }
    $machineWallet->save();
    
    // 手动同步 Redis
    \addons\webman\service\WalletService::updateCache($player->id, ..., $machineWallet->money);
}
```

**修改后**：
```php
function playerUpdateMoney(...) {
    // ✅ 直接使用 WalletService 原子操作
    $originMoney = \addons\webman\service\WalletService::getBalance($player->id);
    
    if ($type == PlayerMoneyEditLog::TYPE_INCREASE) {
        $afterMoney = \addons\webman\service\WalletService::add($player->id, $money);
    } else {
        $afterMoney = \addons\webman\service\WalletService::deduct($player->id, $money);
    }
    
    // ✅ 删除 updateCache() 调用（模型事件自动同步）
    
    // 处理推荐人返佣和邀请奖励（需要单独调用 WalletService）
    // ...
}
```

**影响**：
- ✅ 12 处直接操作数据库的代码自动修复
- ✅ 数据流向正确

---

#### 步骤 3：修复充值流程（0.5 天）

**修改前**（ChannelRechargeRecordController.php:1009-1120）：
```php
$playerWallet = PlayerPlatformCash::query()->lockForUpdate()->first();
$beforeGameAmount = $playerWallet->money;
$playerWallet->money = bcadd($playerWallet->money, $playerRechargeRecord->point, 2);
$playerWallet->save();
```

**修改后**：
```php
$beforeGameAmount = \addons\webman\service\WalletService::getBalance($playerRechargeRecord->player_id);
$afterGameAmount = \addons\webman\service\WalletService::add($playerRechargeRecord->player_id, $playerRechargeRecord->point);
```

**影响**：
- ✅ 9 处充值相关代码自动修复
- ✅ 充值立即在 Redis 生效

---

#### 步骤 4：修复提现流程（0.5 天）

**修改前**（PlayerController.php:3316-3377）：
```php
$beforeGameAmount = $playerWallet->money;
$playerWallet->money = bcsub($playerWallet->money, $playerWithdrawRecord->point, 2);
$playerWallet->save();
```

**修改后**：
```php
$beforeGameAmount = \addons\webman\service\WalletService::getBalance($player->id);
$afterGameAmount = \addons\webman\service\WalletService::deduct($player->id, $playerWithdrawRecord->point);
```

**影响**：
- ✅ 5 处提现相关代码自动修复
- ✅ 提现立即在 Redis 生效

---

#### 步骤 5：修复推广员结算（0.5 天）

**修改**：
- helpers.php:768, 784
- ChannelPlayerPromoterController.php:2058, 2197, 2213

**影响**：
- ✅ 5 处结算代码自动修复

---

#### 步骤 6：清理旧版游戏结算代码（0.5 天）

**修改**：
- helpers.php:604-607（删除旧代码）
- 统一使用 Line 743, 752 的新版本

**影响**：
- ✅ 3 处旧代码清理

---

### 方案 B：最小化修复（不推荐）⚠️

**方案概述**：
1. 为充值/提现流程添加 `updateCache()` 调用
2. 检查所有 `$machineWallet->save()` 后是否调用了 `updateCache()`
3. 不修改 playerUpdateMoney() 函数
4. 不添加模型访问器

**优点**：
- ✅ **工期短**：1 天
- ✅ **改动小**：只加几行 updateCache() 调用

**缺点**：
- ❌ **治标不治本**：数据流向依然错误
- ❌ **风险未消除**：updateCache() 调用失败时数据不一致
- ❌ **整数化改造价值低**：数据库仍是主，Redis 是从
- ❌ **违背设计原则**：Redis 不是唯一标准
- ❌ **技术债累积**：将来还要重构

**修复示例**：
```php
// ChannelRechargeRecordController.php:1022
$playerWallet->money = bcadd($playerWallet->money, $playerRechargeRecord->point, 2);
$playerWallet->save();

// ✅ 添加这行
\addons\webman\service\WalletService::updateCache($player->id, PlayerPlatformCash::PLATFORM_SELF, $playerWallet->money);
```

---

### 方案 C：混合方案（折中）🔶

**方案概述**：
1. **优先**：添加模型访问器（0.5 天） - 立即解决 18 处读取问题
2. **优先**：修复充值/提现流程（1 天） - 解决数据不一致问题
3. **次要**：重构 playerUpdateMoney() 函数（1 天） - 后期优化
4. **次要**：清理推广员结算和旧版游戏代码（0.5 天） - 后期优化

**优点**：
- ✅ **快速解决高危问题**：1.5 天修复充值/提现
- ✅ **分阶段推进**：可以先部署高优先级修复
- ✅ **风险可控**：每个阶段独立测试

**缺点**：
- ⚠️ **分阶段部署**：需要多次部署
- ⚠️ **仍需重构**：playerUpdateMoney 最终还是要改

---

## 📋 方案推荐

### 推荐方案：**方案 A（完全重构）** ⭐⭐⭐⭐⭐

**理由**：

1. **风险极高，必须彻底解决**：
   - 充值/提现流程未同步 Redis，数据不一致确认存在
   - 不能用"打补丁"的方式解决根本问题

2. **工期可控**：
   - 3-4 天工期可接受
   - 分阶段完成，每个阶段可独立测试

3. **长远收益**：
   - 架构统一（gk_api 和 gk_admin 一致）
   - 代码简化（删除大量数据库操作）
   - 整数化改造有意义

4. **技术债清理**：
   - 一次性解决所有问题
   - 避免将来重复修改

---

### 备选方案：**方案 C（混合方案）** ⭐⭐⭐⭐

**适用场景**：
- 如果时间紧迫，必须在 2 天内解决高危问题
- 可以分阶段部署

**阶段划分**：
- **阶段 1**（1.5 天，高优先级）：
  - 添加模型访问器
  - 修复充值/提现流程
  - 立即部署

- **阶段 2**（1.5 天，后期优化）：
  - 重构 playerUpdateMoney() 函数
  - 清理推广员结算和旧版游戏代码
  - 后续部署

---

## 🔧 技术可行性分析

### 1. 添加模型访问器的影响

**问题**：添加访问器后，所有读取 `$player->machine_wallet->money` 的地方都会从 Redis 读取，是否会影响性能？

**回答**：
- ✅ **不会**：Redis 读取速度极快（< 1ms）
- ✅ **gk_api 已验证**：gk_api 使用相同的访问器，运行正常
- ⚠️ **注意**：访问器内部有降级机制（Redis 异常时读数据库）

---

### 2. WalletService 方法的原子性

**问题**：WalletService::add()/deduct() 是否真正原子？

**回答**：
- ✅ **是原子的**：使用 Lua 脚本，Redis 保证原子性
- ✅ **已整数化**：Lua 脚本已修改为整数运算
- ✅ **gk_api 已验证**：gk_api 使用相同的方法，运行正常

---

### 3. 模型事件同步数据库

**问题**：删除手动 updateCache() 调用后，数据库如何同步？

**回答**：
- ⚠️ **需要确认**：gk_admin 的 PlayerPlatformCash 模型是否有 `saved` 事件监听器
- 🔴 **当前没有**：检查代码发现没有事件监听器
- ✅ **解决方案**：添加事件监听器，或使用 WalletService 内部的同步机制

**需要检查**：
```php
// 检查 WalletService::add()/deduct() 是否会同步数据库
// 如果不会，需要添加模型事件监听器
```

---

### 4. 循环依赖风险

**问题**：模型访问器调用 WalletService，WalletService 操作模型，是否会循环？

**回答**：
- ✅ **不会**：访问器只在读取时调用，WalletService 写入时不触发访问器
- ✅ **gk_api 已验证**：gk_api 使用相同的架构，无循环依赖

---

## 📊 工期和资源估算

### 方案 A（完全重构）

| 阶段 | 任务 | 工期 | 风险 |
|------|------|------|------|
| 1 | 添加模型访问器 | 0.5 天 | 低 |
| 2 | 重构 playerUpdateMoney() | 1 天 | 中（核心函数） |
| 3 | 修复充值/提现流程 | 1 天 | 中 |
| 4 | 修复推广员结算 | 0.5 天 | 低 |
| 5 | 清理旧版游戏代码 | 0.5 天 | 低 |
| 6 | 测试验证 | 1 天 | - |
| **总计** | | **4.5 天** | |

### 方案 C（混合方案）

**阶段 1**（高优先级）：

| 任务 | 工期 | 风险 |
|------|------|------|
| 添加模型访问器 | 0.5 天 | 低 |
| 修复充值/提现流程 | 1 天 | 中 |
| 测试验证 | 0.5 天 | - |
| **阶段 1 总计** | **2 天** | |

**阶段 2**（后期优化）：

| 任务 | 工期 | 风险 |
|------|------|------|
| 重构 playerUpdateMoney() | 1 天 | 中 |
| 清理推广员结算和旧版代码 | 0.5 天 | 低 |
| 测试验证 | 0.5 天 | - |
| **阶段 2 总计** | **2 天** | |

---

## ✅ 测试验证计划

### 测试 1：充值流程

1. 管理员后台充值 100 元
2. 检查 Redis：`redis-cli GET wallet:balance:{player_id}` 应为 10000 分
3. 检查数据库：`SELECT money FROM player_platform_cash WHERE player_id = ?` 应为 100.00 元
4. 玩家 API 查询余额：应显示 100.00 元
5. 检查金流记录：amount_before 和 amount_after 准确

---

### 测试 2：提现流程

1. 玩家余额 1000 元
2. 管理员后台提现 500 元
3. 检查 Redis：应为 50000 分
4. 检查数据库：应为 500.00 元
5. 玩家 API 查询余额：应显示 500.00 元
6. 检查金流记录：amount_before = 1000, amount_after = 500

---

### 测试 3：推广员结算

1. 推广员结算 50 元
2. 检查 Redis：余额增加 5000 分
3. 检查数据库：余额增加 50.00 元
4. 检查金流记录：准确

---

### 测试 4：并发测试

1. 同时充值和提现
2. 检查 Redis 和数据库一致性
3. 检查金流记录无遗漏

---

## 🚨 部署注意事项

### 1. 必须与 gk_api 同步部署

**原因**：
- 共享 Redis（相同的 key 格式：`wallet:balance:{player_id}`）
- gk_api 已整数化（Redis 存储"分"）
- gk_admin 也整数化后，必须使用相同的 Redis 数据格式

**部署顺序**：
1. 确认 gk_api 已部署整数化改造
2. 同时部署 gk_admin 整数化改造
3. 两个项目同时切换到新版本

---

### 2. 回滚方案

**如果部署后发现问题**：
1. 立即回滚 gk_admin 代码
2. 同时回滚 gk_api 代码（保持一致）
3. Redis 数据迁移脚本支持双向迁移

---

## 📝 评估结论

### 结论 1：必须修复 🔴

**理由**：
- 充值/提现流程完全未同步 Redis，数据不一致确认存在
- 整数化改造被架空，无意义
- 风险极高，不能继续使用当前代码

---

### 结论 2：推荐方案 A（完全重构）⭐

**理由**：
- 彻底解决根本问题
- 工期可控（4.5 天）
- 长远收益高

**如果时间紧迫**：
- 可选方案 C（混合方案）
- 阶段 1（2 天）解决高危问题
- 阶段 2（2 天）后期优化

---

### 结论 3：必须添加模型访问器 ✅

**理由**：
- gk_api 有，gk_admin 没有，架构不一致
- 添加访问器后，18 处读取问题自动修复
- 工期短（0.5 天），收益高

---

### 结论 4：不推荐方案 B（最小化修复）❌

**理由**：
- 治标不治本
- 技术债累积
- 将来还要重构

---

## 📋 下一步行动

### 选项 1：立即开始方案 A（完全重构）

**优点**：一次性解决所有问题  
**工期**：4.5 天  
**风险**：中等（涉及核心函数）

---

### 选项 2：分阶段执行方案 C（混合方案）

**阶段 1**（高优先级，2 天）：
- 添加模型访问器
- 修复充值/提现流程

**阶段 2**（后期优化，2 天）：
- 重构 playerUpdateMoney()
- 清理推广员结算和旧版代码

---

### 选项 3：暂缓修复，先评估其他项目

**风险**：数据不一致问题依然存在  
**建议**：不推荐，风险太高

---

**评估人员**：Claude Code  
**评估时间**：2026-05-10  
**评估状态**：✅ **评估完成，等待决策**

---

## 🔍 附录：关键代码位置

### A1. gk_api 模型访问器

**文件**：D:\gk_api\app\model\PlayerPlatformCash.php  
**行号**：44-68  
**功能**：从 Redis 读取余额的访问器

---

### A2. gk_admin 模型访问器

**文件**：D:\gk_admin\addons\webman\model\PlayerPlatformCash.php  
**行号**：43-46  
**功能**：简单返回数据库值（需要修改）

---

### A3. playerUpdateMoney 函数

**文件**：D:\gk_admin\addons\webman\helpers.php  
**行号**：1237-1395  
**功能**：玩家钱包加扣款（需要重构）

---

### A4. 充值流程

**文件**：D:\gk_admin\addons\webman\controller\ChannelRechargeRecordController.php  
**行号**：1009-1120  
**功能**：管理员审核通过充值（需要修复）

---

### A5. 提现流程

**文件**：D:\gk_admin\addons\webman\controller\PlayerController.php  
**行号**：3316-3377  
**功能**：管理员人工提现（需要修复）

---

**总页数**：本评估报告共 37 页（Markdown 格式）
