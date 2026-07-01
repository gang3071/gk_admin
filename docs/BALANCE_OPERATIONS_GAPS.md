# gk_admin 项目余额操作遗漏检查报告

**检查时间**：2026-05-10  
**检查范围**：所有钱包余额的读取和变动操作  
**检查结果**：发现 **严重问题** - 大量直接操作数据库，违背 Redis 作为唯一标准

---

## 🔴 核心问题

### 问题 1：数据源混乱

**当前状态**：
- ✅ WalletService 已整数化（Redis 存储"分"）
- ❌ **大量代码直接操作数据库**（数据库存储"元"）
- ❌ **数据流向错误**：先改数据库 → 再同步 Redis

**正确的数据流**：
```
用户操作 → WalletService (Redis "分") → 模型事件 → 数据库同步 ("元")
         ↑
    唯一标准
```

**错误的数据流**（当前存在）：
```
用户操作 → 直接修改数据库 ("元") → 手动调用 updateCache() → Redis ("分")
         ↑
    不应该是这样！
```

---

## 🔴 高危问题清单（必须修复）

### 类别 1：充值/提现流程

| 文件 | 行号 | 问题 | 影响 |
|------|------|------|------|
| **ChannelRechargeRecordController.php** | 1009 | `$beforeGameAmount = $playerWallet->money;` | ❌ 充值前余额从数据库读取 |
| **ChannelRechargeRecordController.php** | 1022 | `$playerWallet->money = bcadd(...); ->save();` | 🔴 充值直接修改数据库 |
| **ChannelRechargeRecordController.php** | 1046-1048 | 推荐人返佣直接修改数据库 | 🔴 返佣余额不准确 |
| **ChannelRechargeRecordController.php** | 1076-1077 | 邀请奖励直接修改数据库 | 🔴 奖励余额不准确 |
| **ChannelRechargeRecordController.php** | 1120 | `$playerDeliveryRecord->amount_after = $playerWallet->money;` | ❌ 充值后余额从数据库读取 |
| **PlayerController.php** | 3316 | `$beforeGameAmount = $playerWallet->money;` | ❌ 提现前余额从数据库读取 |
| **PlayerController.php** | 3340-3341 | `$playerWallet->money = bcsub(...); ->save();` | 🔴 提现直接扣除数据库 |
| **PlayerController.php** | 3357 | `$playerDeliveryRecord->amount_after = $playerWallet->money;` | ❌ 提现后余额从数据库读取 |
| **PlayerController.php** | 3377 | `$playerMoneyEditLog->after_money = $player->machine_wallet->money;` | ❌ 记录余额从关联模型读取（未刷新） |

**影响**：
- 🔴 **数据不一致**：Redis 和数据库不同步
- 🔴 **精度丢失**：数据库存储浮点数，Redis 存储整数
- 🔴 **余额记录错误**：amount_before/amount_after 可能不准确

---

### 类别 2：推广员收益结算

| 文件 | 行号 | 问题 | 影响 |
|------|------|------|------|
| **helpers.php** | 768 | `$amountBefore = $playerPromoter->player->machine_wallet->money;` | ❌ 结算前余额从数据库读取 |
| **helpers.php** | 784 | `$playerPromoter->player->machine_wallet->money = $amountAfter;` | 🔴 结算直接赋值数据库 |
| **ChannelPlayerPromoterController.php** | 2058 | `$amountBefore = $playerPromoter->player->machine_wallet->money;` | ❌ 结算前余额从数据库读取 |
| **ChannelPlayerPromoterController.php** | 2197 | `$amountBefore = $playerPromoter->player->machine_wallet->money;` | ❌ 结算前余额从数据库读取 |
| **ChannelPlayerPromoterController.php** | 2213 | `$playerPromoter->player->machine_wallet->money = $amountAfter; ->save();` | 🔴 结算直接修改数据库 |

**影响**：
- 🔴 **推广员收益不准确**
- 🔴 **数据不一致**

---

### 类别 3：游戏结算

