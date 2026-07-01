# 📋 获取当前活动API - 响应结构说明

## 接口信息
- **接口**: `POST /lottery-ticket/current-activity`
- **文件**: `D:/gk_api/app/api/controller/v1/LotteryTicketController.php`
- **方法**: `getCurrentActivity()`

---

## ✅ 实际返回结构

### 完整响应示例

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
      "my_ticket_count": 5,
      "my_win_count": 1,
      "countdown": {
        "type": "ongoing",
        "label": "活动进行中",
        "end_time": "2026-06-30 23:59:59",
        "seconds_remaining": 1123200
      },
      "has_drawn": false,
      "live_url": "mojiangjuan",
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

---

## 📊 数据结构层级

```
data
├── has_activity (bool)
├── activity (object) ⭐ 活动基础信息
│   ├── id
│   ├── name
│   ├── description
│   ├── cover_image
│   ├── start_time
│   ├── end_time
│   ├── status
│   ├── status_text
│   ├── my_ticket_count ⭐ 我的奖券数
│   ├── my_win_count ⭐ 我的中奖数
│   ├── countdown (object)
│   │   ├── type
│   │   ├── label
│   │   ├── end_time
│   │   └── seconds_remaining
│   ├── has_drawn (bool)
│   ├── live_url
│   └── total_winners
├── prize_levels (array) ⭐ 奖品等级（独立字段，不在activity内）
│   └── [
│       ├── level_rank
│       ├── level_name
│       ├── prize_amount
│       └── prize_count
│     ]
├── vip_configs (array) ⭐ VIP配置（独立字段，不在activity内）
│   └── [
│       ├── vip_level_id
│       ├── vip_level_name
│       ├── bet_amount_required
│       └── ticket_count
│     ]
└── bet_progress (object|null) ⭐⭐ 打码进度（独立字段，核心功能）
    ├── bet_amount_required
    ├── current_bet_amount
    ├── progress_percent
    ├── remaining_bet_amount
    ├── cycles_completed
    ├── total_tickets_issued
    └── ticket_count_per_cycle
```

---

## ⚠️ 重要说明

### 1. **独立字段 vs 嵌套字段**

**❌ 错误理解（旧文档）：**
```json
{
  "activity": {
    "prize_levels": [...],  // ❌ 错误，不在activity内
    "vip_configs": [...]     // ❌ 错误，不在activity内
  }
}
```

**✅ 正确结构（实际API）：**
```json
{
  "activity": { ... },        // 活动基础信息
  "prize_levels": [...],      // ✅ 独立字段
  "vip_configs": [...],       // ✅ 独立字段
  "bet_progress": { ... }     // ✅ 独立字段
}
```

---

### 2. **bet_progress 字段特性**

**值类型：**
- `object` - 玩家有打码记录
- `null` - 玩家未参与活动或VIP等级未配置

**为什么是独立字段？**
1. `bet_progress` 是玩家个人数据，与活动配置信息分离
2. 可能为 `null`，独立字段更易于处理
3. 避免 `activity` 对象过于臃肿

---

### 3. **客户端使用建议**

#### ❌ 错误方式（从vip_configs计算）

```javascript
// ❌ 不推荐：手动从vip_configs计算
const vipConfig = data.activity.vip_configs.find(c => c.vip_level_id === player.vip_level_id);
const required = vipConfig.bet_amount_required;
const current = await fetchPlayerBetAmount(); // 需要额外API
const percent = (current / required) * 100;

// 问题：
// 1. 需要额外请求获取当前打码量
// 2. 无法获取已完成周期数
// 3. 计算逻辑复杂，容易出错
```

#### ✅ 正确方式（使用bet_progress）

```javascript
// ✅ 推荐：直接使用bet_progress
if (data.bet_progress) {
  const progress = data.bet_progress;
  
  // 所有数据都已计算好
  updateProgressBar(progress.progress_percent);
  showRemaining(progress.remaining_bet_amount);
  showAchievement(progress.cycles_completed, progress.total_tickets_issued);
} else {
  // 未参与活动
  showEmptyProgress('开始游戏即可获得摸奖券');
}
```

