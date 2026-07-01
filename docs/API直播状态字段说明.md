# 📺 摸奖券API - 直播状态字段说明

## 修改日期
2026-06-17

---

## 新增字段

### API接口
`POST /api/v1/lottery-ticket/current-activity`

### 新增返回字段

```json
{
  "activity": {
    "live_url": "mojiangjuan",
    "live_status": 1,           // ⭐ 新增：直播状态
    "live_status_text": "直播中"  // ⭐ 新增：直播状态文字
  }
}
```

---

## 直播状态枚举

### 状态值定义

| 状态值 | 常量名 | 状态名称 | 说明 |
|--------|--------|---------|------|
| 0 | LIVE_STATUS_NOT_STARTED | 未开播 | 直播尚未开始 |
| 1 | LIVE_STATUS_ONGOING | 直播中 | 正在直播 |
| 2 | LIVE_STATUS_ENDED | 已结束 | 直播已结束 |

---

## 字段用途

### live_status（直播状态值）

**类型**: `int`

**用途**: 
- 控制直播按钮的显示/隐藏
- 控制直播按钮的样式（颜色、动画）
- 控制直播按钮的可点击状态

**客户端判断逻辑**:
```javascript
switch(activity.live_status) {
  case 0: // 未开播
    // 隐藏直播按钮
    break;
  case 1: // 直播中
    // 显示"直播中"按钮（红色、闪烁动画）
    // 可点击进入直播
    break;
  case 2: // 已结束
    // 显示"已结束"按钮（灰色）
    // 禁用点击
    break;
}
```

---

### live_status_text（直播状态文字）

**类型**: `string`

**值范围**: 
- `"未开播"` - 直播未开始
- `"直播中"` - 正在直播
- `"已结束"` - 直播已结束

**用途**:
- 直接显示在直播按钮文字上
- 显示在直播状态提示中

**多语言支持**:
```javascript
// API返回的是繁体中文
live_status_text: "直播中"

// 客户端可以自己映射为其他语言
const liveStatusMap = {
  '未开播': 'Not Started',
  '直播中': 'Live',
  '已结束': 'Ended'
};
```

---

## 使用场景

### 场景1: 活动未开播

```json
{
  "live_url": null,
  "live_status": 0,
  "live_status_text": "未开播"
}
```

**前端处理**:
```javascript
if (!activity.live_url || activity.live_status === 0) {
  // 不显示直播按钮
  document.getElementById('live-btn').style.display = 'none';
}
```

---

### 场景2: 活动直播中

```json
{
  "live_url": "mojiangjuan",
  "live_status": 1,
  "live_status_text": "直播中"
}
```

**前端处理**:
```javascript
if (activity.live_url && activity.live_status === 1) {
  const liveBtn = document.getElementById('live-btn');
  
  // 显示直播按钮（红色、闪烁）
  liveBtn.style.display = 'block';
  liveBtn.className = 'btn-live-ongoing';
  
  // 添加闪烁动画
  liveBtn.innerHTML = `
    <span class="live-dot"></span>
    <span>${activity.live_status_text}</span>
  `;
  
  // 点击进入直播
  liveBtn.onclick = () => openLive(activity.live_url);
}
```

**CSS样式**:
```css
.btn-live-ongoing {
  background: #ff4444;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 20px;
  cursor: pointer;
  position: relative;
}

.live-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: white;
  border-radius: 50%;
  margin-right: 5px;
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.2); }
}
```

---

### 场景3: 活动直播已结束

```json
{
  "live_url": "mojiangjuan",
  "live_status": 2,
  "live_status_text": "已结束"
}
```

**前端处理**:
```javascript
if (activity.live_url && activity.live_status === 2) {
  const liveBtn = document.getElementById('live-btn');
  
  // 显示灰色按钮，禁用点击
  liveBtn.style.display = 'block';
  liveBtn.className = 'btn-live-ended';
  liveBtn.innerHTML = activity.live_status_text;
  liveBtn.disabled = true;
}
```