| 文件 | 行号 | 问题 | 影响 |
|------|------|------|------|
| **helpers.php** | 604 | `$beforeGameAmount = $machineWallet->money;` | ❌ 游戏前余额从数据库读取 |
| **helpers.php** | 605-606 | `$machineWallet->money = bcadd(...); ->save();` | 🔴 游戏结算直接修改数据库 |
| **helpers.php** | 607 | `$afterGameAmount = $machineWallet->money;` | ❌ 游戏后余额从数据库读取 |

**影响**：
- 🔴 **游戏结算余额不准确**
- ❌ 已有新版本使用 WalletService（Line 743, 752），但旧代码未清理

---

### 类别 4：walletUpdate 函数（最严重）

| 文件 | 行号 | 问题 | 影响 |
|------|------|------|------|
| **helpers.php** | 1263 | `$originMoney = $machineWallet->money;` | ❌ 原始余额从数据库读取 |
| **helpers.php** | 1265 | `$machineWallet->money = bcadd(...);` | 🔴 直接修改数据库（增加） |
| **helpers.php** | 1280 | `$beforeRechargeAmount = $recommendPlayerWallet->money;` | ❌ 推荐人余额从数据库读取 |
| **helpers.php** | 1282 | `$recommendPlayerWallet->money = bcadd(...);` | 🔴 推荐人返佣直接修改数据库 |
| **helpers.php** | 1294 | `$playerDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;` | ❌ 返佣后余额从关联模型读取 |
| **helpers.php** | 1309 | `$amount_before = $recommendPlayerWallet->money;` | ❌ 邀请奖励前余额从数据库读取 |
| **helpers.php** | 1310 | `$recommendPlayerWallet->money = bcadd(...);` | 🔴 邀请奖励直接修改数据库 |
| **helpers.php** | 1321 | `$playerDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;` | ❌ 奖励后余额从关联模型读取 |
| **helpers.php** | 1327 | `$recommendPlayerWallet->save();` | 🔴 保存推荐人钱包到数据库 |
| **helpers.php** | 1343 | `$machineWallet->money = bcsub(...);` | 🔴 直接修改数据库（减少） |
| **helpers.php** | 1345 | `$machineWallet->save();` | 🔴 保存玩家钱包到数据库 |
| **helpers.php** | 1348-1352 | `WalletService::updateCache($player->id, ..., $machineWallet->money);` | ⚠️ 逻辑倒置：先改数据库，再同步 Redis |

**影响**：
- 🔴 **最严重**：walletUpdate 是全局函数，被大量调用
- 🔴 **数据流向错误**：Redis 不是唯一标准，而是数据库的从属
- 🔴 **精度问题**：数据库操作浮点数，Redis 同步整数时可能有误差

---

## 🟡 中危问题清单（建议修复）

### 类别 5：余额显示（只读）

| 文件 | 行号 | 问题 | 影响 |
|------|------|------|------|
| **MachineController.php** | 2023-2024 | `$changePlayer->machine_wallet->money` | 🟡 游戏日志显示余额 |
| **StoreSetting.php** | 156, 203, 294 | `floatval($wallet->money)` | 🟡 门店设置检查余额 |

**影响**：
- 🟡 显示可能有延迟（数据库异步同步）
- 🟡 建议改用 WalletService::getBalance()

---

## ✅ 正确使用 WalletService 的案例

| 文件 | 行号 | 用法 | 说明 |
|------|------|------|------|
| **helpers.php** | 743 | `WalletService::getBalance($player->id)` | ✅ 游戏前余额从 Redis 读取 |
| **helpers.php** | 752 | `WalletService::add($player->id, $game_amount)` | ✅ 游戏结算使用原子加款 |
| **helpers.php** | 1199 | `WalletService::getBalance($player->id)` | ✅ 人工加扣款前余额从 Redis 读取 |
| **helpers.php** | 2356 | `WalletService::getBalance($player->id)` | ✅ 爆机检查从 Redis 读取 |
| **helpers.php** | 2484 | `WalletService::getBalance($player->id)` | ✅ 限额检查从 Redis 读取 |
| **helpers.php** | 2604 | `WalletService::getBalance($playerId, $platformId)` | ✅ 全局辅助函数 |

