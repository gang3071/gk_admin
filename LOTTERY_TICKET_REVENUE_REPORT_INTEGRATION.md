# 摸奖券奖励 - 营收报表集成方案

> **任务**: 将摸奖券奖励(TYPE_LOTTERY_TICKET_REWARD)集成到总站/渠道/代理/店家的营收报表中  
> **影响范围**: 3个核心报表控制器 + PlayerDeliveryRecord 模型  
> **类型**: 支出类型 (增加玩家余额 = 平台支出)

---

## 📊 当前报表结构分析

### 现有支出类型 (影响营收的类型)

```php
// PlayerDeliveryRecord 模型中的支出类型
TYPE_ACTIVITY_BONUS = 10;        // 活动奖金 (支出)
TYPE_LOTTERY = 13;                // 彩金中奖 (支出)
TYPE_REGISTER_PRESENT = 11;       // 注册赠送 (支出)
TYPE_MODIFIED_AMOUNT_ADD = 1;     // 管理后台加点 (支出)

// ⭐ 新增
TYPE_LOTTERY_TICKET_REWARD = 33;  // 摸奖券中奖奖励 (支出) ✅ 已添加
```

### 报表中的支出统计字段

所有报表都包含这些支出字段:
- `activity_total` - 活动奖励总额
- `lottery_total` - 彩金奖励总额  
- `modified_total` - 管理员加点/扣点差额

**问题**: 摸奖券奖励 (`TYPE_LOTTERY_TICKET_REWARD`) 目前**没有**单独的统计字段

**解决方案**: 有两个选择
1. **方案A**: 将摸奖券奖励归入 `activity_total` (推荐 ✅)
2. **方案B**: 新增 `lottery_ticket_total` 独立字段

---

## 🎯 推荐方案: 方案A (归入 activity_total)

### 理由

1. **语义合理**: 摸奖券本身就是一种活动形式
2. **无需改表结构**: 不需要新增字段和列
3. **前端无需改动**: 现有的"活动奖励"统计自动包含
4. **保持一致性**: 与现有的活动奖金逻辑一致

### 实现方式

将 `TYPE_LOTTERY_TICKET_REWARD` 在统计时与 `TYPE_ACTIVITY_BONUS` 合并计算:

```php
// 修改前
WHERE type = TYPE_ACTIVITY_BONUS

// 修改后
WHERE type IN (TYPE_ACTIVITY_BONUS, TYPE_LOTTERY_TICKET_REWARD)
```

---

## 📝 需要修改的文件列表

### 1. ✅ 已完成

**文件**: `addons/webman/model/PlayerDeliveryRecord.php`
- [x] 新增常量 `TYPE_LOTTERY_TICKET_REWARD = 33`

**文件**: `addons/webman/controller/ChannelLotteryTicketActivityController.php`  
- [x] `batchDistributeActivity()` 中使用 `PlayerDeliveryRecord`
- [x] `distributeByTicketNo()` 中使用 `PlayerDeliveryRecord`

---

### 2. 🔧 待修改: 营收报表控制器

#### 文件 1: `addons/webman/controller/ChannelPlayerReportController.php` (渠道玩家报表)

**修改位置 1**: 汇总统计 (约第 197-199 行)
```php
// 当前代码
$summaryData['activity_total'] = $playerDeliveryRecordBaseQuery->clone()
    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS)
    ->sum('player_delivery_record.amount');

// ⭐ 修改为
$summaryData['activity_total'] = $playerDeliveryRecordBaseQuery->clone()
    ->whereIn('player_delivery_record.type', [
        PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS,
        PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD  // ⭐ 新增摸奖券奖励
    ])
    ->sum('player_delivery_record.amount');
```

**修改位置 2**: 按日期统计 SQL (约第 223 行)
```php
// 当前代码
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS activity_total,

// ⭐ 修改为
SUM(CASE 
    WHEN player_delivery_record.type IN (" . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . ", " . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . ") 
    THEN player_delivery_record.amount 
    ELSE 0 
END) AS activity_total,
```

