# 摸奖券系统 API 对接文档

## 文档版本
- **版本号**: v2.0
- **更新日期**: 2026-06-17
- **适用系统**: YJB Gaming Platform - 摸奖券系统

---

## 目录
1. [快速开始](#快速开始) ⭐ 新增
2. [系统概述](#系统概述)
3. [业务流程](#业务流程)
4. [状态说明](#状态说明)
5. [API接口](#api接口)
6. [数据模型](#数据模型)
7. [错误码](#错误码)
8. [WebSocket推送](#websocket推送)
9. [常见问题](#常见问题)
10. [附录](#附录)

---

## 快速开始

### 1. 准备工作

#### 1.1 获取认证Token

所有API接口和WebSocket连接都需要JWT Token认证。

```javascript
// 登录获取Token
const response = await fetch('https://api.yourdomain.com/api/v1/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    username: 'player123',
    password: 'your_password'
  })
});

const data = await response.json();
const jwtToken = data.data.token;

// 保存Token（建议使用安全方式）
// 详见: 7.8.1 Token安全存储
```

---

#### 1.2 测试API连接

```javascript
// 测试：获取当前活动
const response = await fetch('https://api.yourdomain.com/api/v1/lottery-ticket/current-activity', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + jwtToken
  }
});

const result = await response.json();

if (result.code === 200) {
  console.log('✅ API连接成功！');
  console.log('当前活动:', result.data);
} else {
  console.error('❌ API请求失败:', result.msg);
}
```

---

#### 1.3 测试WebSocket连接

```javascript
// 连接WebSocket
const ws = new WebSocket('wss://api.yourdomain.com:3131?token=' + jwtToken);

ws.onopen = () => {
  console.log('✅ WebSocket连接成功！');
  console.log('已自动订阅个人频道和渠道广播频道');
};

ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  console.log('📩 收到推送:', message);
};

ws.onerror = (error) => {
  console.error('❌ WebSocket错误:', error);
};
```

---

### 2. 完整集成示例（5分钟快速上手）

```html
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <title>摸奖券系统 - 快速示例</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; }
    .activity-card { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
    .ticket-item { background: #f5f5f5; padding: 10px; margin: 5px 0; border-radius: 4px; }
    .progress-bar { width: 100%; height: 30px; background: #eee; border-radius: 15px; overflow: hidden; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); transition: width 0.3s; }
    .notification { position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); animation: slideIn 0.3s; }
    @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
  </style>
</head>
<body>
  <h1>🎲 摸奖券系统</h1>

  <!-- 当前活动 -->
  <div id="current-activity" class="activity-card">
    <h2>📢 当前活动</h2>
    <p>加载中...</p>
  </div>

  <!-- 打码进度 -->
  <div class="activity-card">
    <h2>📊 打码进度</h2>
    <div class="progress-bar">
      <div id="progress-fill" class="progress-fill" style="width: 0%"></div>
    </div>
    <p id="progress-text">打码进度：0% | 还需打码：0 元</p>
  </div>

  <!-- 我的奖券 -->
  <div class="activity-card">
    <h2>🎫 我的奖券</h2>
    <div id="my-tickets">加载中...</div>
  </div>

  <!-- 中奖记录 -->
  <div class="activity-card">
    <h2>🏆 中奖记录</h2>
    <div id="winning-records">暂无中奖记录</div>
  </div>

  <script>
    // ========== 配置 ==========
    const API_BASE = 'https://api.yourdomain.com/api/v1/lottery-ticket';
    const WS_URL = 'wss://api.yourdomain.com:3131';
    const JWT_TOKEN = 'YOUR_JWT_TOKEN_HERE'; // 请替换为实际Token

    // ========== API请求封装 ==========
    async function apiRequest(endpoint, data = {}) {
      const response = await fetch(`${API_BASE}${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${JWT_TOKEN}`
        },
        body: JSON.stringify(data)
      });
      return await response.json();
    }

    // ========== 显示通知 ==========
    function showNotification(title, message) {
      const notification = document.createElement('div');
      notification.className = 'notification';
      notification.innerHTML = `<strong>${title}</strong><br>${message}`;
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.remove();
      }, 5000);
    }

    // ========== 加载当前活动 ==========
    async function loadCurrentActivity() {
      const result = await apiRequest('/current-activity');
      
      if (result.code === 200) {
        const activity = result.data;
        document.getElementById('current-activity').innerHTML = `
          <h2>📢 ${activity.name}</h2>
          <p>状态：${activity.status_text}</p>
          <p>时间：${activity.start_time} ~ ${activity.end_time}</p>
          <p>已发券数：${activity.current_ticket_no} / ${activity.max_ticket_no}</p>
          ${activity.live_url ? `<p>🎥 <a href="${activity.live_url}" target="_blank">观看直播</a></p>` : ''}
        `;
      } else {
        document.getElementById('current-activity').innerHTML = `<p>${result.msg}</p>`;
      }
    }

    // ========== 加载我的奖券 ==========
    async function loadMyTickets() {
      const result = await apiRequest('/my-tickets', { page: 1, size: 10 });
      
      if (result.code === 200 && result.data.list.length > 0) {
        const html = result.data.list.map(ticket => `
          <div class="ticket-item">
            🎫 券号: <strong>${ticket.ticket_no}</strong> | 
            ${ticket.is_winner ? '🎉 已中奖！' : '等待开奖'} | 
            过期时间: ${ticket.expired_at}
          </div>
        `).join('');
        document.getElementById('my-tickets').innerHTML = html;
      } else {
        document.getElementById('my-tickets').innerHTML = '<p>暂无奖券</p>';
      }
    }

    // ========== 加载中奖记录 ==========
    async function loadWinningRecords() {
      const result = await apiRequest('/winning-records', { page: 1, size: 5 });
      
      if (result.code === 200 && result.data.list.length > 0) {
        const html = result.data.list.map(record => `
          <div class="ticket-item">
            🏆 ${record.prize_level} | 
            奖金: <strong>¥${record.prize_amount.toLocaleString()}</strong> | 
            券号: ${record.ticket_no} | 
            ${record.status_text}
          </div>
        `).join('');
        document.getElementById('winning-records').innerHTML = html;
      }
    }

    // ========== 加载打码进度 ==========
    async function loadBetProgress() {
      const result = await apiRequest('/bet-progress');
      
      if (result.code === 200) {
        updateProgressBar(
          result.data.progress_percent,
          result.data.remaining_bet_amount
        );
      }
    }

    // ========== 更新进度条 ==========
    function updateProgressBar(percent, remaining) {
      document.getElementById('progress-fill').style.width = percent + '%';
      document.getElementById('progress-text').textContent = 
        `打码进度：${percent.toFixed(2)}% | 还需打码：${remaining.toFixed(2)} 元`;
    }

    // ========== WebSocket连接 ==========
    function connectWebSocket() {
      const ws = new WebSocket(`${WS_URL}?token=${JWT_TOKEN}`);

      ws.onopen = () => {
        console.log('✅ WebSocket已连接');
      };

      ws.onmessage = (event) => {
        const message = JSON.parse(event.data);
        console.log('📩 收到推送:', message);

        switch (message.type) {
          case 'ticket_issued':
            showNotification(message.title, message.message);
            loadMyTickets(); // 刷新奖券列表
            loadBetProgress(); // 刷新进度
            break;

          case 'lottery_win':
            showNotification('🎉 恭喜中奖！', message.message);
            loadWinningRecords(); // 刷新中奖记录
            break;

          case 'activity_status_change':
            showNotification(message.title, message.message);
            loadCurrentActivity(); // 刷新活动信息
            break;

          case 'live_started':
            showNotification(message.title, message.message);
            loadCurrentActivity(); // 刷新以显示直播链接
            break;

          case 'bet_progress_update':
            // 静默更新进度条
            updateProgressBar(
              message.data.progress_percent,
              message.data.remaining_amount
            );
            break;

          case 'lottery_prize_distributed':
            showNotification(message.title, message.message);
            loadWinningRecords(); // 刷新中奖记录
            break;
        }
      };

      ws.onclose = () => {
        console.log('⚠️ WebSocket已断开，3秒后重连...');
        setTimeout(connectWebSocket, 3000);
      };

      ws.onerror = (error) => {
        console.error('❌ WebSocket错误:', error);
      };
    }

    // ========== 页面加载时初始化 ==========
    window.onload = () => {
      // 加载初始数据
      loadCurrentActivity();
      loadMyTickets();
      loadWinningRecords();
      loadBetProgress();

      // 连接WebSocket
      connectWebSocket();
    };
  </script>
</body>
</html>
```

**使用步骤**:
1. 将上述代码保存为`lottery.html`
2. 替换`YOUR_JWT_TOKEN_HERE`为实际的JWT Token
3. 在浏览器中打开文件
4. ✅ 完成！系统会自动加载数据并接收实时推送

---

### 3. 生产环境检查清单

在正式上线前，请确认以下事项：

- [ ] ✅ JWT Token安全存储（使用HttpOnly Cookie或内存存储）
- [ ] ✅ API请求添加限流保护（10次/分钟）
- [ ] ✅ WebSocket断线重连机制已实现
- [ ] ✅ 错误处理和用户提示已完善
- [ ] ✅ 消息去重机制已实现
- [ ] ✅ 本地缓存策略已配置
- [ ] ✅ XSS防护已实现（转义用户输入）
- [ ] ✅ HTTPS协议已启用（wss://）
- [ ] ✅ 性能监控已部署
- [ ] ✅ 日志记录已配置

---

## 系统概述

### 1.1 功能简介
摸奖券系统是一个基于玩家打码（投注）自动发券、线下物理摇球开奖的抽奖系统。

**核心特点：**
- ✅ 玩家通过打码（游戏投注）自动获取摸奖券
- ✅ VIP等级配置不同的打码要求和发券数量
- ✅ 线下物理摇球开奖（非系统自动摇球）
- ✅ 管理员手动录入中奖结果
- ✅ 支持现金奖励自动发放
- ✅ 支持直播地址展示

### 1.2 业务角色
- **玩家端**：查看活动、获取奖券、查看中奖记录
- **管理后台**：创建活动、配置奖品、开始开奖、录入中奖、发放奖金
- **推送服务**：实时通知玩家活动状态变更、中奖信息

---

## 业务流程

### 2.1 完整流程图

```
┌─────────────────────────────────────────────────────────────┐
│                    摸奖券完整业务流程                          │
└─────────────────────────────────────────────────────────────┘

1. 活动准备阶段
   ┌──────────────┐
   │ 管理员创建活动 │ → 配置活动时间、奖品等级、VIP打码规则
   └──────────────┘
          ↓
   ┌──────────────┐
   │ 状态：未开始  │ (STATUS_NOT_STARTED = 0)
   └──────────────┘

2. 活动进行阶段（玩家打码获券）
   ┌──────────────┐
   │ 到达start_time│
   └──────────────┘
          ↓ (自动流转)
   ┌──────────────┐
   │ 状态：进行中  │ (STATUS_ONGOING = 1)
   └──────────────┘
          ↓
   玩家投注 → 累计打码量 → 达标自动发券
   ├─ VIP1: 打码1000元 → 获得1张券
   ├─ VIP2: 打码800元  → 获得2张券
   └─ VIP3: 打码500元  → 获得3张券
          ↓
   重复获券（可获得多张券）
   券号：000000, 000001, 000002, ...

3. 活动结束阶段
   ┌──────────────┐
   │ 到达end_time  │ → 停止发券，但状态仍为进行中
   └──────────────┘
          ↓ (自动流转)
   ┌──────────────┐
   │ 状态：已结束  │ (STATUS_ENDED = 2)
   └──────────────┘
          ↓
   【只有已结束状态才能开奖】

4. 开奖阶段（线下物理摇球）
   ┌──────────────┐
   │ 管理员点击开奖 │ → 填写直播地址
   └──────────────┘
          ↓
   ┌──────────────┐
   │ 状态：开奖中  │ (STATUS_DRAWING = 6)
   └──────────────┘
          ↓
   【线下使用物理摇球机】
   ├─ 放入1000000个号码球（000000~999999）
   ├─ 管理员启动摇球机
   ├─ 随机摇出中奖球号
   └─ 记录中奖券号

5. 录入中奖阶段
   ┌──────────────┐
   │ 管理员录入券号 │ → 例：特等奖 000123
   └──────────────┘
          ↓
   系统处理：
   ├─ 查找券号000123的持有者
   ├─ 创建中奖记录（状态：待发放）
   ├─ 推送中奖通知给玩家
   └─ 等待管理员发放奖金

6. 发放奖金阶段
   ┌──────────────┐
   │ 管理员发放奖金 │
   └──────────────┘
          ↓
   系统处理：
   ├─ 增加玩家余额
   ├─ 记录财务流水
   ├─ 更新中奖记录状态（已发放）
   └─ 推送发放成功通知

7. 结束阶段
   ┌──────────────┐
   │ 管理员停止开奖 │ → 所有奖品发放完毕
   └──────────────┘
          ↓
   ┌──────────────┐
   │ 状态：已结束  │ (STATUS_ENDED = 2)
   └──────────────┘
```

### 2.2 关键时间点

| 时间点 | 说明 | 状态变化 |
|--------|------|----------|
| `start_time` | 活动开始时间 | NOT_STARTED → ONGOING |
| `end_time` | 活动结束时间（停止发券） | ONGOING → ENDED |
| 管理员点击"开始开奖" | 进入开奖阶段 | ENDED → DRAWING |
| 管理员点击"停止开奖" | 开奖完成 | DRAWING → ENDED |
| 管理员点击"关闭活动" | 异常关闭 | 任何状态 → CLOSED |

**⚠️ 重要规则：**
- ✅ 到达`end_time`后不再发券，但玩家仍可查看活动
- ✅ 只有`STATUS_ENDED`状态才能开始开奖
- ✅ 进行中的活动禁止开奖（必须等待自然结束）

---

## 状态说明

### 3.1 活动状态（LotteryTicketActivity.status）

```php
const STATUS_NOT_STARTED = 0;  // 未开始
const STATUS_ONGOING = 1;      // 进行中（玩家打码获券阶段）
const STATUS_ENDED = 2;        // 已结束（等待开奖或已完成开奖）
const STATUS_CLOSED = 3;       // 已关闭（手动关闭、异常终止）
const STATUS_DRAWING = 6;      // 开奖中（管理员线下摇球阶段）
```

| 状态值 | 状态名 | 说明 | 玩家可见 | 可发券 | 可开奖 |
|--------|--------|------|---------|-------|--------|
| 0 | 未开始 | 活动未到开始时间 | ✅ | ❌ | ❌ |
| 1 | 进行中 | 玩家打码获券阶段 | ✅ | ✅ | ❌ |
| 2 | 已结束 | 停止发券，等待或完成开奖 | ✅ | ❌ | ✅ |
| 3 | 已关闭 | 异常关闭 | ❌ | ❌ | ❌ |
| 6 | 开奖中 | 管理员线下摇球 | ✅ | ❌ | - |

### 3.2 奖券状态（LotteryTicket.status）

```php
const STATUS_UNUSED = 0;   // 未使用
const STATUS_USED = 1;     // 已使用
const STATUS_EXPIRED = 2;  // 已过期
```

### 3.3 中奖记录状态（LotteryTicketRecord.status）

```php
const STATUS_PENDING = 0;     // 待发放
const STATUS_CLAIMED = 1;     // 已发放
const STATUS_CANCELLED = 2;   // 已取消
```

### 3.4 奖品类型（LotteryTicketRecord.prize_type）

```php
const PRIZE_TYPE_CASH = 'cash';     // 现金（当前只支持现金）
const PRIZE_TYPE_BONUS = 'bonus';   // 红利（预留）
const PRIZE_TYPE_ITEM = 'item';     // 实物（预留）
const PRIZE_TYPE_EMPTY = 'empty';   // 空奖（未中奖）
```

**⚠️ 注意：** `LotteryTicketPrizeLevel`表只支持现金奖励，`prize_type`字段已删除。

---

## API接口

### 4.1 基础信息

**Base URL**: `https://api.yourdomain.com/api/v1`

**认证方式**: JWT Token (Header: `Authorization: Bearer {token}`)

**请求头**:
```
Content-Type: application/json
Accept: application/json
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

**错误响应格式**:
```json
{
  "code": 400,
  "msg": "错误描述",
  "data": null
}
```

---

### 4.2 玩家端API

#### 4.2.1 获取当前活动（智能）

**接口**: `POST /lottery-ticket/current-activity`

**说明**: 智能返回当前最相关的活动（按优先级）

**优先级规则**:
1. 开奖中的活动（最高优先级）
2. 进行中的活动（打码获券阶段）
3. 即将开始的活动（7天内）
4. 刚结束的活动（如果没有新活动）

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
      "live_url": "https://live.example.com/lottery123",
      "total_tickets": 150,
      "used_tickets": 80,
      "my_ticket_count": 5,
      "my_win_count": 1,
      "prize_levels": [
        {
          "level_rank": 1,
          "level_name": "特等奖",
          "prize_amount": 10000.00,
          "prize_count": 1
        },
        {
          "level_rank": 2,
          "level_name": "一等奖",
          "prize_amount": 5000.00,
          "prize_count": 3
        }
      ],
      "vip_configs": [
        {
          "vip_level_id": 1,
          "vip_level_name": "VIP1",
          "bet_amount_required": 1000.00,
          "ticket_count": 1
        },
        {
          "vip_level_id": 2,
          "vip_level_name": "VIP2",
          "bet_amount_required": 800.00,
          "ticket_count": 2
        }
      ]
    }
  }
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

**关键字段说明**:

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `my_ticket_count` | int | 玩家在当前活动中的有效奖券总数（不包括已过期的券）<br>统计条件：`status IN (0,1,3,4)` |
| `my_win_count` | int | 玩家在当前活动中的中奖次数<br>统计条件：`status = 3` (已中奖) |
| `has_drawn` | bool | 是否已开奖（`status` 为 DRAWING 或 ENDED 时为 true） |
| `live_url` | string | 直播流名称（如：`mojiangjuan`），需配合腾讯云配置生成完整播放地址 |
| `total_winners` | int | 活动总中奖人数（已开奖时显示） |

**奖券状态说明**:
- `0` - 未使用：已发放但未参与摇号
- `1` - 已使用：已参与摇号，等待开奖
- `2` - 已过期：超过有效期，不计入`my_ticket_count`
- `3` - 已中奖：开奖后确认中奖，计入`my_win_count`
- `4` - 未中奖：开奖后确认未中奖

**限流**: 10次/分钟

---

#### 4.2.2 获取我的奖券列表

**接口**: `POST /lottery-ticket/my-tickets`

**说明**: 获取玩家在指定活动中的所有奖券

**请求参数**:
```json
{
  "activity_id": 1,       // 必填，活动ID
  "page": 1,              // 可选，页码，默认1
  "size": 20              // 可选，每页数量，默认20，最大100
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
        "status": 1,
        "status_text": "已使用",
        "is_winning": true,
        "prize_level": 2,
        "prize_amount": 5000.00,
        "issued_at": "2026-06-16 10:30:00",
        "expired_at": "2026-07-15 23:59:59",
        "created_at": "2026-06-16 10:30:00"
      },
      {
        "id": 102,
        "ticket_no": "000456",
        "source": "betting",
        "source_text": "打码获得",
        "status": 0,
        "status_text": "未使用",
        "is_winning": false,
        "prize_level": null,
        "prize_amount": 0,
        "issued_at": "2026-06-17 14:20:00",
        "expired_at": "2026-07-15 23:59:59",
        "created_at": "2026-06-17 14:20:00"
      }
    ],
    "total": 5,
    "page": 1,
    "size": 20
  }
}
```

**字段说明**:
- `source`: 奖券来源（`betting`=打码获得, `recharge`=充值赠送, `manual`=手动发放, `activity`=活动赠送）
- `status`: 奖券状态（0=未使用, 1=已使用, 2=已过期）
- `is_winning`: 是否中奖（通过查询`LotteryTicketRecord`表判断）
- `prize_level`: 中奖等级（1=特等奖, 2=一等奖, ...）

**限流**: 10次/分钟

---

#### 4.2.3 获取中奖记录

**接口**: `POST /lottery-ticket/winning-records`

**说明**: 获取玩家的中奖记录

**请求参数**:
```json
{
  "activity_id": 1,       // 可选，活动ID（不传则返回所有活动）
  "page": 1,              // 可选，页码，默认1
  "size": 20              // 可选，每页数量，默认20，最大100
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
        "ticket_id": 101,
        "ticket_no": "000123",
        "prize_level": 2,
        "prize_level_name": "一等奖",
        "prize_type": "cash",
        "prize_type_text": "现金",
        "prize_name": "一等奖",
        "prize_amount": 5000.00,
        "status": 1,
        "status_text": "已发放",
        "claimed_at": "2026-06-18 10:00:00",
        "remark": "恭喜中奖！",
        "created_at": "2026-06-17 20:00:00"
      }
    ],
    "total": 1,
    "page": 1,
    "size": 20
  }
}
```

**字段说明**:
- `prize_type`: 奖品类型（`cash`=现金）
- `status`: 发放状态（0=待发放, 1=已发放, 2=已取消）
- `claimed_at`: 发放时间（已发放时才有值）

**限流**: 10次/分钟

---

#### 4.2.4 获取打码进度

**接口**: `POST /lottery-ticket/bet-progress`

**说明**: 获取玩家在指定活动中的打码进度

**请求参数**:
```json
{
  "activity_id": 1        // 必填，活动ID
}
```

**成功响应**:
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "activity_id": 1,
    "player_id": 10001,
    "vip_level_id": 2,
    "bet_amount_required": 1000.00,
    "current_bet_amount": 650.50,
    "progress_percent": 65.05,
    "remaining_bet_amount": 349.50,
    "cycles_completed": 2,
    "total_tickets_issued": 4,
    "ticket_count_per_cycle": 2,
    "status": 1,
    "updated_at": "2026-06-17 15:30:00"
  }
}
```

**字段说明**:
- `bet_amount_required`: 单次达标所需打码量
- `current_bet_amount`: 当前周期累计打码量
- `progress_percent`: 当前周期进度百分比
- `remaining_bet_amount`: 距离下次发券还需打码
- `cycles_completed`: 已完成周期数（已获券次数）
- `total_tickets_issued`: 总共已发券数
- `ticket_count_per_cycle`: 每次达标发券数量

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

## 数据模型

### 5.1 活动表（lottery_ticket_activity）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int | 主键ID |
| department_id | int | 所属渠道部门ID |
| name | varchar(100) | 活动名称 |
| description | text | 活动说明 |
| cover_image | varchar(500) | 封面图片URL |
| start_time | datetime | 活动开始时间 |
| end_time | datetime | 活动结束时间 |
| status | tinyint | 活动状态（0=未开始,1=进行中,2=已结束,3=已关闭,6=开奖中） |
| total_tickets | int | 总发放摸奖券数量 |
| used_tickets | int | 已使用摸奖券数量 |
| current_ticket_no | int | 当前已发券数（下一张券从这里开始） |
| max_ticket_no | int | 最大可发券数（默认1000000，券号000000~999999） |
| live_url | varchar(500) | 直播地址 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |
| deleted_at | datetime | 删除时间（软删除） |

### 5.2 奖品等级表（lottery_ticket_prize_level）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int | 主键ID |
| activity_id | int | 活动ID |
| level_rank | int | 等级排名（1-10） |
| level_name | varchar(50) | 等级名称（特等奖、一等奖等） |
| prize_amount | decimal(15,2) | 奖品金额（现金） |
| prize_count | int | 奖品数量 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

**⚠️ 重要**：`prize_type`字段已删除，只支持现金奖励。

### 5.3 VIP配置表（lottery_ticket_vip_config）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int | 主键ID |
| activity_id | int | 活动ID |
| vip_level_id | int | VIP等级ID |
| bet_amount_required | decimal(15,2) | 打码量要求 |
| ticket_count | int | 发放摸奖券数量 |
| status | tinyint | 状态（0=禁用,1=启用） |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### 5.4 奖券表（lottery_ticket）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint | 主键ID |
| activity_id | int | 活动ID |
| player_id | bigint | 玩家ID |
| department_id | int | 所属渠道部门ID |
| ticket_no | varchar(10) | 券号（6位数字，000000~999999） |
| source | varchar(20) | 来源（betting=打码,recharge=充值,manual=手动,activity=活动） |
| status | tinyint | 状态（0=未使用,1=已使用,2=已过期） |
| issued_at | datetime | 发放时间 |
| used_at | datetime | 使用时间 |
| expired_at | datetime | 过期时间 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### 5.5 中奖记录表（lottery_ticket_record）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint | 主键ID |
| activity_id | int | 活动ID |
| player_id | bigint | 玩家ID |
| department_id | int | 所属渠道部门ID |
| ticket_id | bigint | 摸奖券ID |
| ticket_no | varchar(10) | 券号 |
| prize_level | int | 中奖等级 |
| prize_type | varchar(20) | 奖品类型（cash=现金） |
| prize_name | varchar(100) | 奖品名称 |
| prize_amount | decimal(15,2) | 奖品金额 |
| status | tinyint | 状态（0=待发放,1=已发放,2=已取消） |
| claimed_at | datetime | 发放时间 |
| remark | varchar(500) | 备注 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### 5.6 打码进度表（lottery_ticket_bet_progress）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint | 主键ID |
| activity_id | int | 活动ID |
| player_id | bigint | 玩家ID |
| vip_level_id | int | VIP等级ID |
| bet_amount_required | decimal(15,2) | 单次达标所需打码量 |
| current_bet_amount | decimal(15,2) | 当前周期累计打码量 |
| progress_percent | decimal(5,2) | 当前周期进度百分比 |
| remaining_bet_amount | decimal(15,2) | 距离下次发券还需打码 |
| cycles_completed | int | 已完成周期数 |
| total_tickets_issued | int | 总共已发券数 |
| ticket_count_per_cycle | int | 每次达标发券数量 |
| status | tinyint | 状态（0=停止,1=进行中） |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

---

## 错误码

### 6.1 HTTP状态码

| 状态码 | 说明 |
|--------|------|
| 200 | 请求成功 |
| 400 | 请求参数错误 |
| 401 | 未授权（Token无效或过期） |
| 403 | 禁止访问（权限不足） |
| 404 | 资源不存在 |
| 429 | 请求过于频繁（触发限流） |
| 500 | 服务器内部错误 |

### 6.2 业务错误码

| code | msg | 说明 |
|------|-----|------|
| 200 | success | 成功 |
| 400 | 活动不存在或无权访问 | 活动ID无效或不属于该渠道 |
| 400 | 未找到打码进度记录 | 玩家未参与该活动 |
| 400 | 活动还未开奖 | 尝试查看中奖结果但活动未开奖 |
| 401 | Token无效或已过期 | 需要重新登录 |
| 429 | 请求过于频繁 | 触发限流，稍后重试 |

---

## WebSocket推送

### 7.1 推送架构

摸奖券系统使用**Redis队列 + WebSocket**的异步推送架构：

```
┌─────────────────────────────────────────────────────────┐
│                   推送架构流程                            │
└─────────────────────────────────────────────────────────┘

业务触发 → Redis队列(异步) → Push服务 → WebSocket → 客户端
   ↓
LotteryTicketPushService
   ├─ pushTicketIssued()      发券通知
   ├─ pushWinNotification()   中奖通知  
   ├─ pushActivityStatusChange() 活动状态
   ├─ pushLiveStarted()       直播开始
   └─ pushBetProgressUpdate() 打码进度
        ↓
   Redis Queue: lottery_ticket_push
        ↓
   LotteryTicketPushQueue (消费者)
        ↓
   Push服务 (gk_api:3131/3232)
        ↓
   客户端WebSocket连接
```

**关键特性：**
- ✅ 异步推送，不阻塞主业务流程
- ✅ 队列保证消息可靠传递
- ✅ 支持延迟推送（批量中奖时平滑压力）
- ✅ 大奖（≥10000元）优先推送

---

### 7.2 推送频道（Channel）

系统使用两种频道格式，**客户端无需手动订阅，连接后自动订阅**。

#### 7.2.1 单个玩家频道（私密推送）

**格式**: `player-{player_id}`

**示例**: `player-10001`

**用途**: 发券、中奖、打码进度等个人通知

**订阅方式**: 🔒 自动订阅（Push服务解析JWT Token自动加入）

---

#### 7.2.2 渠道广播频道（公开推送）

**格式**: `private-admin_group-channel-{department_id}`

**示例**: `private-admin_group-channel-100`

**用途**: 活动状态变更、直播开始等全渠道通知

**订阅方式**: 🔒 自动订阅（Push服务解析JWT Token自动加入）

---

#### 7.2.3 频道订阅机制（重要说明）

**⭐ 客户端无需手动订阅频道！**

```javascript
// ❌ 错误示例 - 不需要手动订阅
ws.send(JSON.stringify({
  action: 'subscribe',
  channel: 'player-10001'
}));

// ✅ 正确方式 - 只需连接即可
const ws = new WebSocket(`wss://api.yourdomain.com:3131?token=${jwtToken}`);

// Push服务会自动完成以下操作：
// 1. 解析JWT Token获取player_id和department_id
// 2. 自动将客户端加入 player-{player_id} 频道
// 3. 自动将客户端加入 private-admin_group-channel-{department_id} 频道
// 4. 后续所有推送消息自动路由到客户端
```

**频道与消息类型对应关系**:

| 消息类型 | 频道类型 | 频道格式 | 推送范围 |
|---------|---------|---------|---------|
| ticket_issued | 个人频道 | `player-{player_id}` | 单个玩家 |
| lottery_win | 个人频道 | `player-{player_id}` | 单个玩家 |
| bet_progress_update | 个人频道 | `player-{player_id}` | 单个玩家 |
| lottery_prize_distributed | 个人频道 | `player-{player_id}` | 单个玩家 |
| activity_status_change | 广播频道 | `private-admin_group-channel-{department_id}` | 全渠道 |
| live_started | 广播频道 | `private-admin_group-channel-{department_id}` | 全渠道 |

---

### 7.3 推送事件

#### 7.3.1 获得奖券通知

**type**: `ticket_issued`

**频道**: `player-{player_id}`（单播）

**触发时机**:
- 玩家打码达标自动发券
- 管理员手动发券

**Payload**:
```json
{
  "type": "ticket_issued",
  "title": "恭喜獲得摸獎券",
  "message": "您在活動「春节摸奖活动」中獲得了 2 張摸獎券！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "ticket_id": 101,
    "ticket_no": "000123",
    "count": 2,
    "expired_at": "2026-07-15 23:59:59"
  },
  "show_notification": true,
  "timestamp": 1718630400
}
```

**字段说明**:
- `count`: 本次发放的券数（批量发放时）
- `show_notification`: 是否显示通知（true=显示弹窗，false=静默更新）
- `timestamp`: Unix时间戳

---

#### 7.3.2 中奖通知

**type**: `lottery_win`

**频道**: `player-{player_id}`（单播）

**触发时机**:
- 管理员录入中奖券号后（立即推送）

**Payload**:
```json
{
  "type": "lottery_win",
  "title": "🎉 恭喜中獎！",
  "message": "您在活動「春节摸奖活动」中獲得 一等奖 - 5,000.00 元！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "ticket_no": "000123",
    "prize_level": "一等奖",
    "prize_type": "cash",
    "prize_amount": 5000.00,
    "record_id": 1001
  },
  "show_notification": true,
  "priority": "high",
  "timestamp": 1718640000
}
```

**字段说明**:
- `prize_level`: 奖品等级名称（特等奖、一等奖等）
- `prize_type`: 奖品类型（目前只有`cash`）
- `priority`: 优先级（`high`表示大奖，客户端应给予特殊展示）

**批量推送机制**:
- 大奖（≥10000元）立即推送
- 小奖按顺序推送，每10条延迟1秒（避免轰炸）
- 所有中奖通知按金额从大到小排序

---

#### 7.3.3 活动状态变更通知

**type**: `activity_status_change`

**频道**: `private-admin_group-channel-{department_id}`（广播）

**触发时机**:
- 活动开始（`activity_start`）
- 开始开奖（`drawing_start`）
- 活动结束（`ended`）

**Payload（activity_start）**:
```json
{
  "type": "activity_status_change",
  "title": "摸獎券活動開始",
  "message": "活動「春节摸奖活动」正式開始，快來參與打碼領券！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "status": 1,
    "event": "activity_start"
  },
  "timestamp": 1718550000
}
```

**Payload（drawing_start）**:
```json
{
  "type": "activity_status_change",
  "title": "開獎進行中",
  "message": "活動「春节摸奖活动」開獎中，快來查看中獎結果！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "status": 6,
    "event": "drawing_start"
  },
  "timestamp": 1718640000
}
```

**Payload（ended）**:
```json
{
  "type": "activity_status_change",
  "title": "活動已結束",
  "message": "活動「春节摸奖活动」已結束，感謝參與！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "status": 2,
    "event": "ended"
  },
  "timestamp": 1718650000
}
```

**event类型**:
- `activity_start`: 活动开始（到达start_time）
- `drawing_start`: 开始开奖（管理员触发）
- `ended`: 活动结束（自然结束或管理员停止开奖）

---

#### 7.3.4 直播开始通知

**type**: `live_started`

**频道**: `private-admin_group-channel-{department_id}`（广播）

**触发时机**:
- 管理员设置直播地址并启动直播

**Payload**:
```json
{
  "type": "live_started",
  "title": "直播開始",
  "message": "活動「春节摸奖活动」直播已開始，快來觀看！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "live_url": "https://live.example.com/lottery123",
    "live_status": 1
  },
  "timestamp": 1718640000
}
```

---

#### 7.3.5 打码进度更新通知（静默推送）

**type**: `bet_progress_update`

**频道**: `player-{player_id}`（单播）

**触发时机**:
- 玩家每次游戏投注后，打码进度更新

**Payload**:
```json
{
  "type": "bet_progress_update",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "progress_percent": 65.50,
    "remaining_amount": 345.00
  },
  "show_notification": false,
  "timestamp": 1718630000
}
```

**字段说明**:
- `show_notification`: `false`（静默推送，不弹窗，用于实时更新UI进度条）
- `progress_percent`: 当前周期进度百分比
- `remaining_amount`: 距离下次发券还需打码金额

---

#### 7.3.6 奖金发放通知

**type**: `lottery_prize_distributed`

**频道**: `player-{player_id}`（单播）

**触发时机**:
- 管理员手动发放奖金后

**Payload**:
```json
{
  "type": "lottery_prize_distributed",
  "title": "獎金已到賬",
  "message": "您的一等獎獎金 5,000.00 元已發放到賬！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "ticket_no": "000123",
    "prize_level": "一等奖",
    "prize_amount": 5000.00,
    "message": "恭喜中獎！"
  },
  "show_notification": true,
  "timestamp": 1718650000
}
```

---

### 7.4 WebSocket连接

#### 7.4.1 连接信息

**WebSocket地址**: `wss://your-domain.com:3131`

