# 摸奖券直播功能 - 最终实施总结

## 📅 完成时间：2026-06-18

---

## 🎯 核心改进点

### **关键逻辑：只在直播中时返回播放地址**

```
live_status = 0 (未开播) → play_urls = null
live_status = 1 (直播中)  → play_urls = {...}  ✅ 生成完整播放地址
live_status = 2 (已结束) → play_urls = null
```

**原因：**
- ✅ 节省服务器资源 - 不在非直播时生成地址
- ✅ 避免客户端误用 - 明确只有直播中才能播放
- ✅ 安全考虑 - 地址只在需要时生成

---

## 📝 最终修改清单

### **gk_admin 项目（3个文件）**

| # | 文件 | 改动 | 说明 |
|---|------|------|------|
| 1 | `LotteryTicketPushService.php` | 新增+增强 | `pushLiveEnded()` + 增强 `pushLiveStarted()` |
| 2 | `ChannelLotteryTicketActivityController.php` | 增强 | `startLive()`, `endLive()`, `getLiveInfo()` |
| 3-6 | `lang/{zh-TW,zh-CN,en,jp}/lottery_ticket.php` | 补充翻译 | 3个新错误消息 |
| 7 | `lottery_ticket_activities.vue` | 新增功能 | 开始/结束直播按钮 |

### **gk_api 项目（2个文件）**

| # | 文件 | 改动 | 说明 |
|---|------|------|------|
| 1 | `support/helpers.php` | 新增函数 | `generateLotteryLiveUrls()` |
| 2 | `app/api/controller/v1/LotteryTicketController.php` | 增强 | `buildActivityResponse()` + `generatePlayUrls()` |

---

## 🔄 完整数据流

```
┌─────────────────────────────────────────────────────────────┐
│              直播完整业务流程（三个项目协同）                 │
└─────────────────────────────────────────────────────────────┘

1. 管理员操作（gk_admin）
   ↓
   设置直播地址（流名称）
   live_url = "lottery_20260618"
   live_status = 0 (未开播)
   ↓
   点击"开始直播"按钮
   ↓
   调用 startLive() API
   ↓
   更新 live_status = 1 (直播中)
   ↓
   调用 generateLotteryLiveUrls() 生成播放地址
   ↓
   推送通知 (gk_admin → gk_api Push服务)
   {
       "type": "live_started",
       "data": {
           "stream_name": "lottery_20260618",
           "play_urls": {
               "webrtc": "webrtc://...",  ✅ 完整地址
               "flv": "http://...",
               "hls": "http://..."
           },
           "live_status": 1
       }
   }

2. 客户端接收推送（gk_api）
   ↓
   收到 live_started 事件
   ↓
   提取 play_urls.webrtc
   ↓
   直接播放（无需自己生成地址）

3. 客户端轮询（备用方案）
   ↓
   调用 getCurrentActivity() API (gk_api)
   ↓
   if (live_status === 1) {
       生成 play_urls ✅
   } else {
       play_urls = null
   }
   ↓
   返回活动信息
   {
       "activity": {
           "stream_name": "lottery_20260618",
           "play_urls": {...},  // ✅ 仅在直播中时有值
           "live_status": 1
       }
   }

4. 管理员结束直播（gk_admin）
   ↓
   点击"结束直播"按钮
   ↓
   调用 endLive() API
   ↓
   更新 live_status = 2 (已结束)
   ↓
   推送通知 (gk_admin → gk_api Push服务)
   {
       "type": "live_ended",
       "data": {
           "stream_name": "lottery_20260618",
           "live_status": 2
       }
   }

5. 客户端再次轮询
   ↓
   调用 getCurrentActivity() API
   ↓
   if (live_status === 2) {
       play_urls = null  ✅ 已结束，不返回播放地址
   }
   ↓
   客户端关闭播放器
```

---

## 📊 API返回数据对比

### **未开播（live_status = 0）**

```json
{
    "activity": {
        "stream_name": "lottery_20260618",  // ⭐ 有流名称
        "play_urls": null,                  // ⭐ 无播放地址
        "live_status": 0,
        "live_status_text": "未開播"
    }
}
```

### **直播中（live_status = 1）**

