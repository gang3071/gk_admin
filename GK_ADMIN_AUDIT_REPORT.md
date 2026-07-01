# gk_admin 项目"待开奖"状态审查报告

## 📅 审查日期
2026-06-18

---

## 🎯 审查范围

**项目：** gk_admin  
**审查重点：** 加入 STATUS_PENDING_DRAW (5) 状态后的逻辑完整性

---

## ✅ 审查结果总结

| 类别 | 状态 | 问题数 |
|------|------|--------|
| 渠道后台控制器 | ⚠️ 已修复 | 2 |
| 代理后台控制器 | ⚠️ 已修复 | 3 |
| 店家后台控制器 | ⚠️ 已修复 | 3 |
| 统计控制器 | ⚠️ 已修复 | 1 |
| 服务层 | ✅ 通过 | 0 |
| 定时任务 | ✅ 通过 | 0 |
| 业务逻辑 | ✅ 通过 | 0 |

**总计：** 发现 9 个问题，已全部修复 ✅

---

## 📝 详细审查

### 1. ChannelLotteryTicketActivityController ⚠️ → ✅

**文件：** `addons/webman/controller/ChannelLotteryTicketActivityController.php`

**问题 1：** 状态筛选逻辑缺失数值类型处理
- **位置：** Line 197-215 `getActivities()` 方法
- **影响：** Vue 组件传入数值 `5` 时无法正确筛选待开奖活动
- **修复：** 添加 `is_numeric()` 检查，处理数值类型的状态值

**修复前：**
```php
if ($status === 'ongoing') {
    $query->where('status', LotteryTicketActivity::STATUS_ONGOING);
} elseif ($status === 'ended') {
    $query->whereIn('status', [
        LotteryTicketActivity::STATUS_ENDED,
        LotteryTicketActivity::STATUS_CLOSED
    ]);
}
```

**修复后：**
```php
// 数值类型（直接匹配）
if (is_numeric($status)) {
    $query->where('status', (int)$status);
}
// 字符串值（向后兼容）
elseif ($status === 'ongoing') {
    $query->where('status', LotteryTicketActivity::STATUS_ONGOING);
}
```

**问题 2：** Grid 状态显示缺少新状态
- **位置：** Line 523-539 状态标签映射
- **影响：** 待开奖和开奖中状态无法正确显示颜色和文本
- **修复：** 添加 `STATUS_PENDING_DRAW` 和 `STATUS_DRAWING` 的映射

**修复：**
```php
const STATUS_COLORS = [
    LotteryTicketActivity::STATUS_NOT_STARTED => 'blue',
    LotteryTicketActivity::STATUS_ONGOING => 'green',
    LotteryTicketActivity::STATUS_PENDING_DRAW => 'orange',  // ⭐ 新增
    LotteryTicketActivity::STATUS_DRAWING => 'purple',       // ⭐ 新增
    LotteryTicketActivity::STATUS_ENDED => 'default',
    LotteryTicketActivity::STATUS_CLOSED => 'red',
];
```

**结论：** ✅ 2个问题已修复

---

### 2. AgentLotteryTicketActivityController ⚠️ → ✅

**文件：** `addons/webman/controller/AgentLotteryTicketActivityController.php`

**问题 1：** Grid 状态显示缺少新状态
- **位置：** Line 76-89 状态标签 `display()` 方法

**修复：**
```php
$statusMap = [
    LotteryTicketActivity::STATUS_NOT_STARTED => ['text' => admin_trans('lottery_ticket.status.not_started'), 'color' => 'default'],
    LotteryTicketActivity::STATUS_ONGOING => ['text' => admin_trans('lottery_ticket.status.ongoing'), 'color' => 'processing'],
    LotteryTicketActivity::STATUS_PENDING_DRAW => ['text' => admin_trans('lottery_ticket.status.pending_draw'), 'color' => 'warning'],  // ⭐ 新增
    LotteryTicketActivity::STATUS_DRAWING => ['text' => admin_trans('lottery_ticket.status.drawing'), 'color' => 'purple'],  // ⭐ 新增
    LotteryTicketActivity::STATUS_ENDED => ['text' => admin_trans('lottery_ticket.status.ended'), 'color' => 'success'],
    LotteryTicketActivity::STATUS_CLOSED => ['text' => admin_trans('lottery_ticket.status.closed'), 'color' => 'error'],
];
```

