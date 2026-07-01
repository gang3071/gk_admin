# 交班数据统计问题修复报告

## 📋 检查时间
2026-03-24

## ❌ 发现的问题

### 问题描述
**自动交班和手动交班的数据统计逻辑缺少了重要的账变类型，导致统计数据不完整！**

---

## 🔍 详细分析

### 1. API端账变类型（D:\gk_api）

根据 `PlayerController.php` 的分析，发现以下账变记录：

#### 开分 API (`open-score`)
- **路由参数：** `score_option` = `custom` 或其他预设选项
- **创建记录：**
  1. `PlayerRechargeRecord` - 充值记录（TYPE_ARTIFICIAL）
  2. `PlayerDeliveryRecord` - 账变记录（**TYPE_RECHARGE = 6**）
- **金额字段：** `amount`（充值金额）
- **备注：** "机台按钮开分：{选项}"

#### 洗分 API (`present-auto`)
- **功能：** 自动洗分到上级推广员
- **创建记录：**
  1. `PlayerWithdrawRecord` - 提现记录（TYPE_SELF）
  2. `PlayerDeliveryRecord` - 账变记录（**TYPE_WITHDRAWAL = 7**）
- **金额字段：** `amount`（提现金额）
- **逻辑：** 保留十位，只洗到百位（最低100）

---

### 2. 当前交班统计的账变类型

#### 自动交班 (`AutoShiftService::calculateShiftStatistics`)
**文件：** `D:\gk_admin\app\service\store\AutoShiftService.php:347-358`

```php
->selectRaw('
    SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as present_in_amount,
    SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as present_out_amount,
    SUM(CASE WHEN type = ? THEN point ELSE 0 END) as machine_put_point,
    SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as lottery_amount
', [
    PlayerDeliveryRecord::TYPE_PRESENT_IN,      // 2 - 玩家转入
    PlayerDeliveryRecord::TYPE_PRESENT_OUT,     // 3 - 转出
    PlayerDeliveryRecord::TYPE_MACHINE,         // 23 - 投钞
    PlayerDeliveryRecord::TYPE_LOTTERY          // 13 - 彩金
])
```

#### 手动交班 (`ChannelIndexController::shiftHandover`)
**文件：** `D:\gk_admin\addons\webman\controller\ChannelIndexController.php:2724-2729`

```php
->whereIn('player_delivery_record.type', [
    PlayerDeliveryRecord::TYPE_PRESENT_IN,      // 2 - 玩家转入
    PlayerDeliveryRecord::TYPE_PRESENT_OUT,     // 3 - 转出
    PlayerDeliveryRecord::TYPE_MACHINE,         // 23 - 投钞
    PlayerDeliveryRecord::TYPE_LOTTERY,         // 13 - 彩金
])
```

---

### 3. 缺少的账变类型

| 类型常量 | 值 | 说明 | 来源 | 影响 |
|---------|---|------|------|------|
| **TYPE_RECHARGE** | 6 | **充值** | open-score 开分API | ⚠️ **高** - 开分收入未统计 |
| **TYPE_WITHDRAWAL** | 7 | **提现** | present-auto 洗分API | ⚠️ **高** - 洗分支出未统计 |
| TYPE_MODIFIED_AMOUNT_ADD | 1 | 管理后台加点 | 后台人工操作 | ⚠️ 中 - 人工加点未统计 |
| TYPE_MODIFIED_AMOUNT_DEDUCT | 8 | 管理后台扣点 | 后台人工操作 | ⚠️ 中 - 人工扣点未统计 |

---

## 💥 问题影响

### 场景1：玩家通过机台按钮开分
1. 玩家点击开分按钮，选择金额（如1000）
2. API创建账变记录：**TYPE_RECHARGE (6)**
3. **交班统计：未统计此收入** ❌
4. **结果：** 收入少计1000

### 场景2：玩家通过机台洗分
1. 玩家点击洗分按钮，余额1500
2. 系统洗分1400（保留百位）
3. API创建账变记录：**TYPE_WITHDRAWAL (7)**
4. **交班统计：未统计此支出** ❌
5. **结果：** 支出少计1400

### 场景3：管理员后台加点/扣点
1. 管理员在后台给玩家加点500
2. 系统创建账变记录：**TYPE_MODIFIED_AMOUNT_ADD (1)**
3. **交班统计：未统计此收入** ❌
4. **结果：** 收入少计500

---

## ✅ 修复方案

### 方案A：完整统计所有收支类型（推荐）