**CSS样式**:
```css
.btn-live-ended {
  background: #cccccc;
  color: #666666;
  border: none;
  padding: 10px 20px;
  border-radius: 20px;
  cursor: not-allowed;
}
```

---

## 与活动状态的关系

### 活动状态 vs 直播状态

| 活动状态 | 活动状态值 | 常见的直播状态 | 说明 |
|---------|-----------|--------------|------|
| 未开始 | 0 | 0（未开播） | 活动还没开始 |
| 进行中 | 1 | 0（未开播） | 打码阶段，无直播 |
| 已结束 | 2 | 0/2（未开播/已结束） | 等待开奖或已完成 |
| 开奖中 | 6 | 1（直播中） | **最常见：开奖时直播** |
| 已关闭 | 3 | 2（已结束） | 活动关闭 |

**关键规则**:
- 活动状态 ≠ 直播状态
- 开奖中（status=6）时，通常直播中（live_status=1）
- 但管理员可以手动控制直播状态

**判断是否显示直播**:
```javascript
// ❌ 错误：只判断活动状态
if (activity.status === 6) {
  showLiveButton(); // 可能直播已结束
}

// ✅ 正确：同时判断直播状态
if (activity.live_url && activity.live_status === 1) {
  showLiveButton(); // 确保正在直播
}
```

---

## 完整前端实现示例

### HTML结构

```html
<div class="activity-header">
  <h2 id="activity-name">春节摸奖活动</h2>
  
  <!-- 直播按钮 -->
  <button id="live-btn" style="display: none;">
    直播中
  </button>
</div>

<!-- 直播播放器（Modal） -->
<div id="live-player-modal" class="modal" style="display: none;">
  <div class="modal-content">
    <span class="close">&times;</span>
    <iframe id="live-player-frame" width="100%" height="500px"></iframe>
  </div>
</div>
```

---

### JavaScript实现

```javascript
// 加载活动数据
async function loadActivity() {
  const response = await fetch('/api/v1/lottery-ticket/current-activity', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + jwtToken,
      'Content-Type': 'application/json'
    }
  });

  const result = await response.json();
  const activity = result.data.activity;

  // 显示活动名称
  document.getElementById('activity-name').textContent = activity.name;

  // 处理直播按钮
  renderLiveButton(activity);
}

// 渲染直播按钮
function renderLiveButton(activity) {
  const liveBtn = document.getElementById('live-btn');

  // 没有直播地址 - 隐藏按钮
  if (!activity.live_url) {
    liveBtn.style.display = 'none';
    return;
  }

  // 根据直播状态显示
  switch(activity.live_status) {
    case 0: // 未开播 - 隐藏按钮
      liveBtn.style.display = 'none';
      break;

    case 1: // 直播中 - 显示可点击按钮
      liveBtn.style.display = 'inline-block';
      liveBtn.className = 'btn-live btn-live-ongoing';
      liveBtn.disabled = false;
      liveBtn.innerHTML = `
        <span class="live-dot"></span>
        <span>${activity.live_status_text}</span>
      `;
      
      liveBtn.onclick = () => openLive(activity.live_url);
      break;

    case 2: // 已结束 - 显示禁用按钮
      liveBtn.style.display = 'inline-block';
      liveBtn.className = 'btn-live btn-live-ended';
      liveBtn.disabled = true;
      liveBtn.innerHTML = `<span>${activity.live_status_text}</span>`;
      
      liveBtn.onclick = null;
      break;

    default:
      liveBtn.style.display = 'none';
  }
}

// 打开直播
async function openLive(streamName) {
  try {
    // 获取播放地址
    const response = await fetch('/api/get-live-player-config', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + jwtToken,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ stream_name: streamName })
    });

    const result = await response.json();
    
    if (result.code === 0) {
      // 显示播放器
      const modal = document.getElementById('live-player-modal');
      const iframe = document.getElementById('live-player-frame');
      
      iframe.src = `/lottery-live-player.html?url=${encodeURIComponent(result.data.play_url)}`;
      modal.style.display = 'block';
    } else {
      alert('获取直播地址失败：' + result.msg);
    }
  } catch (error) {
    console.error('打开直播失败:', error);
    alert('打开直播失败，请稍后重试');
  }
}

// 关闭播放器
document.querySelector('.close').onclick = function() {
  const modal = document.getElementById('live-player-modal');
  const iframe = document.getElementById('live-player-frame');
  
  iframe.src = ''; // 停止播放
  modal.style.display = 'none';
};
```