**问题 2：** 筛选器选项缺少新状态
- **位置：** Line 128-135 `filter->select('status')`

**修复：**
```php
->options([
    LotteryTicketActivity::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
    LotteryTicketActivity::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
    LotteryTicketActivity::STATUS_PENDING_DRAW => admin_trans('lottery_ticket.status.pending_draw'),  // ⭐ 新增
    LotteryTicketActivity::STATUS_DRAWING => admin_trans('lottery_ticket.status.drawing'),  // ⭐ 新增
    LotteryTicketActivity::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
    LotteryTicketActivity::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
])
```

**问题 3：** Detail 页面状态文本缺失
- **位置：** Line 187-196 `getActivityDetail()` 方法

**修复：**
```php
$statusMap = [
    LotteryTicketActivity::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
    LotteryTicketActivity::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
    LotteryTicketActivity::STATUS_PENDING_DRAW => admin_trans('lottery_ticket.status.pending_draw'),  // ⭐ 新增
    LotteryTicketActivity::STATUS_DRAWING => admin_trans('lottery_ticket.status.drawing'),  // ⭐ 新增
    LotteryTicketActivity::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
    LotteryTicketActivity::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
];
```

**结论：** ✅ 3个问题已修复

---

### 3. StoreLotteryTicketActivityController ⚠️ → ✅

**文件：** `addons/webman/controller/StoreLotteryTicketActivityController.php`

**问题 1-3：** 与 AgentLotteryTicketActivityController 完全相同的3个问题

**位置：**
1. Line 75-86: Grid 状态显示
2. Line 125-132: 筛选器选项
3. Line 184-193: Detail 状态文本

**修复：** 与代理后台相同的修复方案

**结论：** ✅ 3个问题已修复

---

### 4. ChannelLotteryTicketStatisticsController ⚠️ → ✅

**文件：** `addons/webman/controller/ChannelLotteryTicketStatisticsController.php`

**问题：** 活跃活动查询缺少待开奖状态
- **位置：** Line 300-307 `index()` 方法
- **影响：** 统计页面不显示待开奖状态的活动

**修复前：**
```php
// 进行中的活动简要信息（简化后只有2个进行中状态）
$ongoingActivities = LotteryTicketActivity::where('department_id', $departmentId)
    ->whereIn('status', [
        LotteryTicketActivity::STATUS_ONGOING,
        LotteryTicketActivity::STATUS_DRAWING,
    ])
```

**修复后：**
```php
// 进行中的活动简要信息（3个活跃状态）
$ongoingActivities = LotteryTicketActivity::where('department_id', $departmentId)
    ->whereIn('status', [
        LotteryTicketActivity::STATUS_ONGOING,
        LotteryTicketActivity::STATUS_PENDING_DRAW,  // ⭐ 新增：待开奖也属于活跃状态
        LotteryTicketActivity::STATUS_DRAWING,
    ])
```

**结论：** ✅ 已修复

---

### 5. LotteryTicketIssueService ✅

**文件：** `addons/webman/service/LotteryTicketIssueService.php`

**检查项：** 发券权限检查（Line 60-68）

**代码：**
```php
// ✅ 检查活动状态：只有进行中才能发券
// 新状态流程：ONGOING → PENDING_DRAW → DRAWING → ENDED
// 待开奖(PENDING_DRAW)后不再发券（打码进度已结束）
if ($activity->status !== LotteryTicketActivity::STATUS_ONGOING) {
    throw new \Exception('活动未进行中，无法发券');
}

// ✅ 双重检查：防止活动时间结束但状态未更新的边缘情况
if (strtotime($activity->end_time) < time()) {
    throw new \Exception('活动已结束，无法发券');
}
```