**修改位置 3**: 输赢计算 (约第 233-234 行)
```php
// 当前代码
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) -
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS winn_los_total,

// ⭐ 修改为
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) -
SUM(CASE 
    WHEN player_delivery_record.type IN (" . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . ", " . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . ") 
    THEN player_delivery_record.amount 
    ELSE 0 
END) AS winn_los_total,
```

**影响**: 渠道后台的玩家报表

---

#### 文件 2: `addons/webman/controller/PlayerReportController.php` (总站玩家报表)

需要进行**完全相同**的修改 (3处):
1. 汇总统计的 `activity_total` 查询
2. 按日期统计的 `activity_total` SQL
3. 输赢计算的 `winn_los_total` SQL

**影响**: 总站后台的玩家报表

---

#### 文件 3: `addons/webman/controller/ChannelIndexController.php` (渠道首页仪表盘)

**查找关键字**: `TYPE_ACTIVITY_BONUS`

**预期位置**: 首页统计卡片 (支出统计)

```php
// 当前代码 (预估)
$activityBonus = PlayerDeliveryRecord::where('department_id', $departmentId)
    ->where('type', PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS)
    ->sum('amount');

// ⭐ 修改为
$activityBonus = PlayerDeliveryRecord::where('department_id', $departmentId)
    ->whereIn('type', [
        PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS,
        PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD
    ])
    ->sum('amount');
```

**影响**: 渠道首页的"活动奖励"统计卡片

---

### 3. 🔍 需要检查的其他文件

以下文件**可能**需要修改 (取决于是否有营收统计):

| 文件 | 用途 | 是否需要修改 |
|------|------|--------------|
| `ChannelStoreProfitReportController.php` | 店家分润报表 | ✅ 需要 |
| `ChannelStoreAgentProfitRecordController.php` | 代理分润报表 | ✅ 需要 |
| `ChannelProfitRecordController.php` | 渠道分润记录 | ✅ 需要 |
| `ProfitRecordController.php` | 总站分润记录 | ✅ 需要 |
| `AgentStoreProfitReportController.php` | 代理后台店家分润 | ✅ 需要 |
| `ChannelMachineReportController.php` | 机台报表 | ❌ 不需要 (机台专用) |
| `MachineReportController.php` | 总站机台报表 | ❌ 不需要 (机台专用) |

---

## 🛠️ 详细修改步骤

### Step 1: 修改渠道玩家报表

**文件**: `addons/webman/controller/ChannelPlayerReportController.php`

**位置 1**: 第 197-199 行
```php
// BEFORE
$summaryData['activity_total'] = $playerDeliveryRecordBaseQuery->clone()
    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS)
    ->sum('player_delivery_record.amount');

// AFTER
$summaryData['activity_total'] = $playerDeliveryRecordBaseQuery->clone()
    ->whereIn('player_delivery_record.type', [
        PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS,
        PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD  // ⭐ 摸奖券奖励
    ])
    ->sum('player_delivery_record.amount');
```

**位置 2**: 第 223 行 (selectRaw 中)
```php
// BEFORE
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS activity_total,

// AFTER
SUM(CASE WHEN player_delivery_record.type IN (" . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . ", " . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . ") THEN player_delivery_record.amount ELSE 0 END) AS activity_total,
```

**位置 3**: 第 233-234 行 (winn_los_total 计算)
```php
// BEFORE
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) -
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS winn_los_total,

// AFTER
SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) -
SUM(CASE WHEN player_delivery_record.type IN (" . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . ", " . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . ") THEN player_delivery_record.amount ELSE 0 END) AS winn_los_total,
```

---

### Step 2: 修改总站玩家报表

**文件**: `addons/webman/controller/PlayerReportController.php`

进行与 Step 1 **完全相同**的 3 处修改:
1. `activity_total` 汇总查询
2. `activity_total` SQL CASE 语句
3. `winn_los_total` SQL CASE 语句

---

### Step 3: 修改渠道首页仪表盘

**文件**: `addons/webman/controller/ChannelIndexController.php`

查找所有 `TYPE_ACTIVITY_BONUS` 的使用,修改为 `whereIn([TYPE_ACTIVITY_BONUS, TYPE_LOTTERY_TICKET_REWARD])`