**连接方式**: 查询参数传递Token

```
wss://your-domain.com:3131?token={jwt_token}
```

**连接参数**:
- `token`: JWT认证令牌（登录后获得）

#### 7.4.2 订阅频道

连接成功后，客户端会自动订阅以下频道：
1. `player-{player_id}` - 个人频道（自动订阅）
2. `private-admin_group-channel-{department_id}` - 渠道频道（自动订阅）

**无需手动订阅**，Push服务会根据Token自动识别玩家ID和渠道ID。

---

#### 7.4.3 消息格式

所有推送消息都遵循统一格式：

```json
{
  "type": "消息类型",
  "title": "通知标题（可选）",
  "message": "通知内容（可选）",
  "data": {
    // 具体数据
  },
  "show_notification": true/false,
  "priority": "high/normal（可选）",
  "timestamp": 1718630400
}
```

---

#### 7.4.4 完整示例代码

```javascript
// 1. 创建WebSocket连接
const jwtToken = localStorage.getItem('jwt_token');
const ws = new WebSocket(`wss://your-domain.com:3131?token=${jwtToken}`);

// 2. 连接成功
ws.onopen = function() {
  console.log('WebSocket已连接');
  console.log('已自动订阅个人频道和渠道频道');
};

// 3. 接收消息
ws.onmessage = function(event) {
  const message = JSON.parse(event.data);
  
  console.log('收到推送:', message);
  
  // 根据消息类型处理
  switch(message.type) {
    case 'ticket_issued':
      // 发券通知
      if (message.show_notification) {
        showNotification(message.title, message.message);
        playSound('ticket_received.mp3');
      }
      // 更新我的奖券列表
      refreshMyTickets();
      break;
      
    case 'lottery_win':
      // 中奖通知 - 重点展示
      if (message.priority === 'high') {
        // 大奖特效
        showBigWinAnimation(message.data);
      } else {
        showWinAnimation(message.data);
      }
      showNotification(message.title, message.message);
      playSound('win.mp3');
      // 更新中奖记录
      refreshWinningRecords();
      break;
      
    case 'activity_status_change':
      // 活动状态变更
      console.log('活动状态变更:', message.data.event);
      
      if (message.data.event === 'activity_start') {
        // 活动开始
        showNotification(message.title, message.message);
        // 刷新活动信息
        refreshCurrentActivity();
      } else if (message.data.event === 'drawing_start') {
        // 开始开奖
        showNotification(message.title, message.message);
        // 跳转到直播页面或开奖页面
        navigateToLiveDrawing(message.data.activity_id);
      } else if (message.data.event === 'ended') {
        // 活动结束
        showNotification(message.title, message.message);
        refreshCurrentActivity();
      }
      break;
      
    case 'live_started':
      // 直播开始
      showNotification(message.title, message.message);
      // 提示用户观看直播
      showLiveUrlDialog(message.data.live_url);
      break;
      
    case 'bet_progress_update':
      // 打码进度更新（静默）
      updateBetProgressBar(
        message.data.progress_percent,
        message.data.remaining_amount
      );
      break;
      
    case 'lottery_prize_distributed':
      // 奖金发放通知
      showNotification(message.title, message.message);
      playSound('money_received.mp3');
      // 更新余额
      refreshBalance();
      // 更新中奖记录状态
      refreshWinningRecords();
      break;
      
    default:
      console.warn('未知消息类型:', message.type);
  }
};