---

## 📝 字段详细说明

### bet_progress 对象字段

| 字段 | 类型 | 说明 | 前端用途 |
|------|------|------|---------|
| `bet_amount_required` | decimal | 单次达标所需打码量（如：1000.00） | 进度条最大值、显示目标 |
| `current_bet_amount` | decimal | 当前周期累计打码量（如：650.50） | 进度条当前值、显示已完成 |
| `progress_percent` | decimal | 当前周期进度百分比（如：65.05） | **直接用于CSS width** |
| `remaining_bet_amount` | decimal | 距离下次发券还需打码（如：349.50） | **显示"还需"提示** |
| `cycles_completed` | int | 已完成周期数（如：2） | **显示成就"已获券X次"** |
| `total_tickets_issued` | int | 总共已发券数（如：5） | **显示"已获得X张券"** |
| `ticket_count_per_cycle` | int | 每次达标发券数量（如：1） | 显示规则说明 |

---

### countdown 对象字段

| 字段 | 类型 | 说明 | 前端用途 |
|------|------|------|---------|
| `type` | string | 倒计时类型（not_started/ongoing/ended） | 控制倒计时组件显示状态 |
| `label` | string | 倒计时标签文字（即将开始/进行中/已结束） | 显示在倒计时标题 |
| `end_time` | datetime | 目标时间（活动开始/结束时间） | 倒计时目标 |
| `seconds_remaining` | int | 剩余秒数 | **倒计时计算** |

---

## 💻 前端完整使用示例

```javascript
async function loadCurrentActivity() {
  const response = await fetch('/api/v1/lottery-ticket/current-activity', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + jwtToken,
      'Content-Type': 'application/json'
    }
  });

  const result = await response.json();

  if (result.code !== 200) {
    showError(result.msg);
    return;
  }

  const data = result.data;

  if (!data.has_activity) {
    showEmptyState('当前暂无活动，敬请期待');
    return;
  }

  // ===== 1. 显示活动基础信息 =====
  const activity = data.activity;
  document.getElementById('activity-name').textContent = activity.name;
  document.getElementById('activity-desc').textContent = activity.description;
  document.getElementById('cover-image').src = activity.cover_image;
  document.getElementById('status-badge').textContent = activity.status_text;

  // ===== 2. 显示奖券统计 =====
  document.getElementById('my-tickets').textContent = activity.my_ticket_count;
  document.getElementById('my-wins').textContent = activity.my_win_count;

  // ===== 3. 显示倒计时 =====
  if (activity.countdown) {
    startCountdown(activity.countdown.seconds_remaining, activity.countdown.label);
  }

  // ===== 4. 显示奖品列表（独立字段）=====
  const prizeLevelsHtml = data.prize_levels.map(prize => `
    <div class="prize-item">
      <span class="prize-name">${prize.level_name}</span>
      <span class="prize-amount">¥${prize.prize_amount.toLocaleString()}</span>
      <span class="prize-count">×${prize.prize_count}</span>
    </div>
  `).join('');
  document.getElementById('prize-levels').innerHTML = prizeLevelsHtml;

  // ===== 5. 显示VIP规则（独立字段）=====
  const vipConfigsHtml = data.vip_configs.map(config => `
    <tr>
      <td>${config.vip_level_name}</td>
      <td>¥${config.bet_amount_required.toLocaleString()}</td>
      <td>${config.ticket_count}张</td>
    </tr>
  `).join('');
  document.getElementById('vip-rules').innerHTML = vipConfigsHtml;

  // ===== 6. 显示打码进度（独立字段，核心功能）⭐ =====
  const betProgressContainer = document.getElementById('bet-progress-container');
  
  if (data.bet_progress) {
    const progress = data.bet_progress;

    // 显示进度容器
    betProgressContainer.style.display = 'block';

    // 更新进度条
    const progressBar = document.getElementById('progress-bar');
    progressBar.style.width = progress.progress_percent + '%';
    progressBar.setAttribute('aria-valuenow', progress.progress_percent);
    progressBar.textContent = progress.progress_percent.toFixed(2) + '%';

    // 更新进度文字
    document.getElementById('current-bet').textContent = 
      `¥${progress.current_bet_amount.toLocaleString()}`;
    document.getElementById('required-bet').textContent = 
      `¥${progress.bet_amount_required.toLocaleString()}`;
    document.getElementById('remaining-bet').textContent = 
      `¥${progress.remaining_bet_amount.toLocaleString()}`;

    // 显示成就
    document.getElementById('cycles-completed').textContent = progress.cycles_completed;
    document.getElementById('total-tickets').textContent = progress.total_tickets_issued;

    // 即将获券提示
    if (progress.progress_percent >= 95) {
      showNotification(
        '🎉 即将获得摸奖券！',
        `还需打码 ¥${progress.remaining_bet_amount.toFixed(2)} 元`
      );
    }

    // 进度条颜色（根据进度）
    if (progress.progress_percent >= 90) {
      progressBar.className = 'progress-bar near-complete';
    } else if (progress.progress_percent >= 50) {
      progressBar.className = 'progress-bar half-complete';
    } else {
      progressBar.className = 'progress-bar';
    }

  } else {
    // 未参与活动，隐藏进度容器或显示空状态
    betProgressContainer.style.display = 'none';
    showEmptyProgress('开始游戏即可获得摸奖券');
  }

  // ===== 7. 显示直播按钮（如果有）=====
  if (activity.live_url && activity.status === 6) {
    showLiveButton(activity.live_url);
  }

  // ===== 8. 显示中奖人数（已开奖时）=====
  if (activity.has_drawn && activity.total_winners > 0) {
    document.getElementById('total-winners').textContent = 
      `已有 ${activity.total_winners} 人中奖`;
  }
}
```