---

### Step 4: 修改分润报表 (如果存在)

**检查以下文件** 是否有 `TYPE_ACTIVITY_BONUS` 相关的统计:

1. `ChannelStoreProfitReportController.php`
2. `ChannelStoreAgentProfitRecordController.php`
3. `ChannelProfitRecordController.php`
4. `ProfitRecordController.php`
5. `AgentStoreProfitReportController.php`

**修改规则**:
- 所有 `WHERE type = TYPE_ACTIVITY_BONUS` 改为 `WHERE type IN (...)`
- 所有 `CASE WHEN type = TYPE_ACTIVITY_BONUS` 改为 `CASE WHEN type IN (...)`

---

## 🧪 测试验证

### 测试用例

**前置条件:**
1. 创建一个摸奖券活动
2. 录入 3 条中奖记录 (券号 000001, 000002, 000003)
   - 000001: 一等奖 10,000 元
   - 000002: 二等奖 5,000 元
   - 000003: 三等奖 1,000 元

**测试步骤:**

1. **发放奖励** (批量发放)
   ```
   操作: 点击"发放奖励"按钮
   预期: 成功发放 3 条记录
   ```

2. **检查 player_delivery_record 表**
   ```sql
   SELECT * FROM player_delivery_record 
   WHERE type = 33  -- TYPE_LOTTERY_TICKET_REWARD
   AND target = 'lottery_ticket_record';
   
   预期: 3 条记录,金额分别为 10000, 5000, 1000
   ```

3. **检查玩家余额**
   ```sql
   SELECT id, name, money FROM player 
   WHERE id IN (中奖玩家ID);
   
   预期: 余额正确增加
   ```

4. **检查渠道玩家报表**
   ```
   路径: /ex-admin/channel-player-report/index
   
   预期: "活动奖励" 字段包含摸奖券发放的金额
   预期: "总支出" 字段正确计算 (包含摸奖券)
   预期: "输赢" 字段正确计算 (减去摸奖券奖励)
   ```

5. **检查总站玩家报表**
   ```
   路径: /ex-admin/player-report/index
   
   预期: 同渠道报表
   ```

6. **检查渠道首页统计**
   ```
   路径: /ex-admin/channel/index
   
   预期: "活动奖励" 统计卡片包含摸奖券金额
   ```

---

## 📊 数据流转示意图

```
发放摸奖券奖励
      ↓
┌─────────────────────────────────────┐
│ PlayerDeliveryRecord 创建           │
│ - type: TYPE_LOTTERY_TICKET_REWARD │
│ - amount: 10000                    │
│ - target: lottery_ticket_record    │
└─────────────────────────────────────┘
      ↓
┌─────────────────────────────────────┐
│ 报表统计查询                         │
│ WHERE type IN (                     │
│   TYPE_ACTIVITY_BONUS,              │
│   TYPE_LOTTERY_TICKET_REWARD        │
│ )                                   │
└─────────────────────────────────────┘
      ↓
┌─────────────────────────────────────┐
│ 前端展示                             │
│ activity_total: 10,000 元           │
│ (活动奖励 = 活动奖金 + 摸奖券)       │
└─────────────────────────────────────┘
```

---

## 🔍 SQL 查询示例

### 查看摸奖券发放记录

```sql
SELECT 
    pdr.id,
    pdr.player_id,
    p.name AS player_name,
    pdr.type,
    pdr.amount,
    pdr.tradeno AS ticket_no,
    pdr.remark,
    pdr.created_at
FROM player_delivery_record pdr
LEFT JOIN player p ON pdr.player_id = p.id
WHERE pdr.type = 33  -- TYPE_LOTTERY_TICKET_REWARD
ORDER BY pdr.created_at DESC;
```

### 统计摸奖券总支出 (按活动)