```php
// 收入类型
$incomeTypes = [
    PlayerDeliveryRecord::TYPE_PRESENT_IN,          // 2 - 玩家转入
    PlayerDeliveryRecord::TYPE_MACHINE,             // 23 - 投钞
    PlayerDeliveryRecord::TYPE_LOTTERY,             // 13 - 彩金
    PlayerDeliveryRecord::TYPE_RECHARGE,            // 6 - 充值（开分）⭐ 新增
    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD, // 1 - 后台加点 ⭐ 新增
];

// 支出类型
$expenseTypes = [
    PlayerDeliveryRecord::TYPE_PRESENT_OUT,             // 3 - 转出
    PlayerDeliveryRecord::TYPE_WITHDRAWAL,              // 7 - 提现（洗分）⭐ 新增
    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT,  // 8 - 后台扣点 ⭐ 新增
];
```

### 方案B：只统计必要类型（最小修改）

如果只关注机台业务，至少要添加：
1. **TYPE_RECHARGE (6)** - 开分是核心收入
2. **TYPE_WITHDRAWAL (7)** - 洗分是核心支出

---

## 🔧 具体修改代码

### 修改1：自动交班统计

**文件：** `D:\gk_admin\app\service\store\AutoShiftService.php`

**修改位置：** 第347-378行

#### 当前代码：
```php
$result = PlayerDeliveryRecord::query()
    ->selectRaw('
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as present_in_amount,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as present_out_amount,
        SUM(CASE WHEN type = ? THEN point ELSE 0 END) as machine_put_point,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as lottery_amount
    ', [
        PlayerDeliveryRecord::TYPE_PRESENT_IN,
        PlayerDeliveryRecord::TYPE_PRESENT_OUT,
        PlayerDeliveryRecord::TYPE_MACHINE,
        PlayerDeliveryRecord::TYPE_LOTTERY
    ])
    ->join('player', 'player_delivery_record.player_id', '=', 'player.id')
    ->where('player.department_id', $admin->department_id)
    ->where('player.store_admin_id', $bindAdminUserId)
    ->where('player.is_promoter', 0)
    ->where('player_delivery_record.created_at', '>', $startTime)
    ->where('player_delivery_record.created_at', '<=', $endTime)
    ->first();
```

#### 修改后代码：
```php
$result = PlayerDeliveryRecord::query()
    ->selectRaw('
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as present_in_amount,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as present_out_amount,
        SUM(CASE WHEN type = ? THEN point ELSE 0 END) as machine_put_point,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as lottery_amount,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as recharge_amount,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as withdrawal_amount,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as modified_add_amount,
        SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as modified_deduct_amount
    ', [
        PlayerDeliveryRecord::TYPE_PRESENT_IN,
        PlayerDeliveryRecord::TYPE_PRESENT_OUT,
        PlayerDeliveryRecord::TYPE_MACHINE,
        PlayerDeliveryRecord::TYPE_LOTTERY,
        PlayerDeliveryRecord::TYPE_RECHARGE,            // ⭐ 新增：开分
        PlayerDeliveryRecord::TYPE_WITHDRAWAL,          // ⭐ 新增：洗分
        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD, // ⭐ 新增：后台加点
        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT // ⭐ 新增：后台扣点
    ])
    ->join('player', 'player_delivery_record.player_id', '=', 'player.id')
    ->where('player.department_id', $admin->department_id)
    ->where('player.store_admin_id', $bindAdminUserId)
    ->where('player.is_promoter', 0)
    ->where('player_delivery_record.created_at', '>', $startTime)
    ->where('player_delivery_record.created_at', '<=', $endTime)
    ->first();

$data = $result ? $result->toArray() : [
    'present_in_amount' => 0,
    'present_out_amount' => 0,
    'machine_put_point' => 0,
    'lottery_amount' => 0,
    'recharge_amount' => 0,          // ⭐ 新增
    'withdrawal_amount' => 0,        // ⭐ 新增
    'modified_add_amount' => 0,      // ⭐ 新增
    'modified_deduct_amount' => 0,   // ⭐ 新增
];

// 计算总收入（含开分、后台加点）
$totalIncome = bcadd(
    bcadd($data['present_in_amount'], $data['recharge_amount'], 2),
    $data['modified_add_amount'],
    2
);

// 计算总支出（含洗分、后台扣点）
$totalExpense = bcadd(
    bcadd($data['present_out_amount'], $data['withdrawal_amount'], 2),
    $data['modified_deduct_amount'],
    2
);

// 计算利润（机台投钞 + 总收入 - 总支出）
$totalProfit = bcsub(
    bcadd($data['machine_put_point'], $totalIncome, 2),
    $totalExpense,
    2
);

return [
    'machine_amount' => (float)$machineAmount,
    'machine_point' => (int)$data['machine_put_point'],
    'total_in' => (float)$totalIncome,          // ⭐ 修改：包含开分和加点
    'total_out' => (float)$totalExpense,        // ⭐ 修改：包含洗分和扣点
    'lottery_amount' => (float)$data['lottery_amount'],
    'total_profit' => (float)$totalProfit,      // ⭐ 修改：准确的利润计算
    // 新增详细字段
    'recharge_amount' => (float)$data['recharge_amount'],
    'withdrawal_amount' => (float)$data['withdrawal_amount'],
    'modified_add_amount' => (float)$data['modified_add_amount'],
    'modified_deduct_amount' => (float)$data['modified_deduct_amount'],
];
```

