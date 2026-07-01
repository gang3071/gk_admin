# 📡 摸奖券直播集成指南

## 🎯 功能概述

本功能允许你**复用 `machine_tencent_play` 表中的腾讯云配置**来生成摸奖券直播地址，无需手动复制粘贴地址或担心txSecret/txTime参数。

---

## ✨ 核心特性

### 1. 自动生成防盗链签名
- ✅ 基于 `machine_tencent_play` 配置自动生成 `txSecret` 和 `txTime`
- ✅ 支持自定义过期天数（默认30天）
- ✅ 一键生成4种播放协议地址（RTMP、FLV、HLS、WebRTC）

### 2. TCPlayer v5播放器
- ✅ 使用腾讯云官方播放器TCPlayer v5.1.0
- ✅ 支持FLV、HLS、WebRTC协议
- ✅ 自动协议检测
- ✅ 完善的错误提示

### 3. 简洁的后台界面
- ✅ 移除播放器选择器（统一使用TCPlayer v5）
- ✅ 一键预览直播
- ✅ 一键复制地址
- ✅ 新窗口打开

---

## 📋 使用步骤

### 步骤1：配置腾讯云（如果还没配置）

#### 1.1 进入腾讯云播放配置管理

```
后台管理 → 机台管理 → 腾讯云播放配置
```

#### 1.2 检查配置项

确保以下字段已填写：

| 字段 | 说明 | 示例 |
|------|------|------|
| **拉流域名（国际）** | `pull_domain` | `tencent2.kkcnw.cn` |
| **拉流KEY（国际）** | `pull_key` | `防盗链密钥` |
| **标题** | `title` | `摸奖券直播配置` |

**注意：** 拉流域名和KEY是必填项！

---

### 步骤2：生成直播地址

#### 方法A：使用API生成（推荐）⭐

**接口：** `POST /ex-admin/channel-lottery-ticket-activity/generateLiveUrls`

**请求参数：**
```json
{
  "config_id": 1,             // machine_tencent_play.id
  "stream_name": "mojiangjuan", // 流名称（自定义）
  "expire_days": 30           // 有效天数（可选，默认30）
}
```

**返回示例：**
```json
{
  "code": 0,
  "message": "直播地址生成成功",
  "data": {
    "rtmp": "rtmp://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx",
    "flv": "http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=xxx&txTime=xxx",
    "hls": "http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8?txSecret=xxx&txTime=xxx",
    "webrtc": "webrtc://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx",
    "expire_time": "2026-07-17 12:00:00",
    "tx_time": "6A5C9A80",
    "tx_secret": "a1b2c3d4e5f6..."
  }
}
```

#### 方法B：使用辅助函数

在PHP代码中：

```php
try {
    // 参数：config_id, stream_name, expire_days
    $urls = generateLotteryLiveUrls(1, 'mojiangjuan', 30);
    
    /*
     * $urls = [
     *   'rtmp' => 'rtmp://...',
     *   'flv' => 'http://...flv',
     *   'hls' => 'http://...m3u8',
     *   'webrtc' => 'webrtc://...',
     *   'expire_time' => '2026-07-17 12:00:00',
     *   'tx_time' => '6A5C9A80',
     *   'tx_secret' => 'a1b2c3d4...'
     * ]
     */
    
    // 推荐使用FLV格式（低延迟，性价比高）
    $recommendedUrl = $urls['flv'];
    
} catch (\Exception $e) {
    // 错误处理
    echo $e->getMessage();
}
```

---

### 步骤3：配置活动直播地址

#### 3.1 进入摸奖券活动管理

```
后台管理 → 摸奖券活动列表
```

#### 3.2 添加/更新直播地址

```
活动列表 → 更多操作 → 添加直播地址
```

**填写地址：** 选择合适的协议格式

| 协议 | 延迟 | 适用场景 | 推荐度 |
|------|------|---------|--------|
| **FLV** | 2-5秒 | 后台管理预览 | ⭐⭐⭐⭐⭐ |
| **HLS** | 10-30秒 | 移动端观看 | ⭐⭐⭐⭐ |
| **WebRTC** | <1秒 | 实时互动 | ⭐⭐⭐⭐⭐ (费用稍高) |
| **RTMP** | 无法播放 | 仅推流用 | ❌ |

**推荐配置：**
- **后台预览：** 使用 **FLV** 格式（`http://xxx.flv`）
- **玩家观看：** 使用 **HLS** 格式（`http://xxx.m3u8`）
- **极致体验：** 使用 **WebRTC** 格式（`webrtc://xxx`）

---

### 步骤4：OBS推流配置

#### 4.1 打开OBS Studio

#### 4.2 配置推流服务器

**设置 → 流：**

```
服务：自定义
服务器：rtmp://tencent2.kkcnw.cn/live/
串流密钥：mojiangjuan?txSecret=xxx&txTime=xxx
```

**注意：** 推流使用RTMP地址！

#### 4.3 推荐推流参数

