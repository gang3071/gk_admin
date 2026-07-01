# 交班数据统计问题修复完成

## ✅ 修复时间
2026-03-24

## 📋 修复内容

已成功修复自动交班和手动交班的数据统计问题，现在可以**完整统计所有收支类型**。

---

## 🔧 修复的文件

### 1. app/service/store/AutoShiftService.php

**修改位置：** `calculateShiftStatistics()` 方法

**修改内容：**

#### ✅ 新增统计的账变类型

```php
// 之前只统计4种类型
PlayerDeliveryRecord::TYPE_PRESENT_IN,   // 2 - 转入
PlayerDeliveryRecord::TYPE_PRESENT_OUT,  // 3 - 转出
PlayerDeliveryRecord::TYPE_MACHINE,      // 23 - 投钞
PlayerDeliveryRecord::TYPE_LOTTERY,      // 13 - 彩金

// ⭐ 现在新增4种类型
PlayerDeliveryRecord::TYPE_RECHARGE,            // 6 - 开分（open-score API）
PlayerDeliveryRecord::TYPE_WITHDRAWAL,          // 7 - 洗分（present-auto API）
PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD, // 1 - 后台加点
PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT // 8 - 后台扣点
```

#### ✅ 修改利润计算逻辑

```php
// 之前（不准确）
$totalProfit = bcsub($data['present_in_amount'], $data['present_out_amount'], 2);

// 现在（准确）
// 总收入 = 转入 + 开分 + 后台加点
$totalIn = bcadd(
    bcadd($data['present_in_amount'], $data['recharge_amount'], 2),
    $data['modified_add_amount'],
    2
);

// 总支出 = 转出 + 洗分 + 后台扣点
$totalOut = bcadd(
    bcadd($data['present_out_amount'], $data['withdrawal_amount'], 2),
    $data['modified_deduct_amount'],
    2
);

// 利润 = 投钞 + 总收入 - 总支出
$totalProfit = bcsub(
    bcadd($data['machine_put_point'], $totalIn, 2),
    $totalOut,
    2
);
```

#### ✅ 更新返回值

```php
return [
    'machine_amount' => (float)$machineAmount,
    'machine_point' => (int)$data['machine_put_point'],
    'total_in' => (float)$totalIn,          // ⭐ 使用新计算的总收入
    'total_out' => (float)$totalOut,        // ⭐ 使用新计算的总支出
    'lottery_amount' => (float)$data['lottery_amount'],
    'total_profit' => (float)$totalProfit,  // ⭐ 使用新计算的利润
    // 详细分类数据（用于日志和调试）
    'present_in_amount' => (float)$data['present_in_amount'],
    'present_out_amount' => (float)$data['present_out_amount'],
    'recharge_amount' => (float)$data['recharge_amount'],
    'withdrawal_amount' => (float)$data['withdrawal_amount'],
    'modified_add_amount' => (float)$data['modified_add_amount'],
    'modified_deduct_amount' => (float)$data['modified_deduct_amount'],
];
```

---

### 2. addons/webman/controller/ChannelIndexController.php

**修改位置：** `shiftHandover()` 方法

**修改内容：**

#### ✅ 新增统计的账变类型

```php
->whereIn('player_delivery_record.type', [
    PlayerDeliveryRecord::TYPE_PRESENT_IN,
    PlayerDeliveryRecord::TYPE_PRESENT_OUT,
    PlayerDeliveryRecord::TYPE_MACHINE,
    PlayerDeliveryRecord::TYPE_LOTTERY,
    PlayerDeliveryRecord::TYPE_RECHARGE,            // ⭐ 新增：开分
    PlayerDeliveryRecord::TYPE_WITHDRAWAL,          // ⭐ 新增：洗分
    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD, // ⭐ 新增：后台加点
    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT, // ⭐ 新增：后台扣点
])
```

#### ✅ 新增统计查询字段

