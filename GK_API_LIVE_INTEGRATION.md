# gk_api 项目直播功能集成说明

## 📅 更新时间：2026-06-18

---

## 🎯 改动目的

**问题：** 客户端获取活动信息时，只有直播流名称，需要自己生成播放地址

**解决方案：** 在 gk_api 项目中集成直播地址生成功能，返回完整的播放地址给客户端

---

## 📝 修改清单

### **1. 添加辅助函数**

**文件：** `D:\gk_api\support\helpers.php`

**新增函数：** `generateLotteryLiveUrls()`

```php
/**
 * 生成摸奖券活动直播地址（支持多协议）
 *
 * @param int $configId 腾讯云配置ID（MachineTencentPlay表）
 * @param string $streamName 直播流名称（live_url字段值）
 * @param int $expireDays 地址有效期（天），默认30天
 * @param bool $useCnDomain 是否优先使用大陆域名，默认true
 * @param string $preferProtocol 优先协议（webrtc/flv/hls），默认webrtc
 * @return array 返回4种播放地址：RTMP、FLV、HLS、WebRTC
 * @throws \Exception
 */
function generateLotteryLiveUrls(int $configId, string $streamName, int $expireDays = 30, bool $useCnDomain = true, string $preferProtocol = 'webrtc'): array
```

**功能：**
- ✅ 从 `machine_tencent_play` 表获取配置
- ✅ 优先使用大陆域名（`pull_domain_cn`）
- ✅ 生成防盗链签名（`MD5(key + streamName + txTime)`）
- ✅ 返回4种播放地址：WebRTC、FLV、HLS、RTMP

**返回格式：**
```php
[
    'rtmp' => 'rtmp://domain/live/stream?txSecret=xxx&txTime=xxx',
    'webrtc' => 'webrtc://domain/live/stream?txSecret=xxx&txTime=xxx',
    'flv' => 'http://domain/live/stream.flv?txSecret=xxx&txTime=xxx',
    'hls' => 'http://domain/live/stream.m3u8?txSecret=xxx&txTime=xxx',
    'expire_time' => '2026-07-18 12:00:00',
    'expire_timestamp' => 1721275200,
    'tx_time' => '66E7B000',
    'tx_secret' => 'abc123...',
    'region' => 'CN',
    'pull_domain' => 'live.example.com',
]
```

---

### **2. 修改活动API响应**

**文件：** `D:\gk_api\app\api\controller\v1\LotteryTicketController.php`

**修改方法：** `buildActivityResponse()`

#### **改动前：**

```php
// ✅ 直播相关
'live_url' => $activity->live_url ?? null,  // ❌ 只有流名称
'live_status' => $activity->live_status ?? 0,
'live_status_text' => $this->getLiveStatusText($activity->live_status ?? 0),
```

#### **改动后：**

```php
// ✅ 直播相关（包含完整播放地址）
'stream_name' => $activity->live_url ?? null,  // ⭐ 流名称（备用）
'play_urls' => $this->generatePlayUrls($activity->live_url),  // ⭐ 完整播放地址
'live_status' => $activity->live_status ?? 0,
'live_status_text' => $this->getLiveStatusText($activity->live_status ?? 0),
```

---

### **3. 添加私有辅助方法**

**文件：** `D:\gk_api\app\api\controller\v1\LotteryTicketController.php`

**新增方法：** `generatePlayUrls()`

```php
/**
 * 生成完整的直播播放地址
 * @param string|null $streamName 流名称
 * @return array|null
 */
private function generatePlayUrls(?string $streamName): ?array
{
    if (empty($streamName)) {
        return null;
    }

    try {
        // 使用固定配置ID=1，生成30天有效期的播放地址
        $urls = generateLotteryLiveUrls(1, $streamName, 30);

        return [
            'webrtc' => $urls['webrtc'], // 推荐：超低延迟 <1秒
            'flv' => $urls['flv'],       // 备选：HTTP-FLV
            'hls' => $urls['hls'],       // 备选：HLS（兼容性好）
            'expire_time' => $urls['expire_time'],
            'expire_timestamp' => $urls['expire_timestamp'],
            'region' => $urls['region'], // CN（大陆）或 Global（全球）
        ];
    } catch (\Exception $e) {
        // 生成播放地址失败时记录日志
        \support\Log::warning('生成摸奖券直播播放地址失败', [
            'stream_name' => $streamName,
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}
```

