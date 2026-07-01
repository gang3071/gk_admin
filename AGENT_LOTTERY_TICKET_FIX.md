# 代理后台摸奖券功能 - 代码修复

## 🐛 发现的问题

### 1️⃣ **AgentLotteryTicketActivityController 缺少导入**

**问题:**
- ❌ 未导入 `Actions` 类
- ❌ 使用了 `abort()` 函数但未导入

**修复:**
```php
// ✅ 添加导入
use ExAdmin\ui\component\grid\grid\Actions;
```

**说明:**
- `Actions` 用于操作栏按钮
- `abort()` 是 Laravel 全局函数，无需导入（PhpStorm 误报）

---

### 2️⃣ **AgentLotteryTicketController 字段使用错误**

**问题:**
- ❌ 使用了不存在的字段 `is_used` 和 `is_won`
- ❌ 使用了不存在的常量 `LotteryTicket::USED` 和 `LotteryTicket::WON`

**原因:**
`LotteryTicket` 模型的字段结构与预期不同：

**实际结构:**
```php
// LotteryTicket 模型字段
status        // 状态(0:未使用,1:已使用,2:已过期)
source        // 来源(recharge/activity/manual)
ticket_no     // 券号
used_at       // 使用时间
expired_at    // 过期时间

// 常量定义
const STATUS_UNUSED = 0;    // 未使用
const STATUS_USED = 1;      // 已使用
const STATUS_EXPIRED = 2;   // 已过期

const SOURCE_RECHARGE = 'recharge';  // 充值赠送
const SOURCE_ACTIVITY = 'activity';  // 活动赠送
const SOURCE_MANUAL = 'manual';      // 手动发放
```

**修复:**

**修复前 (错误):**
```php
// ❌ 错误：使用不存在的字段
$grid->column('is_used', admin_trans('lottery_ticket.fields.is_used'))
    ->display(function ($val) {
        if ($val == LotteryTicket::USED) {
            return Tag::create(admin_trans('lottery_ticket.used_status.used'))->color('success');
        }
        return Tag::create(admin_trans('lottery_ticket.used_status.unused'))->color('default');
    });

$grid->column('is_won', admin_trans('lottery_ticket.fields.is_won'))
    ->display(function ($val) {
        if ($val == LotteryTicket::WON) {
            return Tag::create(admin_trans('lottery_ticket.won_status.won'))->color('error');
        }
        return Tag::create(admin_trans('lottery_ticket.won_status.not_won'))->color('default');
    });

$grid->column('bet_amount', admin_trans('lottery_ticket.fields.bet_amount'));
```

**修复后 (正确):**
```php
// ✅ 正确：使用 status 字段
$grid->column('status', admin_trans('lottery_ticket.fields.status'))
    ->width(100)->align('center')
    ->display(function ($val) {
        $statusMap = [
            LotteryTicket::STATUS_UNUSED => ['text' => admin_trans('lottery_ticket.ticket_status.unused'), 'color' => 'default'],
            LotteryTicket::STATUS_USED => ['text' => admin_trans('lottery_ticket.ticket_status.used'), 'color' => 'success'],
            LotteryTicket::STATUS_EXPIRED => ['text' => admin_trans('lottery_ticket.ticket_status.expired'), 'color' => 'error'],
        ];
        $config = $statusMap[$val] ?? ['text' => $val, 'color' => 'default'];
        return Tag::create($config['text'])->color($config['color']);
    });

// ✅ 新增：显示来源
$grid->column('source', admin_trans('lottery_ticket.fields.source'))
    ->width(100)->align('center')
    ->display(function ($val) {
        $sourceMap = [
            LotteryTicket::SOURCE_RECHARGE => admin_trans('lottery_ticket.source.recharge'),
            LotteryTicket::SOURCE_ACTIVITY => admin_trans('lottery_ticket.source.activity'),
            LotteryTicket::SOURCE_MANUAL => admin_trans('lottery_ticket.source.manual'),
        ];
        return $sourceMap[$val] ?? $val;
    });

// ✅ 新增：显示过期时间
$grid->column('expired_at', admin_trans('lottery_ticket.fields.expired_at'))
    ->width(160)->align('center')
    ->display(function ($val) {
        return $val ?: '-';
    });
```

**筛选器修复:**

**修复前:**
```php
// ❌ 错误
$filter->eq()->select('is_used')
    ->options([
        LotteryTicket::UNUSED => admin_trans('lottery_ticket.used_status.unused'),
        LotteryTicket::USED => admin_trans('lottery_ticket.used_status.used'),
    ]);

$filter->eq()->select('is_won')
    ->options([
        LotteryTicket::NOT_WON => admin_trans('lottery_ticket.won_status.not_won'),
        LotteryTicket::WON => admin_trans('lottery_ticket.won_status.won'),
    ]);
```

