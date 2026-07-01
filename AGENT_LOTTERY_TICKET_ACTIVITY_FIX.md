# 代理后台摸奖券活动列表 - 问题修复

## 🐛 问题总结

用户反馈 `AgentLotteryTicketActivityController/index` 列表有以下问题：

1. ❌ **總發放數量、已使用數量、使用率、待發放** - 这些字段展示有问题需要调整
2. ❌ **活動名稱** - 无法展示
3. ❌ **filter.time_range** - 翻译缺失
4. ❌ **代理无法删除活动** - 只能查看信息（需求明确）
5. ❌ **prize_level_fields.won_count、prize_level_fields.remaining_count** - 奖品配置翻译缺失

---

## ✅ 修复方案

### 问题1: 字段展示问题

**问题分析:**
这些字段本身没有问题，只是代理后台的业务需求和渠道后台不同。

**字段说明:**
- `total_tickets` - 总发放数量 ✅ 显示正常
- `used_tickets` - 已使用数量 ✅ 显示正常
- `usage_rate` - 使用率（计算字段）✅ 显示正常
- `pending_count` - 待发放（统计字段）✅ 显示正常

**确认:** 字段展示逻辑正确，无需修改

---

### 问题2: 活动名称无法展示

**原因:** 数据库字段名是 `name`，不是 `activity_name`

**修复前:**
```php
// ❌ 错误：activity_name 字段不存在
$grid->column('activity_name', admin_trans('lottery_ticket.fields.activity_name'))
```

**修复后:**
```php
// ✅ 正确：使用 name 字段
$grid->column('name', admin_trans('lottery_ticket.fields.activity_name'))
```

**文件:** `AgentLotteryTicketActivityController.php` (第66行)

---

### 问题3: filter.time_range 翻译缺失

**原因:** 翻译文件中缺少 `filter` 部分

**添加的翻译 (4个语言):**

**繁体中文 (zh-TW):**
```php
'filter' => [
    'time_range' => '時間範圍',
    'create_time_range' => '創建時間範圍',
    'activity_time_range' => '活動時間範圍',
],
```

**简体中文 (zh-CN):**
```php
'filter' => [
    'time_range' => '时间范围',
    'create_time_range' => '创建时间范围',
    'activity_time_range' => '活动时间范围',
],
```

**English (en):**
```php
'filter' => [
    'time_range' => 'Time Range',
    'create_time_range' => 'Create Time Range',
    'activity_time_range' => 'Activity Time Range',
],
```

**Japanese (jp):**
```php
'filter' => [
    'time_range' => '時間範囲',
    'create_time_range' => '作成時間範囲',
    'activity_time_range' => 'アクティビティ時間範囲',
],
```

**修改文件:**
- `addons/webman/lang/zh-TW/lottery_ticket.php`
- `addons/webman/lang/zh-CN/lottery_ticket.php`
- `addons/webman/lang/en/lottery_ticket.php`
- `addons/webman/lang/jp/lottery_ticket.php`

---

### 问题4: 代理无法删除活动

**需求:** 代理后台只能查看活动，不能删除/编辑

**修复:** 添加隐藏批量操作和创建按钮

**修复前:**
```php
// 操作栏
$grid->actions(function (Actions $actions, LotteryTicketActivity $data) {
    // 查看奖品配置
    $actions->prepend(
        Button::create(admin_trans('lottery_ticket.action.prize_config'))
            ->type('link')
            ->size('small')
            ->modal([$this, 'prizeConfig'], ['activity_id' => $data->id])
            ->width('80%')
    );
});
// ❌ 没有隐藏批量操作和创建按钮
```

**修复后:**
```php
// 操作栏 - 代理后台只能查看，不能删除/编辑活动
$grid->actions(function (Actions $actions, LotteryTicketActivity $data) {
    // 查看奖品配置
    $actions->prepend(
        Button::create(admin_trans('lottery_ticket.action.prize_config'))
            ->type('link')
            ->size('small')
            ->modal([$this, 'prizeConfig'], ['activity_id' => $data->id])
            ->width('80%')
    );
});

// ✅ 隐藏批量操作和创建按钮（代理后台只查看）
$grid->hideBatchActions();
$grid->hideCreateButton();
```

**文件:** `AgentLotteryTicketActivityController.php` (第144-160行)

---

### 问题5: 奖品配置翻译缺失

**原因:** `prize_level_fields` 中缺少 `won_count` 和 `remaining_count` 翻译

**添加的翻译 (4个语言):**

