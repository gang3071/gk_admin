# 代理后台摸奖券活动列表 - 列字段优化

## 🎯 优化目标

根据渠道后台 `ChannelLotteryTicketActivityController` 的实现，优化代理后台的列表展示，移除无用字段，添加有实际意义的字段。

---

## 🔄 字段对比

### 优化前（无用字段）

| 字段 | 说明 | 问题 |
|------|------|------|
| `total_tickets` | 总发放数量 | ❌ 对代理无实际意义，只是统计字段 |
| `used_tickets` | 已使用数量 | ❌ 对代理无实际意义，只是统计字段 |
| `usage_rate` | 使用率 | ❌ 需要计算，对代理无实际意义 |
| `pending_count` | 待发放 | ⚠️ 统计所有待发放记录，包括未中奖的 |

---

### 优化后（有用字段）

| 字段 | 说明 | 用途 |
|------|------|------|
| `max_ticket_no` ✅ | 最大券号 | **方便店家抽奖时知道放多少球的号码** |
| `pending_count` ✅ | 待发放奖励数 | **只统计有实际奖金的待发放记录** |

---

## 📝 修改内容

### 移除无用字段

**移除的字段:**
```php
// ❌ 移除：总发放数量
$grid->column('total_tickets', admin_trans('lottery_ticket.fields.total_tickets'))
    ->width(100)->align('center')
    ->display(function ($val) {
        return number_format($val);
    });

// ❌ 移除：已使用数量
$grid->column('used_tickets', admin_trans('lottery_ticket.fields.used_tickets'))
    ->width(100)->align('center')
    ->display(function ($val) {
        return number_format($val);
    });

// ❌ 移除：使用率
$grid->column('usage_rate', admin_trans('lottery_ticket.fields.usage_rate'))
    ->width(100)->align('center')
    ->display(function ($val, LotteryTicketActivity $data) {
        if ($data->total_tickets == 0) {
            return '0%';
        }
        $rate = ($data->used_tickets / $data->total_tickets) * 100;
        return number_format($rate, 2) . '%';
    });
```

---

### 添加有用字段

#### 1️⃣ 最大券号 (max_ticket_no)

**用途:** 方便店家抽奖时知道放多少球的号码

**实现:**
```php
// ✅ 新增：最大券号 - 方便店家抽奖时知道放多少球的号码
$grid->column('max_ticket_no', admin_trans('lottery_ticket.fields.max_ticket_no'))
    ->width(120)->align('center')
    ->display(function ($val, LotteryTicketActivity $data) {
        // 查询当前活动的最大券号（从 lottery_ticket 表中查询）
        $maxTicket = \addons\webman\model\LotteryTicket::where('activity_id', $data->id)
            ->orderBy('ticket_no', 'desc')
            ->value('ticket_no');

        if ($maxTicket) {
            return Tag::create($maxTicket)->color('blue');
        } else {
            return Tag::create('000000')->color('default');
        }
    });
```

**示例显示:**
- 活动有券号：`000001`, `000002`, `000003` → 显示 <Badge color="blue">000003</Badge>
- 活动无券号 → 显示 <Badge>000000</Badge>

---

#### 2️⃣ 待发放奖励数 (pending_count) - 优化

**用途:** 只统计有实际奖金的待发放中奖记录（排除未中奖和0元奖）

**优化前:**
```php
// ⚠️ 旧版本：统计所有待发放记录（包括未中奖的）
$grid->column('pending_count', admin_trans('lottery_ticket.fields.pending_count'))
    ->width(100)->align('center')
    ->display(function ($val, LotteryTicketActivity $data) {
        $count = LotteryTicketRecord::where('activity_id', $data->id)
            ->where('status', LotteryTicketRecord::STATUS_PENDING)
            ->count();  // ❌ 包括未中奖的记录
        return $count > 0 ? Tag::create($count)->color('warning') : $count;
    });
```