**修复后:**
```php
// ✅ 正确：按 status 筛选
$filter->eq()->select('status')
    ->placeholder(admin_trans('lottery_ticket.fields.status'))
    ->options([
        LotteryTicket::STATUS_UNUSED => admin_trans('lottery_ticket.ticket_status.unused'),
        LotteryTicket::STATUS_USED => admin_trans('lottery_ticket.ticket_status.used'),
        LotteryTicket::STATUS_EXPIRED => admin_trans('lottery_ticket.ticket_status.expired'),
    ]);

// ✅ 新增：按来源筛选
$filter->eq()->select('source')
    ->placeholder(admin_trans('lottery_ticket.fields.source'))
    ->options([
        LotteryTicket::SOURCE_RECHARGE => admin_trans('lottery_ticket.source.recharge'),
        LotteryTicket::SOURCE_ACTIVITY => admin_trans('lottery_ticket.source.activity'),
        LotteryTicket::SOURCE_MANUAL => admin_trans('lottery_ticket.source.manual'),
    ]);
```

**数据权限过滤修复:**

**修复前:**
```php
// ❌ 通过 activity 关联过滤（多余）
$grid->model()->whereHas('activity', function ($query) use ($departmentId) {
    $query->where('department_id', $departmentId);
});
```

**修复后:**
```php
// ✅ 直接过滤（LotteryTicket 表本身有 department_id 字段）
$grid->model()->where('department_id', $departmentId);
```

---

## 📊 修复后的列定义对比

| 修复前 | 修复后 | 说明 |
|--------|--------|------|
| id | id | ✅ 保持不变 |
| ticket_no | ticket_no | ✅ 保持不变 |
| activity.activity_name | activity.activity_name | ✅ 保持不变 |
| player.uuid | player.uuid | ✅ 保持不变 |
| player.name | player.name | ✅ 保持不变 |
| ❌ is_used | ✅ status | 修改为正确字段 |
| ❌ is_won | ❌ 删除 | 中奖信息在 WinRecord 表 |
| ❌ bet_amount | ❌ 删除 | LotteryTicket 表无此字段 |
| created_at | created_at | ✅ 保持不变 |
| used_at | used_at | ✅ 保持不变 |
| - | ✅ source | 新增来源字段 |
| - | ✅ expired_at | 新增过期时间 |

---

## 🔍 为什么没有中奖信息？

**疑问:** 为什么 `LotteryTicket` 表没有 `is_won` 字段？

**解答:** 

摸奖券系统采用**两表分离设计**：

1. **`lottery_ticket` 表** - 摸奖券基本信息
   - 记录券的发放、使用状态
   - 字段：ticket_no, status, source, used_at, expired_at

2. **`lottery_ticket_win_record` 表** - 中奖记录
   - 记录哪些券中奖了
   - 字段：ticket_no, prize_level_id, prize_amount, is_distributed

**关系:**
```
lottery_ticket (摸奖券)
    ↓ 1:1 (可选)
lottery_ticket_win_record (中奖记录)
```

- 如果券中奖，会在 `lottery_ticket_win_record` 表中插入一条记录
- 如果券未中奖，`lottery_ticket_win_record` 表中没有对应记录

**判断是否中奖:**
```php
// 方式1：关联查询
$ticket = LotteryTicket::with('winRecord')->find($id);
$isWon = $ticket->winRecord ? true : false;

// 方式2：exists 查询
$isWon = LotteryTicketWinRecord::where('ticket_no', $ticketNo)->exists();
```

**代理后台设计决策:**
- 摸奖券列表：只显示券的基本状态（未使用/已使用/已过期）
- 中奖记录列表：专门显示中奖的券（在 `AgentLotteryTicketWinRecordController`）

这样职责更清晰，避免在券列表中进行复杂的关联查询影响性能。

---

## ✅ 修复总结

**修改文件:**
1. `AgentLotteryTicketActivityController.php` - 添加 Actions 导入
2. `AgentLotteryTicketController.php` - 完全重写，使用正确字段

**字段更正:**
- ❌ 删除: `is_used`, `is_won`, `bet_amount`
- ✅ 使用: `status`, `source`, `expired_at`

**常量更正:**
- ❌ 删除: `USED`, `UNUSED`, `WON`, `NOT_WON`
- ✅ 使用: `STATUS_UNUSED`, `STATUS_USED`, `STATUS_EXPIRED`
- ✅ 使用: `SOURCE_RECHARGE`, `SOURCE_ACTIVITY`, `SOURCE_MANUAL`

**功能影响:**
- ✅ 摸奖券列表可以正常显示和筛选
- ✅ 显示券的状态（未使用/已使用/已过期）
- ✅ 显示券的来源（充值/活动/手动）
- ✅ 中奖信息在专门的"中奖记录"页面查看

**数据库一致性:**
- ✅ 所有字段与数据库表结构一致
- ✅ 所有常量与模型定义一致

修复完成！🎉