---

### CSS样式

```css
/* 直播按钮基础样式 */
.btn-live {
  border: none;
  padding: 10px 20px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

/* 直播中样式 */
.btn-live-ongoing {
  background: linear-gradient(135deg, #ff4444, #ff6666);
  color: white;
  box-shadow: 0 2px 8px rgba(255, 68, 68, 0.4);
}

.btn-live-ongoing:hover {
  background: linear-gradient(135deg, #ff6666, #ff8888);
  box-shadow: 0 4px 12px rgba(255, 68, 68, 0.6);
  transform: translateY(-2px);
}

/* 直播已结束样式 */
.btn-live-ended {
  background: #e0e0e0;
  color: #999999;
  cursor: not-allowed;
}

/* 直播中闪烁点 */
.live-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: white;
  border-radius: 50%;
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.5;
    transform: scale(1.2);
  }
}

/* 播放器Modal */
.modal {
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8);
}

.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 20px;
  border-radius: 8px;
  width: 90%;
  max-width: 1200px;
}

.close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}

.close:hover {
  color: #000;
}
```

---

## WebSocket推送直播状态变更

### live_started 事件

当管理员开始直播时，会推送此事件：

```json
{
  "type": "live_started",
  "title": "直播開始",
  "message": "活動「春节摸奖活动」直播已開始，快來觀看！",
  "data": {
    "activity_id": 1,
    "activity_name": "春节摸奖活动",
    "live_url": "mojiangjuan",
    "live_status": 1
  },
  "timestamp": 1718640000
}
```

**前端处理**:
```javascript
ws.onmessage = (event) => {
  const message = JSON.parse(event.data);
  
  if (message.type === 'live_started') {
    // 显示通知
    showNotification(message.title, message.message);
    
    // 刷新活动数据（会更新直播状态）
    loadActivity();
    
    // 或者直接更新UI
    updateLiveButton({
      live_url: message.data.live_url,
      live_status: 1,
      live_status_text: '直播中'
    });
  }
};
```

---

## 管理后台如何修改直播状态

**场景1**: 开始直播

```php
$activity->live_status = LotteryTicketActivity::LIVE_STATUS_ONGOING;
$activity->save();

// 推送通知
LotteryTicketPushService::pushLiveStarted($activity);
```

**场景2**: 结束直播

```php
$activity->live_status = LotteryTicketActivity::LIVE_STATUS_ENDED;
$activity->save();

// 可选：推送直播结束通知
```

---

## 总结

### 字段作用

| 字段 | 作用 |
|------|------|
| `live_url` | 判断是否配置了直播 |
| `live_status` | **控制直播按钮状态** |
| `live_status_text` | 显示直播状态文字 |

### 显示逻辑

```
没有live_url → 不显示按钮
有live_url + live_status=0 → 不显示按钮（未开播）
有live_url + live_status=1 → 显示红色按钮（直播中）⭐
有live_url + live_status=2 → 显示灰色按钮（已结束）
```

### 客户端收益

- ✅ 知道直播是否正在进行
- ✅ 防止用户点击已结束的直播
- ✅ 提供更好的用户体验（明确的状态提示）
- ✅ 支持直播状态的实时更新（WebSocket）

---

**文档创建日期**: 2026-06-17  
**维护者**: 后端开发团队
