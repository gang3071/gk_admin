# 摸奖券打码进度追踪功能使用指南

## 📋 功能概述

摸奖券打码进度追踪功能实现了**玩家打码自动发券**的完整闭环：

```
玩家游戏 → 产生打码 → 累计到目标 → 自动发券 → 参与抽奖
```

### 核心特性

- ✅ 支持VIP等级差异化配置（不同VIP等级不同打码要求）
- ✅ 自动累计玩家打码量
- ✅ 达标自动发放摸奖券
- ✅ 支持循环发券（可多次达标）
- ✅ 实时进度追踪
- ✅ 完整的数据统计

---

## 🗄️ 数据库结构

### lottery_ticket_bet_progress 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT | 主键ID |
| activity_id | INT | 活动ID |
| player_id | INT | 玩家ID |
| department_id | INT | 渠道部门ID |
| vip_level_id | INT | VIP等级ID |
| bet_amount_required | DECIMAL | 基础打码量要求 |
| current_bet_amount | DECIMAL | 当前累计打码量 |
| ticket_count_per_cycle | INT | 每次达标发券数 |
| cycles_completed | INT | 已完成周期数（达标次数）|
| total_tickets_issued | INT | 总共已发放券数 |
| last_issued_at | DATETIME | 最后发券时间 |
| status | TINYINT | 状态(0:已结束,1:进行中) |

**示例数据:**
```
玩家A (VIP3):
- bet_amount_required: 50,000  // 需要打码5万
- current_bet_amount: 125,000  // 当前已打12.5万
- ticket_count_per_cycle: 2    // 每次发2张券
- cycles_completed: 2          // 已完成2个周期
- total_tickets_issued: 4      // 总共发了4张券
```

---

## ⚙️ 配置流程

### 1. 创建活动并配置VIP打码量

在后台创建活动时，系统会要求配置每个VIP等级的打码要求：

```
活动: 春节摸奖大礼包

VIP配置:
┌─────────┬──────────────┬──────────┐
│ VIP等级 │ 所需打码量    │ 发放券数 │
├─────────┼──────────────┼──────────┤
│ VIP 1   │ 100,000      │ 1张      │
│ VIP 2   │ 50,000       │ 1张      │
│ VIP 3   │ 20,000       │ 2张      │
│ VIP 4   │ 10,000       │ 3张      │
└─────────┴──────────────┴──────────┘
```

**配置说明:**
- **所需打码量**: 玩家需要打码的基础金额（可循环）
- **发放券数**: 每达到一次基础打码量，发放的券数

### 2. 活动开始时初始化进度

活动状态变更为"进行中"时，系统会自动为符合条件的玩家创建进度记录：

```php
// 方式1: 手动调用（活动开始时）
use addons\webman\service\LotteryTicketBetProgressService;

$count = LotteryTicketBetProgressService::initializeActivityProgress($activityId);
// 返回: 创建的进度记录数量
```

**自动创建规则:**
- 筛选该渠道下所有启用的玩家
- 按VIP等级匹配配置
- 为每个玩家创建进度记录

---

## 🔄 打码更新流程

### 自动更新机制

**推荐方式:** 在玩家游戏记录创建时自动触发

在 `PlayerGameLog` 创建后调用：

```php
use addons\webman\service\LotteryTicketBetProgressService;

// 玩家游戏后，产生了打码记录
$gameLog = PlayerGameLog::create([
    'player_id' => $playerId,
    'chip_amount' => 5000,  // 本次打码5000
    // ... 其他字段
]);

// 自动更新打码进度
$result = LotteryTicketBetProgressService::updateBetProgress(
    $playerId,
    $gameLog->chip_amount
);

// 返回结果
if ($result['success']) {
    // 如果触发了发券，results中包含发券信息
    foreach ($result['results'] as $activityResult) {
        echo "活动: {$activityResult['activity_name']}\n";
        echo "发放券数: {$activityResult['tickets_issued']}\n";
        echo "总计券数: {$activityResult['total_tickets']}\n";
    }
}
```