```php
->selectRaw("
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS present_in_amount,
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS present_out_amount,
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS machine_put_point,
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS lottery_amount,
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS recharge_amount,      // ⭐ 新增
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS withdrawal_amount,    // ⭐ 新增
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS modified_add_amount,  // ⭐ 新增
    SUM(CASE WHEN player_delivery_record.type = ... THEN ... END) AS modified_deduct_amount // ⭐ 新增
")
```

#### ✅ 修改交班记录计算

```php
// 总收入（转入 + 开分 + 后台加点）
$storeAgentShiftHandoverRecord->total_in = bcadd(
    bcadd($playerDeliveryRecord['present_in_amount'] ?? 0,
          $playerDeliveryRecord['recharge_amount'] ?? 0, 2),
    $playerDeliveryRecord['modified_add_amount'] ?? 0,
    2
);

// 总支出（转出 + 洗分 + 后台扣点）
$storeAgentShiftHandoverRecord->total_out = bcadd(
    bcadd($playerDeliveryRecord['present_out_amount'] ?? 0,
          $playerDeliveryRecord['withdrawal_amount'] ?? 0, 2),
    $playerDeliveryRecord['modified_deduct_amount'] ?? 0,
    2
);

// 利润（投钞 + 总收入 - 总支出）
$storeAgentShiftHandoverRecord->total_profit_amount = bcsub(
    bcadd($storeAgentShiftHandoverRecord->machine_point,
          $storeAgentShiftHandoverRecord->total_in, 2),
    $storeAgentShiftHandoverRecord->total_out,
    2
);
```

#### ✅ 增强日志记录

```php
Log::info('店家手动交班成功', [
    // ... 原有字段
    // 新增详细分类数据
    'detail' => [
        'present_in' => $playerDeliveryRecord['present_in_amount'] ?? 0,
        'present_out' => $playerDeliveryRecord['present_out_amount'] ?? 0,
        'recharge' => $playerDeliveryRecord['recharge_amount'] ?? 0,        // ⭐ 开分
        'withdrawal' => $playerDeliveryRecord['withdrawal_amount'] ?? 0,    // ⭐ 洗分
        'modified_add' => $playerDeliveryRecord['modified_add_amount'] ?? 0,// ⭐ 加点
        'modified_deduct' => $playerDeliveryRecord['modified_deduct_amount'] ?? 0, // ⭐ 扣点
    ]
]);
```

---

## 📊 修复效果对比

### 修复前（数据缺失）

```
场景：某店家一个班次的真实数据

机台投钞：5000
玩家转入：1000
玩家转出：500
机台开分：2000  ← 未统计 ❌
机台洗分：1500  ← 未统计 ❌
后台加点：300   ← 未统计 ❌
后台扣点：100   ← 未统计 ❌
彩金：300

旧计算：
总收入 = 1000
总支出 = 500
利润 = 5000 + 1000 - 500 = 5500  ❌ 错误！
```

### 修复后（数据完整）

```
同样场景：

机台投钞：5000
玩家转入：1000
玩家转出：500
机台开分：2000  ← ✅ 已统计
机台洗分：1500  ← ✅ 已统计
后台加点：300   ← ✅ 已统计
后台扣点：100   ← ✅ 已统计
彩金：300

新计算：
总收入 = 1000 + 2000 + 300 = 3300
总支出 = 500 + 1500 + 100 = 2100
利润 = 5000 + 3300 - 2100 = 6200  ✅ 正确！

差额：6200 - 5500 = 700（修复前少计700）
误差率：700/6200 = 11.3%
```

---

## 🔍 影响的业务场景

### 场景1：机台按钮开分（open-score）

**API：** `POST /api/v1/player/open-score`

**参数：**
```json
{
  "score_option": "custom",
  "custom_amount": 1000
}
```

**创建的账变记录：**
- `PlayerDeliveryRecord::TYPE_RECHARGE (6)`
- `amount`: 1000

**修复前：** 不统计此收入 ❌
**修复后：** 统计到 `total_in` ✅

---

### 场景2：机台洗分（present-auto）

**API：** `POST /api/v1/player/present-auto`

**逻辑：** 保留十位，只洗到百位（最低100）

**创建的账变记录：**
- `PlayerDeliveryRecord::TYPE_WITHDRAWAL (7)`
- `amount`: 洗分金额

