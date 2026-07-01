# 摸奖券系统 API 对接文档（精简版）

## 文档版本
- **版本号**: v2.0
- **更新日期**: 2026-06-17
- **适用系统**: YJB Gaming Platform - 摸奖券系统

---

## 目录
1. [快速开始](#快速开始)
2. [系统概述](#系统概述)
3. [业务流程](#业务流程)
4. [API接口](#api接口)
5. [WebSocket推送](#websocket推送)
6. [错误码](#错误码)
7. [常见问题](#常见问题)

---

## 快速开始

### 1. 认证方式

所有API接口和WebSocket连接都需要JWT Token认证。

**获取Token:**
```javascript
const response = await fetch('https://api.yourdomain.com/api/v1/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    username: 'player123',
    password: 'your_password'
  })
});

const data = await response.json();
const jwtToken = data.data.token;
```

---

### 2. 基础配置

**Base URL**: `https://api.yourdomain.com/api/v1`

**请求头**:
```
Content-Type: application/json
Authorization: Bearer {jwt_token}
```

**通用响应格式**:
```json
{
  "code": 200,
  "msg": "success",
  "data": { ... }
}
```

---

## 系统概述

### 核心特点

- ✅ 玩家通过打码（游戏投注）自动获取摸奖券
- ✅ VIP等级配置不同的打码要求和发券数量
- ✅ 线下物理摇球开奖（非系统自动）
- ✅ 管理员手动录入中奖结果
- ✅ 支持现金奖励自动发放
- ✅ 支持腾讯云直播

---

### 活动状态

| 状态值 | 状态名 | 说明 | 可发券 | 可开奖 |
|--------|--------|------|-------|--------|
| 0 | 未开始 | 活动未到开始时间 | ❌ | ❌ |
| 1 | 进行中 | 玩家打码获券阶段 | ✅ | ❌ |
| 2 | 已结束 | 停止发券，等待或完成开奖 | ❌ | ✅ |
| 3 | 已关闭 | 异常关闭 | ❌ | ❌ |
| 6 | 开奖中 | 管理员线下摇球 | ❌ | - |

---

### 奖券状态

| 状态值 | 状态名 | 说明 | 计入总数 | 计入中奖 |
|--------|--------|------|---------|---------|
| 0 | 未使用 | 已发放但未参与摇号 | ✅ | ❌ |
| 1 | 已使用 | 已参与摇号，等待开奖 | ✅ | ❌ |
| 2 | 已过期 | 超过有效期 | ❌ | ❌ |
| 3 | 已中奖 | 开奖后确认中奖 | ✅ | ✅ |
| 4 | 未中奖 | 开奖后确认未中奖 | ✅ | ❌ |

**统计规则:**
- `my_ticket_count`: 统计 `status IN (0,1,3,4)` 的券数
- `my_win_count`: 统计 `status = 3` 的券数

---

## 业务流程

```
1. 活动准备阶段
   管理员创建活动 → 配置奖品和VIP规则 → STATUS_NOT_STARTED (0)

2. 活动进行阶段
   到达start_time → STATUS_ONGOING (1) → 玩家打码 → 达标自动发券

3. 活动结束阶段
   到达end_time → STATUS_ENDED (2) → 停止发券

4. 开奖阶段
   管理员点击开奖 → STATUS_DRAWING (6) → 线下物理摇球 → 录入中奖券号

5. 发放阶段
   管理员发放奖金 → 增加余额 → 推送通知 → STATUS_ENDED (2)
```

---

## API接口

### 4.1 获取当前活动

**接口**: `POST /lottery-ticket/current-activity`

**说明**: 智能返回当前最相关的活动

**优先级**: 开奖中 > 进行中 > 即将开始 > 刚结束

**请求参数**: 无

**成功响应**:
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "has_activity": true,
    "activity": {
      "id": 1,
      "name": "春节摸奖活动",
      "description": "充值打码赢大奖",
      "cover_image": "https://cdn.example.com/lottery/cover.jpg",
      "start_time": "2026-06-15 00:00:00",
      "end_time": "2026-06-30 23:59:59",
      "status": 1,
      "status_text": "进行中",
      "live_url": "mojiangjuan",
      "live_status": 1,
      "live_status_text": "直播中",
      "my_ticket_count": 5,
      "my_win_count": 1,
      "countdown": {
        "type": "ongoing",
        "label": "活动进行中",
        "end_time": "2026-06-30 23:59:59",
        "seconds_remaining": 1123200
      },
      "has_drawn": false,
      "total_winners": 0
    },
    "prize_levels": [
      {
        "level_rank": 1,
        "level_name": "特等奖",
        "prize_amount": 10000.00,
        "prize_count": 1
      }
    ],
    "vip_configs": [
      {
        "vip_level_id": 1,
        "vip_level_name": "VIP1",
        "bet_amount_required": 1000.00,
        "ticket_count": 1
      }
    ],
    "bet_progress": {
      "bet_amount_required": 1000.00,
      "current_bet_amount": 650.50,
      "progress_percent": 65.05,
      "remaining_bet_amount": 349.50,
      "cycles_completed": 2,
      "total_tickets_issued": 5,
      "ticket_count_per_cycle": 1
    }
  }
}
```

**字段用处说明**:

| 字段 | 类型 | 用处 |
|------|------|------|
| `id` | int | 活动唯一标识，用于后续API调用 |
| `name` | string | 显示在活动标题/横幅 |
| `description` | string | 显示在活动说明/副标题 |
| `cover_image` | string | 活动封面图，显示在列表/详情页顶部 |
| `start_time` | datetime | 显示活动开始时间，用于倒计时 |
| `end_time` | datetime | 显示活动结束时间，用于倒计时 |
| `status` | int | 控制UI显示状态（进行中、已结束等） |
| `status_text` | string | 直接显示在状态标签上 |
| `live_url` | string | **直播流名称**（如：`mojiangjuan`），需调用API生成完整播放地址 |
| `live_status` | int | **直播状态**（0=未开播，1=直播中，2=已结束） |
| `live_status_text` | string | **直播状态文字**（未开播/直播中/已结束），显示在直播按钮上 |
| `my_ticket_count` | int | **显示"我的奖券：X张"**，用于统计用户参与度 |
| `my_win_count` | int | **显示"已中奖：X次"**，用于中奖提示/恭喜动画 |
| `prize_levels` | array | 显示奖品列表/等级表格，吸引用户参与 |
| `prize_levels[].level_rank` | int | 奖品排序，从1开始（1=最高奖） |
| `prize_levels[].level_name` | string | 显示奖品等级名称（特等奖、一等奖） |
| `prize_levels[].prize_amount` | decimal | 显示奖金金额，格式化为货币显示 |
| `prize_levels[].prize_count` | int | 显示奖品数量（如：1个、3个） |
| `vip_configs` | array | 显示打码规则表格，告知用户如何获券 |
| `vip_configs[].vip_level_name` | string | 显示VIP等级名称 |
| `vip_configs[].bet_amount_required` | decimal | 显示打码要求（如：打码1000元） |
| `vip_configs[].ticket_count` | int | 显示发券数量（如：获得1张券） |
| `bet_progress` | object | **打码进度对象**（核心功能），`null`表示未参与活动 |
| `bet_progress.bet_amount_required` | decimal | **打码目标金额**，用于进度条最大值 |
| `bet_progress.current_bet_amount` | decimal | **当前已打码金额**，用于进度条当前值 |
| `bet_progress.progress_percent` | decimal | **进度百分比**，直接用于CSS width |
| `bet_progress.remaining_bet_amount` | decimal | **还需打码金额**，用于提示文字 |
| `bet_progress.cycles_completed` | int | **已完成周期数**（已获券次数） |
| `bet_progress.total_tickets_issued` | int | **总发券数** |
| `bet_progress.ticket_count_per_cycle` | int | 每次发券数量 |

**⚠️ 重要说明：**
- `bet_progress` 是独立字段，**不在** `activity` 对象内
- 如果玩家未参与活动或VIP等级未配置，`bet_progress` 为 `null`
- **客户端应优先使用 `bet_progress` 显示打码进度**，而非从 `vip_configs` 计算

**前端使用示例**:
```javascript
// 1. 显示活动信息
document.getElementById('activity-title').textContent = activity.name;
document.getElementById('activity-desc').textContent = activity.description;
document.getElementById('cover-img').src = activity.cover_image;

// 2. 显示奖券统计（重要）
document.getElementById('my-tickets').textContent = activity.my_ticket_count;
document.getElementById('my-wins').textContent = activity.my_win_count;

// 3. 显示状态
document.getElementById('status-badge').textContent = activity.status_text;
document.getElementById('status-badge').className = 
  activity.status === 1 ? 'badge-ongoing' : 'badge-ended';

// 4. 直播按钮（根据直播状态显示）⭐
if (activity.live_url) {
  const liveBtn = document.getElementById('live-btn');
  
  // 根据直播状态显示不同UI
  switch(activity.live_status) {
    case 0: // 未开播
      liveBtn.style.display = 'none'; // 隐藏按钮
      break;
      
    case 1: // 直播中
      liveBtn.style.display = 'block';
      liveBtn.className = 'btn-live-ongoing';
      liveBtn.innerHTML = `
        <span class="live-dot"></span>
        ${activity.live_status_text}
      `;
      liveBtn.onclick = async () => {
        // 调用后端API获取完整播放地址
        const response = await fetch('/api/get-live-player-config', {
          method: 'POST',
          body: JSON.stringify({ stream_name: activity.live_url })
        });
        const liveData = await response.json();
        showLivePlayer(liveData.data.play_url);
      };
      break;
      
    case 2: // 已结束
      liveBtn.style.display = 'block';
      liveBtn.className = 'btn-live-ended';
      liveBtn.innerHTML = activity.live_status_text;
      liveBtn.disabled = true;
      break;
  }
}

// 5. 显示奖品列表
activity.prize_levels.forEach(prize => {
  addPrizeRow(prize.level_name, prize.prize_amount, prize.prize_count);
});

// 6. 显示打码规则
data.vip_configs.forEach(config => {
  addVipRule(config.vip_level_name, config.bet_amount_required, config.ticket_count);
});

// 7. 显示打码进度（重要⭐）
if (data.bet_progress) {
  const progress = data.bet_progress;
  
  // 更新进度条
  const progressBar = document.getElementById('progress-bar');
  progressBar.style.width = progress.progress_percent + '%';
  
  // 更新进度文字
  document.getElementById('progress-text').innerHTML = `
    <div class="progress-stats">
      <span>已打码：¥${progress.current_bet_amount.toLocaleString()}</span>
      <span class="highlight">还需：¥${progress.remaining_bet_amount.toLocaleString()}</span>
    </div>
    <div class="progress-achievement">
      <span>📊 已完成 ${progress.cycles_completed} 周期</span>
      <span>🎫 已获得 ${progress.total_tickets_issued} 张券</span>
    </div>
  `;
  
  // 即将获券提示
  if (progress.progress_percent >= 95) {
    showNotification('🎉 即将获得摸奖券！', `还需打码 ¥${progress.remaining_bet_amount.toFixed(2)} 元`);
  }
} else {
  // 未参与活动
  showEmptyProgress('开始游戏即可获得摸奖券');
}
```

**无活动响应**:
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "has_activity": false,
    "activity": null
  }
}
```

**前端处理**:
```javascript
if (!data.has_activity) {
  showEmptyState('当前暂无活动，敬请期待');
}
```

**限流**: 10次/分钟

---

### 4.2 获取我的奖券列表

**接口**: `POST /lottery-ticket/my-tickets`

**说明**: 获取玩家在指定活动中的所有奖券

**请求参数**:
```json
{
  "activity_id": 1,
  "page": 1,
  "size": 20
}
```

**成功响应**:
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "tickets": [
      {
        "id": 101,
        "ticket_no": "000123",
        "source": "betting",
        "source_text": "打码获得",
        "status": 3,
        "status_text": "已中奖",
        "is_winning": true,
        "prize_level": 2,
        "prize_amount": 5000.00,
        "issued_at": "2026-06-16 10:30:00",
        "expired_at": "2026-07-15 23:59:59"
      }
    ],
    "total": 5,
    "page": 1,
    "size": 20
  }
}
```

**字段用处说明**:

| 字段 | 类型 | 用处 |
|------|------|------|
| `id` | int | 奖券唯一标识，内部使用 |
| `ticket_no` | string | **显示券号**（6位数字），用于列表展示和查询 |
| `source` | string | 券来源类型（betting/recharge/manual） |
| `source_text` | string | **显示来源文字**（打码获得/充值赠送），用于列表标签 |
| `status` | int | 控制券的显示状态（颜色、图标） |
| `status_text` | string | **显示状态文字**（未使用/已中奖），用于状态标签 |
| `is_winning` | bool | **控制是否显示中奖标识**（金色边框/奖杯图标） |
| `prize_level` | int | 中奖等级，用于显示奖品名称 |
| `prize_amount` | decimal | **显示中奖金额**，格式化为货币 |
| `issued_at` | datetime | 显示发放时间 |
| `expired_at` | datetime | **显示过期时间**，用于倒计时提醒 |
| `total` | int | 用于分页器显示总数 |
| `page` | int | 当前页码 |
| `size` | int | 每页数量 |

**前端使用示例**:
```javascript
// 显示奖券列表
tickets.forEach(ticket => {
  const ticketCard = document.createElement('div');
  ticketCard.className = ticket.is_winning ? 'ticket-card winning' : 'ticket-card';
  
  ticketCard.innerHTML = `
    <div class="ticket-no">🎫 ${ticket.ticket_no}</div>
    <div class="ticket-source">${ticket.source_text}</div>
    <div class="ticket-status ${getStatusClass(ticket.status)}">
      ${ticket.status_text}
    </div>
    ${ticket.is_winning ? `
      <div class="prize-info">
        🏆 ${ticket.prize_level}等奖
        <span class="amount">¥${ticket.prize_amount.toLocaleString()}</span>
      </div>
    ` : ''}
    <div class="ticket-time">
      发放：${formatDate(ticket.issued_at)}<br>
      过期：${formatDate(ticket.expired_at)}
    </div>
  `;
  
  ticketList.appendChild(ticketCard);
});

// 分页器
renderPagination(data.total, data.page, data.size);
```

**限流**: 10次/分钟

---

### 4.3 获取中奖记录

**接口**: `POST /lottery-ticket/winning-records`

**说明**: 获取玩家的中奖记录

**请求参数**:
```json
{
  "activity_id": 1,
  "page": 1,
  "size": 20
}
```

**成功响应**:
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "records": [
      {
        "id": 1001,
        "activity_id": 1,
        "activity_name": "春节摸奖活动",
        "ticket_no": "000123",
        "prize_level": 2,
        "prize_level_name": "一等奖",
        "prize_amount": 5000.00,
        "status": 1,
        "status_text": "已发放",
        "claimed_at": "2026-06-18 10:00:00",
        "created_at": "2026-06-17 20:00:00"
      }
    ],
    "total": 1,
    "page": 1,
    "size": 20
  }
}
```

**字段用处说明**:

| 字段 | 类型 | 用处 |
|------|------|------|
| `id` | int | 记录唯一标识 |
| `activity_name` | string | **显示活动名称**，用于历史记录区分 |
| `ticket_no` | string | **显示中奖券号**，用于查询/验证 |
| `prize_level` | int | 内部等级值 |
| `prize_level_name` | string | **显示奖品等级名称**（特等奖、一等奖） |
| `prize_amount` | decimal | **显示中奖金额**，格式化为货币，用于金额高亮显示 |
| `status` | int | 控制发放状态显示（颜色） |
| `status_text` | string | **显示发放状态**（待发放/已发放），用于状态标签 |
| `claimed_at` | datetime | **显示到账时间**，用于时间轴展示 |
| `created_at` | datetime | **显示中奖时间**（开奖时间） |

**前端使用示例**:
```javascript
// 显示中奖记录列表
records.forEach(record => {
  const recordCard = document.createElement('div');
  recordCard.className = 'win-record-card';
  
  recordCard.innerHTML = `
    <div class="record-header">
      <span class="activity-name">📢 ${record.activity_name}</span>
      <span class="win-time">${formatDate(record.created_at)}</span>
    </div>
    <div class="record-body">
      <div class="prize-info">
        <span class="prize-level">🏆 ${record.prize_level_name}</span>
        <span class="prize-amount">¥${record.prize_amount.toLocaleString()}</span>
      </div>
      <div class="ticket-no">券号: ${record.ticket_no}</div>
    </div>
    <div class="record-footer">
      <span class="status ${getStatusClass(record.status)}">
        ${record.status_text}
      </span>
      ${record.claimed_at ? `
        <span class="claimed-time">
          💰 ${formatDate(record.claimed_at)} 到账
        </span>
      ` : ''}
    </div>
  `;
  
  winRecordList.appendChild(recordCard);
});
```

**限流**: 10次/分钟

---

### 4.4 获取打码进度

**接口**: `POST /lottery-ticket/bet-progress`

**说明**: 获取玩家在指定活动中的打码进度

**请求参数**:
```json
{
  "activity_id": 1
}
```

**成功响应**:
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "activity_id": 1,
    "vip_level_id": 2,
    "bet_amount_required": 1000.00,
    "current_bet_amount": 650.50,
    "progress_percent": 65.05,
    "remaining_bet_amount": 349.50,
    "cycles_completed": 2,
    "total_tickets_issued": 4,
    "ticket_count_per_cycle": 2
  }
}
```

**字段用处说明**:

| 字段 | 类型 | 用处 |
|------|------|------|
| `bet_amount_required` | decimal | **显示打码目标**（如：需打码1000元），用于进度条最大值 |
| `current_bet_amount` | decimal | **显示当前打码量**（如：已打码650元），用于进度条当前值 |
| `progress_percent` | decimal | **进度条百分比**（直接用于CSS width），如：65.05% |
| `remaining_bet_amount` | decimal | **显示还需打码**（如：还需349.50元），用于提示文字 |
| `cycles_completed` | int | **显示已完成周期数**（如：已获券2次），用于成就展示 |
| `total_tickets_issued` | int | **显示总发券数**（如：已获得4张券） |
| `ticket_count_per_cycle` | int | 显示每次发券数量（如：每次2张） |

**前端使用示例**:
```javascript
// 更新进度条
const progressBar = document.getElementById('progress-bar');
const progressText = document.getElementById('progress-text');

progressBar.style.width = data.progress_percent + '%';
progressBar.setAttribute('aria-valuenow', data.progress_percent);

progressText.innerHTML = `
  <div class="progress-label">
    <span>打码进度</span>
    <span class="percent">${data.progress_percent.toFixed(2)}%</span>
  </div>
  <div class="progress-detail">
    <span>已打码：¥${data.current_bet_amount.toLocaleString()}</span>
    <span>还需：¥${data.remaining_bet_amount.toLocaleString()}</span>
  </div>
  <div class="progress-stats">
    <span>📊 已完成 ${data.cycles_completed} 周期</span>
    <span>🎫 已获得 ${data.total_tickets_issued} 张券</span>
  </div>
`;

// 进度条颜色（根据进度）
if (data.progress_percent >= 90) {
  progressBar.className = 'progress-bar near-complete';
} else if (data.progress_percent >= 50) {
  progressBar.className = 'progress-bar half-complete';
} else {
  progressBar.className = 'progress-bar';
}

// 即将获券提示
if (data.progress_percent >= 95) {
  showNotification('🎉 即将获得摸奖券！', `还需打码 ¥${data.remaining_bet_amount.toFixed(2)} 元`);
}
```

**未找到记录**:
```json
{
  "code": 400,
  "msg": "未找到打码进度记录",
  "data": null
}
```

**限流**: 10次/分钟

---

## WebSocket推送

### 5.1 连接方式

**WebSocket地址**: `wss://your-domain.com:3131?token={jwt_token}`

**自动订阅频道**:
- `player-{player_id}` - 个人频道（发券、中奖、进度更新）
- `private-admin_group-channel-{department_id}` - 渠道频道（活动状态、直播）

**连接示例**:
```javascript
const ws = new WebSocket(`wss://api.yourdomain.com:3131?token=${jwtToken}`);

ws.onopen = () => console.log('✅ WebSocket已连接');
ws.onmessage = (event) => handleMessage(JSON.parse(event.data));
ws.onclose = () => setTimeout(() => connectWebSocket(), 3000); // 断线重连
```

---

### 5.2 推送事件

#### 5.2.1 发券通知

**type**: `ticket_issued`

**Payload**:
```json
{
  "type": "ticket_issued",
  "title": "恭喜獲得摸獎券",
  "message": "您獲得了 2 張摸獎券！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "ticket_no": "000123",
    "count": 2,
    "expired_at": "2026-07-15 23:59:59"
  },
  "show_notification": true,
  "timestamp": 1718630400
}
```

**前端处理**:
```javascript
case 'ticket_issued':
  if (message.show_notification) {
    showNotification(message.title, message.message);
    playSound('ticket_received.mp3');
  }
  refreshMyTickets(); // 刷新奖券列表
  refreshBetProgress(); // 刷新打码进度
  break;