**特点：**
- ✅ 生成失败时返回 null（不影响API正常返回）
- ✅ 记录警告日志，便于排查问题
- ✅ 客户端可根据 `play_urls` 是否为 null 判断是否降级

---

## 📊 API响应格式

### **GET /api/v1/lottery-ticket/get-current-activity**

**返回数据：**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "has_activity": true,
        "activity": {
            "id": 123,
            "name": "摸奖券活动",
            "description": "...",
            "cover_image": "https://...",
            "start_time": "2026-06-01 00:00:00",
            "end_time": "2026-06-30 23:59:59",
            "status": 1,
            "status_text": "進行中",
            "my_ticket_count": 5,
            "my_win_count": 2,
            "countdown": {...},
            "has_drawn": false,
            
            // ⭐ 直播相关字段（增强）
            "stream_name": "lottery_20260618",  // ⭐ 流名称（备用）
            "play_urls": {                      // ⭐ 完整播放地址
                "webrtc": "webrtc://domain/live/lottery_20260618?txSecret=xxx&txTime=xxx",
                "flv": "http://domain/live/lottery_20260618.flv?txSecret=xxx&txTime=xxx",
                "hls": "http://domain/live/lottery_20260618.m3u8?txSecret=xxx&txTime=xxx",
                "expire_time": "2026-07-18 12:00:00",
                "expire_timestamp": 1721275200,
                "region": "CN"
            },
            "live_status": 1,           // 0=未开播, 1=直播中, 2=已结束
            "live_status_text": "直播中",
            
            "total_winners": 0
        },
        "prize_levels": [...],
        "vip_configs": [...],
        "bet_progress": {...}
    }
}
```

---

## 🔄 客户端使用示例

### **获取活动信息**

```javascript
// 请求活动信息
const response = await fetch('/api/v1/lottery-ticket/get-current-activity');
const { data } = await response.json();

if (data.has_activity && data.activity.live_status === 1) {
    const { play_urls } = data.activity;
    
    if (play_urls) {
        // ✅ 直接使用服务端生成的播放地址
        // 优先使用 WebRTC（超低延迟）
        const playUrl = play_urls.webrtc || play_urls.flv || play_urls.hls;
        
        // 初始化播放器
        player.src(playUrl);
        player.play();
        
        // 检查地址有效期（提前1天更新）
        const expireTime = play_urls.expire_timestamp;
        const now = Math.floor(Date.now() / 1000);
        if ((expireTime - now) < 86400) {
            // 需要重新获取活动信息，更新播放地址
            console.warn('播放地址即将过期，需要更新');
        }
    } else {
        // 降级：使用流名称自行生成（向后兼容）
        const streamName = data.activity.stream_name;
        const playUrl = generatePlayUrlLocally(streamName);
        player.src(playUrl);
    }
}
```

---

### **配合推送通知使用**

```javascript
// 监听直播开始推送
ws.on('live_started', (pushData) => {
    const { play_urls } = pushData;
    
    if (play_urls) {
        // ✅ 推送已包含完整播放地址，直接使用
        const playUrl = play_urls.webrtc || play_urls.flv || play_urls.hls;
        player.src(playUrl);
        player.play();
    } else {
        // 降级：查询API获取最新信息
        refreshActivityInfo();
    }
});

