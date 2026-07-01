# ✅ 摸奖券API最终设计方案

**确定时间:** 2026-06-12  
**设计原则:** 职责分离，客户端按需请求  
**API数量:** 4个独立API

---

## 🎯 最终方案：保持API独立

根据用户反馈，**不使用预览数据整合**，保持各API职责分离：

| API | 用途 | 调用时机 |
|-----|------|---------|
| `getCurrentActivity` | 活动概览 + 统计 | 首页加载（必调） |
| `myTickets` | 我的奖券列表（分页） | 点击"我的奖券"时调用 |
| `winningRecords` | 中奖记录列表（分页） | 点击"中奖记录"时调用 |
| `betProgress` | 打码进度详情 | 打码进度弹窗时调用 |

---

## 📋 API详细设计

### 1. getCurrentActivity - 活动概览（核心API）

**路由:** `POST /api/v1/lottery-ticket/current-activity`

**用途:** 获取当前活动基本信息、统计数据、开奖结果

**请求参数:** 无（仅需 JWT token）

**响应数据:**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "has_activity": true,
        "activity": {
            "id": 1,
            "name": "第1期摸奖券活动",
            "description": "充值即送摸奖券，100%中奖",
            "cover_image": "https://example.com/cover.jpg",
            "start_time": "2026-06-01 00:00:00",
            "end_time": "2026-06-30 23:59:59",
            "status": 5,
            "status_text": "打码中",
            
            // 我的统计数据
            "my_ticket_count": 10,
            "my_win_count": 1,
            
            // 倒计时
            "countdown": {
                "type": "end",
                "label": "距離活動結束",
                "seconds": 86400,
                "formatted": "1天"
            },
            
            // 开奖信息（未开奖时为null）
            "ball_result": [1, 2, 3, 4, 5, 6],
            "live_url": "https://live.example.com/lottery1",
            "total_winners": 523
        },
        
        // 奖品等级配置
        "prize_levels": [
            {
                "level_rank": 1,
                "level_name": "一等奖",
                "prize_type": "cash",
                "prize_amount": 10000,
                "prize_count": 1
            },
            {
                "level_rank": 2,
                "level_name": "二等奖",
                "prize_type": "cash",
                "prize_amount": 5000,
                "prize_count": 5
            }
        ],
        
        // 打码进度（活动进行中时返回，已结束时为null）
        "bet_progress": {
            "bet_amount_required": 500,
            "current_bet_amount": 320,
            "progress_percent": 64,
            "remaining_bet_amount": 180,
            "cycles_completed": 3,
            "total_tickets_issued": 3,
            "ticket_count_per_cycle": 1
        }
    }
}
```

**客户端展示:**

```
┌──────────────────────────────────────┐
│   第1期摸奖券活动                     │
│   状态: 打码中                        │
├──────────────────────────────────────┤
│                                      │
│   📊 我的统计                         │
│   奖券总数: 10张        [查看详情 >] │
│   中奖情况: 1张中奖 🎉  [查看详情 >] │
│                                      │
│   ⏰ 距离活动结束: 1天                │
│                                      │
├──────────────────────────────────────┤
│   🎱 开奖号码:                        │
│   ┌───┬───┬───┬───┬───┬───┐         │
│   │ 1 │ 2 │ 3 │ 4 │ 5 │ 6 │         │
│   └───┴───┴───┴───┴───┴───┘         │
│                                      │
│   📊 中奖人数: 523人                  │
│   📺 观看直播 >                       │
│                                      │
├──────────────────────────────────────┤
│   💪 打码进度                         │
│   ┌────────────────────────────────┐ │
│   │ ████████░░░░░░░░░░ 64%         │ │
│   │ 已打码: 320 / 需要: 500         │ │
│   │ 还差: 180 可获得下一张奖券      │ │
│   └────────────────────────────────┘ │
│                                      │
└──────────────────────────────────────┘
```

---

### 2. myTickets - 我的奖券列表

**路由:** `POST /api/v1/lottery-ticket/my-tickets`

**用途:** 查看我的所有奖券（带分页）

**请求参数:**

```json
{
    "activity_id": 1,
    "page": 1,
    "size": 20
}
```

**响应数据:**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "tickets": [
            {
                "id": 123,
                "ticket_no": "123456",
                "source": "recharge",
                "source_text": "充值赠送",
                "status": 0,
                "status_text": "未使用",
                "is_winning": false,
                "prize_level": null,
                "prize_amount": 0,
                "issued_at": "2026-06-01 12:00:00",
                "expired_at": "2026-06-30 23:59:59",
                "created_at": "2026-06-01 12:00:00"
            },
            {
                "id": 124,
                "ticket_no": "123457",
                "source": "betting",
                "source_text": "打码获得",
                "status": 1,
                "status_text": "已使用",
                "is_winning": true,
                "prize_level": 2,
                "prize_amount": 5000,
                "issued_at": "2026-06-01 13:00:00",
                "expired_at": "2026-06-30 23:59:59",
                "created_at": "2026-06-01 13:00:00"
            }
        ],
        "total": 10,
        "page": 1,
        "size": 20
    }
}
```