```

---

#### 5.2.2 中奖通知

**type**: `lottery_win`

**Payload**:
```json
{
  "type": "lottery_win",
  "title": "🎉 恭喜中獎！",
  "message": "您獲得 一等奖 - 5,000.00 元！",
  "data": {
    "activity_id": 1,
    "ticket_no": "000123",
    "prize_level": "一等奖",
    "prize_amount": 5000.00,
    "record_id": 1001
  },
  "show_notification": true,
  "priority": "high",
  "timestamp": 1718640000
}
```

**前端处理**:
```javascript
case 'lottery_win':
  if (message.priority === 'high') {
    showBigWinAnimation(message.data); // 大奖特效
  } else {
    showWinAnimation(message.data);
  }
  showNotification(message.title, message.message);
  playSound('win.mp3');
  refreshWinningRecords();
  break;
```

---

#### 5.2.3 活动状态变更

**type**: `activity_status_change`

**Payload**:
```json
{
  "type": "activity_status_change",
  "title": "活動開始",
  "message": "活動正式開始，快來參與！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "status": 1,
    "event": "activity_start"
  },
  "timestamp": 1718550000
}
```

**event类型**:
- `activity_start` - 活动开始
- `drawing_start` - 开始开奖
- `ended` - 活动结束

**前端处理**:
```javascript
case 'activity_status_change':
  showNotification(message.title, message.message);
  refreshCurrentActivity();
  
  if (message.data.event === 'drawing_start') {
    navigateToLiveDrawing(message.data.activity_id); // 跳转到直播页
  }
  break;