| 参数 | 推荐值 | 说明 |
|------|--------|------|
| **视频码率** | 2000-4000 Kbps | 根据网络调整 |
| **分辨率** | 1280×720 (720p) | 或1920×1080 |
| **帧率** | 25 或 30 fps | 稳定流畅 |
| **音频码率** | 128 Kbps | 清晰音质 |
| **关键帧间隔** | 2秒 | 降低延迟 |

---

### 步骤5：预览直播

#### 5.1 后台预览

```
活动列表 → 更多操作 → 预览直播
```

**特点：**
- ✅ 使用TCPlayer v5播放器
- ✅ 自动协议检测
- ✅ 完善的错误提示
- ✅ 支持全屏播放

#### 5.2 新窗口打开

```
预览Modal → 新窗口打开按钮
```

**窗口尺寸：** 1280×720（适合监控）

#### 5.3 复制地址

```
预览Modal → 复制地址按钮
```

**用途：** 分享给其他管理员或用于测试

---

## 🧪 测试流程

### 测试清单

#### 1. 地址生成测试

**调用API：**
```bash
curl -X POST "http://localhost:8789/ex-admin/channel-lottery-ticket-activity/generateLiveUrls" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "stream_name": "mojiangjuan",
    "expire_days": 30
  }'
```

**预期结果：** 返回4种协议地址，txSecret和txTime正确生成

---

#### 2. 播放器测试

**步骤：**
```
1. 配置活动直播地址（FLV格式）
2. 启动OBS推流
3. 点击「预览直播」
4. 等待2-5秒
```

**预期结果：**
- ✅ 播放器自动检测FLV协议
- ✅ 显示「HTTP-FLV（低延遲 2-5秒）」
- ✅ 画面流畅播放
- ✅ 延迟2-5秒

---

#### 3. 错误处理测试

**场景A：直播流未推送**

**操作：** 未启动OBS的情况下预览

**预期：**
- 显示加载提示3秒
- 显示错误：「網絡錯誤：無法加載直播流」
- 提示：「請確認 OBS 已開始推流」

**场景B：地址过期**

**操作：** 使用过期的txTime参数

**预期：**
- 显示网络错误
- 提示更新直播地址

**场景C：RTMP地址**

**操作：** 填写RTMP播放地址（错误用法）

**预期：**
- 显示：「⚠️ RTMP 協議不支持瀏覽器播放」
- 建议使用FLV/HLS/WebRTC

---

#### 4. 功能测试

- [ ] 一键预览直播
- [ ] 新窗口打开
- [ ] 复制地址
- [ ] 全屏播放
- [ ] 音量调节
- [ ] 播放/暂停

---

## ⚙️ 配置说明

### helpers.php 辅助函数

**函数名：** `generateLotteryLiveUrls()`

**位置：** `addons/webman/helpers.php`

**参数：**
```php
/**
 * @param int $configId     machine_tencent_play.id
 * @param string $streamName 流名称（如：mojiangjuan）
 * @param int $expireDays    有效天数（默认30天）
 * @return array             返回4种播放地址
 * @throws \Exception
 */
function generateLotteryLiveUrls(int $configId, string $streamName, int $expireDays = 30): array
```

**返回值：**
```php
[
    'rtmp' => 'rtmp://domain/live/stream?txSecret=xxx&txTime=xxx',
    'flv' => 'http://domain/live/stream.flv?txSecret=xxx&txTime=xxx',
    'hls' => 'http://domain/live/stream.m3u8?txSecret=xxx&txTime=xxx',
    'webrtc' => 'webrtc://domain/live/stream?txSecret=xxx&txTime=xxx',
    'expire_time' => '2026-07-17 12:00:00',
    'tx_time' => '6A5C9A80',
    'tx_secret' => 'a1b2c3d4e5f6...'
]
```

---

### 播放器文件

**文件名：** `lottery-live-player.html`

**位置：** `public/lottery-live-player.html`

**特点：**
- 使用TCPlayer v5.1.0
- 支持FLV/HLS/WebRTC
- 自动协议检测
- 完善的错误处理

**URL参数：**
```
/lottery-live-player.html?url=<直播地址>
```

---

### Vue组件修改

**文件：** `addons/webman/views/lottery_ticket_activities.vue`

**修改点：**
1. ✅ 移除播放器选择器（简化UI）
2. ✅ 统一使用`lottery-live-player.html`
3. ✅ 移除`playerType`数据字段
4. ✅ 简化`getLivePlayerUrl()`方法
5. ✅ 移除`switchPlayer()`方法

**核心代码：**
```vue
<!-- 直播播放器iframe（使用TCPlayer v5）-->
<iframe
    :src="getLivePlayerUrl()"
    style="width: 100%; height: 70vh; border: none; display: block;"
    frameborder="0"
    allowfullscreen
></iframe>
```

```javascript
// 获取直播播放器URL（使用TCPlayer v5）
getLivePlayerUrl() {
  return `/lottery-live-player.html?url=${encodeURIComponent(this.livePreviewUrl)}`;
}
```