**逻辑分析：**
- ✅ 只在 ONGOING 状态发券
- ✅ PENDING_DRAW 时打码进度已在 `LotteryActivityStatusTransitionTask::onPendingDraw()` 中调用 `endActivityProgress()` 结束
- ✅ 不会再触发发券逻辑

**修改：** 仅更新注释，澄清业务逻辑 ✅

**结论：** ✅ 逻辑正确，无需修改

---

### 6. LotteryTicketBetProgressService ✅

**文件：** `addons/webman/service/LotteryTicketBetProgressService.php`

**检查项：** 打码进度更新逻辑

**代码：**
```php
public static function updateBetProgress(int $playerId, float $chipAmount, ?int $activityId = null): array
{
    // 查找该玩家参与的所有进行中的活动
    $query = LotteryTicketBetProgress::where('player_id', $playerId)
        ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
        ->with('activity');
    
    if ($activityId) {
        $query->where('activity_id', $activityId);
    }
```

**逻辑分析：**
- ✅ 查询条件是 `LotteryTicketBetProgress::STATUS_ACTIVE`
- ✅ 当活动进入 `PENDING_DRAW` 时，`endActivityProgress()` 会将所有进度设为非活跃
- ✅ 不会再更新待开奖活动的打码进度

**结论：** ✅ 逻辑正确

---

### 7. LotteryActivityStatusTransitionTask ✅

**文件：** `process/LotteryActivityStatusTransitionTask.php`

**检查项：** 待开奖状态处理（Line 182-195）

**代码：**
```php
protected function onPendingDraw(LotteryTicketActivity $activity)
{
    Log::info('摸奖券活动进入待开奖状态', [
        'activity_id' => $activity->id,
        'activity_name' => $activity->name,
        'end_time' => $activity->end_time,
    ]);

    // 停止所有玩家的打码进度（不再发券）
    LotteryTicketBetProgressService::endActivityProgress($activity->id);

    // 发送待开奖通知
    \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'pending_draw');
}
```

**功能：**
1. ✅ 记录日志
2. ✅ 停止打码进度（不再发券）
3. ✅ 发送推送通知

**结论：** ✅ 功能完整

---

### 8. ChannelLotteryTicketRecordController ✅

**文件：** `addons/webman/controller/ChannelLotteryTicketRecordController.php`

**检查项 1：** 录入中奖逻辑（Line 847-856）

**代码：**
```php
// ⭐ 核心业务：线下摇球后录入中奖券号
// 允许在以下状态录入：
// - ONGOING（活动进行中）- 可提前录入
// - PENDING_DRAW（待开奖）- 活动结束等待开奖
// - DRAWING（开奖中）- 正在开奖
$allowedStatuses = [
    LotteryTicketActivity::STATUS_ONGOING,
    LotteryTicketActivity::STATUS_PENDING_DRAW,
    LotteryTicketActivity::STATUS_DRAWING,
];
```

**逻辑：**
- ✅ 允许在待开奖状态录入中奖券号
- ✅ 与之前修复的逻辑一致

**检查项 2：** 发放奖励逻辑（Line 338-344, Line 519-525）

**代码：**
```php
// 检查活动状态（线下摇球，只需检查状态）⭐
$allowedStatuses = [
    LotteryTicketActivity::STATUS_DRAWING,
    LotteryTicketActivity::STATUS_ENDED,
];
if (!in_array($activity->status, $allowedStatuses)) {
    throw new \Exception(admin_trans('lottery_ticket.error.activity_not_in_drawing_status'));
}
```

**业务逻辑分析：**
- **录入中奖** = 记录哪些券号中奖（可提前） → ONGOING/PENDING_DRAW/DRAWING ✅
- **发放奖励** = 实际给玩家加钱（应谨慎） → DRAWING/ENDED ✅

**决策：**
- ✅ 当前逻辑合理
- ✅ 不需要在 PENDING_DRAW 时发放奖励
- ✅ 应该在管理员正式开奖后再发放

**结论：** ✅ 逻辑正确，无需修改

---

## 🔍 其他检查项

### 历史活动查询 ✅

**文件：** `ChannelLotteryTicketActivityController.php`

**位置：** Line 509-512, Line 656-659