### 发券触发逻辑

```php
示例：VIP3玩家（打码要求20,000，每次发2张）

打码累计过程：
  0     →  5,000  →  15,000  →  21,000  →  40,500  →  60,000
  |         |         |          |           |           |
 初始     游戏1     游戏2      游戏3       游戏4       游戏5
                              ↓
                         触发发券2张
                         （第1次达标）
                                          ↓
                                    触发发券2张
                                    （第2次达标）
                                                      ↓
                                                触发发券2张
                                                （第3次达标）

最终:
- 总打码: 60,000
- 完成周期: 3次 (60,000 / 20,000)
- 发放券数: 6张 (3次 × 2张)
```

---

## 📊 查询进度API

### 后台管理查询

```php
use addons\webman\model\LotteryTicketBetProgress;

// 1. 查询活动的所有进度
$progressList = LotteryTicketBetProgress::where('activity_id', $activityId)
    ->with(['player', 'vipLevel'])
    ->get();

// 2. 查询玩家的进度
$progress = LotteryTicketBetProgress::where('activity_id', $activityId)
    ->where('player_id', $playerId)
    ->first();

if ($progress) {
    echo "当前打码: {$progress->current_bet_amount}\n";
    echo "进度百分比: {$progress->progress_percent}%\n";
    echo "还需打码: {$progress->remaining_bet_amount}\n";
    echo "已发券数: {$progress->total_tickets_issued}\n";
}
```

### 客户端API（需在 gk_api 项目实现）

```php
// GET /api/v1/lottery-activity/{activityId}/bet-progress

返回示例:
{
    "code": 200,
    "data": {
        "activity_id": 1,
        "activity_name": "春节摸奖大礼包",
        "vip_level": "VIP3",
        "bet_amount_required": 20000,
        "current_bet_amount": 15000,
        "progress_percent": 75.0,
        "remaining_bet_amount": 5000,
        "cycles_completed": 0,
        "total_tickets_issued": 0,
        "ticket_count_per_cycle": 2,
        "status": 1
    }
}
```

---

## 🎯 使用场景

### 场景1: 活动创建

```php
// 1. 管理员创建活动
$activity = LotteryTicketActivity::create([
    'name' => '春节摸奖',
    'start_time' => '2026-02-01 00:00:00',
    'end_time' => '2026-02-28 23:59:59',
    // ...
]);

// 2. 配置VIP打码量
foreach ($vipConfigs as $config) {
    LotteryTicketVipConfig::create([
        'activity_id' => $activity->id,
        'vip_level_id' => $config['vip_level_id'],
        'bet_amount_required' => $config['bet_amount'],
        'ticket_count' => $config['ticket_count'],
    ]);
}

// 3. 活动开始时初始化进度
$count = LotteryTicketBetProgressService::initializeActivityProgress($activity->id);
echo "为 {$count} 名玩家创建了进度记录\n";
```

### 场景2: 玩家游戏

```php
// 玩家在游戏中产生打码
$gameLog = PlayerGameLog::create([
    'player_id' => 12345,
    'chip_amount' => 8000,  // 打码8000
    // ...
]);

// 自动更新进度并检查是否发券
$result = LotteryTicketBetProgressService::updateBetProgress(
    12345,
    8000
);

// 如果触发发券，推送通知给玩家
if (!empty($result['results'])) {
    foreach ($result['results'] as $activityResult) {
        // 推送通知: "恭喜您获得{$activityResult['tickets_issued']}张摸奖券！"
    }
}
```

### 场景3: 活动结束

```php
// 活动结束时，关闭所有进度记录
$count = LotteryTicketBetProgressService::endActivityProgress($activityId);
echo "结束了 {$count} 条进度记录\n";
```

---

## 🛠️ 高级功能

### 1. 数据校准

如果需要重新计算玩家的打码量（例如数据修复）：