```

---

#### 5.2.4 直播开始通知

**type**: `live_started`

**Payload**:
```json
{
  "type": "live_started",
  "title": "直播開始",
  "message": "直播已開始，快來觀看！",
  "data": {
    "activity_id": 1,
    "live_url": "mojiangjuan",
    "live_status": 1
  },
  "timestamp": 1718640000
}
```

**前端处理**:
```javascript
case 'live_started':
  showNotification(message.title, message.message);
  showLiveButton(message.data.activity_id); // 显示直播按钮
  break;
```

---

#### 5.2.5 打码进度更新（静默）

**type**: `bet_progress_update`

**Payload**:
```json
{
  "type": "bet_progress_update",
  "data": {
    "activity_id": 1,
    "progress_percent": 65.50,
    "remaining_amount": 345.00
  },
  "show_notification": false,
  "timestamp": 1718630000
}
```

**前端处理**:
```javascript
case 'bet_progress_update':
  // 静默更新进度条，不弹窗
  updateProgressBar(
    message.data.progress_percent,
    message.data.remaining_amount
  );
  break;
```

---

#### 5.2.6 奖金发放通知

**type**: `lottery_prize_distributed`

**Payload**:
```json
{
  "type": "lottery_prize_distributed",
  "title": "獎金已到賬",
  "message": "您的獎金 5,000.00 元已發放！",
  "data": {
    "activity_id": 1,
    "ticket_no": "000123",
    "prize_level": "一等奖",
    "prize_amount": 5000.00
  },
  "show_notification": true,
  "timestamp": 1718650000
}
```

**前端处理**:
```javascript
case 'lottery_prize_distributed':
  showNotification(message.title, message.message);
  playSound('money_received.mp3');
  refreshBalance(); // 刷新余额
  refreshWinningRecords(); // 更新中奖记录状态
  break;