// 刷新活动信息（获取最新播放地址）
async function refreshActivityInfo() {
    const response = await fetch('/api/v1/lottery-ticket/get-current-activity');
    const { data } = await response.json();
    
    if (data.has_activity && data.activity.play_urls) {
        const playUrl = data.activity.play_urls.webrtc;
        player.src(playUrl);
        player.play();
    }
}
```

---

## 🔐 数据库依赖

### **machine_tencent_play 表**

**必需字段：**

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| `id` | INT | 配置ID（固定使用1） | 1 |
| `pull_domain_cn` | VARCHAR | 大陆播放域名 | `live-cn.example.com` |
| `pull_key_cn` | VARCHAR | 大陆拉流密钥 | `abc123...` |
| `pull_domain` | VARCHAR | 全球播放域名（备用） | `live.example.com` |
| `pull_key` | VARCHAR | 全球拉流密钥（备用） | `def456...` |

**配置优先级：**
1. ✅ 优先使用大陆配置（`pull_domain_cn` + `pull_key_cn`）
2. ⚠️ 大陆配置为空时，降级到全球配置（`pull_domain` + `pull_key`）
3. ❌ 都为空时，抛出异常

---

## ⚙️ 配置说明

### **腾讯云配置ID**

当前硬编码为 `1`：

```php
// LotteryTicketController.php
$urls = generateLotteryLiveUrls(1, $streamName, 30);
//                              ^ 固定配置ID
```

**如需使用多套配置：**

可以在渠道表（`channel`）中添加 `tencent_play_config_id` 字段，根据不同渠道使用不同配置：

```php
// 未来扩展示例
$configId = $activity->channel->tencent_play_config_id ?? 1;
$urls = generateLotteryLiveUrls($configId, $streamName, 30);
```

---

### **地址有效期**

当前默认 **30天**：

```php
$urls = generateLotteryLiveUrls(1, $streamName, 30);
//                                             ^^ 30天
```

**建议：**
- ✅ 活动周期短（<7天）：使用 7 天有效期
- ✅ 活动周期长（>30天）：使用 30 天有效期
- ✅ 客户端应检查 `expire_timestamp`，提前1天更新地址

---

## 🧪 测试要点

### **功能测试**

- [ ] 调用活动API，返回 `play_urls` 字段
- [ ] `play_urls.webrtc` 可以正常播放
- [ ] `play_urls.flv` 可以正常播放
- [ ] `play_urls.hls` 可以正常播放
- [ ] `expire_time` 为30天后的时间
- [ ] `region` 字段为 'CN' 或 'Global'

### **降级测试**

- [ ] 活动无直播地址时，`play_urls` 为 null
- [ ] 腾讯云配置不存在时，`play_urls` 为 null
- [ ] `play_urls` 为 null 时，API仍然正常返回（不报错）

### **兼容性测试**

- [ ] 旧版客户端使用 `stream_name` 仍然可用
- [ ] 新版客户端使用 `play_urls` 可用
- [ ] 推送和API返回格式一致

---

## 📋 与 gk_admin 的对比

| 项目 | gk_admin | gk_api |
|------|---------|--------|
| **推送通知** | ✅ `pushLiveStarted()` | ❌ 无（由 gk_admin 推送） |
| **辅助函数** | ✅ `helpers.php` | ✅ `support/helpers.php` |
| **API返回** | ✅ `getLiveInfo()` | ✅ `getCurrentActivity()` |
| **使用场景** | 管理员查询 | 玩家查询 |
| **调用频率** | 低（手动操作） | 高（APP轮询） |

**关键点：**
- ✅ 推送通知由 gk_admin 发送（已包含完整播放地址）
- ✅ gk_api 的 API 也返回完整播放地址（支持客户端轮询）
- ✅ 两边使用相同的 `generateLotteryLiveUrls()` 函数（逻辑一致）

---

## 🎯 总结

通过这次改动：

1. ✅ **客户端获取活动信息时包含完整播放地址** - 无需自己生成
2. ✅ **统一数据格式** - 推送和API返回格式一致
3. ✅ **向后兼容** - 保留 `stream_name` 字段
4. ✅ **降级友好** - 生成失败时返回 null，不影响API
5. ✅ **安全** - 密钥不暴露给客户端

**客户端只需使用返回的 `play_urls` 直接播放，无需了解签名算法！** 🚀

---

## 📚 相关文档

- **gk_admin 改动：** [LIVE_FEATURE_IMPLEMENTATION.md](./LIVE_FEATURE_IMPLEMENTATION.md)
- **改动总结：** [LIVE_FEATURE_CHANGES_SUMMARY.md](./LIVE_FEATURE_CHANGES_SUMMARY.md)
- **客户端集成指南：** 见 LIVE_FEATURE_IMPLEMENTATION.md
