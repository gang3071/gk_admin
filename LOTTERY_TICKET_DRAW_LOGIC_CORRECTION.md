# 摸奖券开奖逻辑澄清

**日期:** 2026-06-10  
**澄清者:** 用户指正  
**问题:** 错误理解了"开奖进度"的含义

---

## ❌ 错误理解

**我之前的理解:**
```
后端分批摸球，逐个开奖
  ↓
需要记录 draw_current_count (已开4个)
  ↓
WebSocket 推送每次开奖进度
  ↓
前端被动接收并展示
```

**建议的数据库字段（❌ 错误）:**
```sql
ALTER TABLE lottery_ticket_activity 
ADD COLUMN draw_current_count INT DEFAULT 0 COMMENT '当前已开奖次数',
ADD COLUMN draw_total_count INT DEFAULT 0 COMMENT '计划开奖总次数';
```

---

## ✅ 正确理解

**实际业务流程:**
```
1. 后台管理员点击"开奖"
   ↓
2. 后端一次性摸出所有中奖号码（60个）
   ↓
3. 保存到数据库 (lottery_ticket表status改为WINNING)
   ↓
4. WebSocket推送"开奖完成"通知给所有玩家
   ↓
5. 前端收到完整中奖号码列表
   ↓
6. 前端自己控制展示速度（逐个显示）
   ↓
7. 前端动画显示"本期已開獎4次號"（前端变量）
```

---

## 📊 开奖流程对比

### 错误流程（我之前理解的）

```
后端                           前端
  │                             │
  ├─ 摸第1个球 ────────────────▶ 显示第1个号
  │  (WebSocket推送)            │
  │                             │
  ├─ 摸第2个球 ────────────────▶ 显示第2个号
  │  (WebSocket推送)            │
  │                             │
  ├─ 摸第3个球 ────────────────▶ 显示第3个号
  │  (WebSocket推送)            │
  │                             │
  └─ ... 60次推送               └─ 60次接收
```

**问题:**
- ❌ 后端需要维护开奖进度状态
- ❌ 需要60次WebSocket推送
- ❌ 网络延迟会导致动画卡顿
- ❌ 复杂度高，容错性差

---

### 正确流程（实际业务）

```
后端                           前端
  │                             │
  ├─ 一次性摸出60个球           │
  │  (批量处理)                 │
  │                             │
  ├─ 保存到数据库               │
  │  (lottery_ticket.status)    │
  │                             │
  ├─ 1次WebSocket推送 ─────────▶ 收到完整结果列表
  │  "开奖完成"                  │  [60个号码]
  │                             │
  │                             ├─ 前端逐个展示（定时器）
  │                             │  "本期已開獎1次號"
  │                             │   setTimeout(500ms)
  │                             ├─ "本期已開獎2次號"
  │                             │   setTimeout(500ms)
  │                             ├─ "本期已開獎3次號"
  │                             │   setTimeout(500ms)
  │                             └─ ... 前端控制速度
```

**优势:**
- ✅ 后端简单：一次性处理完成
- ✅ 只需1次WebSocket推送
- ✅ 前端控制动画，流畅不卡顿
- ✅ 离线用户也能查看完整结果

---

## 🔧 正确的实现方式

### 后端实现（已有，无需修改）

```php
// 1. 开奖服务（已实现）
class LotteryBallDrawService
{
    public function draw(LotteryTicketActivity $activity): array
    {
        // 一次性摸出所有中奖号码
        $winningTickets = $this->drawAllTickets($activity);
        
        // 批量更新奖券状态
        foreach ($winningTickets as $ticket) {
            $ticket->status = LotteryTicket::STATUS_WINNING;
            $ticket->prize_level = $this->determinePrizeLevel($ticket);
            $ticket->prize_amount = $this->getPrizeAmount($ticket);
            $ticket->save();
        }
        
        // 推送开奖完成通知（1次）
        LotteryTicketPushService::pushDrawComplete($activity->id, [
            'winning_tickets' => $winningTickets
        ]);
        
        return $winningTickets;
    }
}

// 2. 获取开奖结果API（需要补充）
class LotteryTicketController
{
    public function drawResults(Request $request): Response
    {
        $activityId = $request->input('activity_id');
        
        // 返回所有中奖号码（已摸出的）
        $winningTickets = LotteryTicket::query()
            ->where('activity_id', $activityId)
            ->where('status', LotteryTicket::STATUS_WINNING)
            ->orderBy('prize_level')
            ->orderBy('ticket_no')
            ->get(['ticket_no', 'prize_level', 'prize_amount']);
            
        return jsonSuccessResponse('success', [
            'winning_tickets' => $winningTickets,
            'total_count' => $winningTickets->count()
        ]);
    }
}
```