// 4. 连接关闭 - 自动重连
ws.onclose = function(event) {
  console.log('WebSocket已断开:', event.code, event.reason);
  console.log('3秒后重连...');
  setTimeout(() => connectWebSocket(), 3000);
};

// 5. 连接错误
ws.onerror = function(error) {
  console.error('WebSocket错误:', error);
};

// 6. 心跳保持（可选，防止连接超时）
setInterval(() => {
  if (ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify({ type: 'ping' }));
  }
}, 30000); // 每30秒发送一次心跳

// UI更新函数示例
function updateBetProgressBar(percent, remaining) {
  document.getElementById('progress-bar').style.width = percent + '%';
  document.getElementById('progress-text').textContent = 
    `進度：${percent.toFixed(2)}% | 還需打碼：${remaining.toFixed(2)} 元`;
}

function showBigWinAnimation(data) {
  // 大奖动画效果
  const modal = document.getElementById('big-win-modal');
  modal.querySelector('.prize-name').textContent = data.prize_level;
  modal.querySelector('.prize-amount').textContent = 
    `¥${data.prize_amount.toLocaleString()}`;
  modal.classList.add('show');
  // 播放特效和音效
  playConfetti();
  playSound('big_win.mp3');
}
```

---

### 7.5 推送频率与性能

**发券通知**:
- 实时推送（0秒延迟）
- 批量发券时合并为一条消息

**中奖通知**:
- 大奖（≥10000元）: 立即推送
- 小奖: 每10条延迟1秒，平滑推送压力

**打码进度**:
- 每次投注后更新
- 静默推送，不显示通知

**活动状态**:
- 广播给整个渠道
- 实时推送

**推送可靠性**:
- 使用Redis队列保证
- 消息丢失时会重试（队列机制）
- 客户端断线重连后，新消息会继续推送（旧消息不补发）

---

### 7.6 错误处理最佳实践

#### 7.6.1 连接失败处理

```javascript
class LotteryWebSocket {
  constructor(token, options = {}) {
    this.token = token;
    this.url = options.url || 'wss://api.yourdomain.com:3131';
    this.reconnectDelay = options.reconnectDelay || 3000;
    this.maxReconnectAttempts = options.maxReconnectAttempts || 10;
    this.reconnectAttempts = 0;
    this.ws = null;
    this.handlers = {};
  }