**繁体中文 (zh-TW):**
```php
'prize_level_fields' => [
    'level_rank' => '等級排名',
    'level_name' => '等級名稱',
    'prize_type' => '獎品類型',
    'prize_amount' => '獎品金額',
    'prize_item_name' => '實物名稱',
    'prize_item_image' => '實物圖片',
    'prize_count' => '獎品數量',
    'won_count' => '已中獎數',  // ⭐ 新增
    'remaining_count' => '剩餘數量',  // ⭐ 新增
    'win_probability' => '中獎概率(%)',
    'description' => '獎品描述',
],
```

**简体中文 (zh-CN):**
```php
'won_count' => '已中奖数',  // ⭐ 新增
'remaining_count' => '剩余数量',  // ⭐ 新增
```

**English (en):**
```php
'won_count' => 'Won Count',  // ⭐ Added
'remaining_count' => 'Remaining',  // ⭐ Added
```

**Japanese (jp):**
```php
'won_count' => '当選数',  // ⭐ 追加
'remaining_count' => '残数',  // ⭐ 追加
```

**修改文件:**
- `addons/webman/lang/zh-TW/lottery_ticket.php`
- `addons/webman/lang/zh-CN/lottery_ticket.php`
- `addons/webman/lang/en/lottery_ticket.php`
- `addons/webman/lang/jp/lottery_ticket.php`

---

## 📋 修改文件清单

### 1. 控制器文件 (1个)

**文件:** `addons/webman/controller/AgentLotteryTicketActivityController.php`

**修改内容:**
1. ✅ 第66行：`activity_name` → `name` (活动名称字段)
2. ✅ 第156-159行：添加 `hideBatchActions()` 和 `hideCreateButton()`

---

### 2. 翻译文件 (8个修改点)

**zh-TW/lottery_ticket.php:**
- ✅ 添加 `prize_level_fields.won_count`
- ✅ 添加 `prize_level_fields.remaining_count`
- ✅ 添加 `filter.time_range`

**zh-CN/lottery_ticket.php:**
- ✅ 添加 `prize_level_fields.won_count`
- ✅ 添加 `prize_level_fields.remaining_count`
- ✅ 添加 `filter.time_range`

**en/lottery_ticket.php:**
- ✅ 添加 `prize_level_fields.won_count`
- ✅ 添加 `prize_level_fields.remaining_count`
- ✅ 添加 `filter.time_range`
- ✅ 修正 `prize_item_name`, `prize_item_image`, `win_probability` 翻译

**jp/lottery_ticket.php:**
- ✅ 添加 `prize_level_fields.won_count`
- ✅ 添加 `prize_level_fields.remaining_count`
- ✅ 添加 `filter.time_range`

---

## 🧪 测试验证

### 测试1: 活动名称显示

**访问:** 代理后台 → 摸奖券管理 → 摸奖券活动

**预期结果:**
- ✅ 活动名称列正常显示活动名称
- ✅ 不再显示为空或报错

---

### 测试2: 筛选器翻译

**操作:** 点击筛选器 → 查看"时间范围"标签

**预期结果:**
- ✅ 繁体中文：显示"時間範圍"
- ✅ 简体中文：显示"时间范围"
- ✅ English：显示"Time Range"
- ✅ Japanese：显示"時間範囲"

---

### 测试3: 批量操作和创建按钮

**访问:** 代理后台 → 摸奖券活动列表

**预期结果:**
- ✅ 没有"创建活动"按钮
- ✅ 没有批量删除等批量操作
- ✅ 只有"查看奖品配置"操作按钮

---

### 测试4: 奖品配置翻译

**操作:** 点击"查看奖品配置" → 查看弹窗表格

**预期结果:**
- ✅ "已中奖数"列显示正确翻译
  - 繁体中文：已中獎數
  - 简体中文：已中奖数
  - English：Won Count
  - Japanese：当選数
- ✅ "剩余数量"列显示正确翻译
  - 繁体中文：剩餘數量
  - 简体中文：剩余数量
  - English：Remaining
  - Japanese：残数

---

### 测试5: 数据权限

**测试场景:**
- 渠道 department_id = 1001
- 代理A (admin_id=10, department_id=1001)
- 代理B (admin_id=11, department_id=1001)
- 活动1 (department_id=1001)

**预期结果:**
- ✅ 代理A登录 → 能看到活动1
- ✅ 代理B登录 → 能看到活动1
- ✅ 两个代理都能查看同一个渠道的活动（这是正确的）