**代码：**
```php
->whereIn('status', [
    LotteryTicketActivity::STATUS_ENDED,
    LotteryTicketActivity::STATUS_CLOSED
])
```

**逻辑：**
- ✅ 历史记录只显示真正结束的活动
- ✅ 不应该包含待开奖或开奖中的活动

**结论：** ✅ 逻辑正确

---

## 📊 修复总结

### 修复的问题

| 问题 | 位置 | 严重程度 | 状态 |
|------|------|---------|------|
| Vue 数值筛选失效 | `ChannelLotteryTicketActivityController.getActivities()` | 🔴 高 | ✅ 已修复 |
| Grid 状态显示缺失 | `ChannelLotteryTicketActivityController.index()` | 🔴 高 | ✅ 已修复 |
| 代理后台 Grid 显示 | `AgentLotteryTicketActivityController.index()` | 🔴 高 | ✅ 已修复 |
| 代理后台筛选器 | `AgentLotteryTicketActivityController.index()` | 🟡 中 | ✅ 已修复 |
| 代理后台详情页 | `AgentLotteryTicketActivityController.getActivityDetail()` | 🟡 中 | ✅ 已修复 |
| 店家后台 Grid 显示 | `StoreLotteryTicketActivityController.index()` | 🔴 高 | ✅ 已修复 |
| 店家后台筛选器 | `StoreLotteryTicketActivityController.index()` | 🟡 中 | ✅ 已修复 |
| 店家后台详情页 | `StoreLotteryTicketActivityController.getActivityDetail()` | 🟡 中 | ✅ 已修复 |
| 统计页活跃活动查询 | `ChannelLotteryTicketStatisticsController.index()` | 🟡 中 | ✅ 已修复 |

### 验证通过（无需修改）

| 检查项 | 位置 | 结论 |
|--------|------|------|
| 发券权限 | `LotteryTicketIssueService.issueTickets()` | ✅ 逻辑正确（仅注释更新） |
| 打码进度 | `LotteryTicketBetProgressService.updateBetProgress()` | ✅ 逻辑正确 |
| 待开奖处理 | `LotteryActivityStatusTransitionTask.onPendingDraw()` | ✅ 功能完整 |
| 录入中奖 | `ChannelLotteryTicketRecordController.recordWinByTickets()` | ✅ 逻辑正确 |
| 发放奖励 | `ChannelLotteryTicketRecordController.distributeReward()` | ✅ 逻辑正确 |
| 历史查询 | `ChannelLotteryTicketActivityController` | ✅ 逻辑正确 |

---

## ✅ 审查结论

### 总体评估

**状态：** ✅ 通过（已修复所有问题）

**修改文件：**
1. ✅ `addons/webman/controller/ChannelLotteryTicketActivityController.php` - 2处修复
2. ✅ `addons/webman/controller/AgentLotteryTicketActivityController.php` - 3处修复
3. ✅ `addons/webman/controller/StoreLotteryTicketActivityController.php` - 3处修复
4. ✅ `addons/webman/controller/ChannelLotteryTicketStatisticsController.php` - 1处修复
5. ✅ `addons/webman/service/LotteryTicketIssueService.php` - 注释更新

**未修改文件（逻辑正确）：**
1. ✅ `addons/webman/service/LotteryTicketBetProgressService.php` - 无需修改
2. ✅ `process/LotteryActivityStatusTransitionTask.php` - 无需修改
3. ✅ `addons/webman/controller/ChannelLotteryTicketRecordController.php` - 无需修改

---

## 🔄 关键业务流程验证

### 完整状态流转