  connect() {
    try {
      this.ws = new WebSocket(`${this.url}?token=${this.token}`);
      
      this.ws.onopen = () => {
        console.log('✅ WebSocket已连接');
        this.reconnectAttempts = 0; // 重置重连计数
        this.onConnectionSuccess();
      };

      this.ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data);
          this.handleMessage(message);
        } catch (error) {
          console.error('❌ 消息解析失败:', error, event.data);
        }
      };

      this.ws.onclose = (event) => {
        console.log('⚠️ WebSocket已断开:', event.code, event.reason);
        this.handleDisconnect();
      };

      this.ws.onerror = (error) => {
        console.error('❌ WebSocket错误:', error);
      };

    } catch (error) {
      console.error('❌ WebSocket连接失败:', error);
      this.handleDisconnect();
    }
  }

  handleDisconnect() {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      console.log(`🔄 ${this.reconnectDelay/1000}秒后尝试第${this.reconnectAttempts}次重连...`);
      setTimeout(() => this.connect(), this.reconnectDelay);
    } else {
      console.error('❌ 达到最大重连次数，请检查网络或刷新页面');
      this.onMaxReconnectFailed();
    }
  }

  handleMessage(message) {
    const handler = this.handlers[message.type];
    if (handler) {
      handler(message);
    } else {
      console.warn('⚠️ 未知消息类型:', message.type);
    }
  }

  on(type, handler) {
    this.handlers[type] = handler;
  }

  onConnectionSuccess() {
    // 连接成功回调
    if (this.handlers['connection_success']) {
      this.handlers['connection_success']();
    }
  }

  onMaxReconnectFailed() {
    // 重连失败回调
    if (this.handlers['max_reconnect_failed']) {
      this.handlers['max_reconnect_failed']();
    }
  }

  close() {
    if (this.ws) {
      this.ws.close();
    }
  }
}