**客户端展示:**

```
┌──────────────────────────────────────┐
│   ← 我的奖券（全部 10张）             │
├──────────────────────────────────────┤
│   🎫 券号: 123456                     │
│   来源: 充值赠送                      │
│   状态: 未使用                        │
│   时间: 2026-06-01 12:00:00          │
├──────────────────────────────────────┤
│   🎫 券号: 123457                     │
│   来源: 打码获得                      │
│   状态: 已使用 - 中奖 🎉             │
│   奖品: 二等奖 5,000元               │
│   时间: 2026-06-01 13:00:00          │
├──────────────────────────────────────┤
│   🎫 券号: 123458                     │
│   来源: 手动发放                      │
│   状态: 未使用                        │
│   时间: 2026-06-01 14:00:00          │
├──────────────────────────────────────┤
│                                      │
│   [加载更多]                          │
└──────────────────────────────────────┘
```

---

### 3. winningRecords - 中奖记录列表

**路由:** `POST /api/v1/lottery-ticket/winning-records`

**用途:** 查看我的中奖记录（带分页）

**请求参数:**

```json
{
    "activity_id": 1,
    "page": 1,
    "size": 20
}
```

**响应数据:**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "records": [
            {
                "id": 45,
                "ticket_no": "123457",
                "prize_level": 2,
                "prize_level_name": "二等奖",
                "prize_type": "cash",
                "prize_amount": 5000,
                "status": 0,
                "status_text": "待发放",
                "created_at": "2026-06-01 20:00:00",
                "claimed_at": null
            }
        ],
        "total": 1,
        "page": 1,
        "size": 20
    }
}
```

**客户端展示:**

```
┌──────────────────────────────────────┐
│   ← 中奖记录（全部 1条）              │
├──────────────────────────────────────┤
│   🏆 券号: 123457                     │
│   奖品: 二等奖                        │
│   金额: 5,000元                       │
│   状态: 待发放                        │
│   时间: 2026-06-01 20:00:00          │
└──────────────────────────────────────┘
```

---

### 4. betProgress - 打码进度详情

**路由:** `POST /api/v1/lottery-ticket/bet-progress`

**用途:** 查看打码进度详情（弹窗展示）

**请求参数:**

```json
{
    "activity_id": 1
}
```

**响应数据:**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "activity_id": 1,
        "player_id": 123,
        "vip_level_id": 1,
        "bet_amount_required": 500,
        "current_bet_amount": 320,
        "progress_percent": 64,
        "remaining_bet_amount": 180,
        "cycles_completed": 3,
        "total_tickets_issued": 3,
        "ticket_count_per_cycle": 1,
        "status": 1,
        "created_at": "2026-06-01 00:00:00",
        "updated_at": "2026-06-01 15:30:00"
    }
}
```

**客户端展示:**

```
┌──────────────────────────────────────┐
│   💪 打码进度详情                     │
├──────────────────────────────────────┤
│                                      │
│   当前进度                            │
│   ┌────────────────────────────────┐ │
│   │ ████████░░░░░░░░░░ 64%         │ │
│   └────────────────────────────────┘ │
│                                      │
│   已完成打码: 320元                   │
│   需要打码: 500元                     │
│   还差: 180元                         │
│                                      │
│   已完成周期: 3次                     │
│   已获得奖券: 3张                     │
│   每周期奖券: 1张                     │
│                                      │
│   最后更新: 2026-06-01 15:30:00      │
│                                      │
│   [关闭]                              │
└──────────────────────────────────────┘
```

---

## 🔄 客户端调用流程

### 场景1: 首页加载

```javascript
// 1. 调用活动概览API（必调）
const { activity, prize_levels, bet_progress } = await getCurrentActivity();

// 2. 显示统计数据
console.log(`奖券总数: ${activity.my_ticket_count}`);
console.log(`中奖数量: ${activity.my_win_count}`);

// 3. 显示开奖结果（如果已开奖）
if (activity.ball_result) {
    console.log(`开奖号码: ${activity.ball_result.join(', ')}`);
    console.log(`中奖人数: ${activity.total_winners}`);
}

// 4. 显示打码进度（如果活动进行中）
if (bet_progress) {
    console.log(`打码进度: ${bet_progress.progress_percent}%`);
}
```