```

---

### 5.3 完整示例

```javascript
class LotteryWebSocket {
  constructor(token) {
    this.token = token;
    this.ws = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 10;
  }

  connect() {
    this.ws = new WebSocket(`wss://api.yourdomain.com:3131?token=${this.token}`);
    
    this.ws.onopen = () => {
      console.log('✅ WebSocket已连接');
      this.reconnectAttempts = 0;
    };

    this.ws.onmessage = (event) => {
      const message = JSON.parse(event.data);
      this.handleMessage(message);
    };

    this.ws.onclose = () => {
      if (this.reconnectAttempts < this.maxReconnectAttempts) {
        this.reconnectAttempts++;
        console.log(`🔄 ${this.reconnectAttempts}次重连...`);
        setTimeout(() => this.connect(), 3000);
      }
    };

    this.ws.onerror = (error) => {
      console.error('❌ WebSocket错误:', error);
    };
  }

  handleMessage(message) {
    switch (message.type) {
      case 'ticket_issued':
        if (message.show_notification) {
          showNotification(message.title, message.message);
        }
        refreshMyTickets();
        refreshBetProgress();
        break;

      case 'lottery_win':
        showBigWinAnimation(message.data);
        refreshWinningRecords();
        break;

      case 'activity_status_change':
        refreshCurrentActivity();
        break;

      case 'bet_progress_update':
        updateProgressBar(
          message.data.progress_percent,
          message.data.remaining_amount
        );
        break;

      case 'lottery_prize_distributed':
        refreshBalance();
        refreshWinningRecords();
        break;
    }
  }
}