```php
use addons\webman\service\LotteryTicketBetProgressService;

// 重新统计玩家在活动期间的总打码量
$totalBetAmount = LotteryTicketBetProgressService::calculateTotalBetAmount(
    $activityId,
    $playerId
);

// 手动更新进度
$progress = LotteryTicketBetProgress::where('activity_id', $activityId)
    ->where('player_id', $playerId)
    ->first();

if ($progress) {
    $progress->current_bet_amount = $totalBetAmount;
    $progress->save();
}
```

### 2. 批量初始化

为已经开始的活动补充进度记录：

```php
// 为新加入的玩家创建进度
$progress = LotteryTicketBetProgressService::createProgressForPlayer(
    $activityId,
    $playerId
);
```

### 3. 监控统计

```php
// 活动打码统计
$stats = LotteryTicketBetProgress::where('activity_id', $activityId)
    ->selectRaw('
        COUNT(*) as total_players,
        SUM(current_bet_amount) as total_bet_amount,
        SUM(total_tickets_issued) as total_tickets_issued,
        AVG(current_bet_amount) as avg_bet_amount
    ')
    ->first();

echo "参与玩家: {$stats->total_players}\n";
echo "总打码量: {$stats->total_bet_amount}\n";
echo "已发券数: {$stats->total_tickets_issued}\n";
echo "平均打码: {$stats->avg_bet_amount}\n";
```

---

## ⚠️ 注意事项

### 1. 数据库索引

表中已创建以下索引，确保查询性能：
- `idx_activity_id` - 按活动查询
- `idx_player_id` - 按玩家查询
- `idx_activity_player` - 唯一索引，防止重复记录

### 2. 事务处理

发券过程使用数据库事务，确保数据一致性：
```php
Db::beginTransaction();
try {
    // 更新打码量
    // 发放摸奖券
    // 更新周期数
    Db::commit();
} catch (\Exception $e) {
    Db::rollBack();
}
```

### 3. 性能优化

- 打码更新是高频操作，已做优化处理
- 只在必要时才查询活动状态
- 批量发券减少数据库操作

### 4. 券号生成规则

券号格式：`时间戳后6位 + 随机4位`
- 例如：`123456 + 7890 = 1234567890`
- 保证唯一性的同时易于识别

---

## 🔗 集成到客户端

### 需要实现的API（在 gk_api 项目）

**1. 查询打码进度**
```
GET /api/v1/lottery-activity/{activityId}/bet-progress
```

**2. 查询历史发券记录**
```
GET /api/v1/lottery-activity/{activityId}/ticket-history
```

**3. WebSocket 实时推送**
```javascript
// 当玩家获得摸奖券时，推送通知
{
    "event": "lottery_ticket_issued",
    "data": {
        "activity_id": 1,
        "activity_name": "春节摸奖",
        "tickets_count": 2,
        "total_tickets": 4
    }
}
```

---

## 📝 常见问题

**Q: 玩家VIP等级变化后会怎样？**
A: 当前进度记录会保持原VIP配置，新的打码会使用新VIP配置创建新记录。

**Q: 活动结束后还能打码发券吗？**
A: 不能。活动结束时所有进度记录状态变为"已结束"，不再累计打码。

**Q: 可以手动调整玩家的打码进度吗？**
A: 可以。直接修改 `lottery_ticket_bet_progress` 表的 `current_bet_amount` 字段即可。

**Q: 如何防止重复发券？**
A: 使用 `cycles_completed` 字段记录已发券次数，每次检查是否有新周期完成。

---

## ✅ 完成标记

- [x] 数据库迁移文件
- [x] 模型类 (LotteryTicketBetProgress)
- [x] 服务类 (LotteryTicketBetProgressService)
- [ ] 后台管理界面（查看进度列表）
- [ ] 客户端API（gk_api项目）
- [ ] WebSocket推送通知
- [ ] 定时任务（检查活动状态自动初始化）

---

**作者:** Claude Code  
**日期:** 2026-06-09  
**版本:** 1.0