// 使用示例
const lotteryWS = new LotteryWebSocket(jwtToken, {
  reconnectDelay: 3000,
  maxReconnectAttempts: 10
});

// 注册消息处理器
lotteryWS.on('ticket_issued', (message) => {
  console.log('🎫 收到发券通知:', message);
  showNotification(message.title, message.message);
  refreshMyTickets();
});

lotteryWS.on('lottery_win', (message) => {
  console.log('🎉 中奖了！', message);
  showBigWinAnimation(message.data);
});

lotteryWS.on('connection_success', () => {
  console.log('✅ WebSocket连接成功，已自动订阅频道');
  // 刷新数据
  refreshCurrentActivity();
});

lotteryWS.on('max_reconnect_failed', () => {
  // 显示错误提示，引导用户刷新页面
  showErrorDialog('连接已断开，请刷新页面重试');
});

// 连接
lotteryWS.connect();
```

---

#### 7.6.2 Token过期处理

```javascript
lotteryWS.ws.onclose = (event) => {
  // WebSocket关闭码
  if (event.code === 1008) {
    // Token无效或过期
    console.error('❌ Token已过期，请重新登录');
    
    // 跳转到登录页
    localStorage.removeItem('jwt_token');
    window.location.href = '/login';
  } else {
    // 其他原因，尝试重连
    handleDisconnect();
  }
};
```

---

#### 7.6.3 消息去重处理

```javascript
class MessageDeduplicator {
  constructor(ttl = 60000) { // 60秒TTL
    this.receivedMessages = new Map();
    this.ttl = ttl;
  }

  isDuplicate(message) {
    const key = this.getMessageKey(message);
    
    if (this.receivedMessages.has(key)) {
      console.warn('⚠️ 重复消息，已忽略:', key);
      return true;
    }

    // 记录消息
    this.receivedMessages.set(key, Date.now());

    // 清理过期消息
    this.cleanup();

    return false;
  }

  getMessageKey(message) {
    // 根据消息类型生成唯一key
    switch (message.type) {
      case 'ticket_issued':
        return `ticket-${message.data.ticket_id}`;
      case 'lottery_win':
        return `win-${message.data.record_id}`;
      case 'bet_progress_update':
        return `progress-${message.data.activity_id}-${message.timestamp}`;
      default:
        return `${message.type}-${message.timestamp}`;
    }
  }

  cleanup() {
    const now = Date.now();
    for (const [key, timestamp] of this.receivedMessages.entries()) {
      if (now - timestamp > this.ttl) {
        this.receivedMessages.delete(key);
      }
    }
  }
}

// 使用
const deduplicator = new MessageDeduplicator();