---

### 修改2：手动交班统计

**文件：** `D:\gk_admin\addons\webman\controller\ChannelIndexController.php`

**修改位置：** 第2719-2750行

#### 当前代码：
```php
$result = PlayerDeliveryRecord::query()
    ->join('player', 'player_delivery_record.player_id', '=', 'player.id')
    ->where('player.department_id', $admin->department_id)
    ->where('player.store_admin_id', $admin->id)
    ->where('player.is_promoter', 0)
    ->whereIn('player_delivery_record.type', [
        PlayerDeliveryRecord::TYPE_PRESENT_IN,
        PlayerDeliveryRecord::TYPE_PRESENT_OUT,
        PlayerDeliveryRecord::TYPE_MACHINE,
        PlayerDeliveryRecord::TYPE_LOTTERY,
    ])
    ->where('player_delivery_record.created_at', '>', $startTime)
    ->where('player_delivery_record.created_at', '<=', $endTime)
    ->selectRaw("
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . "
            THEN player_delivery_record.amount ELSE 0 END) AS present_in_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . "
            THEN player_delivery_record.amount ELSE 0 END) AS present_out_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE . "
            THEN player_delivery_record.amount ELSE 0 END) AS machine_put_point,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . "
            THEN player_delivery_record.amount ELSE 0 END) AS lottery_amount
    ")
    ->first();
```

#### 修改后代码：
```php
$result = PlayerDeliveryRecord::query()
    ->join('player', 'player_delivery_record.player_id', '=', 'player.id')
    ->where('player.department_id', $admin->department_id)
    ->where('player.store_admin_id', $admin->id)
    ->where('player.is_promoter', 0)
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
    ->where('player_delivery_record.created_at', '>', $startTime)
    ->where('player_delivery_record.created_at', '<=', $endTime)
    ->selectRaw("
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . "
            THEN player_delivery_record.amount ELSE 0 END) AS present_in_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . "
            THEN player_delivery_record.amount ELSE 0 END) AS present_out_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE . "
            THEN player_delivery_record.amount ELSE 0 END) AS machine_put_point,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . "
            THEN player_delivery_record.amount ELSE 0 END) AS lottery_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . "
            THEN player_delivery_record.amount ELSE 0 END) AS recharge_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . "
            THEN player_delivery_record.amount ELSE 0 END) AS withdrawal_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD . "
            THEN player_delivery_record.amount ELSE 0 END) AS modified_add_amount,
        SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT . "
            THEN player_delivery_record.amount ELSE 0 END) AS modified_deduct_amount
    ")
    ->first();

$playerDeliveryRecord = $result ? $result->toArray() : [
    'present_in_amount' => 0,
    'present_out_amount' => 0,
    'machine_put_point' => 0,
    'lottery_amount' => 0,
    'recharge_amount' => 0,          // ⭐ 新增
    'withdrawal_amount' => 0,        // ⭐ 新增
    'modified_add_amount' => 0,      // ⭐ 新增
    'modified_deduct_amount' => 0,   // ⭐ 新增
];

// 修改交班记录的计算逻辑
$storeAgentShiftHandoverRecord->total_in = bcadd(
    bcadd($playerDeliveryRecord['present_in_amount'],
          $playerDeliveryRecord['recharge_amount'], 2),
    $playerDeliveryRecord['modified_add_amount'],
    2
);

$storeAgentShiftHandoverRecord->total_out = bcadd(
    bcadd($playerDeliveryRecord['present_out_amount'],
          $playerDeliveryRecord['withdrawal_amount'], 2),
    $playerDeliveryRecord['modified_deduct_amount'],
    2
);

// 利润 = 机台投钞 + 总收入 - 总支出
$storeAgentShiftHandoverRecord->total_profit_amount = bcsub(
    bcadd($storeAgentShiftHandoverRecord->machine_point,
          $storeAgentShiftHandoverRecord->total_in, 2),
    $storeAgentShiftHandoverRecord->total_out,
    2
);
```

---

## 📊 修改前后对比

### 修改前（缺失数据）
```
机台投钞：5000
转入：     1000
转出：     500
彩金：     300
---
利润：     5000 + 1000 - 500 = 5500  ❌ 不准确
```

