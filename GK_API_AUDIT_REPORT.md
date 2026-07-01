# gk_api 项目"待开奖"状态审查报告

## 📅 审查日期
2026-06-18

---

## 🎯 审查范围

**项目：** gk_api  
**审查重点：** 加入 STATUS_PENDING_DRAW (5) 状态后的逻辑完整性

---

## ✅ 审查结果总结

| 类别 | 状态 | 问题数 |
|------|------|--------|
| 模型定义 | ✅ 通过 | 0 |
| API优先级 | ✅ 通过 | 0 |
| 状态文本 | ⚠️ 已修复 | 1 |
| 倒计时逻辑 | ⚠️ 已修复 | 1 |
| 业务逻辑 | ✅ 通过 | 0 |

**总计：** 发现 2 个问题，已全部修复 ✅

---

## 📝 详细审查

### 1. 模型定义 ✅

**文件：** `app/model/LotteryTicketActivity.php`

**检查项：**
- ✅ 状态常量定义完整
- ✅ 常量值正确 (STATUS_PENDING_DRAW = 5)
- ✅ 注释清晰

**代码：**
```php
const STATUS_NOT_STARTED = 0;      // 未开始
const STATUS_ONGOING = 1;          // 进行中（玩家打码获券阶段）
const STATUS_ENDED = 2;            // 已结束（完全结束，所有流程完成）
const STATUS_CLOSED = 3;           // 已关闭（手动关闭，异常终止）
const STATUS_PENDING_DRAW = 5;     // 待开奖（end_time 到达，等待管理员开奖）✅
const STATUS_DRAWING = 6;          // 开奖中（管理员摇球阶段）
```

**结论：** ✅ 无问题

---

### 2. API优先级逻辑 ✅

**文件：** `app/api/controller/v1/LotteryTicketController.php`  
**方法：** `getSmartActivity()`

**检查项：**
- ✅ 优先级包含待开奖状态
- ✅ 排序正确（待开奖排第2位）
- ✅ 缓存逻辑正确

**代码：**
```php
// 优先级1: 开奖中的活动（最高优先级）
$activity = LotteryTicketActivity::query()
    ->where('status', LotteryTicketActivity::STATUS_DRAWING)
    ->first();

if (!$activity) {
    // 优先级2: 待开奖的活动 ✅ 已添加
    $activity = LotteryTicketActivity::query()
        ->where('status', LotteryTicketActivity::STATUS_PENDING_DRAW)
        ->first();
}

if (!$activity) {
    // 优先级3: 进行中的活动
    $activity = LotteryTicketActivity::query()
        ->where('status', LotteryTicketActivity::STATUS_ONGOING)
        ->first();
}
```

**结论：** ✅ 无问题

---

### 3. 状态文本映射 ⚠️ → ✅

**文件：** `app/api/controller/v1/LotteryTicketController.php`  
**方法：** `getActivityStatusText()`

**问题：** 缺少 `STATUS_PENDING_DRAW` 的文本映射

**修复前：**
```php
private function getActivityStatusText(int $status): string
{
    return match($status) {
        LotteryTicketActivity::STATUS_NOT_STARTED => '即將開始',
        LotteryTicketActivity::STATUS_ONGOING => '進行中',
        // ❌ 缺少 STATUS_PENDING_DRAW
        LotteryTicketActivity::STATUS_DRAWING => '開獎中',
        LotteryTicketActivity::STATUS_ENDED => '已結束',
        LotteryTicketActivity::STATUS_CLOSED => '已關閉',
        default => '未知狀態',
    };
}
```

**影响：**
- 待开奖状态的活动会返回 `"未知狀態"`
- 客户端显示不正确

**修复后：**
```php
private function getActivityStatusText(int $status): string
{
    return match($status) {
        LotteryTicketActivity::STATUS_NOT_STARTED => '即將開始',
        LotteryTicketActivity::STATUS_ONGOING => '進行中',
        LotteryTicketActivity::STATUS_PENDING_DRAW => '待開獎',  // ✅ 已添加
        LotteryTicketActivity::STATUS_DRAWING => '開獎中',
        LotteryTicketActivity::STATUS_ENDED => '已結束',
        LotteryTicketActivity::STATUS_CLOSED => '已關閉',
        default => '未知狀態',
    };
}
```

**结论：** ✅ 已修复

---

### 4. 倒计时逻辑 ⚠️ → ✅