lotteryWS.ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  
  if (deduplicator.isDuplicate(message)) {
    return; // 忽略重复消息
  }
  
  handleMessage(message);
};
```

---

### 7.7 性能优化建议

#### 7.7.1 API请求优化

```javascript
// ❌ 错误示例 - 频繁轮询
setInterval(() => {
  fetch('/api/v1/lottery-ticket/my-tickets', {
    method: 'POST',
    headers: { 'Authorization': 'Bearer ' + token }
  });
}, 5000); // 每5秒请求一次

// ✅ 正确方式 - 使用WebSocket + 按需请求
lotteryWS.on('ticket_issued', () => {
  // 收到发券通知后才请求
  fetchMyTickets();
});

// 页面首次加载时请求一次
fetchMyTickets();
```

---

#### 7.7.2 本地缓存策略

```javascript
class LotteryCache {
  constructor() {
    this.cache = {
      currentActivity: null,
      myTickets: null,
      winningRecords: null,
      betProgress: null
    };
    this.cacheTTL = {
      currentActivity: 60000,    // 1分钟
      myTickets: 30000,         // 30秒
      winningRecords: 300000,   // 5分钟
      betProgress: 10000        // 10秒
    };
    this.cacheTimestamps = {};
  }

  get(key) {
    const timestamp = this.cacheTimestamps[key];
    const ttl = this.cacheTTL[key];

    if (timestamp && Date.now() - timestamp < ttl) {
      console.log('📦 使用缓存:', key);
      return this.cache[key];
    }

    return null;
  }

  set(key, value) {
    this.cache[key] = value;
    this.cacheTimestamps[key] = Date.now();
  }

  invalidate(key) {
    delete this.cache[key];
    delete this.cacheTimestamps[key];
  }

  invalidateAll() {
    this.cache = {};
    this.cacheTimestamps = {};
  }
}

// 使用
const lotteryCache = new LotteryCache();

async function getCurrentActivity() {
  // 尝试从缓存获取
  const cached = lotteryCache.get('currentActivity');
  if (cached) {
    return cached;
  }

  // 缓存未命中，请求API
  const response = await fetch('/api/v1/lottery-ticket/current-activity', {
    method: 'POST',
    headers: { 'Authorization': 'Bearer ' + token }
  });
  const data = await response.json();

  // 缓存结果
  lotteryCache.set('currentActivity', data);

  return data;
}

// WebSocket推送时清除缓存
lotteryWS.on('activity_status_change', () => {
  lotteryCache.invalidate('currentActivity');
  getCurrentActivity(); // 重新获取
});

lotteryWS.on('ticket_issued', () => {
  lotteryCache.invalidate('myTickets');
  lotteryCache.invalidate('betProgress');
});
```

---

#### 7.7.3 防抖与节流

```javascript
// 防抖函数（适用于实时输入搜索）
function debounce(func, wait) {
  let timeout;
  return function(...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

// 节流函数（适用于滚动加载）
function throttle(func, wait) {
  let lastTime = 0;
  return function(...args) {
    const now = Date.now();
    if (now - lastTime >= wait) {
      lastTime = now;
      func.apply(this, args);
    }
  };
}

// 使用示例：打码进度更新节流
lotteryWS.on('bet_progress_update', throttle((message) => {
  updateBetProgressBar(
    message.data.progress_percent,
    message.data.remaining_amount
  );
}, 1000)); // 最多每秒更新一次
```

---

### 7.8 安全注意事项

#### 7.8.1 Token安全存储

```javascript
// ❌ 不安全 - 存储在localStorage容易被XSS攻击
localStorage.setItem('jwt_token', token);

// ✅ 推荐方式1 - 使用HttpOnly Cookie（后端设置）
// 前端无法通过JavaScript访问，防止XSS

// ✅ 推荐方式2 - 内存存储 + 自动刷新
class SecureTokenStorage {
  constructor() {
    this.token = null;
    this.refreshInterval = null;
  }

  setToken(token, expiresIn) {
    this.token = token;
    
    // 在Token过期前自动刷新
    const refreshTime = (expiresIn - 60) * 1000; // 提前1分钟刷新
    this.refreshInterval = setTimeout(() => {
      this.refreshToken();
    }, refreshTime);
  }

  getToken() {
    return this.token;
  }

  async refreshToken() {
    try {
      const response = await fetch('/api/v1/auth/refresh', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + this.token }
      });
      const data = await response.json();
      this.setToken(data.data.token, data.data.expires_in);
    } catch (error) {
      console.error('Token刷新失败:', error);
      // 跳转登录页
      window.location.href = '/login';
    }
  }

  clear() {
    this.token = null;
    if (this.refreshInterval) {
      clearTimeout(this.refreshInterval);
    }
  }
}
```

---

#### 7.8.2 消息验证

```javascript
function validateMessage(message) {
  // 验证必需字段
  if (!message.type) {
    console.error('❌ 消息缺少type字段:', message);
    return false;
  }

  // 验证消息类型
  const validTypes = [
    'ticket_issued',
    'lottery_win',
    'activity_status_change',
    'live_started',
    'bet_progress_update',
    'lottery_prize_distributed'
  ];

  if (!validTypes.includes(message.type)) {
    console.warn('⚠️ 未知消息类型:', message.type);
    return false;
  }

  // 验证data字段
  if (!message.data || typeof message.data !== 'object') {
    console.error('❌ 消息data字段无效:', message);
    return false;
  }

  return true;
}

lotteryWS.ws.onmessage = (event) => {
  try {
    const message = JSON.parse(event.data);
    
    if (!validateMessage(message)) {
      return; // 忽略无效消息
    }
    
    handleMessage(message);
  } catch (error) {
    console.error('❌ 消息处理失败:', error);
  }
};
```

---

#### 7.8.3 防止XSS攻击

```javascript
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

// 显示通知时转义用户输入
lotteryWS.on('lottery_win', (message) => {
  const safePrizeName = escapeHtml(message.data.prize_level);
  const safeActivityName = escapeHtml(message.data.activity_name);
  
  showNotification(
    message.title,
    `恭喜您在活动「${safeActivityName}」中获得 ${safePrizeName}！`
  );
});
```

---

### 7.9 调试技巧

#### 7.9.1 WebSocket调试控制台

```javascript
class WebSocketDebugger {
  constructor(ws) {
    this.ws = ws;
    this.messageLog = [];
    this.maxLogSize = 100;
    
    // 拦截所有消息
    const originalOnMessage = ws.onmessage;
    ws.onmessage = (event) => {
      this.logMessage('RECEIVE', event.data);
      if (originalOnMessage) {
        originalOnMessage(event);
      }
    };

    // 拦截发送消息
    const originalSend = ws.send.bind(ws);
    ws.send = (data) => {
      this.logMessage('SEND', data);
      originalSend(data);
    };
  }

  logMessage(direction, data) {
    const log = {
      direction,
      data: typeof data === 'string' ? data : JSON.stringify(data),
      timestamp: new Date().toISOString()
    };

    this.messageLog.push(log);

    // 限制日志大小
    if (this.messageLog.length > this.maxLogSize) {
      this.messageLog.shift();
    }

    console.log(`[${direction}] ${log.timestamp}:`, log.data);
  }

  getMessageLog() {
    return this.messageLog;
  }