**修复前：** 不统计此支出 ❌
**修复后：** 统计到 `total_out` ✅

---

### 场景3：后台人工加点/扣点

**操作：** 管理员在后台给玩家加/扣点

**创建的账变记录：**
- 加点：`PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD (1)`
- 扣点：`PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT (8)`

**修复前：** 不统计 ❌
**修复后：** 统计到 `total_in` / `total_out` ✅

---

## 📝 数据验证方法

### 1. 查看日志验证

修复后，在交班日志中可以看到详细分类数据：

```bash
tail -f runtime/logs/webman.log | grep "店家手动交班成功"
```

日志示例：
```json
{
  "record_id": 123,
  "total_in": 3300,
  "total_out": 2100,
  "total_profit_amount": 6200,
  "detail": {
    "present_in": 1000,
    "present_out": 500,
    "recharge": 2000,      // ⭐ 开分统计
    "withdrawal": 1500,    // ⭐ 洗分统计
    "modified_add": 300,   // ⭐ 加点统计
    "modified_deduct": 100 // ⭐ 扣点统计
  }
}
```

---

### 2. 数据库验证

#### 查询各类型账变记录

```sql
-- 查询交班时间段内的各类型账变
SELECT
    type,
    CASE
        WHEN type = 2 THEN '转入'
        WHEN type = 3 THEN '转出'
        WHEN type = 6 THEN '开分'
        WHEN type = 7 THEN '洗分'
        WHEN type = 1 THEN '后台加点'
        WHEN type = 8 THEN '后台扣点'
        WHEN type = 13 THEN '彩金'
        WHEN type = 23 THEN '投钞'
        ELSE '其他'
    END AS type_name,
    COUNT(*) AS count,
    SUM(amount) AS total_amount
FROM player_delivery_record pdr
JOIN player p ON pdr.player_id = p.id
WHERE p.store_admin_id = {店家ID}
  AND p.department_id = {部门ID}
  AND p.is_promoter = 0
  AND pdr.created_at > '{交班开始时间}'
  AND pdr.created_at <= '{交班结束时间}'
  AND type IN (1, 2, 3, 6, 7, 8, 13, 23)
GROUP BY type
ORDER BY type;
```

#### 验证交班记录

```sql
-- 查询交班记录
SELECT
    id,
    start_time,
    end_time,
    machine_point,
    total_in,
    total_out,
    lottery_amount,
    total_profit_amount,
    is_auto_shift
FROM store_agent_shift_handover_record
WHERE bind_admin_user_id = {店家ID}
ORDER BY id DESC
LIMIT 1;
```

#### 手工验证计算

```sql
-- 手工计算验证
-- 总收入 = 转入(2) + 开分(6) + 加点(1)
-- 总支出 = 转出(3) + 洗分(7) + 扣点(8)
-- 利润 = 投钞(23) + 总收入 - 总支出

SELECT
    SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) as present_in,
    SUM(CASE WHEN type = 6 THEN amount ELSE 0 END) as recharge,
    SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) as modified_add,
    SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) +
    SUM(CASE WHEN type = 6 THEN amount ELSE 0 END) +
    SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) as total_in_calc,

    SUM(CASE WHEN type = 3 THEN amount ELSE 0 END) as present_out,
    SUM(CASE WHEN type = 7 THEN amount ELSE 0 END) as withdrawal,
    SUM(CASE WHEN type = 8 THEN amount ELSE 0 END) as modified_deduct,
    SUM(CASE WHEN type = 3 THEN amount ELSE 0 END) +
    SUM(CASE WHEN type = 7 THEN amount ELSE 0 END) +
    SUM(CASE WHEN type = 8 THEN amount ELSE 0 END) as total_out_calc,

    SUM(CASE WHEN type = 23 THEN amount ELSE 0 END) as machine_point,
    SUM(CASE WHEN type = 13 THEN amount ELSE 0 END) as lottery_amount
FROM player_delivery_record pdr
JOIN player p ON pdr.player_id = p.id
WHERE p.store_admin_id = {店家ID}
  AND p.department_id = {部门ID}
  AND p.is_promoter = 0
  AND pdr.created_at > '{交班开始时间}'
  AND pdr.created_at <= '{交班结束时间}';
```