**文件：** `app/api/controller/v1/LotteryTicketController.php`  
**方法：** `calculateCountdown()`

**问题：** 缺少 `STATUS_PENDING_DRAW` 的倒计时处理

**修复前：**
```php
switch ($activity->status) {
    case LotteryTicketActivity::STATUS_NOT_STARTED:
        // 距离开始时间
        return [...];
    
    case LotteryTicketActivity::STATUS_ONGOING:
        // 距离结束时间
        return [...];
    
    // ❌ 缺少 STATUS_PENDING_DRAW
    
    case LotteryTicketActivity::STATUS_DRAWING:
        // 开奖中
        return [...];
}
```

**影响：**
- 待开奖状态的活动倒计时返回 `null`
- 客户端可能显示异常或空白

**修复后：**
```php
switch ($activity->status) {
    case LotteryTicketActivity::STATUS_NOT_STARTED:
        // 距离开始时间
        return [...];
    
    case LotteryTicketActivity::STATUS_ONGOING:
        // 距离结束时间
        return [...];
    
    case LotteryTicketActivity::STATUS_PENDING_DRAW:  // ✅ 已添加
        // 待开奖，显示等待开奖提示
        return [
            'type' => 'pending_draw',
            'label' => '等待開獎',
            'seconds' => 0,
            'formatted' => '等待開獎中'
        ];
    
    case LotteryTicketActivity::STATUS_DRAWING:
        // 开奖中
        return [...];
}
```

**返回示例：**
```json
{
  "countdown": {
    "type": "pending_draw",
    "label": "等待開獎",
    "seconds": 0,
    "formatted": "等待開獎中"
  }
}
```

**结论：** ✅ 已修复

---

### 5. 业务逻辑检查 ✅

#### 5.1 开奖状态判断 (`has_drawn`)

**代码：**
```php
'has_drawn' => in_array($activity->status, [
    LotteryTicketActivity::STATUS_DRAWING,
    LotteryTicketActivity::STATUS_ENDED,
]),
```

**逻辑分析：**
- `has_drawn` = "是否已开奖"
- 待开奖状态 = 还没开奖 → 不应该包含 ✅
- 开奖中状态 = 正在开奖 → 应该包含 ✅
- 已结束状态 = 已经开奖完成 → 应该包含 ✅

**结论：** ✅ 逻辑正确，无需修改

---

#### 5.2 中奖人数显示 (`total_winners`)

**代码：**
```php
'total_winners' => in_array($activity->status, [
    LotteryTicketActivity::STATUS_DRAWING,
    LotteryTicketActivity::STATUS_ENDED,
])
    ? LotteryTicketRecord::where('activity_id', $activity->id)->count()
    : 0,
```

**逻辑分析：**
- 待开奖状态：可能管理员已经提前录入中奖券号
- 是否应该显示中奖人数？

**决策：**
- ❓ 需要确认业务需求
- 建议：待开奖时也显示已录入的中奖人数

**建议修改：**
```php
'total_winners' => in_array($activity->status, [
    LotteryTicketActivity::STATUS_PENDING_DRAW,  // ⭐ 建议添加
    LotteryTicketActivity::STATUS_DRAWING,
    LotteryTicketActivity::STATUS_ENDED,
])
    ? LotteryTicketRecord::where('activity_id', $activity->id)->count()
    : 0,
```

**结论：** ⚠️ 建议调整（待确认业务需求）

---

#### 5.3 有效摸奖券统计

**文件：** `app/api/controller/v1/PlayerController.php`  
**方法：** `info()`

**代码：**
```php
$validLotteryTicketCount = LotteryTicket::query()
    ->join('lottery_ticket_activity as a', 'lottery_ticket.activity_id', '=', 'a.id')
    ->where('lottery_ticket.player_id', $player->id)
    ->where('lottery_ticket.status', LotteryTicket::STATUS_UNUSED)
    ->where('lottery_ticket.expired_at', '>', date('Y-m-d H:i:s'))
    ->where('a.status', '!=', LotteryTicketActivity::STATUS_CLOSED)  // 只排除关闭的活动
    ->count('lottery_ticket.id');
```

**逻辑分析：**
- 只排除了 `STATUS_CLOSED` 状态
- 待开奖、开奖中、已结束的活动的奖券都算有效 ✅
- 这是合理的，因为这些状态下奖券仍可以兑奖

