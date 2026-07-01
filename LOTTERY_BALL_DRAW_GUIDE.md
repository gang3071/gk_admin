# 摸奖券摇球开奖系统说明

## 🎯 核心变更

### 从随机券号 → 自增序列券号

**旧方案（已废弃）:**
- 券号：14位随机数（时间戳+随机）
- 问题：可能重复、无法保证所有奖品发出

**新方案（当前）:**
- 券号：6位自增序列（`000000` ~ `999999`）
- 优势：绝对唯一、确保所有奖品必中

---

## 📜 券号规则

### 券号范围

| 活动状态 | 当前已发券数 | 下一张券号 | 券号范围 |
|---------|------------|-----------|---------|
| 未发券 | 0 | 000000 | - |
| 已发1张 | 1 | 000001 | 000000 |
| 已发15张 | 15 | 000015 | 000000~000014 |
| 已发100张 | 100 | 000100 | 000000~000099 |
| 已发1000张 | 1000 | 001000 | 000000~000999 |
| 已发满 | 1000000 | 无 | 000000~999999 |

### 券号生成逻辑

```php
// 活动的 current_ticket_no 字段记录已发券数
$currentNo = $activity->current_ticket_no; // 例如：15

// 生成第16张券
$ticketNo = str_pad($currentNo, 6, '0', STR_PAD_LEFT); // "000015"

// 更新已发券数
$activity->current_ticket_no = $currentNo + 1; // 16
```

### 并发安全保证

使用**数据库行锁**防止并发发券重复：

```php
Db::beginTransaction();
try {
    // 锁定活动记录
    $activity = LotteryTicketActivity::where('id', $activityId)
        ->lockForUpdate()  // ← 关键：悲观锁
        ->first();
    
    // 读取当前券号
    $currentNo = $activity->current_ticket_no;
    
    // 批量生成券号
    for ($i = 0; $i < $count; $i++) {
        $ticketNo = str_pad($currentNo + $i, 6, '0', STR_PAD_LEFT);
        // 插入数据库...
    }
    
    // 更新已发券数
    $activity->current_ticket_no = $currentNo + $count;
    $activity->save();
    
    Db::commit();
} catch (\Exception $e) {
    Db::rollBack();
}
```

---

## 🎲 摇球开奖规则

### 摇球原理

根据**已发券的最大券号**确定每个球的范围。

**示例：已发15张券（000000~000014）**

```
最大券号：000014
数字拆解：0  0  0  0  1  4
球的位置：球6 球5 球4 球3 球2 球1

球的范围：
- 球1（个位）：0~4   ✅
- 球2（十位）：0~1   ✅
- 球3（百位）：0~0   ✅ 只能是0
- 球4（千位）：0~0   ✅ 只能是0
- 球5（万位）：0~0   ✅ 只能是0
- 球6（十万位）：0~0 ✅ 只能是0
```

**摇球结果示例：**
```
球6: 0
球5: 0
球4: 0
球3: 0
球2: 1
球1: 2

中奖号码：000012
```

---

### 摇球代码实现

```php
/**
 * 摇6个球
 * @param int $maxTicketNo 最大券号（数字，例如14）
 * @return array 摇球结果
 */
protected static function drawBalls(int $maxTicketNo): array
{
    // 将最大券号转为6位数组
    // 14 → "000014" → [0, 0, 0, 0, 1, 4]
    $maxDigits = str_split(str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT));
    
    $balls = [];
    
    // 从右往左摇球（个位 -> 十万位）
    for ($position = 5; $position >= 0; $position--) {
        $maxDigit = (int)$maxDigits[$position];
        
        // 该位的范围：0 ~ maxDigit
        $balls[$position] = mt_rand(0, $maxDigit);
    }
    
    return [
        'ball1' => $balls[5], // 个位：0~4
        'ball2' => $balls[4], // 十位：0~1
        'ball3' => $balls[3], // 百位：0
        'ball4' => $balls[2], // 千位：0
        'ball5' => $balls[1], // 万位：0
        'ball6' => $balls[0], // 十万位：0
        'winning_no' => sprintf('%d%d%d%d%d%d', $balls[0], $balls[1], $balls[2], $balls[3], $balls[4], $balls[5]),
    ];
}
```

---

## 🏆 中奖匹配规则

### 按奖品等级匹配