---

## 💡 使用建议

### 1. 选择合适的协议

**后台管理界面：**
```
推荐：FLV
理由：延迟低（2-5秒），费用标准，兼容性好
地址：http://tencent2.kkcnw.cn/live/mojiangjuan.flv
```

**玩家H5页面：**
```
推荐：HLS
理由：兼容性最好，移动端原生支持
地址：http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8
```

**实时互动需求：**
```
推荐：WebRTC
理由：延迟<1秒，实时性最好
地址：webrtc://tencent2.kkcnw.cn/live/mojiangjuan
注意：费用约为标准直播1.5-2倍
```

---

### 2. 流名称规范

**建议格式：**
```
lottery_{activity_id}     // 示例：lottery_123
lottery_{activity_name}   // 示例：lottery_spring_festival
mojiangjuan              // 通用流名称
```

**注意：**
- 流名称需要与OBS推流配置一致
- 避免使用特殊字符
- 建议使用英文和数字

---

### 3. 过期时间管理

**默认30天：** 适合长期活动

**自定义：**
```php
// 7天（短期活动）
$urls = generateLotteryLiveUrls(1, 'mojiangjuan', 7);

// 90天（长期活动）
$urls = generateLotteryLiveUrls(1, 'mojiangjuan', 90);
```

**检查过期：**
```javascript
const txTime = parseInt('6A5C9A80', 16);
const expireDate = new Date(txTime * 1000);
console.log('地址过期时间：', expireDate);
```

---

### 4. 推流优化建议

**降低延迟：**
- 关键帧间隔设为2秒（GOP=2）
- 使用FLV或WebRTC协议
- 选择距离近的推流域名

**提升稳定性：**
- 稳定的网络带宽（上传至少4Mbps）
- 适当降低码率（2000-3000 Kbps）
- 使用有线网络推流

---

## 🔧 故障排查

### 问题1：播放失败

**症状：** 黑屏或显示错误

**检查：**
```bash
# 1. 检查直播流是否推送
curl -I http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8

# 2. 检查txTime是否过期
echo $((0x6A5C9A80))  # 转换为时间戳
date -d @1781793382   # 查看日期

# 3. 检查配置是否存在
SELECT * FROM machine_tencent_play WHERE id = 1;
```

**解决：**
- 启动OBS推流
- 重新生成地址（更新txTime）
- 检查`pull_domain`和`pull_key`配置

---

### 问题2：地址生成失败

**错误：** "腾讯云配置不存在"

**检查：**
```sql
SELECT id, title, pull_domain, pull_key FROM machine_tencent_play;
```

**解决：**
- 确保`config_id`正确
- 检查配置表中是否有数据
- 确保`pull_domain`和`pull_key`已填写

---

### 问题3：延迟过高

**症状：** 延迟超过30秒

**原因：**
- 使用HLS协议（正常延迟10-30秒）
- GOP设置过大
- 网络带宽不足

**解决：**
- 切换到FLV协议（延迟2-5秒）
- 设置GOP=2秒
- 升级到WebRTC（延迟<1秒，费用更高）

---

## 📊 费用说明

### 腾讯云直播计费

**标准直播（FLV/HLS）：**
- 流量计费：0.26元/GB（中国大陆）
- 带宽峰值：0.64元/Mbps/天

**快直播（WebRTC）：**
- 流量计费：0.52元/GB（约2倍）
- 带宽峰值：1.28元/Mbps/天

**示例计算：**
```
1小时直播，码率2Mbps，100人观看：
- 流量：2Mbps × 3600秒 × 100 / 8 / 1024 ≈ 88GB
- 标准直播：88GB × 0.26 ≈ 23元
- 快直播：88GB × 0.52 ≈ 46元
```

---

## 📞 技术支持

### 相关文档

- **腾讯云直播配置指南：** `docs/TENCENT_CLOUD_LIVE_GUIDE.md`
- **直播测试指南：** `docs/LIVE_TEST_GUIDE.md`
- **摸奖券API文档：** `摸奖券API对接文档.md`

### 联系方式

- **腾讯云文档：** https://cloud.tencent.com/document/product/267
- **TCPlayer文档：** https://cloud.tencent.com/document/product/881
- **地址生成器：** https://console.cloud.tencent.com/live/tools/address

---

## ✅ 快速开始

### 1分钟快速测试

```bash
# 1. 生成直播地址
curl -X POST "http://localhost:8789/ex-admin/channel-lottery-ticket-activity/generateLiveUrls" \
  -H "Content-Type: application/json" \
  -d '{"config_id": 1, "stream_name": "mojiangjuan", "expire_days": 30}'

# 2. 复制返回的FLV地址

# 3. 进入后台：活动列表 → 更多操作 → 添加直播地址

# 4. 粘贴FLV地址并保存

# 5. 启动OBS推流（使用RTMP地址）

# 6. 点击「预览直播」测试
```

---

*最后更新：2026-06-17*  
*版本：v1.0*  
*作者：Claude Code*