  exportLog() {
    const blob = new Blob([JSON.stringify(this.messageLog, null, 2)], {
      type: 'application/json'
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `websocket-log-${Date.now()}.json`;
    a.click();
  }
}

// 使用
const debugger = new WebSocketDebugger(lotteryWS.ws);

// 导出日志
window.exportWSLog = () => debugger.exportLog();
// 在控制台输入: exportWSLog()
```

---

#### 7.9.2 性能监控

```javascript
class WebSocketPerformanceMonitor {
  constructor() {
    this.metrics = {
      messageCount: 0,
      totalLatency: 0,
      avgLatency: 0,
      minLatency: Infinity,
      maxLatency: 0
    };
  }

  recordMessage(serverTimestamp) {
    const clientTimestamp = Date.now();
    const latency = clientTimestamp - serverTimestamp * 1000;

    this.metrics.messageCount++;
    this.metrics.totalLatency += latency;
    this.metrics.avgLatency = this.metrics.totalLatency / this.metrics.messageCount;
    this.metrics.minLatency = Math.min(this.metrics.minLatency, latency);
    this.metrics.maxLatency = Math.max(this.metrics.maxLatency, latency);
  }

  getMetrics() {
    return {
      ...this.metrics,
      avgLatency: Math.round(this.metrics.avgLatency),
      minLatency: this.metrics.minLatency === Infinity ? 0 : this.metrics.minLatency,
      maxLatency: this.metrics.maxLatency
    };
  }

  reset() {
    this.metrics = {
      messageCount: 0,
      totalLatency: 0,
      avgLatency: 0,
      minLatency: Infinity,
      maxLatency: 0
    };
  }
}

// 使用
const perfMonitor = new WebSocketPerformanceMonitor();

lotteryWS.ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  
  if (message.timestamp) {
    perfMonitor.recordMessage(message.timestamp);
  }
  
  handleMessage(message);
};

// 查看性能指标
setInterval(() => {
  console.log('📊 WebSocket性能指标:', perfMonitor.getMetrics());
}, 60000); // 每分钟输出一次
```

---

## 常见问题

### 8.1 业务问题

**Q1: 玩家打码后多久能收到奖券？**

A: 实时发放。玩家每次游戏投注后，系统会自动累计打码量，一旦达到VIP等级要求的打码量，立即自动发券并通过WebSocket推送通知。

---

**Q2: 奖券有有效期吗？**

A: 有。奖券的`expired_at`字段定义了过期时间，通常为活动结束后15天。过期后的券无法参与开奖。

---

**Q3: 一个玩家能获得多少张奖券？**

A: 没有上限。只要玩家持续打码，每达标一次就发放相应数量的奖券。例如VIP2玩家，每打码800元获得2张券，理论上可以无限循环。

---

**Q4: 为什么活动"进行中"不能开奖？**

A: 业务规则要求只有活动自然结束（到达`end_time`并自动转为`STATUS_ENDED`状态）后才能开奖。这是为了确保所有玩家公平参与，避免提前开奖。

---

**Q5: 开奖是自动的吗？**

A: ❌ 不是。本系统采用**线下物理摇球**开奖方式：
1. 管理员点击"开始开奖"，活动进入`STATUS_DRAWING`状态
2. 管理员在线下使用物理摇球机摇号（放入1000000个球）
3. 摇出的中奖球号由管理员手动录入系统
4. 系统根据券号查找持有者并创建中奖记录
5. 管理员手动发放奖金

---

### 8.2 技术问题

**Q1: 为什么`max_ticket_no`显示1000000？**

A: 这是抽奖时放球的最大号码。系统支持券号从`000000`到`999999`，共100万个号码。`max_ticket_no`字段值为1000000表示"最大可发券数"。

`current_ticket_no`才是当前已发放的券号进度。

---

**Q2: API返回429错误怎么办？**

A: 触发了限流（Rate Limiter）。大部分接口限制为10次/分钟。请在客户端实现：
- 请求失败重试机制（指数退避）
- 本地缓存机制（减少重复请求）
- 请求队列（控制并发）

---

**Q3: WebSocket连接断开怎么办？**

A: 实现断线重连机制：
```javascript
function connectWebSocket() {
  const ws = new WebSocket('wss://api.yourdomain.com:3131?token=' + token);
  
  ws.onclose = function() {
    console.log('WebSocket断开，3秒后重连...');
    setTimeout(connectWebSocket, 3000);
  };
  
  ws.onerror = function(error) {
    console.error('WebSocket错误:', error);
  };
  
  return ws;
}
```

---

**Q4: 如何处理并发发券？**

A: 系统已实现：
- 数据库行锁（`lockForUpdate()`）防止重复发券
- Redis分布式锁确保券号唯一性
- 事务保证原子性操作

客户端无需特殊处理，但建议：
- 监听WebSocket推送更新UI
- 不要频繁调用`/my-tickets`接口

---

**Q5: 如何测试WebSocket推送？**

A: 使用以下工具：
1. **在线工具**: websocket.org、websocketking.com
2. **浏览器控制台**:
   ```javascript
   const ws = new WebSocket('wss://api.yourdomain.com:3131?token=YOUR_TOKEN');
   ws.onmessage = (e) => console.log(JSON.parse(e.data));
   ```
3. **Postman**: 新版本支持WebSocket测试

---

### 8.3 数据一致性

**Q1: 如何确保中奖记录不重复？**

A: 系统在录入中奖时会检查：
- 同一`ticket_id`只能创建一条中奖记录
- 使用数据库唯一索引约束
- 事务回滚机制

---

**Q2: 打码进度如何更新？**

A: 
1. 玩家每次游戏投注后，由`gk_work`项目的`LotteryBetProgressScanTask`定时任务扫描
2. 任务每分钟执行一次，读取玩家游戏记录
3. 累计打码量，判断是否达标
4. 达标后自动发券并更新进度
5. 通过WebSocket推送通知玩家

---

**Q3: 如何防止券号冲突？**

A: 
1. 使用`current_ticket_no`字段作为自增计数器
2. 发券时使用行锁：`lockForUpdate()`
3. 券号生成规则：`str_pad($current_ticket_no, 6, '0', STR_PAD_LEFT)`
4. 原子性操作：发券和递增`current_ticket_no`在同一事务中

---

## 附录

### A. 版本兼容性

#### A.1 当前版本

**v2.0** (2026-06-17)

**支持的客户端环境**:
- 浏览器: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- 移动端: iOS 14+, Android 8+
- WebSocket协议: RFC 6455
- TLS版本: TLS 1.2+

**依赖要求**:
- JavaScript ES6+
- Fetch API支持
- WebSocket支持
- Promise/Async-Await支持

---

#### A.2 版本变更日志

**v2.0 (2026-06-17)**
- ✅ 简化活动状态系统（5个状态）
- ✅ 移除自动摇球功能（改为线下物理摇球）
- ✅ 新增手动开奖和停止开奖功能
- ✅ 优化WebSocket推送架构（Redis队列）
- ✅ 新增大奖优先推送机制
- ✅ 完善API文档和错误处理示例
- ⚠️ **重大变更**: 移除`ball_result`字段
- ⚠️ **重大变更**: 移除`draw_time`字段
- ⚠️ **重大变更**: 移除`preheat_start_time`字段
- ⚠️ **重大变更**: 状态值调整（STATUS_DRAWING = 6）

**v1.0 (2026-05-01)** - 初始版本
- 基础功能实现
- 自动摇球功能（已废弃）
- 7个活动状态（已简化）

---

#### A.3 向后兼容性

**不兼容变更（v1.x → v2.0）**:

1. **活动状态变更**
   ```javascript
   // ❌ v1.x - 不再支持的状态
   STATUS_PREHEATING = 3  // 已移除
   STATUS_DRAWN = 4       // 已移除
   STATUS_SETTLED = 5     // 已移除

   // ✅ v2.0 - 新的状态系统
   STATUS_NOT_STARTED = 0
   STATUS_ONGOING = 1
   STATUS_ENDED = 2
   STATUS_CLOSED = 3
   STATUS_DRAWING = 6
   ```

2. **API响应字段变更**
   ```javascript
   // ❌ v1.x - 已移除的字段
   {
     "ball_result": [...],        // 已移除
     "draw_time": "...",           // 已移除
     "preheat_start_time": "..."   // 已移除
   }

   // ✅ v2.0 - 新增字段
   {
     "live_url": "...",            // 直播地址
     "live_status": 1              // 直播状态
   }
   ```

3. **WebSocket消息格式变更**
   ```javascript
   // ❌ v1.x - 旧消息格式
   {
     "event": "lottery_activity_status_changed",
     "data": {...}
   }

   // ✅ v2.0 - 新消息格式
   {
     "type": "activity_status_change",
     "title": "...",
     "message": "...",
     "data": {...},
     "show_notification": true,
     "timestamp": 1718630400
   }
   ```

**迁移建议**:

如果您正在使用v1.x，请按以下步骤迁移到v2.0：

```javascript
// 1. 更新状态检查逻辑
// 旧代码
if (activity.status === 3) { // PREHEATING
  // ...
}

// 新代码（v2.0不支持预热状态）
if (activity.status === 1) { // ONGOING
  // ...
}

// 2. 移除ball_result相关代码
// 旧代码
if (activity.ball_result && activity.ball_result.length > 0) {
  showBallResult(activity.ball_result);
}

// 新代码（v2.0改为手动录入中奖）
// 不再有ball_result字段，改为查询中奖记录
const winningRecords = await apiRequest('/winning-records');

// 3. 更新WebSocket消息处理
// 旧代码
ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  if (message.event === 'lottery_activity_status_changed') {
    // ...
  }
};

// 新代码
ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  if (message.type === 'activity_status_change') {
    // message.data.event = 'activity_start' | 'drawing_start' | 'ended'
    // ...
  }
};
```

---

#### A.4 API版本控制

当前所有API端点均在`/api/v1/lottery-ticket/`路径下。

**未来版本兼容策略**:
- 新增字段不会影响现有客户端
- 移除字段会在新版本（v2.x, v3.x）中进行
- 重大变更会通过新版本路径（如`/api/v2/`）发布
- 旧版本API至少维护6个月

**版本协商**（可选）:
```javascript
// 客户端可在请求头中指定期望的API版本
fetch('/api/v1/lottery-ticket/current-activity', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'X-API-Version': '2.0'  // 可选
  }
});
```

---

### B. 状态转换图

```
┌─────────────────────────────────────────────────────────────┐
│                     活动状态转换图                            │
└─────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │ NOT_STARTED  │ 未开始 (0)
    │              │
    └──────┬───────┘
           │ 到达start_time（自动）
           ↓
    ┌──────────────┐
    │   ONGOING    │ 进行中 (1)
    │  玩家打码获券 │
    └──────┬───────┘
           │ 到达end_time（自动）
           ↓
    ┌──────────────┐
    │    ENDED     │ 已结束 (2)
    │  等待开奖    │
    └──────┬───────┘
           │ 管理员点击"开始开奖"（手动）
           ↓
    ┌──────────────┐
    │   DRAWING    │ 开奖中 (6)
    │ 线下摇球阶段 │
    └──────┬───────┘
           │ 管理员点击"停止开奖"（手动）
           ↓
    ┌──────────────┐
    │    ENDED     │ 已结束 (2)
    │  开奖完成    │
    └──────────────┘
    
    任何状态 ─────────→  CLOSED (3) 已关闭
              管理员关闭
