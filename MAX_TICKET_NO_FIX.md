# 最大券号显示修复 & 30天活动限制

## 📅 修改日期
2026-06-18

---

## 🎯 问题描述

### 问题1：最大券号显示错误
**现象：** 面板显示 `最大券號: 1000000`  
**期望：** 显示当前已发放的实际最大券号（如：123）

### 问题2：活动列表过长
**现象：** 面板显示所有历史活动  
**期望：** 只显示最近30天的活动

---

## ✅ 修复方案

### 1. 最大券号修复

**原逻辑（错误）：**
- 使用数据库字段 `max_ticket_no`（默认值 1000000）
- 这是抽奖球的**容量上限**，不是当前已发放的最大券号

**新逻辑（正确）：**
- 查询 `lottery_ticket` 表的 `MAX(ticket_no)`
- 返回当前已发放的实际最大券号
- 转换为数字显示（去除前导0）

**示例：**
```
已发放券号：000001, 000002, ..., 000123
显示：123（不是 1000000）
```

---

## 📝 修改文件清单

### 1. ChannelLotteryTicketActivityController.php ⭐

**文件：** `addons/webman/controller/ChannelLotteryTicketActivityController.php`

#### 修改1：getActivities() 方法 - 批量查询优化

**位置：** Line 218-241

**修改前（N+1查询）：**
```php
$activities = $activities->map(function ($activity) {
    // ❌ 每个活动单独查询（N+1问题）
    $maxTicketNo = \addons\webman\model\LotteryTicket::where('activity_id', $activity->id)
        ->orderBy('ticket_no', 'desc')
        ->value('ticket_no');
    
    $activityArray['max_ticket_no'] = $maxTicketNo ? (int)$maxTicketNo : 0;
    return $activityArray;
});
```

**修改后（批量查询）：**
```php
// ✅ 批量查询优化（避免N+1）
$activityIds = $activities->pluck('id')->toArray();

// 批量查询：最大券号
$maxTicketNos = \addons\webman\model\LotteryTicket::query()
    ->whereIn('activity_id', $activityIds)
    ->select('activity_id', Db::raw('MAX(CAST(ticket_no AS UNSIGNED)) as max_no'))
    ->groupBy('activity_id')
    ->pluck('max_no', 'activity_id')
    ->toArray();

// 添加字段
$activities = $activities->map(function ($activity) use ($maxTicketNos) {
    $activityArray['max_ticket_no'] = $maxTicketNos[$activity->id] ?? 0;
    return $activityArray;
});
```

**性能优化：**
- 旧逻辑：N+3 次查询（N个活动 + 3次统计查询）
- 新逻辑：3 次查询（批量查询）
- 性能提升：~N倍

#### 修改2：30天活动限制

**位置：** Line 199-202

**新增代码：**
```php
// ✅ 只显示30天内的活动（created_at 在最近30天内）
$thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
$query->where('created_at', '>=', $thirtyDaysAgo);
```

**影响：**
- 面板只显示最近30天创建的活动
- 减少数据量，提升加载速度
- 历史活动可通过"历史记录"入口查看

---

### 2. AgentLotteryTicketActivityController.php

**文件：** `addons/webman/controller/AgentLotteryTicketActivityController.php`

**位置：** Line 91-105

**修改：** Grid 最大券号列显示逻辑

**修改前：**
```php
if ($maxTicket) {
    return Tag::create($maxTicket)->color('blue');  // ❌ 显示 "000123"
} else {
    return Tag::create('000000')->color('default');
}
```

**修改后：**
```php
if ($maxTicket) {
    // ✅ 转换为数字显示（去除前导0）
    $maxNumber = (int)$maxTicket;
    return Tag::create($maxNumber)->color('blue');  // ✅ 显示 "123"
} else {
    return Tag::create('0')->color('default');
}
```

---

### 3. StoreLotteryTicketActivityController.php

**文件：** `addons/webman/controller/StoreLotteryTicketActivityController.php`

**位置：** Line 91-105

**修改：** 与 AgentLotteryTicketActivityController 相同

---

### 4. ChannelLotteryTicketStatisticsController.php

**文件：** `addons/webman/controller/ChannelLotteryTicketStatisticsController.php`

**位置：** Line 59-68

**修改前：**
```php
'current_ticket_no' => $activity->current_ticket_no,
'max_ticket_no' => $activity->current_ticket_no > 0 
    ? str_pad($activity->current_ticket_no - 1, 6, '0', STR_PAD_LEFT) 
    : '000000',  // ❌ 使用 current_ticket_no 字段（已废弃）
```

**修改后：**
```php
// ✅ 查询实际最大券号（店家抽奖时需要知道放多少号球）
'max_ticket_no' => \addons\webman\model\LotteryTicket::where('activity_id', $activityId)
    ->max('ticket_no') ?: 0,
```

---

## 🔍 技术细节

### 批量查询SQL示例

**旧逻辑（N+1）：**
```sql
-- 查询活动列表
SELECT * FROM lottery_ticket_activity WHERE department_id = 34;

-- 对每个活动单独查询（假设10个活动）
SELECT ticket_no FROM lottery_ticket WHERE activity_id = 1 ORDER BY ticket_no DESC LIMIT 1;
SELECT ticket_no FROM lottery_ticket WHERE activity_id = 2 ORDER BY ticket_no DESC LIMIT 1;
...
SELECT ticket_no FROM lottery_ticket WHERE activity_id = 10 ORDER BY ticket_no DESC LIMIT 1;

-- 总查询：11次
```