**结论：** ✅ 逻辑正确

---

## 🔍 其他检查项

### 数据库查询

**已检查的查询：**
1. ✅ `getSmartActivity()` - 优先级查询
2. ✅ `buildActivityResponse()` - 活动详情构建
3. ✅ `calculateCountdown()` - 倒计时计算
4. ✅ 有效摸奖券统计

**未发现的问题：** 无

---

### 状态常量使用

**全部状态引用：**
- ✅ STATUS_NOT_STARTED (0)
- ✅ STATUS_ONGOING (1)
- ✅ STATUS_ENDED (2)
- ✅ STATUS_CLOSED (3)
- ✅ STATUS_PENDING_DRAW (5) - 已添加
- ✅ STATUS_DRAWING (6)

**未发现硬编码状态值**

---

## 📊 修复总结

### 修复的问题

| 问题 | 位置 | 严重程度 | 状态 |
|------|------|---------|------|
| 状态文本缺失 | `getActivityStatusText()` | 🔴 高 | ✅ 已修复 |
| 倒计时逻辑缺失 | `calculateCountdown()` | 🔴 高 | ✅ 已修复 |

### 建议调整（可选）

| 建议 | 位置 | 优先级 | 理由 |
|------|------|--------|------|
| 中奖人数显示 | `buildActivityResponse()` | 🟡 中 | 待开奖时可能已录入中奖，建议显示 |

---

## ✅ 审查结论

### 总体评估

**状态：** ✅ 通过（已修复所有问题）

**修改文件：**
1. ✅ `app/api/controller/v1/LotteryTicketController.php` - 2处修复

**未修改文件（逻辑正确）：**
1. ✅ `app/model/LotteryTicketActivity.php` - 无需修改
2. ✅ `app/api/controller/v1/PlayerController.php` - 无需修改

---

## 🔄 API 响应变化

### 修复前（待开奖状态）

```json
{
  "activity": {
    "status": 5,
    "status_text": "未知狀態",  // ❌ 错误
    "countdown": null           // ❌ 错误
  }
}
```

### 修复后（待开奖状态）

```json
{
  "activity": {
    "status": 5,
    "status_text": "待開獎",    // ✅ 正确
    "countdown": {              // ✅ 正确
      "type": "pending_draw",
      "label": "等待開獎",
      "seconds": 0,
      "formatted": "等待開獎中"
    }
  }
}
```

---

## 🎯 测试建议

### 1. 单元测试

```php
// 测试状态文本
$controller = new LotteryTicketController();
$text = $controller->getActivityStatusText(5);
assert($text === '待開獎');  // ✅

// 测试倒计时
$activity = new LotteryTicketActivity();
$activity->status = LotteryTicketActivity::STATUS_PENDING_DRAW;
$countdown = $controller->calculateCountdown($activity);
assert($countdown['type'] === 'pending_draw');  // ✅
```

### 2. API 测试

```bash
# 获取待开奖活动
curl -X POST http://localhost:8787/api/v1/lottery-ticket/get-current-activity \
  -H "Authorization: Bearer <token>"

# 验证返回：
# - status: 5
# - status_text: "待開獎"
# - countdown.type: "pending_draw"
```

---

## 📝 附录：完整状态流程

```
客户端视角（gk_api）:

0. 未开始 (STATUS_NOT_STARTED)
   status_text: "即將開始"
   countdown: 距离开始时间
   has_drawn: false
   ↓
1. 进行中 (STATUS_ONGOING)
   status_text: "進行中"
   countdown: 距离结束时间
   has_drawn: false
   ↓
2. 待开奖 (STATUS_PENDING_DRAW) ⭐
   status_text: "待開獎" ✅
   countdown: "等待開獎中" ✅
   has_drawn: false ✅
   total_winners: 0 (建议显示已录入数量)
   ↓
3. 开奖中 (STATUS_DRAWING)
   status_text: "開獎中"
   countdown: "開獎中"
   has_drawn: true
   total_winners: 实际中奖人数
   ↓
4. 已结束 (STATUS_ENDED)
   status_text: "已結束"
   countdown: "活動已結束"
   has_drawn: true
   total_winners: 实际中奖人数
```

---

**审查完成时间：** 2026-06-18  
**审查结果：** ✅ 通过（已修复所有问题）  
**修复文件数：** 1  
**发现问题数：** 2（已全部修复）
