# WebRTC 快直播配置指南

## 🎯 WebRTC vs FLV 对比

| 特性 | WebRTC | FLV |
|------|--------|-----|
| **延迟** | <1秒（超低延迟） | 2-5秒 |
| **需要推流协议** | ⚠️ 必须 WebRTC 推流 | ✅ RTMP 推流即可 |
| **浏览器支持** | ✅ 现代浏览器 | ✅ 现代浏览器 |
| **HTTPS 要求** | ⚠️ 必须 HTTPS | ✅ HTTP/HTTPS 都可以 |
| **带宽消耗** | 较高 | 适中 |
| **稳定性** | 对网络要求高 | 稳定 |

---

## ⚠️ WebRTC 使用前提条件

### 1️⃣ **推流端必须使用 WebRTC 协议**

**❌ 不支持的情况：**
```
OBS 推流（RTMP） → 腾讯云 → WebRTC 拉流  ❌ 不支持！
```

**✅ 支持的情况：**
```
WebRTC 推流工具 → 腾讯云 → WebRTC 拉流  ✅ 支持
```

**WebRTC 推流工具选项：**
- **OBS 28+** - 支持 WHIP 协议（WebRTC 推流）
- **腾讯云 WebRTC 推流 SDK**
- **浏览器推流**（使用 getUserMedia API）

### 2️⃣ **页面必须使用 HTTPS**

```
❌ http://localhost:8789/lottery-live-player.html  → WebRTC 被阻止
✅ https://zi-test.5super9.com/lottery-live-player.html  → WebRTC 可用
✅ http://localhost/... (开发环境例外)  → WebRTC 可用
```

### 3️⃣ **腾讯云控制台配置**

需要开通 **快直播（LEB）** 服务：
- 云直播控制台 → 快直播
- 确保域名已开通 WebRTC 支持

---

## 🔧 配置步骤

### 步骤 1：数据库配置（使用大陆域名）

确保 `machine_tencent_play` 表中配置了大陆域名和 Key：

```sql
SELECT 
    id,
    pull_domain_cn,     -- 大陆播放域名
    pull_key_cn,        -- 大陆播放Key
    license,            -- TCPlayer License URL
    license_key         -- TCPlayer License Key
FROM machine_tencent_play 
WHERE id = 1;
```

**必填字段：**
- `pull_domain_cn` - 例如：`tencent2.kkcnw.cn`
- `pull_key_cn` - 32位随机字符串
- `license` - 腾讯云播放器 License URL
- `license_key` - License Key

### 步骤 2：腾讯云控制台配置

#### A. 开通快直播服务

1. 登录 https://console.cloud.tencent.com/live
2. 左侧菜单 → **快直播**
3. 如果未开通，点击 **立即开通**

#### B. 配置播放域名鉴权

1. **域名管理** → 找到您的播放域名（如 `tencent2.kkcnw.cn`）
2. **访问控制** → **鉴权配置**
3. **重要配置项：**
   ```
   ⭐ 鉴权有效时间：3600 秒（1小时）或更长
   ❌ 不要设置为 0 秒！
   ```
4. **保存配置**

#### C. 配置 WebRTC 推流域名（如果需要）

如果使用 WebRTC 推流：
1. **域名管理** → **添加域名**
2. 域名类型：**推流域名**
3. 协议：勾选 **WebRTC**

### 步骤 3：配置推流端（OBS）

#### 使用 OBS 28+ 的 WHIP 插件（推荐）

1. **下载 OBS 28 或更高版本**
2. **安装 WHIP 插件**：
   - OBS → 工具 → 插件管理
   - 搜索 "WHIP"
   - 安装并重启 OBS

3. **配置推流：**
   ```
   服务：WHIP
   服务器：https://tencent2.kkcnw.cn/webrtc/v1/whip
   串流密钥：您的推流 Stream ID + 鉴权参数
   ```

#### 或使用腾讯云推流 SDK

参考腾讯云文档：
https://cloud.tencent.com/document/product/454/56598

---

## 🎬 生成 WebRTC 播放地址

### 方法 1：通过 API 生成（已配置为默认返回 WebRTC）

```javascript
POST /ex-admin/channel-lottery-ticket-activity/getLivePlayerConfig

Request:
{
    "stream_name": "M056_6a32775dd7fae",
    "expire_days": 30
}

Response:
{
    "code": 0,
    "data": {
        "play_url": "webrtc://tencent2.kkcnw.cn/live/M056_6a32775dd7fae?txSecret=xxx&txTime=xxx",
        "urls": {
            "webrtc": "webrtc://...",  // 优先使用
            "flv": "http://...",
            "hls": "http://..."
        },
        "region": "CN",  // 使用大陆域名
        "expire_time": "2026-07-17 20:30:00"
    }
}
```

