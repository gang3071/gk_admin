# 摸奖券直播地址生成 - 优化记录

## 📅 更新日期
2026-06-17

## 🎯 主要改进

### 1️⃣ **优先使用大陆播放域名**

**修改文件:** `addons/webman/helpers.php` (函数 `generateLotteryLiveUrls`)

**改进前:**
```php
// 只使用 pull_domain 和 pull_key（全球域名）
$pullDomain = $config->pull_domain;
$pullKey = $config->pull_key;
```

**改进后:**
```php
// ✅ 优先使用大陆域名（大陆用户访问速度更快）
if ($useCnDomain && !empty($config->pull_domain_cn) && !empty($config->pull_key_cn)) {
    $pullDomain = $config->pull_domain_cn;
    $pullKey = $config->pull_key_cn;
    $region = 'CN'; // 大陆
} else {
    $pullDomain = $config->pull_domain;
    $pullKey = $config->pull_key;
    $region = 'Global'; // 全球/海外
}
```

**效果:**
- ✅ 大陆用户访问速度更快
- ✅ 自动降级到全球域名（如果大陆域名未配置）
- ✅ 返回信息中包含使用的区域标识

---

### 2️⃣ **延长播放地址有效期**

**修改文件:** `addons/webman/controller/ChannelLotteryTicketActivityController.php`

**改进前:**
```php
// 预览配置固定 1 天有效期
$urls = generateLotteryLiveUrls(1, $streamName, 1);
```

**改进后:**
```php
// 支持自定义有效期，默认 30 天
$expireDays = Request::input('expire_days', 30);
$urls = generateLotteryLiveUrls(1, $streamName, $expireDays);
```

**效果:**
- ✅ 默认有效期从 1 天改为 30 天
- ✅ 支持前端自定义有效期
- ✅ 避免频繁过期导致播放失败

---

### 3️⃣ **改用 FLV 作为默认播放格式**

**修改文件:** `addons/webman/controller/ChannelLotteryTicketActivityController.php`

**改进前:**
```php
'play_url' => $urls['webrtc'], // 默认 WebRTC
'urls' => [
    'webrtc' => $urls['webrtc'],
    'flv' => $urls['flv'],
    'hls' => $urls['hls'],
]
```

**改进后:**
```php
'play_url' => $urls['flv'], // ✅ 改为默认 FLV
'urls' => [
    'flv' => $urls['flv'],    // FLV 放在第一位
    'hls' => $urls['hls'],
    'webrtc' => $urls['webrtc'],
]
```

**原因:**
- ❌ **WebRTC 问题:** TCPlayer v5 不完全支持 `webrtc://` 协议
- ❌ **兼容性差:** WebRTC 需要 HTTPS 环境
- ✅ **FLV 优势:** 延迟低（2-5秒）、兼容性好、TCPlayer 完美支持

---

### 4️⃣ **增加调试信息**

**修改文件:** `addons/webman/controller/ChannelLotteryTicketActivityController.php`

**新增返回字段:**
```php
return Response::success([
    // 原有字段 ...
    'expire_timestamp' => $urls['expire_timestamp'], // Unix时间戳
    'region' => $urls['region'],                      // CN 或 Global
    'pull_domain' => $urls['pull_domain'],            // 实际使用的域名
    'tx_time' => $urls['tx_time'],                    // 用于调试
    'tx_secret' => $urls['tx_secret'],                // 用于调试
], '获取播放器配置成功（使用' . $urls['region'] . '域名）');
```

**效果:**
- ✅ 方便排查播放失败问题
- ✅ 可以验证使用的是哪个域名
- ✅ 可以检查 txTime 是否正确

---

## 📊 数据库字段说明

### MachineTencentPlay 模型字段

| 字段名 | 说明 | 优先级 |
|--------|------|--------|
| `pull_domain_cn` | 播放域名（大陆） | ⭐⭐⭐ 优先使用 |
| `pull_key_cn` | 播放Key（大陆） | ⭐⭐⭐ 优先使用 |
| `pull_domain` | 播放域名（全球） | ⭐⭐ 备用 |
| `pull_key` | 播放Key（全球） | ⭐⭐ 备用 |
| `license` | TCPlayer License URL | ✅ 必需 |
| `license_key` | TCPlayer License Key | ✅ 必需 |
| `push_domain` | 推流域名 | - |
| `push_key` | 推流Key | - |

---

## 🔧 配置建议

### 腾讯云控制台配置

**必须检查的配置项:**

1. **播放域名鉴权配置**
   - 位置: 云直播 → 域名管理 → 访问控制 → 鉴权配置
   - ⚠️ **鉴权有效时间**: 必须 ≥ 3600 秒（1小时）
   - ❌ 如果设置为 0 秒，播放会立即失败（`auth_failed`）

2. **播放域名（大陆地区）**
   - 示例: `tencent2.kkcnw.cn`
   - 用于大陆用户访问

3. **播放域名Key（大陆地区）**
   - 格式: 32位随机字符串
   - 用于生成 `txSecret` 签名

### 数据库配置

**在 `machine_tencent_play` 表中配置:**

```sql
UPDATE machine_tencent_play SET
    pull_domain_cn = 'tencent2.kkcnw.cn',           -- 大陆播放域名
    pull_key_cn = 'your_pull_key_here',             -- 大陆播放Key
    pull_domain = 'tencent-global.example.com',     -- 全球播放域名（可选）
    pull_key = 'your_global_pull_key_here',         -- 全球播放Key（可选）
    license = 'https://1350132313.trtcube-license.cn/license/v2/1350132313_1/v_cube.license',
    license_key = '86224641e24beb2a7af2f5ef6ce1f365'
WHERE id = 1;
```