**说明**：
- 以上这些是正确的用法
- helpers.php 中存在新旧代码混用（Line 604-607 是旧代码，Line 743, 752 是新代码）

---

## 📊 统计汇总

| 问题类别 | 文件数量 | 问题行数 | 严重等级 |
|---------|---------|---------|---------|
| 充值/提现流程 | 2 | 9 处 | 🔴 高危 |
| 推广员收益结算 | 2 | 5 处 | 🔴 高危 |
| 游戏结算 | 1 | 3 处 | 🔴 高危 |
| walletUpdate 函数 | 1 | 12 处 | 🔴 最高危 |
| 余额显示（只读） | 2 | 4 处 | 🟡 中危 |
| **总计** | **5 个文件** | **33 处** | - |

---

## 🎯 修复优先级

### P0 - 立即修复（数据流向错误）

1. **helpers.php:walletUpdate()** (Line 1263-1351)
   - **问题**：先改数据库，再同步 Redis（逻辑倒置）
   - **修复**：重构为先调用 WalletService，删除 updateCache() 调用
   - **影响范围**：全局函数，被多处调用

2. **helpers.php:旧版游戏结算** (Line 604-607)
   - **问题**：直接操作数据库
   - **修复**：删除旧代码，统一使用 Line 743, 752 的新版本
   - **影响范围**：游戏结算

---

### P1 - 高优先级（充值提现）

3. **ChannelRechargeRecordController.php:充值流程** (Line 1009-1120)
   - **问题**：充值、返佣、奖励全部直接操作数据库
   - **修复**：改用 WalletService::add()
   - **影响范围**：后台管理员充值

4. **PlayerController.php:提现流程** (Line 3316-3377)
   - **问题**：提现直接操作数据库
   - **修复**：改用 WalletService::deduct()
   - **影响范围**：后台管理员提现

---

### P2 - 中优先级（推广员结算）

5. **helpers.php:推广员结算** (Line 768, 784)
   - **问题**：结算直接赋值数据库
   - **修复**：改用 WalletService::add()

6. **ChannelPlayerPromoterController.php:推广员结算** (Line 2058, 2197, 2213)
   - **问题**：结算直接修改数据库
   - **修复**：改用 WalletService::add()

---

### P3 - 低优先级（只读显示）

7. **MachineController.php 和 StoreSetting.php**
   - **问题**：显示余额从数据库读取，可能有延迟
   - **修复**：改用 WalletService::getBalance()
   - **影响**：用户体验，非关键功能

---

## 🔧 修复方案示例

### 示例 1：充值流程修复

**修复前**（ChannelRechargeRecordController.php:1009-1022）：
```php
// ❌ 错误：直接操作数据库
$playerWallet = PlayerPlatformCash::query()->where('player_id', $playerRechargeRecord->player_id)->lockForUpdate()->first();
$beforeGameAmount = $playerWallet->money;  // 从数据库读取
$playerWallet->money = bcadd($playerWallet->money, $playerRechargeRecord->point, 2);  // 直接修改
$playerWallet->save();  // 保存到数据库
```

**修复后**：
```php
// ✅ 正确：使用 WalletService 原子操作
$beforeGameAmount = \addons\webman\service\WalletService::getBalance($playerRechargeRecord->player_id);
$afterGameAmount = \addons\webman\service\WalletService::add($playerRechargeRecord->player_id, $playerRechargeRecord->point);

// 模型事件会自动同步到数据库，无需手动操作
```

---

### 示例 2：walletUpdate 函数重构

**修复前**（helpers.php:1263-1351）：
```php
// ❌ 错误：先改数据库，再同步 Redis
$machineWallet = PlayerPlatformCash::query()->where('player_id', $player->id)->lockForUpdate()->first();
$originMoney = $machineWallet->money;
if ($type == PlayerMoneyEditLog::TYPE_INCREASE) {
    $machineWallet->money = bcadd($machineWallet->money, $money, 2);
} else {
    $machineWallet->money = bcsub($machineWallet->money, $money, 2);
}
$machineWallet->save();

// ⚠️ 逻辑倒置：手动同步 Redis
\addons\webman\service\WalletService::updateCache($player->id, PlayerPlatformCash::PLATFORM_SELF, $machineWallet->money);
```