```json
{
    "activity": {
        "stream_name": "lottery_20260618",  // ⭐ 流名称
        "play_urls": {                      // ⭐ 完整播放地址
            "webrtc": "webrtc://domain/live/lottery_20260618?txSecret=xxx&txTime=xxx",
            "flv": "http://domain/live/lottery_20260618.flv?txSecret=xxx&txTime=xxx",
            "hls": "http://domain/live/lottery_20260618.m3u8?txSecret=xxx&txTime=xxx",
            "expire_time": "2026-07-18 12:00:00",
            "expire_timestamp": 1721275200,
            "region": "CN"
        },
        "live_status": 1,
        "live_status_text": "直播中"
    }
}
```

### **已结束（live_status = 2）**

```json
{
    "activity": {
        "stream_name": "lottery_20260618",  // ⭐ 有流名称
        "play_urls": null,                  // ⭐ 无播放地址
        "live_status": 2,
        "live_status_text": "已結束"
    }
}
```

---

## 💻 客户端处理逻辑

```javascript
// 获取活动信息
const { activity } = await fetchCurrentActivity();

// 根据直播状态处理
switch (activity.live_status) {
    case 0:  // 未开播
        console.log('直播尚未开始');
        // play_urls = null
        showMessage('直播尚未開始，請稍後再試');
        break;

    case 1:  // 直播中
        if (activity.play_urls) {
            // ✅ 有播放地址，直接播放
            const playUrl = activity.play_urls.webrtc 
                         || activity.play_urls.flv 
                         || activity.play_urls.hls;
            player.src(playUrl);
            player.play();
        } else {
            // 降级：使用流名称自行生成
            const playUrl = generateLocalUrl(activity.stream_name);
            player.src(playUrl);
        }
        break;

    case 2:  // 已结束
        console.log('直播已结束');
        // play_urls = null
        showMessage('直播已結束，感謝觀看');
        player.pause();
        player.close();
        break;
}
```

---

## 🔍 关键代码片段

### **gk_admin - 推送通知（只在直播开始时生成地址）**

```php
// LotteryTicketPushService::pushLiveStarted()

// ✅ 生成完整的直播播放地址
$playUrls = null;
if (!empty($activity->live_url)) {
    try {
        $urls = generateLotteryLiveUrls(1, $activity->live_url, 30);
        $playUrls = [
            'webrtc' => $urls['webrtc'],
            'flv' => $urls['flv'],
            'hls' => $urls['hls'],
            'expire_time' => $urls['expire_time'],
            'region' => $urls['region'],
        ];
    } catch (\Exception $e) {
        Log::warning('生成直播播放地址失败', [/*...*/]);
    }
}

$message = [
    'type' => 'live_started',
    'data' => [
        'stream_name' => $activity->live_url,
        'play_urls' => $playUrls,  // ✅ 包含完整播放地址
        'live_status' => 1,
    ],
];
```

---

### **gk_admin - API查询（只在直播中时生成地址）**

```php
// ChannelLotteryTicketActivityController::getLiveInfo()

// ✅ 只在直播中时生成播放地址
$playUrls = null;
if (!empty($activity->live_url) 
    && $activity->live_status === LotteryTicketActivity::LIVE_STATUS_ONGOING) {
    try {
        $urls = generateLotteryLiveUrls(1, $activity->live_url, 30);
        $playUrls = [
            'webrtc' => $urls['webrtc'],
            'flv' => $urls['flv'],
            'hls' => $urls['hls'],
            'expire_time' => $urls['expire_time'],
            'expire_timestamp' => $urls['expire_timestamp'],
            'region' => $urls['region'],
        ];
    } catch (\Exception $e) {
        \support\Log::warning('获取直播信息时生成播放地址失败', [/*...*/]);
    }
}

return Response::success([
    'stream_name' => $activity->live_url,
    'play_urls' => $playUrls,  // ⭐ 仅直播中时有值
    'live_status' => $activity->live_status,
]);
```

---

### **gk_api - API查询（只在直播中时生成地址）**