### 方法 2：手动拼接（调试用）

**格式：**
```
webrtc://{domain}/live/{streamName}?txSecret={secret}&txTime={time}
```

**示例：**
```
webrtc://tencent2.kkcnw.cn/live/M056_6a32775dd7fae?txSecret=b9848ac13a8bed30b2e52e0db0155492&txTime=668C7F73
```

**参数说明：**
- `domain` - 播放域名（大陆：pull_domain_cn）
- `streamName` - 流名称（与推流一致）
- `txSecret` - MD5(pull_key_cn + streamName + txTime)
- `txTime` - 过期时间戳（十六进制）

---

## 🧪 测试播放

### 测试 URL 格式

```
https://zi-test.5super9.com/lottery-live-player.html?url=<WebRTC地址>&licenseUrl=<License>&licenseKey=<Key>&debug=1
```

### 完整示例

```
https://zi-test.5super9.com/lottery-live-player.html?url=webrtc://tencent2.kkcnw.cn/live/M056_6a32775dd7fae?txSecret=b9848ac13a8bed30b2e52e0db0155492%26txTime=668C7F73&licenseUrl=https://1350132313.trtcube-license.cn/license/v2/1350132313_1/v_cube.license&licenseKey=86224641e24beb2a7af2f5ef6ce1f365&debug=1
```

**注意：** URL 中的 `&` 需要编码为 `%26`

---

## 🐛 调试步骤

### 步骤 1：打开调试面板

访问播放器页面后，按 **D 键** 打开调试面板。

### 步骤 2：检查关键信息

#### ✅ 正常情况应该看到：

**📋 URL 參數：**
```
url: webrtc://tencent2.kkcnw.cn/live/...
licenseUrl: https://1350132313.trtcube-license.cn/...
licenseKey: ✅ 已配置
```

**🎬 播放器配置：**
```
協議類型: webrtc
播放地址: webrtc://tencent2.kkcnw.cn/...
video.src: blob:https://... （有值）✅
License: ✅ 已配置
```

**📊 播放器狀態：**
```
readyState: 4 (HAVE_ENOUGH_DATA) ✅
networkState: 2 (NETWORK_LOADING) ✅
paused: false ✅
error: 無錯誤 ✅
```

**🌐 環境檢測：**
```
頁面協議: https: ✅
WebRTC 可用: ✅ 是
```

**📝 事件日誌：**
```
[時間] 🚀 使用 WebRTC 快直播
[時間] ✅ WebRTC 配置已應用
[時間] ✅ loadedmetadata - 元數據加載完成
[時間] ✅ canplay - 可以開始播放
[時間] ▶️ playing - 正在播放中
```

### 步骤 3：常见错误排查

#### ❌ 错误 1: video.src: 無

**原因：** 播放器未能加载视频源

**解决方案：**
1. 检查 URL 格式是否正确（`webrtc://...`）
2. 检查推流是否已开始
3. 查看浏览器控制台（F12）是否有错误

---

#### ❌ 错误 2: Error Code 2 - 网络错误

**原因：**
- 推流未开始（最常见）
- WebRTC 推流未正确配置
- txTime 已过期

**解决方案：**
1. **检查推流端：**
   - OBS 是否显示 "正在串流"
   - OBS 底部是否有码率数据
   - 确认使用 WebRTC/WHIP 协议推流

2. **检查 txTime：**
   - 调试面板 → URL 参数 → 查看完整 URL
   - 使用在线工具解析 txTime（十六进制转时间戳）
   - 如果过期，重新生成地址

---

#### ❌ 错误 3: auth_failed

**原因：** 鉴权失败

**可能原因：**
1. ⚠️ 腾讯云控制台「鉴权有效时间」设置为 0 秒
2. ⚠️ txSecret 签名错误（使用了错误的 Key）
3. ⚠️ 域名配置错误

**解决方案：**

**检查 1：腾讯云控制台配置**
```
云直播 → 域名管理 → tencent2.kkcnw.cn → 访问控制 → 鉴权配置
→ 鉴权有效时间 ≥ 3600 秒
```

**检查 2：使用的 Key 是否正确**

调试面板应该显示：
```
🌍 使用区域: CN
🌐 播放域名: tencent2.kkcnw.cn
```

确保数据库中配置了：
```sql
SELECT pull_domain_cn, pull_key_cn FROM machine_tencent_play WHERE id = 1;
```

**检查 3：手动验证签名**

```php
// 验证 txSecret 计算是否正确
$streamName = 'M056_6a32775dd7fae';
$txTime = '668C7F73'; // 从 URL 中获取
$pullKeyCn = 'your_key'; // 从数据库获取

$calculatedSecret = md5($pullKeyCn . $streamName . $txTime);
echo "计算的 txSecret: " . $calculatedSecret . "\n";
echo "URL 中的 txSecret: b9848ac13a8bed30b2e52e0db0155492\n";
echo "是否匹配: " . ($calculatedSecret === 'b9848ac13a8bed30b2e52e0db0155492' ? '是' : '否');
```