```sql
SELECT 
    ltr.activity_id,
    lta.name AS activity_name,
    COUNT(*) AS record_count,
    SUM(pdr.amount) AS total_amount
FROM player_delivery_record pdr
INNER JOIN lottery_ticket_record ltr ON pdr.target_id = ltr.id AND pdr.target = 'lottery_ticket_record'
INNER JOIN lottery_ticket_activity lta ON ltr.activity_id = lta.id
WHERE pdr.type = 33
GROUP BY ltr.activity_id, lta.name
ORDER BY total_amount DESC;
```

### 验证报表统计是否正确

```sql
-- 活动奖励总额 (应包含摸奖券)
SELECT 
    SUM(amount) AS activity_total
FROM player_delivery_record
WHERE department_id = 100  -- 替换为实际部门ID
  AND type IN (10, 33)  -- TYPE_ACTIVITY_BONUS, TYPE_LOTTERY_TICKET_REWARD
  AND DATE(created_at) = '2024-01-29';

-- 对比报表显示的值,应该一致
```

---

## ⚠️ 注意事项

### 1. 数据库字段限制

- `type` 字段: tinyint (最大值 127)
- 当前使用到 33,还有充足空间

### 2. 已有数据兼容性

- 新类型 `TYPE_LOTTERY_TICKET_REWARD = 33` 不会影响已有数据
- 报表统计使用 `IN` 查询,向后兼容

### 3. 性能影响

- `WHERE type IN (10, 33)` vs `WHERE type = 10`
  - 性能差异极小
  - 建议在 `type` 字段上创建索引

```sql
-- 检查索引
SHOW INDEX FROM player_delivery_record WHERE Column_name = 'type';

-- 如果不存在,创建索引
CREATE INDEX idx_type ON player_delivery_record(type);
```

### 4. 分润事件

**当前 PlayerDeliveryRecord 模型的 `booted()` 方法**只处理这些类型:
- `TYPE_MODIFIED_AMOUNT_ADD` (管理员加点)
- `TYPE_MODIFIED_AMOUNT_DEDUCT` (管理员扣点)
- `TYPE_REGISTER_PRESENT` (注册赠送)
- `TYPE_ACTIVITY_BONUS` (活动奖励)

**问题**: 摸奖券奖励是否需要触发分润事件?

**建议**:
- 如果摸奖券奖励**不参与分润**: 无需添加事件
- 如果摸奖券奖励**参与分润**: 需要在 `booted()` 中添加:

```php
case PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD: // 摸奖券奖励
    Event::emit('promotion.lotteryTicketReward', $deliveryRecord);
    break;
```

**待确认**: 请确认摸奖券奖励是否参与推广员/代理分润系统

---

## 📋 修改清单

### 已完成 ✅

- [x] PlayerDeliveryRecord 模型添加常量
- [x] ChannelLotteryTicketActivityController::batchDistributeActivity() 使用 PlayerDeliveryRecord
- [x] ChannelLotteryTicketActivityController::distributeByTicketNo() 使用 PlayerDeliveryRecord

### 待完成 🔧

- [ ] ChannelPlayerReportController.php (3处修改)
- [ ] PlayerReportController.php (3处修改)
- [ ] ChannelIndexController.php (查找并修改)
- [ ] ChannelStoreProfitReportController.php (检查并修改)
- [ ] ChannelStoreAgentProfitRecordController.php (检查并修改)
- [ ] ChannelProfitRecordController.php (检查并修改)
- [ ] ProfitRecordController.php (检查并修改)
- [ ] AgentStoreProfitReportController.php (检查并修改)

### 测试任务 🧪

- [ ] 发放摸奖券奖励,检查 player_delivery_record 记录
- [ ] 验证渠道玩家报表"活动奖励"统计
- [ ] 验证总站玩家报表"活动奖励"统计
- [ ] 验证渠道首页"活动奖励"卡片
- [ ] 验证分润报表 (如果适用)
- [ ] SQL 查询验证统计数据准确性

---

## 📖 相关文档

- [摸奖券发放完整流程](./LOTTERY_TICKET_DISTRIBUTION_FLOW.md)
- PlayerDeliveryRecord 模型定义
- 营收报表业务逻辑文档

---

**最后更新**: 2026-06-13  
**维护人员**: Claude  
**状态**: 待实施