---

## 🔍 数据访问路径对比

| 数据 | 错误路径 | 正确路径 |
|------|---------|---------|
| 活动名称 | `data.activity.name` | `data.activity.name` ✅ |
| 我的奖券 | `data.activity.my_ticket_count` | `data.activity.my_ticket_count` ✅ |
| 奖品列表 | ~~`data.activity.prize_levels`~~ | `data.prize_levels` ✅ |
| VIP规则 | ~~`data.activity.vip_configs`~~ | `data.vip_configs` ✅ |
| 打码进度 | ~~`data.activity.bet_progress`~~ | `data.bet_progress` ✅ |
| 进度百分比 | ~~需要手动计算~~ | `data.bet_progress.progress_percent` ✅ |
| 已获券数 | ~~需要调用其他API~~ | `data.bet_progress.total_tickets_issued` ✅ |

---

## ✅ 总结

### 关键要点

1. **`prize_levels`、`vip_configs`、`bet_progress` 都是独立字段**
   - 不在 `activity` 对象内部
   - 访问时使用 `data.bet_progress` 而非 `data.activity.bet_progress`

2. **`bet_progress` 是核心功能字段**
   - 包含所有打码进度相关数据
   - 避免客户端从 `vip_configs` 手动计算
   - 可能为 `null`（未参与活动时）

3. **客户端优先级**
   - ✅ 优先使用 `bet_progress` 显示进度
   - ⚠️ `vip_configs` 仅用于显示规则说明
   - ❌ 不要从 `vip_configs` 计算当前进度

### 文档更新建议

- ✅ 精简版文档已更新正确结构
- ⚠️ 完整版文档需要同步更新
- ✅ 所有示例代码已修正

---

**文档创建日期**: 2026-06-17  
**维护者**: 后端开发团队
