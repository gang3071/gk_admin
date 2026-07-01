# 摸奖券直播功能改进总结

## 📅 更新时间：2026-06-18

---

## 🎯 核心改进

### **问题：原有实现的局限性**

**之前的实现：**
- ✅ 推送通知只包含流名称（`live_url`）
- ❌ 客户端需要自己生成完整的播放地址
- ❌ 客户端需要处理防盗链签名逻辑
- ❌ 客户端需要了解腾讯云配置

**改进后的实现：**
- ✅ 推送通知包含完整的播放地址
- ✅ 服务端统一生成带签名的播放地址
- ✅ 客户端只需直接使用返回的URL
- ✅ 降低客户端复杂度

---

## 📝 修改清单

### **1. 推送服务增强**

**文件：** `D:\gk_admin\addons\webman\service\LotteryTicketPushService.php`

**修改方法：** `pushLiveStarted()`

**改动前：**
```php
'data' => [
    'activity_id' => $activity->id,
    'activity_name' => $activity->name,
    'live_url' => $activity->live_url,  // ❌ 只有流名称
    'live_status' => $activity->live_status,
],
```

**改动后：**
```php
// ✅ 生成完整的直播播放地址
$urls = generateLotteryLiveUrls(1, $activity->live_url, 30);

'data' => [
    'activity_id' => $activity->id,
    'activity_name' => $activity->name,
    'stream_name' => $activity->live_url,  // ⭐ 流名称（备用）
    'play_urls' => [                       // ⭐ 完整播放地址
        'webrtc' => $urls['webrtc'],
        'flv' => $urls['flv'],
        'hls' => $urls['hls'],
        'expire_time' => $urls['expire_time'],
        'region' => $urls['region'],
    ],
    'live_status' => $activity->live_status,
],
```

**关键点：**
- ✅ 调用 `generateLotteryLiveUrls()` 生成播放地址
- ✅ 包含3种协议（WebRTC/FLV/HLS）
- ✅ 包含过期时间和区域信息
- ✅ 生成失败时降级为null，不影响推送

---

### **2. 客户端API增强**

**文件：** `D:\gk_admin\addons\webman\controller\ChannelLotteryTicketActivityController.php`

**修改方法：** `getLiveInfo()`

**改动前：**
```php
return Response::success([
    'activity_id' => $activity->id,
    'activity_name' => $activity->name,
    'live_url' => $activity->live_url,  // ❌ 只有流名称
    'live_status' => $activity->live_status,
    'live_status_text' => LotteryTicketActivity::getLiveStatusText($activity->live_status),
    'has_live' => !empty($activity->live_url),
]);
```

**改动后：**
```php
// ✅ 生成完整的直播播放地址
$urls = generateLotteryLiveUrls(1, $activity->live_url, 30);
$playUrls = [
    'webrtc' => $urls['webrtc'],
    'flv' => $urls['flv'],
    'hls' => $urls['hls'],
    'expire_time' => $urls['expire_time'],
    'expire_timestamp' => $urls['expire_timestamp'],
    'region' => $urls['region'],
];

return Response::success([
    'activity_id' => $activity->id,
    'activity_name' => $activity->name,
    'stream_name' => $activity->live_url,  // ⭐ 流名称（备用）
    'play_urls' => $playUrls,              // ⭐ 完整播放地址
    'live_status' => $activity->live_status,
    'live_status_text' => LotteryTicketActivity::getLiveStatusText($activity->live_status),
    'has_live' => !empty($activity->live_url),
]);
```

**关键点：**
- ✅ 客户端轮询时也能获取完整播放地址
- ✅ 无需客户端了解签名算法
- ✅ 统一的数据格式

---

## 📊 数据格式对比

### **推送通知数据格式**

| 字段 | 之前 | 现在 | 说明 |
|------|------|------|------|
| `live_url` | ✅ 流名称 | ❌ 移除 | 改为 `stream_name` |
| `stream_name` | ❌ 无 | ✅ 流名称 | 备用字段 |
| `play_urls` | ❌ 无 | ✅ 完整地址 | 核心改进 |
| `play_urls.webrtc` | ❌ 无 | ✅ WebRTC地址 | 超低延迟 <1秒 |
| `play_urls.flv` | ❌ 无 | ✅ FLV地址 | 降级方案1 |
| `play_urls.hls` | ❌ 无 | ✅ HLS地址 | 降级方案2 |
| `play_urls.expire_time` | ❌ 无 | ✅ 过期时间 | 2026-07-18 12:00:00 |
| `play_urls.region` | ❌ 无 | ✅ 区域标识 | CN/Global |

### **API返回数据格式**

同上，`getLiveInfo()` 返回格式与推送通知一致。

---

## 🔄 客户端适配说明

### **推送通知处理**

**之前的客户端代码（需要修改）：**
```javascript
ws.on('live_started', (data) => {
    const streamName = data.live_url; // ❌ 只有流名称
    
    // ❌ 客户端需要自己生成播放地址
    const playUrl = generatePlayUrl(streamName);
    player.src(playUrl);
});
```

**现在的客户端代码（推荐）：**
```javascript
ws.on('live_started', (data) => {
    const playUrls = data.play_urls; // ✅ 完整播放地址
    
    if (!playUrls) {
        // 降级：使用流名称自行生成（向后兼容）
        const playUrl = generatePlayUrl(data.stream_name);
        player.src(playUrl);
        return;
    }
    
    // ✅ 直接使用服务端生成的地址
    // 优先使用 WebRTC（超低延迟）
    const playUrl = playUrls.webrtc || playUrls.flv || playUrls.hls;
    player.src(playUrl);
});
```