---

## 🎬 播放格式对比

| 格式 | 延迟 | 兼容性 | TCPlayer支持 | HTTP/HTTPS | 推荐度 |
|------|------|--------|-------------|-----------|--------|
| **FLV** | 2-5秒 | ✅ 好 | ✅ 完美 | ✅ 都支持 | ⭐⭐⭐⭐⭐ |
| **HLS** | 10-30秒 | ✅ 最佳 | ✅ 完美 | ✅ 都支持 | ⭐⭐⭐ |
| **WebRTC** | <1秒 | ⚠️ 需HTTPS | ❌ 不完全支持 | 仅HTTPS | ⭐ |
| **RTMP** | - | ❌ 不支持浏览器 | ❌ 不支持 | - | - |

**推荐使用顺序:**
1. 🥇 **HTTP-FLV** - 延迟低、兼容性好、TCPlayer完美支持
2. 🥈 **HLS** - 兼容性最佳，但延迟较高
3. 🥉 **WebRTC** - 延迟最低，但需要特殊配置和HTTPS环境

---

## 🐛 常见问题解决

### 问题 1: WebRTC 播放失败 (video.src: 無)

**原因:** TCPlayer v5 不直接支持 `webrtc://` 协议

**解决方案:**
- ✅ 改用 FLV 格式
- ✅ 在活动管理后台保存 FLV 地址
- ✅ 后端已默认返回 FLV 地址为 `play_url`

### 问题 2: auth_failed 错误

**可能原因:**
1. ⚠️ 腾讯云控制台「鉴权有效时间」设置为 0 秒
2. ⚠️ txTime 已过期
3. ⚠️ txSecret 签名错误（使用了错误的 Key）

**解决方案:**
1. **检查腾讯云控制台配置**
   - 鉴权有效时间 ≥ 3600 秒
2. **检查 URL 有效期**
   - 查看返回的 `expire_time` 是否正确
3. **检查使用的域名和Key**
   - 查看返回的 `region` 和 `pull_domain`
   - 确保 `pull_domain_cn` 和 `pull_key_cn` 配置正确

### 问题 3: 地址有效期太短

**原因:** 之前默认有效期为 1 天

**解决方案:**
- ✅ 已修改为默认 30 天
- ✅ 支持自定义有效期参数 `expire_days`

---

## 📝 测试步骤

### 1. 生成测试地址

在浏览器调试控制台或Postman中调用:

```javascript
// 获取播放器配置（包含FLV地址）
POST /ex-admin/channel-lottery-ticket-activity/getLivePlayerConfig

Request Body:
{
    "stream_name": "M056_6a32775dd7fae",
    "expire_days": 30  // 可选，默认30天
}

Response:
{
    "code": 0,
    "message": "获取播放器配置成功（使用CN域名）",
    "data": {
        "stream_name": "M056_6a32775dd7fae",
        "play_url": "http://tencent2.kkcnw.cn/live/M056_6a32775dd7fae.flv?txSecret=xxx&txTime=xxx",
        "urls": {
            "flv": "http://...",
            "hls": "http://...",
            "webrtc": "webrtc://..."
        },
        "region": "CN",
        "pull_domain": "tencent2.kkcnw.cn",
        "expire_time": "2026-07-17 20:30:00",
        // ...
    }
}
```

### 2. 测试播放

访问播放器测试地址:

```
https://zi-test.5super9.com/lottery-live-player.html?url=<FLV地址>&licenseUrl=<License>&licenseKey=<Key>&debug=1
```

**检查调试面板:**
- ✅ 协议类型: flv
- ✅ video.src: 有值（不是「無」）
- ✅ readyState: 4 (HAVE_ENOUGH_DATA)
- ✅ 事件日志: 有 `playing - 正在播放中`

---

## 🔄 回滚方案

如果新版本有问题，可以回滚修改:

### 回滚 helpers.php

恢复为只使用 `pull_domain` 和 `pull_key`:

```php
$pullDomain = $config->pull_domain;
$pullKey = $config->pull_key;
```

### 回滚控制器

恢复为固定 1 天有效期:

```php
$urls = generateLotteryLiveUrls(1, $streamName, 1);
```

---

## ✅ 验收清单

在生产环境部署前，请确认:

- [ ] 已在 `machine_tencent_play` 表中配置 `pull_domain_cn` 和 `pull_key_cn`
- [ ] 已在腾讯云控制台设置「鉴权有效时间」≥ 3600 秒
- [ ] 测试 FLV 播放地址可以正常播放
- [ ] 调试面板显示 `region: CN`（使用大陆域名）
- [ ] 调试面板显示 `video.src` 有值
- [ ] 地址有效期为 30 天（`expire_time` 正确）
- [ ] License 配置正确（无 Error 55）

---

## 📞 技术支持

如果遇到问题，请收集以下信息:

1. **调试面板截图** (按 D 键打开)
2. **浏览器控制台错误** (F12 → Console)
3. **播放地址** (隐藏敏感参数)
4. **腾讯云配置** (`machine_tencent_play` 表数据)
5. **API 返回数据** (`getLivePlayerConfig` 接口返回)

---

**更新日志:**
- 2026-06-17: 初版 - 优先使用大陆域名、延长有效期、改用FLV格式