```
0. 未开始 (STATUS_NOT_STARTED)
   ↓ 定时任务自动（start_time 到达）
   
1. 进行中 (STATUS_ONGOING)
   - ✅ 玩家打码获取奖券
   - ✅ 发券服务正常工作
   - ✅ 打码进度持续更新
   - ✅ 可提前录入中奖券号
   ↓ 定时任务自动（end_time 到达）
   
2. 待开奖 (STATUS_PENDING_DRAW) ⭐
   - ✅ 停止发券（打码进度已结束）
   - ✅ 可继续录入中奖券号
   - ✅ 尚未发放奖励（等待管理员开奖）
   - ✅ 统计页显示为活跃状态
   - ✅ 前端显示橙色标签
   ↓ 管理员手动开奖
   
3. 开奖中 (STATUS_DRAWING)
   - ✅ 继续录入中奖券号
   - ✅ 可发放奖励
   - ✅ 前端显示紫色标签
   ↓ 管理员手动停止
   
4. 已结束 (STATUS_ENDED)
   - ✅ 不可录入中奖
   - ✅ 可发放奖励
   - ✅ 出现在历史记录
```

### 三个后台的权限正确性

| 后台类型 | Controller | 状态显示 | 筛选器 | 详情页 | 备注 |
|---------|-----------|---------|--------|-------|------|
| 渠道后台 | ChannelLotteryTicketActivityController | ✅ 完整 | ✅ 完整 | ✅ 完整 | 完整管理权限 |
| 代理后台 | AgentLotteryTicketActivityController | ✅ 完整 | ✅ 完整 | ✅ 完整 | 只读查看 |
| 店家后台 | StoreLotteryTicketActivityController | ✅ 完整 | ✅ 完整 | ✅ 完整 | 只读查看 |

---

## 🎯 测试建议

### 1. 状态流转测试

```bash
# 创建测试活动（2分钟时长）
# start_time: NOW
# end_time: NOW + 2分钟

# 观察日志：
tail -f runtime/logs/webman.log | grep "摸奖券活动"

# 预期流程：
# 00:00 创建活动（STATUS_NOT_STARTED）
# 00:01 定时任务 → STATUS_ONGOING（日志："摸奖券活动开始"）
# 02:01 定时任务 → STATUS_PENDING_DRAW（日志："摸奖券活动进入待开奖状态"）
# 手动点击"开始开奖" → STATUS_DRAWING
# 手动点击"停止开奖" → STATUS_ENDED
```

### 2. 界面显示测试

**渠道后台：**
1. ✅ 状态筛选器显示6个选项
2. ✅ 待开奖活动显示橙色标签
3. ✅ 开奖中活动显示紫色标签
4. ✅ 详情页正确显示状态文本

**代理/店家后台：**
1. ✅ 相同的显示效果
2. ✅ 操作按钮只显示"查看详情"

**统计页面：**
1. ✅ 待开奖活动出现在"进行中的活动"列表

### 3. 功能权限测试

| 状态 | 发券 | 录入中奖 | 发放奖励 |
|------|------|---------|---------|
| ONGOING | ✅ 允许 | ✅ 允许 | ❌ 禁止 |
| PENDING_DRAW | ❌ 禁止 | ✅ 允许 | ❌ 禁止 |
| DRAWING | ❌ 禁止 | ✅ 允许 | ✅ 允许 |
| ENDED | ❌ 禁止 | ❌ 禁止 | ✅ 允许 |

### 4. API 测试

**前端 Vue 筛选：**
```javascript
// 测试数值类型筛选
getActivities({status: 5})  // 应返回所有待开奖活动
getActivities({status: 6})  // 应返回所有开奖中活动

// 测试字符串类型（向后兼容）
getActivities({status: 'ongoing'})  // 应返回所有进行中活动
```

---

## 📝 附录：完整状态常量

```php
// addons/webman/model/LotteryTicketActivity.php
const STATUS_NOT_STARTED = 0;      // 未开始
const STATUS_ONGOING = 1;          // 进行中（玩家打码获券阶段）
const STATUS_ENDED = 2;            // 已结束（完全结束，所有流程完成）
const STATUS_CLOSED = 3;           // 已关闭（手动关闭，异常终止）
const STATUS_PENDING_DRAW = 5;     // 待开奖（end_time 到达，等待管理员开奖）⭐
const STATUS_DRAWING = 6;          // 开奖中（管理员摇球阶段）
```

---

**审查完成时间：** 2026-06-18  
**审查结果：** ✅ 通过（已修复所有问题）  
**修复文件数：** 5  
**发现问题数：** 9（已全部修复）