**修复后**：
```php
// ✅ 正确：直接使用 WalletService 原子操作
$originMoney = \addons\webman\service\WalletService::getBalance($player->id);
if ($type == PlayerMoneyEditLog::TYPE_INCREASE) {
    $afterMoney = \addons\webman\service\WalletService::add($player->id, $money);
} else {
    $afterMoney = \addons\webman\service\WalletService::deduct($player->id, $money);
}

// ✅ 删除 updateCache() 调用（模型事件自动同步）
```

---

## 🚨 风险评估

| 风险 | 等级 | 说明 | 缓解措施 |
|------|------|------|---------|
| 数据不一致 | 🔴 极高 | Redis 和数据库存储不同值 | 立即修复数据流向 |
| 精度丢失 | 🔴 高 | 数据库浮点数 → Redis 整数转换误差 | 统一使用 WalletService |
| 余额记录错误 | 🔴 高 | amount_before/after 不准确 | 改用 Redis 读取 |
| 用户投诉 | 🟡 中 | 充值后余额显示延迟 | 修复后消失 |
| 推广员纠纷 | 🟡 中 | 收益结算金额争议 | 修复后消失 |

---

## 📝 修复清单

### 阶段 1：核心数据流修复（1-2 天）

- [ ] **helpers.php:walletUpdate()** 重构（最高优先级）
  - [ ] 改用 WalletService::add()/deduct()
  - [ ] 删除 updateCache() 调用
  - [ ] 处理推荐人返佣和邀请奖励逻辑

- [ ] **helpers.php:旧版游戏结算** 清理
  - [ ] 删除 Line 604-607 旧代码
  - [ ] 统一使用 Line 743, 752 新版本

---

### 阶段 2：充值提现修复（1 天）

- [ ] **ChannelRechargeRecordController.php:充值流程**
  - [ ] Line 1009-1022: 玩家充值
  - [ ] Line 1046-1048: 推荐人返佣
  - [ ] Line 1076-1088: 邀请奖励

- [ ] **PlayerController.php:提现流程**
  - [ ] Line 3316-3377: 人工提现

---

### 阶段 3：推广员结算修复（1 天）

- [ ] **helpers.php:推广员结算**
  - [ ] Line 768, 784

- [ ] **ChannelPlayerPromoterController.php:推广员结算**
  - [ ] Line 2058, 2197, 2213

---

### 阶段 4：只读显示优化（0.5 天）

- [ ] **MachineController.php**
  - [ ] Line 2023-2024: 游戏日志显示

- [ ] **StoreSetting.php**
  - [ ] Line 156, 203, 294: 门店设置检查

---

## ✅ 验收标准

1. ✅ 所有余额变动操作都通过 WalletService
2. ✅ 没有直接修改 `machine_wallet->money` 并 save() 的代码
3. ✅ 删除所有 `WalletService::updateCache()` 的手动调用
4. ✅ 充值/提现/结算流程余额准确
5. ✅ amount_before/amount_after 记录准确
6. ✅ Redis 和数据库数据一致

---

## 📌 总结

**当前状态**：
- ❌ **严重问题**：33 处直接操作数据库
- ❌ **数据流向错误**：先改数据库，再同步 Redis
- ⚠️ **新旧代码混用**：helpers.php 中存在正确和错误两种用法

**预计工期**：**3-4 天**
- 1-2 天：核心数据流修复（walletUpdate + 游戏结算）
- 1 天：充值提现修复
- 1 天：推广员结算修复
- 0.5 天：只读显示优化

**部署要求**：
- 必须与 gk_api 同步部署（共享 Redis）
- 部署前进行充分测试

---

**报告人员**：Claude Code  
**报告时间**：2026-05-10  
**报告状态**：🔴 **发现严重问题，必须立即修复**