**优化后:**
```php
// ✅ 新版本：只统计有奖金的待发放记录
$grid->column('pending_count', admin_trans('lottery_ticket.fields.pending_count'))
    ->width(100)->align('center')
    ->display(function ($val, LotteryTicketActivity $data) {
        // 只统计有奖金的待发放记录（排除未中奖和0元奖）
        $count = LotteryTicketRecord::where('activity_id', $data->id)
            ->where('status', LotteryTicketRecord::STATUS_PENDING)
            ->where('prize_type', '!=', LotteryTicketRecord::PRIZE_TYPE_EMPTY)  // ✅ 排除未中奖
            ->where('prize_amount', '>', 0)  // ✅ 排除0元奖
            ->count();
        return $count > 0 ? Tag::create($count)->color('warning') : Tag::create('0')->color('success');
    });
```

**示例显示:**
- 有待发放奖励 → 显示 <Badge color="warning">5</Badge>
- 无待发放奖励 → 显示 <Badge color="success">0</Badge>

---

## 📊 优化后的列表结构

| 列名 | 宽度 | 对齐 | 说明 |
|------|------|------|------|
| ID | 80px | center | 活动ID |
| 活动名称 | 200px | left | 活动名称（fixed） |
| 开始时间 | 160px | center | 活动开始时间 |
| 结束时间 | 160px | center | 活动结束时间 |
| 状态 | 100px | center | 活动状态（未开始/进行中/已结束/已关闭） |
| **最大券号** ✅ | 120px | center | **当前已发放的最后一张券号** |
| **待发放奖励数** ✅ | 100px | center | **有实际奖金的待发放记录数** |
| 创建时间 | 160px | center | 活动创建时间 |
| 操作 | auto | center | 查看奖品配置 |

---

## 🎯 业务场景说明

### 场景1: 店家准备抽奖

**需求:** 店家需要知道放多少个号码球

**解决方案:**
- 查看"最大券号"列
- 例如显示 `000150`
- 店家准备 1-150 号的球

**流程:**
```
代理查看活动列表
  ↓
看到"最大券号" = 000150
  ↓
通知店家准备 1-150 号球
  ↓
店家进行摸奖抽取
```

---

### 场景2: 查看待发放奖励

**需求:** 代理需要知道有多少中奖记录还没发放

**解决方案:**
- 查看"待发放奖励数"列
- 只显示有实际奖金的记录（排除未中奖和0元）
- 代理可以联系渠道进行奖励发放

**统计逻辑:**
```sql
-- 待发放奖励数查询
SELECT COUNT(*) 
FROM lottery_ticket_record 
WHERE activity_id = ?
  AND status = 0  -- STATUS_PENDING (待发放)
  AND prize_type != 4  -- 排除 PRIZE_TYPE_EMPTY (未中奖)
  AND prize_amount > 0  -- 排除 0 元奖
```

**示例数据:**
```
活动 ID: 1
中奖记录:
  - 记录1: 100元现金，status=PENDING → ✅ 计入
  - 记录2: 50元红利，status=PENDING → ✅ 计入
  - 记录3: 未中奖，status=PENDING → ❌ 不计入
  - 记录4: 0元，status=PENDING → ❌ 不计入
  - 记录5: 100元现金，status=CLAIMED → ❌ 不计入（已发放）

待发放奖励数 = 2
```

---

## 📋 与渠道后台的对比

### 渠道后台 (ChannelLotteryTicketActivityController)

**使用 Vue 组件:**
```javascript
// 使用 Vue 组件展示，更灵活
return admin_view('lottery_ticket_activities.vue')->attrs([
    'department_id' => $departmentId,
    'vip_levels' => $vipLevels,
    'trans' => $trans,
]);
```

**API 返回数据:**
```php
$activityArray['max_ticket_no'] = str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT);
$activityArray['pending_count'] = LotteryTicketRecord::where(...)
    ->where('prize_type', '!=', PRIZE_TYPE_EMPTY)
    ->where('prize_amount', '>', 0)
    ->count();
```

---

### 代理后台 (AgentLotteryTicketActivityController)

**使用 ExAdmin Grid:**
```php
// 使用 ExAdmin Grid 组件展示
return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
    // 列定义
    $grid->column('max_ticket_no', '...')->display(...);
    $grid->column('pending_count', '...')->display(...);
});
```