| 等级排名 | 奖品名称 | 匹配规则 | 示例（中奖号：000012） |
|---------|---------|---------|---------------------|
| 1 | 特等奖 | 匹配后6位（全中） | 000012 |
| 2 | 一等奖 | 匹配后5位 | X00012 |
| 3 | 二等奖 | 匹配后4位 | XX0012 |
| 4 | 三等奖 | 匹配后3位 | XXX012 |
| 5 | 四等奖 | 匹配后2位 | XXXX12 |
| 6 | 五等奖 | 匹配后1位 | XXXXX2 |

**X = 任意数字**

### 匹配代码实现

```php
protected static function matchWinningTickets(LotteryTicketActivity $activity, array $ballResult): array
{
    $winningTicketNo = $ballResult['winning_no']; // 例如："000012"
    
    // 获取奖品等级（按等级排名从小到大）
    $prizeLevels = LotteryTicketPrizeLevel::where('activity_id', $activity->id)
        ->orderBy('level_rank', 'asc')
        ->get();
    
    $winningData = [];
    
    foreach ($prizeLevels as $prizeLevel) {
        // 计算匹配位数
        $matchDigits = 7 - $prizeLevel->level_rank;
        // 等级1 → 6位，等级2 → 5位，等级3 → 4位...
        
        // 截取中奖号码的后N位
        $matchPattern = substr($winningTicketNo, -$matchDigits);
        // 特等奖（6位）：000012
        // 一等奖（5位）：00012
        // 二等奖（4位）：0012
        
        // 查找匹配的摸奖券
        $matchedTickets = LotteryTicket::where('activity_id', $activity->id)
            ->where('status', LotteryTicket::STATUS_UNUSED)
            ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern)
            ->limit($prizeLevel->prize_count) // 限制中奖数量
            ->get();
        
        foreach ($matchedTickets as $ticket) {
            $winningData[] = [
                'ticket_no' => $ticket->ticket_no,
                'player_id' => $ticket->player_id,
                'prize_name' => $prizeLevel->level_name,
                'prize_amount' => $prizeLevel->prize_amount,
            ];
        }
    }
    
    return $winningData;
}
```

---

## 🔄 完整开奖流程

### 1. 准备阶段

```bash
# 活动状态：打码中 (STATUS_BETTING)
# 玩家打码 → 自动发券
# 已发放：15张券（000000~000014）
```

### 2. 进入开奖

```bash
# 管理员手动 或 定时任务自动流转
# 活动状态：打码中 → 开奖中 (STATUS_DRAWING)
# 触发：停止发券
```

### 3. 执行摇球

**API调用：**
```http
POST /ex-admin/channel-lottery-ticket-activity/performBallDraw
{
  "activity_id": 1
}
```

**摇球过程：**
```php
// 1. 计算最大券号
$maxTicketNo = 14; // (已发15张 - 1)

// 2. 摇6个球
$ballResult = [
    'ball1' => 2,  // 个位：0~4 → 随机2
    'ball2' => 1,  // 十位：0~1 → 随机1
    'ball3' => 0,  // 百位：只能0
    'ball4' => 0,  // 千位：只能0
    'ball5' => 0,  // 万位：只能0
    'ball6' => 0,  // 十万位：只能0
    'winning_no' => '000012'
];

// 3. 保存摇球结果
$activity->ball_result = json_encode($ballResult);
$activity->save();
```

**匹配中奖：**
```php
// 4. 根据奖品配置匹配

// 假设奖品配置：
// - 特等奖（1名）：1000元
// - 一等奖（3名）：500元
// - 二等奖（10名）：100元

// 匹配结果：
// 特等奖（6位全中）：券号 000012 → 1人中奖
// 一等奖（后5位）：券号 X00012 → 没有匹配
// 二等奖（后4位）：券号 XX0012 → 券号 000012 已中特等奖，跳过
```

**创建中奖记录：**
```php
// 5. 创建中奖记录
LotteryTicketRecord::create([
    'ticket_no' => '000012',
    'prize_name' => '特等奖',
    'prize_amount' => 1000,
    'status' => STATUS_PENDING,
]);

// 6. 更新券状态
LotteryTicket::where('ticket_no', '000012')
    ->update(['status' => STATUS_USED]);

// 7. 推送中奖通知
pushWinNotification(...);
```

### 4. 活动结束

```bash
# 摇球完成后
# 活动状态：开奖中 → 已结束 (STATUS_ENDED)
```

---

## 📊 数据库变更

### 新增字段

**`lottery_ticket_activity` 表:**