### 修改后（完整数据）
```
机台投钞：5000
转入：     1000
开分：     2000  ⭐ 新增
后台加点：  500   ⭐ 新增
转出：     500
洗分：     1500  ⭐ 新增
后台扣点：  200   ⭐ 新增
彩金：     300
---
总收入：1000 + 2000 + 500 = 3500
总支出：500 + 1500 + 200 = 2200
利润：  5000 + 3500 - 2200 = 6300  ✅ 准确
```

---

## 🗄️ 数据库字段影响

### 检查是否需要添加新字段

查看 `store_agent_shift_handover_record` 表结构：

```sql
DESC store_agent_shift_handover_record;
```

如果表中只有以下字段：
- `total_in` - 总收入
- `total_out` - 总支出

**建议：** 不需要添加新字段，直接在现有字段中包含新类型即可。

如果需要详细分类，可以考虑添加：
```sql
ALTER TABLE `store_agent_shift_handover_record`
ADD COLUMN `recharge_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '开分金额' AFTER `lottery_amount`,
ADD COLUMN `withdrawal_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '洗分金额' AFTER `recharge_amount`,
ADD COLUMN `modified_add_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '后台加点' AFTER `withdrawal_amount`,
ADD COLUMN `modified_deduct_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '后台扣点' AFTER `modified_add_amount`;
```

---

## 📝 测试建议

### 1. 单元测试
```php
// 测试开分统计
$this->createPlayerDeliveryRecord(PlayerDeliveryRecord::TYPE_RECHARGE, 1000);
$stats = $service->calculateShiftStatistics(...);
$this->assertEquals(1000, $stats['total_in']);

// 测试洗分统计
$this->createPlayerDeliveryRecord(PlayerDeliveryRecord::TYPE_WITHDRAWAL, 500);
$stats = $service->calculateShiftStatistics(...);
$this->assertEquals(500, $stats['total_out']);
```

### 2. 集成测试
1. 创建测试玩家
2. 模拟开分：调用 `open-score` API
3. 执行交班
4. 验证交班记录中的 `total_in` 包含开分金额
5. 模拟洗分：调用 `present-auto` API
6. 再次交班
7. 验证交班记录中的 `total_out` 包含洗分金额

---

## ✅ 修复清单

- [ ] 修改 `AutoShiftService::calculateShiftStatistics()`
  - [ ] 添加 TYPE_RECHARGE 统计
  - [ ] 添加 TYPE_WITHDRAWAL 统计
  - [ ] 添加 TYPE_MODIFIED_AMOUNT_ADD 统计
  - [ ] 添加 TYPE_MODIFIED_AMOUNT_DEDUCT 统计
  - [ ] 更新返回值结构

- [ ] 修改 `ChannelIndexController::shiftHandover()`
  - [ ] 添加 TYPE_RECHARGE 统计
  - [ ] 添加 TYPE_WITHDRAWAL 统计
  - [ ] 添加 TYPE_MODIFIED_AMOUNT_ADD 统计
  - [ ] 添加 TYPE_MODIFIED_AMOUNT_DEDUCT 统计
  - [ ] 更新利润计算逻辑

- [ ] （可选）数据库表结构升级
  - [ ] 添加 `recharge_amount` 字段
  - [ ] 添加 `withdrawal_amount` 字段
  - [ ] 添加 `modified_add_amount` 字段
  - [ ] 添加 `modified_deduct_amount` 字段

- [ ] 测试验证
  - [ ] 单元测试
  - [ ] 集成测试
  - [ ] 生产数据回归测试

---

## 🚨 紧急程度

**高优先级** - 此问题会导致交班数据严重不准确，影响财务统计和店家对账。

建议立即修复并部署。

---

## 📞 需要帮助？

如果修改后遇到问题，请检查：
1. `PlayerDeliveryRecord` 模型中的类型常量定义
2. 数据库中实际的账变记录类型分布
3. API端创建账变记录的代码逻辑
4. 测试环境的数据完整性

---

## ✅ 验证步骤

修复后验证：

1. **查询开分记录**
   ```sql
   SELECT COUNT(*), SUM(amount)
   FROM player_delivery_record
   WHERE type = 6  -- TYPE_RECHARGE
   AND created_at >= '交班开始时间'
   AND created_at <= '交班结束时间';
   ```

2. **查询洗分记录**
   ```sql
   SELECT COUNT(*), SUM(amount)
   FROM player_delivery_record
   WHERE type = 7  -- TYPE_WITHDRAWAL
   AND created_at >= '交班开始时间'
   AND created_at <= '交班结束时间';
   ```

3. **对比交班记录**
   ```sql
   SELECT total_in, total_out, total_profit_amount
   FROM store_agent_shift_handover_record
   WHERE id = 最新记录ID;
   ```

4. **手工计算验证**
   - 总收入 = 转入 + 开分 + 加点
   - 总支出 = 转出 + 洗分 + 扣点
   - 利润 = 投钞 + 总收入 - 总支出

修复完成后，数据应该完全一致！