---

#### ❌ 错误 4: WebRTC 不可用（HTTP 环境）

**现象：**
```
🌐 環境檢測:
頁面協議: http:
WebRTC 可用: ❌ 否 (需要 HTTPS)
```

**解决方案：**

**方案 1：使用 HTTPS 访问**
```
❌ http://zi-test.5super9.com/lottery-live-player.html
✅ https://zi-test.5super9.com/lottery-live-player.html
```

**方案 2：本地开发（localhost 例外）**
```
✅ http://localhost:8789/lottery-live-player.html
✅ http://127.0.0.1:8789/lottery-live-player.html
```

---

## 📊 完整的测试流程

### 1. 确认推流正常

**使用腾讯云控制台检查：**
1. 云直播 → 流管理
2. 搜索您的流 ID（如 `M056_6a32775dd7fae`）
3. 确认状态为 **在线**

**或使用 OBS：**
- 底部显示绿色 "正在串流"
- 有实时码率数据显示

### 2. 生成播放地址

```bash
# 调用 API
curl -X POST https://zi-test.5super9.com/ex-admin/channel-lottery-ticket-activity/getLivePlayerConfig \
  -H "Content-Type: application/json" \
  -d '{"stream_name": "M056_6a32775dd7fae"}'

# 或在浏览器控制台
fetch('/ex-admin/channel-lottery-ticket-activity/getLivePlayerConfig', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({stream_name: 'M056_6a32775dd7fae'})
}).then(r => r.json()).then(console.log);
```

### 3. 访问播放器（带调试）

```
https://zi-test.5super9.com/lottery-live-player.html?url=<WebRTC地址>&licenseUrl=<License>&licenseKey=<Key>&debug=1
```

### 4. 检查调试面板

按 **D 键** 打开调试面板，逐项检查：

- [ ] URL 参数完整（url、licenseUrl、licenseKey）
- [ ] 协议类型：webrtc
- [ ] video.src 有值（blob:https://...）
- [ ] readyState: 4
- [ ] networkState: 2
- [ ] paused: false
- [ ] error: 无错误
- [ ] 页面协议: https:
- [ ] WebRTC 可用: 是
- [ ] 事件日志有 "playing - 正在播放中"

---

## 💡 最佳实践

### 1. 推流建议

- ✅ 使用 **OBS 28+** + **WHIP 插件**（最简单）
- ✅ 码率设置：2000-4000 kbps
- ✅ 关键帧间隔：2 秒
- ✅ 编码器：H.264

### 2. 播放器优化

```javascript
// 播放器配置优化
{
    autoplay: true,           // 自动播放
    preload: 'auto',          // 预加载
    controls: true,           // 显示控制条
    fluid: true,              // 自适应容器
    playbackRates: [1],       // WebRTC 只支持 1 倍速
}
```

### 3. 网络要求

- **推流端：** 上行带宽 ≥ 5 Mbps
- **播放端：** 下行带宽 ≥ 3 Mbps
- **延迟：** RTT < 100ms（最佳）

### 4. 错误处理

```javascript
player.on('error', function(error) {
    if (error.code === 2) {
        // 网络错误：检查推流是否开始
        console.error('推流未开始或网络问题');
    } else if (error.code === 4) {
        // 媒体源不支持：可能是协议问题
        console.error('WebRTC 协议问题，建议降级到 FLV');
    }
});
```

---

## 🔄 WebRTC vs FLV 切换

如果 WebRTC 播放有问题，可以随时切换回 FLV：

### 修改后端默认返回 FLV

编辑 `ChannelLotteryTicketActivityController.php`:

```php
'play_url' => $urls['flv'],  // 改为 FLV
'urls' => [
    'flv' => $urls['flv'],    // FLV 放在第一位
    'webrtc' => $urls['webrtc'],
    'hls' => $urls['hls'],
]
```

### 前端手动指定 FLV

在活动管理后台，直播地址输入 FLV 格式：

```
http://tencent2.kkcnw.cn/live/M056_6a32775dd7fae.flv?txSecret=xxx&txTime=xxx
```

---

## 📞 问题反馈

如果 WebRTC 播放仍然有问题，请提供：

1. **调试面板完整截图**（按 D 键）
2. **浏览器控制台错误**（F12 → Console）
3. **推流端截图**（OBS 状态）
4. **流 ID 和域名**
5. **腾讯云流管理截图**（流是否在线）

---

**更新日期：** 2026-06-17
**版本：** 1.0