---

### 前端实现（建议）

```javascript
// 1. 监听开奖完成推送
socket.on('draw_complete', (data) => {
    // 收到完整中奖号码列表
    const winningTickets = data.winning_tickets; // 60个号码
    
    // 逐个展示动画
    showDrawAnimation(winningTickets);
});

// 2. 逐个展示动画
function showDrawAnimation(tickets) {
    let currentIndex = 0;
    
    const showNext = () => {
        if (currentIndex < tickets.length) {
            // 显示当前号码
            displayTicket(tickets[currentIndex]);
            
            // 更新进度文字
            updateProgressText(`本期已開獎${currentIndex + 1}次號`);
            
            // 0.5秒后显示下一个
            currentIndex++;
            setTimeout(showNext, 500);
        } else {
            // 全部显示完成
            showCompleteMessage();
        }
    };
    
    showNext();
}

// 3. 如果用户错过了推送，也可以手动查询
async function loadDrawResults(activityId) {
    const res = await axios.post('/api/v1/lottery-ticket/draw-results', {
        activity_id: activityId
    });
    
    // 同样逐个展示
    showDrawAnimation(res.data.winning_tickets);
}
```

---

## 📝 需要补充的功能

### ✅ 已有的（无需修改）

1. ✅ 摸球服务 `LotteryBallDrawService`
2. ✅ 奖券状态更新 `LotteryTicket::STATUS_WINNING`
3. ✅ 推送服务 `LotteryTicketPushService`

### ⚠️ 需要新增的

1. **开奖结果查询API**
```php
POST /api/v1/lottery-ticket/draw-results
{
    "activity_id": 1
}

Response:
{
    "code": 200,
    "data": {
        "winning_tickets": [
            {"ticket_no": "102458", "prize_level": 1, "prize_amount": 10000},
            {"ticket_no": "102457", "prize_level": 2, "prize_amount": 5000},
            ...
        ],
        "total_count": 60
    }
}
```

2. **开奖完成推送**
```php
// 在开奖服务中调用
LotteryTicketPushService::pushDrawComplete($activityId, [
    'event' => 'draw_complete',
    'activity_id' => $activityId,
    'winning_tickets' => $winningTickets->map(function($ticket) {
        return [
            'ticket_no' => $ticket->ticket_no,
            'prize_level' => $ticket->prize_level,
            'prize_amount' => $ticket->prize_amount
        ];
    })
]);
```

---

## ❌ 不需要的字段/功能

### 数据库字段（不需要）

```sql
-- ❌ 不需要这些字段
ALTER TABLE lottery_ticket_activity 
ADD COLUMN draw_current_count INT DEFAULT 0;  -- 不需要
ADD COLUMN draw_total_count INT DEFAULT 0;    -- 不需要
```

**原因:**
- 开奖是一次性完成的，不是分批进行
- "已开奖X次"是前端动画效果，不是后端状态
- 后端只需要知道：未开奖 / 已开奖

### WebSocket推送（不需要）

```php
// ❌ 不需要推送每个号码
LotteryTicketPushService::pushDrawProgress($activityId, [
    'current_count' => 4,         // 不需要
    'latest_ticket_no' => '102458' // 不需要
]);
```

**原因:**
- 60个号码一次性摸出
- 只需推送1次"开奖完成"
- 前端自己控制展示速度

---

## ✅ 总结

### 正确的开奖逻辑

```
┌─────────────────────────────────────┐
│ 后端：一次性摸出所有中奖号码          │
│ ├─ 调用摸球服务                     │
│ ├─ 批量更新奖券状态                 │
│ ├─ 推送1次"开奖完成"通知            │
│ └─ 提供查询API返回完整列表          │
└─────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│ 前端：逐个展示中奖号码（动画）        │
│ ├─ 收到完整号码列表                 │
│ ├─ 定时器控制展示速度（0.5秒/个）    │
│ ├─ 更新"已开奖X次"文字              │
│ └─ 展示完成后显示完整结果           │
└─────────────────────────────────────┘
```

### 需要补充的API

1. ✅ `POST /api/v1/lottery-ticket/draw-results` - 获取完整开奖结果
2. ✅ 优化推送服务 - 推送完整结果，不是单个号码

### 不需要的功能

1. ❌ 开奖进度字段 (`draw_current_count`)
2. ❌ 分批推送每个号码
3. ❌ 后端维护开奖状态机

---

**感谢指正！已理解正确的开奖逻辑。** ✅