**新逻辑（批量）：**
```sql
-- 查询活动列表
SELECT * FROM lottery_ticket_activity WHERE department_id = 34;

-- 批量查询所有活动的最大券号
SELECT 
    activity_id, 
    MAX(CAST(ticket_no AS UNSIGNED)) as max_no 
FROM lottery_ticket 
WHERE activity_id IN (1,2,3,4,5,6,7,8,9,10)
GROUP BY activity_id;

-- 总查询：2次
```

### 数据类型转换

**ticket_no 字段类型：** `VARCHAR(6)`（存储 "000123"）

**显示转换：**
```php
(int)"000123"  → 123   // ✅ 去除前导0
(int)"000000"  → 0     // ✅ 默认值
```

---

## 🎯 功能影响

### 1. 面板展示（Vue 组件）

**最大券号卡片：**
```
┌──────────────────┐
│  最大券號         │
│  123             │  ← 旧值：1000000
│  (当前已发放)    │
└──────────────────┘
```

**30天限制：**
- 只显示最近30天创建的活动
- 旧活动不影响性能
- 可通过"历史记录"入口查看全部

### 2. Grid 表格（Agent/Store 后台）

| 活动名称 | 开始时间 | 结束时间 | 状态 | 最大券号 |
|---------|---------|---------|------|---------|
| 测试活动 | 2026-06-15 | 2026-06-20 | 进行中 | **123** |

- 旧值：`000123`（字符串）
- 新值：`123`（数字）

### 3. 统计页面

**券号统计区块：**
```json
{
  "total_tickets": 5000,
  "used_tickets": 123,
  "max_ticket_no": 123,  // ← 旧值：字符串 "000122"
  "ticket_usage_rate": 2.46
}
```

---

## 🔄 数据库查询优化对比

| 场景 | 旧逻辑查询次数 | 新逻辑查询次数 | 优化倍数 |
|------|---------------|---------------|---------|
| 10个活动 | 13次 | 3次 | 4.3x |
| 20个活动 | 23次 | 3次 | 7.7x |
| 50个活动 | 53次 | 3次 | 17.7x |

**固定查询（3次）：**
1. 奖品配置检查（has_prize_config）
2. 待发放数量统计（pending_count）
3. 最大券号查询（max_ticket_no）

---

## ✅ 测试验证

### 1. 最大券号显示

**测试步骤：**
1. 创建新活动
2. 发放奖券：000001, 000002, 000003
3. 刷新面板

**预期结果：**
- 面板显示：`最大券號: 3`（不是 1000000）
- Grid 显示：蓝色标签 `3`（不是 `000003`）

### 2. 30天限制

**测试步骤：**
1. 创建一个31天前的活动（修改数据库 `created_at`）
2. 创建一个今天的活动
3. 刷新面板

**预期结果：**
- 只显示今天的活动
- 31天前的活动不显示

### 3. 性能测试

**测试步骤：**
1. 创建50个活动
2. 打开开发者工具 Network 标签
3. 刷新面板
4. 查看请求时间

**预期结果：**
- 查询时间减少（批量查询优化）
- 数据库查询日志减少（从53次降到3次）

---

## 📌 注意事项

### 1. 历史数据兼容性

**30天限制不影响：**
- 历史活动数据（数据库仍保留）
- "历史记录"页面查询（仍可查看全部）
- 统计报表（统计逻辑未改变）

**只影响：**
- 面板主页的活动列表
- 减少不必要的数据加载

### 2. 最大券号为0的情况

**原因：** 活动刚创建，还未发放任何奖券

**显示：**
- 面板：`最大券號: 0`
- Grid：灰色标签 `0`

### 3. 数据库字段说明

| 字段 | 类型 | 说明 | 是否使用 |
|------|------|------|---------|
| `max_ticket_no` | INT | 抽奖球容量上限（1000000） | ❌ 已废弃 |
| `current_ticket_no` | INT | Redis序列号计数器 | ❌ 已废弃 |
| `lottery_ticket.ticket_no` | VARCHAR(6) | 实际发放的券号 | ✅ 使用 |

---

## 🎉 修复总结

### 修改统计

| 文件 | 修改位置 | 类型 |
|------|---------|------|
| ChannelLotteryTicketActivityController.php | Line 199-202, 218-251 | 功能 + 性能优化 |
| AgentLotteryTicketActivityController.php | Line 91-105 | 显示优化 |
| StoreLotteryTicketActivityController.php | Line 91-105 | 显示优化 |
| ChannelLotteryTicketStatisticsController.php | Line 59-68 | 查询修复 |

### 核心改进

1. ✅ **最大券号正确显示** - 从数据库查询实际值
2. ✅ **批量查询优化** - 避免N+1问题，性能提升4-17倍
3. ✅ **30天活动限制** - 减少数据量，提升加载速度
4. ✅ **数字格式显示** - 去除前导0，更直观

---

**修复完成时间：** 2026-06-18  
**版本：** v2.0 - 最大券号修复 & 30天限制  
**状态：** ✅ 完成（需重启服务）