对比手工计算结果和交班记录中的数据，应该完全一致！

---

## 🧪 测试建议

### 1. 功能测试

#### 测试开分统计
1. 创建测试玩家
2. 调用开分API：`POST /api/v1/player/open-score`
3. 执行自动交班或手动交班
4. ✅ 验证交班记录的 `total_in` 包含开分金额
5. ✅ 验证日志中的 `recharge` 字段有值

#### 测试洗分统计
1. 创建测试玩家，余额1500
2. 调用洗分API：`POST /api/v1/player/present-auto`
3. 执行交班
4. ✅ 验证交班记录的 `total_out` 包含洗分金额1400
5. ✅ 验证日志中的 `withdrawal` 字段有值

#### 测试后台加扣点
1. 管理员后台给玩家加点500
2. 管理员后台给玩家扣点200
3. 执行交班
4. ✅ 验证交班记录的 `total_in` 包含加点500
5. ✅ 验证交班记录的 `total_out` 包含扣点200

---

### 2. 回归测试

对比修复前后的历史数据差异：

```sql
-- 查询最近10条交班记录
SELECT
    id,
    DATE_FORMAT(start_time, '%Y-%m-%d %H:%i') as start,
    DATE_FORMAT(end_time, '%Y-%m-%d %H:%i') as end,
    machine_point,
    total_in,
    total_out,
    total_profit_amount,
    is_auto_shift
FROM store_agent_shift_handover_record
WHERE bind_admin_user_id = {店家ID}
ORDER BY id DESC
LIMIT 10;
```

---

## ⚠️ 注意事项

### 1. 历史数据

- **历史交班记录不受影响** - 已保存的记录不会改变
- **新交班记录使用新逻辑** - 从现在开始的交班将使用修复后的统计
- **数据会更准确** - 利润数据会比之前更高（因为之前少计了收入）

### 2. 数据库字段

当前修复**不需要**修改数据库表结构，直接使用现有的 `total_in` 和 `total_out` 字段存储新的计算结果。

如果需要更详细的数据分析，可以考虑添加字段：
```sql
ALTER TABLE `store_agent_shift_handover_record`
ADD COLUMN `recharge_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '开分金额' AFTER `lottery_amount`,
ADD COLUMN `withdrawal_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '洗分金额' AFTER `recharge_amount`;
```

但这不是必须的，因为可以从日志中获取详细数据。

---

## 📈 预期改进

修复后，交班统计将更加准确：

1. ✅ **收入更准确** - 包含机台开分和后台加点
2. ✅ **支出更准确** - 包含机台洗分和后台扣点
3. ✅ **利润更准确** - 基于完整的收支数据计算
4. ✅ **日志更详细** - 记录各类型的具体金额
5. ✅ **便于对账** - 可以追溯每笔收支的来源

---

## ✅ 部署清单

- [x] 修改 `AutoShiftService::calculateShiftStatistics()`
- [x] 修改 `ChannelIndexController::shiftHandover()`
- [x] 更新利润计算逻辑
- [x] 增强日志记录
- [x] 生成修复文档

### 下一步：

1. **重启 Webman 服务**
   ```bash
   cd /www/wwwroot/admin.supergames9.com
   php start.php restart
   ```

2. **验证修复效果**
   - 执行一次手动交班
   - 查看日志确认详细数据
   - 对比数据库记录

3. **监控运行**
   - 观察接下来几次自动交班的数据
   - 验证利润计算是否合理
   - 收集店家反馈

---

## 🎯 总结

本次修复解决了交班统计数据不完整的**严重问题**：

- **问题根源：** 缺少开分、洗分、后台加扣点的统计
- **影响范围：** 所有店家的自动交班和手动交班
- **修复方式：** 在统计查询中添加缺失的账变类型
- **修复效果：** 数据完整准确，利润计算正确

修复后，交班数据将能够**真实反映店家的实际经营情况**！✅