---

### 场景2: 点击"查看我的奖券"

```javascript
// 1. 调用奖券列表API（按需调用）
const { tickets, total } = await myTickets({
    activity_id: activity.id,
    page: 1,
    size: 20
});

// 2. 显示奖券列表（支持分页）
tickets.forEach(ticket => {
    console.log(`券号: ${ticket.ticket_no}`);
    console.log(`状态: ${ticket.status_text}`);
    if (ticket.is_winning) {
        console.log(`中奖金额: ${ticket.prize_amount}元`);
    }
});

// 3. 加载更多（下一页）
if (page * size < total) {
    const nextPage = await myTickets({
        activity_id: activity.id,
        page: page + 1,
        size: 20
    });
}
```

---

### 场景3: 点击"查看中奖记录"

```javascript
// 1. 调用中奖记录API（按需调用）
const { records, total } = await winningRecords({
    activity_id: activity.id,
    page: 1,
    size: 20
});

// 2. 显示中奖记录
records.forEach(record => {
    console.log(`券号: ${record.ticket_no}`);
    console.log(`奖品: ${record.prize_level_name}`);
    console.log(`金额: ${record.prize_amount}元`);
    console.log(`状态: ${record.status_text}`);
});
```

---

### 场景4: 点击"打码进度详情"

```javascript
// 1. 调用打码进度API（弹窗展示）
const progressDetail = await betProgress({
    activity_id: activity.id
});

// 2. 显示详细进度
console.log(`当前进度: ${progressDetail.progress_percent}%`);
console.log(`已打码: ${progressDetail.current_bet_amount}`);
console.log(`需要打码: ${progressDetail.bet_amount_required}`);
console.log(`还差: ${progressDetail.remaining_bet_amount}`);
console.log(`已完成周期: ${progressDetail.cycles_completed}`);
console.log(`已获得奖券: ${progressDetail.total_tickets_issued}`);
```

---

## 📊 API对比

| 特性 | 整合方案（已废弃） | 独立方案（最终） |
|------|-------------------|-----------------|
| API数量 | 1个 | 4个 |
| 首页加载API | 1个 | 1个 |
| 列表查询API | 内置（无分页） | 2个（支持分页） |
| 首页响应大小 | ~10KB | ~3KB |
| 列表分页支持 | ❌ 不支持 | ✅ 支持 |
| 灵活性 | ⚠️ 低 | ✅ 高 |
| 性能 | ⚠️ 一般 | ✅ 好 |
| 职责分离 | ❌ 耦合 | ✅ 清晰 |

---

## ✅ 最终方案优势

### 1. 职责分离

- ✅ 每个API职责清晰
- ✅ 便于维护和扩展
- ✅ 符合RESTful设计原则

### 2. 性能优化

- ✅ 首页只加载必要数据（3KB）
- ✅ 列表数据按需加载
- ✅ 支持分页，避免一次加载过多数据

### 3. 灵活性

- ✅ 客户端可自由控制调用时机
- ✅ 可独立缓存各API数据
- ✅ 可根据场景优化请求策略

### 4. 可扩展性

- ✅ 新增功能不影响现有API
- ✅ 可单独优化各API性能
- ✅ 便于后续添加筛选、排序等功能

---

## 📌 API状态

| API | 状态 | 用途 |
|-----|------|------|
| `getCurrentActivity` | ✅ 已完善 | 活动概览 + 统计 + 开奖结果 |
| `myTickets` | ✅ 保留 | 奖券列表（分页） |
| `winningRecords` | ✅ 保留 | 中奖记录（分页） |
| `betProgress` | ✅ 保留 | 打码进度详情 |

---

## 🎯 总结

### 最终决策

- ✅ **不使用预览数据整合**
- ✅ **保持4个API独立**
- ✅ **客户端按需调用**

### 优势

- ✅ 职责分离，便于维护
- ✅ 性能优化，按需加载
- ✅ 灵活性高，可独立优化
- ✅ 可扩展性好

### 客户端策略

1. 首页必调：`getCurrentActivity`（获取统计和概览）
2. 按需调用：`myTickets`、`winningRecords`（查看详情时）
3. 可选调用：`betProgress`（打码进度弹窗时）

---

**确定时间:** 2026-06-12  
**API数量:** 4个独立API  
**设计原则:** 职责分离，按需加载  
**状态:** ✅ **最终方案确定**