```

### B. 券号格式说明

- **格式**: 6位数字，前导补零
- **范围**: 000000 ~ 999999
- **示例**: 
  - 第1张券: `000000`
  - 第100张券: `000099`
  - 第1000张券: `000999`
  - 第10000张券: `009999`

**重要说明**:
- 券号在活动内全局唯一
- 券号由系统自动分配，不可指定
- 券号不可修改或重复使用
- 物理摇球时使用相同的6位数字球号（000000~999999）

---

### C. 性能基准测试

#### C.1 API性能指标

基于生产环境实测数据（2026-06-17）:

| 接口 | 平均响应时间 | P95响应时间 | QPS |
|------|-------------|------------|-----|
| /current-activity | 45ms | 120ms | 1000 |
| /my-tickets | 65ms | 180ms | 800 |
| /winning-records | 55ms | 150ms | 500 |
| /bet-progress | 35ms | 90ms | 600 |

**测试环境**:
- 服务器: 8核16G, SSD存储
- 数据库: MySQL 8.0, Redis 6.2
- 网络: 100Mbps带宽
- 并发用户: 5000在线

---

#### C.2 WebSocket性能指标

| 指标 | 数值 |
|------|------|
| 单服务器连接数 | 50,000+ |
| 消息推送延迟（P50） | <100ms |
| 消息推送延迟（P95） | <300ms |
| 消息推送延迟（P99） | <500ms |
| 平均消息大小 | 500 bytes |
| 峰值推送速率 | 10,000 msg/s |

---

#### C.3 推荐的客户端配置

**移动端APP**:
```javascript
const config = {
  // WebSocket
  reconnectDelay: 3000,
  maxReconnectAttempts: 10,
  heartbeatInterval: 30000,
  
  // API请求
  requestTimeout: 10000,
  retryAttempts: 3,
  retryDelay: 1000,
  
  // 缓存
  cacheTTL: {
    currentActivity: 60000,
    myTickets: 30000,
    betProgress: 10000
  }
};
```

**Web端**:
```javascript
const config = {
  // WebSocket
  reconnectDelay: 2000,
  maxReconnectAttempts: 20,
  heartbeatInterval: 60000,
  
  // API请求
  requestTimeout: 15000,
  retryAttempts: 2,
  retryDelay: 500,
  
  // 缓存
  cacheTTL: {
    currentActivity: 120000,
    myTickets: 60000,
    betProgress: 15000
  }
};
```

---

### D. 故障排查指南

#### D.1 常见错误及解决方案

**错误1: WebSocket连接失败**

```
Error: WebSocket connection to 'wss://...' failed
```

**排查步骤**:
1. ✅ 检查URL是否正确（wss:// 而非 ws://）
2. ✅ 检查Token是否有效（未过期）
3. ✅ 检查网络防火墙是否阻止了3131端口
4. ✅ 检查浏览器控制台是否有CORS错误
5. ✅ 尝试使用在线工具测试（websocket.org）

**解决方案**:
```javascript
// 添加详细的错误日志
const ws = new WebSocket(`wss://api.yourdomain.com:3131?token=${token}`);

ws.onerror = (error) => {
  console.error('WebSocket错误详情:', {
    url: ws.url,
    readyState: ws.readyState,
    error: error
  });
  
  // 检查Token
  if (token.split('.').length !== 3) {
    console.error('❌ Token格式无效');
  }
};
```

---

**错误2: 429 Too Many Requests**

```json
{
  "code": 429,
  "msg": "请求过于频繁"
}
```

**原因**: 超过限流（10次/分钟）

**解决方案**:
```javascript
// 实现请求队列
class RequestQueue {
  constructor(maxPerMinute = 10) {
    this.queue = [];
    this.timestamps = [];
    this.maxPerMinute = maxPerMinute;
  }

  async enqueue(requestFn) {
    // 清理1分钟前的时间戳
    const oneMinuteAgo = Date.now() - 60000;
    this.timestamps = this.timestamps.filter(t => t > oneMinuteAgo);

    // 检查是否超限
    if (this.timestamps.length >= this.maxPerMinute) {
      const waitTime = 60000 - (Date.now() - this.timestamps[0]);
      console.log(`⏳ 等待 ${waitTime}ms 后重试...`);
      await new Promise(resolve => setTimeout(resolve, waitTime));
    }

    // 记录时间戳
    this.timestamps.push(Date.now());

    // 执行请求
    return await requestFn();
  }
}

// 使用
const queue = new RequestQueue(10);

async function fetchMyTickets() {
  return await queue.enqueue(() => {
    return apiRequest('/my-tickets');
  });
}
```

---

**错误3: 401 Unauthorized**

```json
{
  "code": 401,
  "msg": "Token无效或已过期"
}
```

**解决方案**:
```javascript
// 自动刷新Token
async function apiRequest(endpoint, data = {}) {
  let response = await fetch(`${API_BASE}${endpoint}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(data)
  });

  const result = await response.json();

  // Token过期，自动刷新
  if (result.code === 401) {
    console.log('🔄 Token已过期，正在刷新...');
    
    const refreshResult = await fetch('/api/v1/auth/refresh', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (refreshResult.ok) {
      const refreshData = await refreshResult.json();
      token = refreshData.data.token;
      
      // 重试原请求
      response = await fetch(`${API_BASE}${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(data)
      });

      return await response.json();
    } else {
      // 刷新失败，跳转登录
      window.location.href = '/login';
    }
  }

  return result;
}
```

---

**错误4: 消息推送延迟或丢失**

**排查步骤**:
1. ✅ 检查WebSocket连接状态（ws.readyState === WebSocket.OPEN）
2. ✅ 检查网络质量（延迟、丢包）
3. ✅ 检查服务器负载
4. ✅ 启用性能监控（见7.9.2）

**解决方案**:
```javascript
// 心跳检测
let lastHeartbeat = Date.now();

setInterval(() => {
  if (ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify({ type: 'ping' }));
    
    // 检查心跳超时
    if (Date.now() - lastHeartbeat > 60000) {
      console.warn('⚠️ 心跳超时，重连WebSocket...');
      ws.close();
      connectWebSocket();
    }
  }
}, 30000);

ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  
  if (message.type === 'pong') {
    lastHeartbeat = Date.now();
    return;
  }
  
  handleMessage(message);
};
```

---

#### D.2 调试检查清单

**API调试**:
- [ ] ✅ Token格式正确（3段，用`.`分隔）
- [ ] ✅ Token未过期（检查exp字段）
- [ ] ✅ 请求Content-Type为application/json
- [ ] ✅ 请求体为有效JSON
- [ ] ✅ 网络请求未被浏览器插件拦截
- [ ] ✅ CORS配置正确
- [ ] ✅ 未触发限流（查看响应头`X-RateLimit-*`）

**WebSocket调试**:
- [ ] ✅ URL协议为wss://（不是ws://）
- [ ] ✅ Token参数正确拼接
- [ ] ✅ 防火墙未阻止3131端口
- [ ] ✅ 连接状态为OPEN（readyState === 1）
- [ ] ✅ 消息格式为有效JSON
- [ ] ✅ 消息处理器已注册
- [ ] ✅ 断线重连机制已实现

---

### E. 联系与支持

#### E.1 技术支持

**邮箱**: support@yourdomain.com

**工作时间**: 周一至周五 9:00-18:00 (UTC+8)

**响应时间**:
- 🔴 紧急问题（服务中断）: 1小时内
- 🟡 重要问题（功能异常）: 4小时内
- 🟢 一般问题（咨询）: 24小时内

---

#### E.2 问题反馈模板

提交问题时，请提供以下信息：

**必需信息**:
1. 问题描述（详细说明）
2. 重现步骤（如何触发问题）
3. 预期结果 vs 实际结果
4. 环境信息（浏览器、设备、网络）
5. 错误日志或截图

**可选信息**:
- WebSocket日志（使用7.9.1导出）
- 网络请求记录（HAR文件）
- 性能监控数据（使用7.9.2）

---

#### E.3 在线资源

**文档中心**: https://docs.yourdomain.com

**更新日志**: https://docs.yourdomain.com/changelog

**开发者社区**: https://community.yourdomain.com

**API状态页**: https://status.yourdomain.com

---

#### E.4 服务等级协议（SLA）

**API可用性**: 99.9%

**WebSocket可用性**: 99.5%

**承诺的性能指标**:
- API响应时间P95 < 500ms
- WebSocket推送延迟P95 < 1000ms
- 并发连接数 > 10,000

**维护窗口**:
- 时间: 每周日 02:00-04:00 (UTC+8)
- 提前通知: 提前3天邮件通知
- 影响: API和WebSocket可能短暂不可用

---

## 文档结尾

感谢使用摸奖券系统API！

如有任何问题或建议，请随时联系我们的技术支持团队。

---

**文档版本**: v2.0  
**最后更新**: 2026-06-17  
**维护者**: YJB Gaming Platform 开发团队

**重要提示**: 本文档为敏感业务文档，请勿传播给未授权人员。