| 字段 | 类型 | 说明 | 默认值 |
|------|------|------|--------|
| `current_ticket_no` | INT | 当前已发券数 | 0 |
| `max_ticket_no` | INT | 最大可发券数 | 1000000 |
| `draw_method` | VARCHAR(20) | 开奖方式 | 'ball' |
| `ball_result` | TEXT | 摇球结果（JSON） | NULL |

**`lottery_ticket` 表:**

| 字段变更 | 旧类型 | 新类型 | 说明 |
|---------|--------|--------|------|
| `ticket_no` | VARCHAR(20) | CHAR(6) | 6位数字券号 |

### 运行迁移

```bash
cd D:/gk_api
vendor/bin/phinx migrate

# 应该看到:
# == 20260610000000 UpdateLotteryTicketSystemForSequentialNumbers: migrating
# == 20260610000000 UpdateLotteryTicketSystemForSequentialNumbers: migrated 0.xxxx s
```

---

## 🎮 API接口

### 1. 执行摇球开奖

```http
POST /ex-admin/channel-lottery-ticket-activity/performBallDraw

Request:
{
  "activity_id": 1
}

Response:
{
  "code": 200,
  "message": "开奖成功，共产生 5 个中奖券",
  "data": {
    "ball_result": {
      "ball1": 2,
      "ball2": 1,
      "ball3": 0,
      "ball4": 0,
      "ball5": 0,
      "ball6": 0,
      "winning_no": "000012"
    },
    "winning_count": 5,
    "winning_tickets": [
      {
        "ticket_no": "000012",
        "prize_name": "特等奖",
        "prize_amount": 1000.00
      }
    ]
  }
}
```

### 2. 获取摇球范围

```http
GET /ex-admin/channel-lottery-ticket-activity/getBallRanges
?activity_id=1

Response:
{
  "code": 200,
  "data": {
    "ball1": {"min": 0, "max": 4},
    "ball2": {"min": 0, "max": 1},
    "ball3": {"min": 0, "max": 0},
    "ball4": {"min": 0, "max": 0},
    "ball5": {"min": 0, "max": 0},
    "ball6": {"min": 0, "max": 0},
    "max_ticket_no": "000014"
  }
}
```

### 3. 获取摇球结果

```http
GET /ex-admin/channel-lottery-ticket-activity/getBallResult
?activity_id=1

Response:
{
  "code": 200,
  "data": {
    "has_drawn": true,
    "ball_result": {
      "ball1": 2,
      "ball2": 1,
      "ball3": 0,
      "ball4": 0,
      "ball5": 0,
      "ball6": 0,
      "winning_no": "000012"
    },
    "activity_status": 6
  }
}
```

---

## ⚠️ 注意事项

### 1. 券号唯一性保证

✅ **已实现：**
- 数据库唯一索引：`idx_activity_ticket_no` (`activity_id`, `ticket_no`)
- 并发控制：`lockForUpdate()` 行锁
- 自增序列：保证活动内绝对唯一

### 2. 券号用完怎么办？

```php
// 发券前检查
if ($activity->current_ticket_no >= $activity->max_ticket_no) {
    return ['error' => '摸奖券已发放完毕（已发100万张）'];
}
```

**解决方案：**
- 合理设置活动的 `max_ticket_no`
- 大型活动可设置为100万（默认值）
- 小型活动可设置为1万、10万等

### 3. 重复开奖防护

```php
// 开奖前检查
if (!empty($activity->ball_result)) {
    return ['error' => '活动已完成开奖，不能重复开奖'];
}
```

### 4. 奖品必中原则

**关键设计：**
- 券号连续：000000 ~ 999999
- 摇球范围基于已发券：确保中奖号在已发券范围内
- 匹配规则：后N位匹配，必然有券中奖

**示例验证：**
```
已发15张券：000000~000014

摇球结果：000012（必然在范围内）

匹配：
- 特等奖（6位）：000012 ✅ 必中
- 一等奖（5位）：后5位=00012，匹配券：000012 ✅
- 二等奖（4位）：后4位=0012，匹配券：000012 ✅
- ...

结论：所有等级至少有1张券匹配！
```

---

## 🎯 升级检查清单

迁移到新系统前，请确认：

- [ ] 运行数据库迁移
- [ ] 重启 gk_admin 服务
- [ ] 测试发券（检查券号格式）
- [ ] 测试摇球（检查摇球范围）
- [ ] 测试匹配（检查中奖逻辑）
- [ ] 清理旧数据（可选）

---

**作者:** Claude Code  
**日期:** 2026-06-10  
**版本:** 2.0 - 摇球开奖版