// 使用
const lotteryWS = new LotteryWebSocket(jwtToken);
lotteryWS.connect();
```

---

## 错误码

### HTTP状态码

| 状态码 | 说明 |
|--------|------|
| 200 | 请求成功 |
| 400 | 请求参数错误 |
| 401 | Token无效或过期 |
| 403 | 权限不足 |
| 429 | 请求过于频繁（限流） |
| 500 | 服务器内部错误 |

### 业务错误码

| code | msg | 处理方式 |
|------|-----|---------|
| 200 | success | 正常处理 |
| 400 | 活动不存在或无权访问 | 提示用户，返回活动列表 |
| 400 | 未找到打码进度记录 | 提示用户去游戏打码 |
| 401 | Token无效或已过期 | 跳转登录页 |
| 429 | 请求过于频繁 | 等待后重试，显示倒计时 |

---

## 常见问题

### Q1: 玩家打码后多久能收到奖券？

A: 实时发放。每次投注后系统自动累计，达标后立即发券并推送通知。

---

### Q2: 奖券有有效期吗？

A: 有。通常为活动结束后15天。过期券不参与开奖，不计入`my_ticket_count`。

---

### Q3: WebSocket断开怎么办？

A: 实现断线重连机制（见5.3示例）。建议最多重连10次，间隔3秒。

---

### Q4: 为什么API返回429错误？

A: 触发限流（10次/分钟）。解决方案：
- 实现本地缓存（减少重复请求）
- 使用WebSocket推送（避免轮询）
- 添加请求队列（控制频率）

---

### Q5: 如何测试WebSocket推送？

A: 使用浏览器控制台：
```javascript
const ws = new WebSocket('wss://api.yourdomain.com:3131?token=YOUR_TOKEN');
ws.onmessage = (e) => console.log(JSON.parse(e.data));
```

---

## 附录

### 版本变更

**v2.0 (2026-06-17)**
- ✅ 简化活动状态（5个状态）
- ✅ 移除自动摇球（改为线下物理摇球）
- ✅ 优化WebSocket推送架构
- ⚠️ **字段变更**: `my_ticket_count` / `my_win_count`（不是tickets/winning）
- ⚠️ **移除字段**: `ball_result`, `draw_time`, `preheat_start_time`

---

### 性能建议

**移动端APP配置**:
```javascript
{
  reconnectDelay: 3000,
  maxReconnectAttempts: 10,
  cacheTTL: {
    currentActivity: 60000,    // 1分钟
    myTickets: 30000,         // 30秒
    betProgress: 10000        // 10秒
  }
}
```

**Web端配置**:
```javascript
{
  reconnectDelay: 2000,
  maxReconnectAttempts: 20,
  cacheTTL: {
    currentActivity: 120000,   // 2分钟
    myTickets: 60000,         // 1分钟
    betProgress: 15000        // 15秒
  }
}
```

---

**文档版本**: v2.0（精简版）  
**最后更新**: 2026-06-17  
**维护者**: YJB Gaming Platform 开发团队