**一致性:**
- ✅ 统计逻辑完全一致
- ✅ 字段含义完全一致
- ✅ 业务场景完全一致

---

## 🧪 测试验证

### 测试1: 最大券号显示

**数据准备:**
```sql
-- 活动1
INSERT INTO lottery_ticket_activity (id, name) VALUES (1, '春节摸奖活动');

-- 摸奖券数据
INSERT INTO lottery_ticket (activity_id, ticket_no) VALUES
    (1, '000001'),
    (1, '000002'),
    (1, '000150');
```

**预期结果:**
- 活动1的"最大券号"列显示：<Badge color="blue">000150</Badge>

---

### 测试2: 待发放奖励数

**数据准备:**
```sql
-- 中奖记录
INSERT INTO lottery_ticket_record (activity_id, prize_type, prize_amount, status) VALUES
    (1, 0, 100, 0),  -- 现金100元，待发放 ✅
    (1, 1, 50, 0),   -- 红利50元，待发放 ✅
    (1, 4, 0, 0),    -- 未中奖，待发放 ❌
    (1, 0, 0, 0),    -- 0元，待发放 ❌
    (1, 0, 100, 1);  -- 现金100元，已发放 ❌
```

**预期结果:**
- 活动1的"待发放奖励数"列显示：<Badge color="warning">2</Badge>

---

### 测试3: 空数据显示

**数据准备:**
```sql
-- 活动2：无摸奖券，无中奖记录
INSERT INTO lottery_ticket_activity (id, name) VALUES (2, '测试活动');
```

**预期结果:**
- 活动2的"最大券号"列显示：<Badge>000000</Badge>
- 活动2的"待发放奖励数"列显示：<Badge color="success">0</Badge>

---

## ⚠️ 注意事项

### 1️⃣ 性能优化

**问题:** 每行都需要查询 `lottery_ticket` 表和 `lottery_ticket_record` 表

**优化方案:**
- 当前实现：每行一次查询（可接受，活动数量不会很大）
- 未来优化：如果活动数量很大，可以考虑预加载（Eager Loading）

**预加载示例（未来优化）:**
```php
// 在 Grid 外部预先统计所有活动的数据
$activityIds = LotteryTicketActivity::where('department_id', $departmentId)
    ->pluck('id')
    ->toArray();

$maxTickets = LotteryTicket::whereIn('activity_id', $activityIds)
    ->select('activity_id', DB::raw('MAX(ticket_no) as max_no'))
    ->groupBy('activity_id')
    ->pluck('max_no', 'activity_id')
    ->toArray();

// 在 Grid 中使用预加载的数据
$grid->column('max_ticket_no')->display(function ($val, $data) use ($maxTickets) {
    return $maxTickets[$data->id] ?? '000000';
});
```

---

### 2️⃣ 数据一致性

**最大券号的计算:**
- 渠道后台：使用 `current_ticket_no - 1`（假设有该字段）
- 代理后台：查询 `MAX(ticket_no)`（更准确）

**推荐方案:** 使用 `MAX(ticket_no)` 查询（当前实现）
- ✅ 数据库实际存在的最大券号
- ✅ 准确反映发放情况
- ✅ 不依赖额外字段

---

### 3️⃣ 翻译完整性

**确认翻译已添加:**
- ✅ `lottery_ticket.fields.max_ticket_no` - 最大券号
- ✅ `lottery_ticket.fields.pending_count` - 待发放
- ✅ 4个语言文件都已添加

---

## ✅ 优化总结

**移除字段 (3个):**
- ❌ `total_tickets` - 总发放数量
- ❌ `used_tickets` - 已使用数量
- ❌ `usage_rate` - 使用率

**保留/优化字段 (2个):**
- ✅ `max_ticket_no` - 最大券号（新增，关键业务字段）
- ✅ `pending_count` - 待发放奖励数（优化统计逻辑）

**业务价值:**
- ✅ 店家抽奖时知道放多少球（max_ticket_no）
- ✅ 代理知道有多少奖励待发放（pending_count）
- ✅ 列表更简洁，信息更有价值

优化完成！现在代理后台的活动列表展示更符合实际业务需求！🎉