```php
// LotteryTicketController::generatePlayUrls()

private function generatePlayUrls(?string $streamName, int $liveStatus): ?array
{
    // ✅ 只在直播中（live_status = 1）时才返回播放地址
    if ($liveStatus !== LotteryTicketActivity::LIVE_STATUS_ONGOING) {
        return null;
    }

    if (empty($streamName)) {
        return null;
    }

    try {
        $urls = generateLotteryLiveUrls(1, $streamName, 30);
        return [
            'webrtc' => $urls['webrtc'],
            'flv' => $urls['flv'],
            'hls' => $urls['hls'],
            'expire_time' => $urls['expire_time'],
            'expire_timestamp' => $urls['expire_timestamp'],
            'region' => $urls['region'],
        ];
    } catch (\Exception $e) {
        \support\Log::warning('生成摸奖券直播播放地址失败', [/*...*/]);
        return null;
    }
}
```

---

## ✅ 优势总结

### **服务端控制**

1. ✅ **资源节约** - 只在直播中时生成地址，节省计算资源
2. ✅ **安全性** - 地址只在需要时生成，减少暴露风险
3. ✅ **明确性** - 客户端通过 `play_urls` 是否为 null 判断能否播放

### **客户端简化**

1. ✅ **逻辑清晰** - `play_urls != null` 即可播放
2. ✅ **无需判断状态** - 不用自己判断 `live_status` 是否可播放
3. ✅ **降级友好** - `play_urls = null` 时可降级到流名称

### **用户体验**

1. ✅ **防止误操作** - 未开播/已结束时无法获取播放地址
2. ✅ **即时性** - 直播开始时推送包含完整地址，立即可播
3. ✅ **一致性** - 推送和API返回逻辑一致

---

## 📋 测试验证清单

### **gk_admin 测试**

#### **开始直播**
- [ ] 未开播时，可以点击"开始直播"
- [ ] 开始直播后，`live_status` 变为 1
- [ ] 推送通知包含 `play_urls` 字段
- [ ] `play_urls.webrtc/flv/hls` 都可以播放
- [ ] "开始直播"按钮消失，"结束直播"按钮出现

#### **结束直播**
- [ ] 直播中时，可以点击"结束直播"
- [ ] 结束直播后，`live_status` 变为 2
- [ ] 推送通知发送成功
- [ ] "结束直播"按钮消失

#### **API查询**
- [ ] 未开播时，`getLiveInfo()` 返回 `play_urls = null`
- [ ] 直播中时，`getLiveInfo()` 返回完整 `play_urls`
- [ ] 已结束时，`getLiveInfo()` 返回 `play_urls = null`

---

### **gk_api 测试**

#### **活动API**
- [ ] 活动未开播时，`play_urls = null`
- [ ] 活动直播中时，`play_urls` 包含完整播放地址
- [ ] 活动已结束时，`play_urls = null`
- [ ] 返回的播放地址可以正常播放
- [ ] `expire_time` 为30天后

---

### **客户端测试**

#### **推送通知**
- [ ] 收到 `live_started` 推送
- [ ] 推送包含 `play_urls`
- [ ] 使用 `play_urls.webrtc` 可以播放
- [ ] 收到 `live_ended` 推送
- [ ] 结束推送无 `play_urls`

#### **API轮询**
- [ ] 轮询获取活动信息
- [ ] 直播中时获取到 `play_urls`
- [ ] 未开播/已结束时 `play_urls = null`
- [ ] 根据 `play_urls` 判断是否显示播放器

---

## 📚 相关文档

1. **完整实施报告：** [LIVE_FEATURE_IMPLEMENTATION.md](./LIVE_FEATURE_IMPLEMENTATION.md)
2. **改动总结：** [LIVE_FEATURE_CHANGES_SUMMARY.md](./LIVE_FEATURE_CHANGES_SUMMARY.md)
3. **gk_api集成：** [GK_API_LIVE_INTEGRATION.md](./GK_API_LIVE_INTEGRATION.md)

---

## 🎉 总结

通过这次完整实施：

1. ✅ **gk_admin** - 管理员端直播控制 + 推送通知
2. ✅ **gk_api** - 客户端API查询 + 完整播放地址
3. ✅ **核心逻辑** - 只在直播中时返回播放地址
4. ✅ **三个项目协同** - 推送、API、客户端完整闭环

**关键改进：**
- ⭐ **`live_status === 1` 才生成 `play_urls`** - 节省资源、逻辑清晰
- ⭐ **服务端统一生成播放地址** - 客户端无需了解签名算法
- ⭐ **向后兼容** - 保留 `stream_name` 字段作为备用

**现在可以开始全面测试！** 🚀