**注意:** 
代理后台的活动列表查看的是**整个渠道**的活动，因为活动是渠道级别创建的。
但摸奖券和中奖记录是**代理级别**的，使用 `agent_admin_id` 过滤。

---

## 📊 字段展示说明

### 活动列表字段

| 字段 | 类型 | 说明 | 计算方式 |
|------|------|------|---------|
| `name` | 数据库字段 | 活动名称 | 直接显示 |
| `start_time` | 数据库字段 | 开始时间 | 直接显示 |
| `end_time` | 数据库字段 | 结束时间 | 直接显示 |
| `status` | 数据库字段 | 活动状态 | 0=未开始,1=进行中,2=已结束,3=已关闭 |
| `total_tickets` | 数据库字段 | 总发放数量 | 直接显示 number_format() |
| `used_tickets` | 数据库字段 | 已使用数量 | 直接显示 number_format() |
| `usage_rate` | 计算字段 | 使用率 | (used_tickets / total_tickets) * 100 |
| `pending_count` | 统计字段 | 待发放 | COUNT(status=PENDING) |
| `created_at` | 数据库字段 | 创建时间 | 直接显示 |

### 奖品配置字段

| 字段 | 类型 | 说明 | 计算方式 |
|------|------|------|---------|
| `level_rank` | 数据库字段 | 等级排名 | 直接显示 |
| `level_name` | 数据库字段 | 等级名称 | 直接显示 |
| `prize_amount` | 数据库字段 | 奖品金额 | number_format(val, 2) |
| `prize_count` | 数据库字段 | 奖品数量 | 直接显示 |
| `won_count` | 数据库字段 | 已中奖数 | 带颜色标签 (>0 绿色) |
| `remaining_count` | 计算字段 | 剩余数量 | prize_count - won_count |

**剩余数量颜色规则:**
- `remaining <= 0` → 红色 (error)
- `remaining <= 3` → 黄色 (warning)
- `remaining > 3` → 绿色 (success)

---

## ⚠️ 注意事项

### 1️⃣ 数据权限差异

**活动列表 vs 摸奖券列表:**

| 控制器 | 数据范围 | 过滤字段 | 说明 |
|--------|---------|---------|------|
| AgentLotteryTicketActivityController | 整个渠道 | `department_id` | 活动是渠道级别的 |
| AgentLotteryTicketController | 当前代理 | `agent_admin_id` | 摸奖券是玩家级别的 |
| AgentLotteryTicketRecordController | 当前代理 | `agent_admin_id` | 中奖记录是玩家级别的 |

**为什么活动使用 department_id？**
- 活动是由渠道创建的，不是代理创建的
- 同一渠道下的所有代理共享同一批活动
- 但每个代理只能看到自己下属玩家的摸奖券和中奖记录

---

### 2️⃣ 翻译文件位置

所有摸奖券相关翻译都在 `lottery_ticket.php` 文件中：
```
addons/webman/lang/
  ├── zh-TW/lottery_ticket.php  (繁体中文)
  ├── zh-CN/lottery_ticket.php  (简体中文)
  ├── en/lottery_ticket.php     (English)
  └── jp/lottery_ticket.php     (Japanese)
```

---

### 3️⃣ 代理后台权限设计

**设计原则:**
- ✅ **查看** - 可以查看活动列表、奖品配置
- ❌ **创建** - 不能创建活动（由渠道创建）
- ❌ **编辑** - 不能编辑活动
- ❌ **删除** - 不能删除活动
- ❌ **批量操作** - 不能批量删除

**实现方式:**
```php
$grid->hideBatchActions();   // 隐藏批量操作
$grid->hideCreateButton();   // 隐藏创建按钮
// 操作栏只保留"查看奖品配置"按钮
```

---

## ✅ 修复总结

**修改控制器:** 1个文件
- ✅ 活动名称字段修正：`activity_name` → `name`
- ✅ 隐藏批量操作和创建按钮

**修改翻译:** 4个文件 × 2个位置 = 8处修改
- ✅ 添加 `filter.time_range` 等翻译
- ✅ 添加 `prize_level_fields.won_count` 翻译
- ✅ 添加 `prize_level_fields.remaining_count` 翻译
- ✅ 修正英文翻译中的中文残留

**功能验证:**
- ✅ 活动名称正常显示
- ✅ 筛选器翻译正确
- ✅ 代理无法删除活动（只能查看）
- ✅ 奖品配置翻译完整

修复完成！🎉