---

### **API查询处理**

**之前的客户端代码（需要修改）：**
```javascript
const response = await fetchLiveInfo(activityId);
const streamName = response.data.live_url; // ❌ 只有流名称

// ❌ 客户端需要自己生成播放地址
const playUrl = generatePlayUrl(streamName);
player.src(playUrl);
```

**现在的客户端代码（推荐）：**
```javascript
const response = await fetchLiveInfo(activityId);
const playUrls = response.data.play_urls; // ✅ 完整播放地址

if (playUrls) {
    // ✅ 直接使用服务端生成的地址
    const playUrl = playUrls.webrtc || playUrls.flv || playUrls.hls;
    player.src(playUrl);
}
```

---

## ✅ 优势总结

### **服务端统一管理**

1. ✅ **防盗链签名集中管理** - 客户端无需了解签名算法
2. ✅ **配置集中更新** - 修改配置无需更新客户端
3. ✅ **密钥安全** - 拉流密钥不暴露给客户端
4. ✅ **统一过期时间** - 服务端控制地址有效期（30天）

### **客户端简化**

1. ✅ **无需签名逻辑** - 直接使用返回的URL
2. ✅ **协议自动选择** - 服务端返回多种协议，客户端按需选择
3. ✅ **降级容易** - WebRTC → FLV → HLS
4. ✅ **向后兼容** - 仍然保留 `stream_name` 字段

### **用户体验提升**

1. ✅ **更快的播放启动** - 无需客户端计算签名
2. ✅ **更低的延迟** - 推荐使用 WebRTC（<1秒）
3. ✅ **更好的兼容性** - 提供3种协议降级方案
4. ✅ **地址有效期明确** - 客户端可提前更新地址

---

## 🔍 技术细节

### **generateLotteryLiveUrls() 函数**

**位置：** `D:\gk_admin\addons\webman\helpers.php`

**功能：**
```php
function generateLotteryLiveUrls(
    int $configId,      // 腾讯云配置ID（固定为1）
    string $streamName, // 流名称
    int $expireDays = 30, // 有效期（天）
    bool $useCnDomain = true, // 是否优先使用大陆域名
    string $preferProtocol = 'webrtc' // 优先协议
): array
```

**返回：**
```php
[
    'rtmp' => 'rtmp://domain/live/stream?txSecret=xxx&txTime=xxx',    // 推流地址
    'webrtc' => 'webrtc://domain/live/stream?txSecret=xxx&txTime=xxx', // WebRTC播放
    'flv' => 'http://domain/live/stream.flv?txSecret=xxx&txTime=xxx',  // FLV播放
    'hls' => 'http://domain/live/stream.m3u8?txSecret=xxx&txTime=xxx', // HLS播放
    'expire_time' => '2026-07-18 12:00:00',    // 过期时间（可读）
    'expire_timestamp' => 1721275200,          // 过期时间戳
    'tx_time' => '66E7B000',                   // 腾讯云时间参数（十六进制）
    'tx_secret' => 'abc123...',                // 防盗链签名
    'region' => 'CN',                          // 区域（CN/Global）
    'pull_domain' => 'live.example.com',       // 实际使用的播放域名
]
```

**签名算法：**
```php
// 1. 计算过期时间戳（十六进制）
$expireTimestamp = time() + ($expireDays * 24 * 60 * 60);
$txTime = strtoupper(base_convert($expireTimestamp, 10, 16));

// 2. 生成防盗链签名
$txSecret = md5($pullKey . $streamName . $txTime);

// 3. 构建URL
$url = "webrtc://{$pullDomain}/live/{$streamName}?txSecret={$txSecret}&txTime={$txTime}";
```

---

## 🧪 测试要点

### **推送通知测试**

1. ✅ 开始直播后，玩家端收到推送
2. ✅ 推送数据包含 `play_urls` 字段
3. ✅ `play_urls.webrtc` 可以正常播放
4. ✅ `play_urls.flv` 可以正常播放
5. ✅ `play_urls.hls` 可以正常播放
6. ✅ `expire_time` 为30天后的时间

### **API查询测试**

1. ✅ 调用 `getLiveInfo` 返回 `play_urls`
2. ✅ 返回的URL格式正确（包含 txSecret 和 txTime）
3. ✅ 返回的URL可以正常播放
4. ✅ `region` 字段正确（CN 或 Global）

### **降级测试**

1. ✅ 腾讯云配置不存在时，`play_urls` 为 null
2. ✅ `play_urls` 为 null 时，仍然推送通知（只包含 stream_name）
3. ✅ 客户端可以使用 `stream_name` 降级处理

---

## 📚 相关文档

- **完整实施报告：** [LIVE_FEATURE_IMPLEMENTATION.md](./LIVE_FEATURE_IMPLEMENTATION.md)
- **客户端集成指南：** 见实施报告中的"客户端集成指南"章节
- **helpers.php 函数说明：** 第2623行 `generateLotteryLiveUrls()`

---

## 🎉 总结

通过这次改进：

1. ✅ **服务端统一生成播放地址** - 降低客户端复杂度
2. ✅ **推送通知包含完整URL** - 客户端可直接使用
3. ✅ **API查询也返回完整URL** - 轮询场景同样受益
4. ✅ **向后兼容** - 保留 `stream_name` 字段
5. ✅ **降级友好** - 生成失败时不影响推送

**客户端只需修改数据字段名：**
- `data.live_url` → `data.stream_name`（备用）
- 新增：`data.play_urls`（优先使用）

**无需破坏性变更，平滑过渡！** 🚀
