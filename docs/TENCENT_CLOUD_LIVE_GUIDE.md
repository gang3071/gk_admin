# 📡 腾讯云直播地址配置指南

## 🎯 你现在有4种播放地址

腾讯云为同一直播流提供了4种不同的播放协议，每种协议有不同的延迟和兼容性特点：

### 1. WebRTC 地址（推荐 - 超低延迟）⭐⭐⭐⭐⭐

**格式：**
```
webrtc://domain.com/live/stream?txSecret=xxx&txTime=xxx
```

**你的地址：**
```
webrtc://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**特点：**
- ✅ 延迟：< 1秒（超低延迟）
- ✅ 适用场景：实时互动、竞技直播、在线教育
- ✅ 浏览器支持：Chrome、Firefox、Edge、Safari
- ⚠️ 需要：腾讯云快直播（WebRTC）服务
- ⚠️ 费用：比标准直播稍贵（但延迟最低）

**何时使用：**
- 需要实时互动（如连麦、抽奖、竞猜）
- 对延迟要求极高（<2秒）
- 愿意支付额外费用

---

### 2. HTTP-FLV 地址（推荐 - 低延迟）⭐⭐⭐⭐

**格式：**
```
http://domain.com/live/stream.flv?txSecret=xxx&txTime=xxx
```

**你的地址：**
```
http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**特点：**
- ✅ 延迟：2-5秒（低延迟）
- ✅ 适用场景：标准直播、游戏直播、秀场直播
- ✅ 浏览器支持：Chrome、Firefox、Edge、Safari（需播放器）
- ✅ 费用：标准直播价格
- ✅ 兼容性：较好（PC端首选）

**何时使用：**
- 通用直播场景（最常用）
- 需要较低延迟但不要求极致
- PC端网页播放（后台管理）

---

### 3. HLS 地址（推荐 - 高兼容性）⭐⭐⭐⭐⭐

**格式：**
```
http://domain.com/live/stream.m3u8?txSecret=xxx&txTime=xxx
```

**你的地址：**
```
http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**特点：**
- ✅ 延迟：10-30秒（延迟较高）
- ✅ 适用场景：移动端播放、APP直播、跨平台兼容
- ✅ 浏览器支持：所有浏览器（iOS原生支持）
- ✅ 费用：标准直播价格
- ✅ 兼容性：最好（移动端首选）

**何时使用：**
- 移动端H5/APP播放
- 对延迟要求不高（如录播回看）
- 需要最大化兼容性
- iOS设备（Safari原生支持HLS）

---

### 4. RTMP 地址（不推荐）❌

**格式：**
```
rtmp://domain.com/live/stream?txSecret=xxx&txTime=xxx
```

**你的地址：**
```
rtmp://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**特点：**
- ❌ 延迟：低（但无法播放）
- ❌ 浏览器支持：无（Flash已淘汰）
- ❌ 使用场景：仅用于推流（OBS推流地址）
- ⚠️ 不要用于播放！

**说明：**
RTMP现在主要用于推流（主播端），不再用于播放（观众端）。你的OBS软件推流使用RTMP，但观众观看请使用WebRTC/FLV/HLS。

---

## 🎬 后台管理界面使用建议

### 方案A：WebRTC（推荐 - 最佳体验）⭐

**填写地址：**
```
webrtc://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**优势：**
- 🚀 延迟 < 1秒，几乎实时
- ✅ 管理员可以实时监控直播状态
- ✅ 开奖过程实时同步

**劣势：**
- 💰 费用稍高（约为标准直播1.5倍）
- ⚠️ 需要开通快直播服务

---

### 方案B：FLV（推荐 - 性价比高）⭐⭐⭐

**填写地址：**
```
http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**优势：**
- ✅ 延迟2-5秒，可接受
- ✅ 标准直播价格
- ✅ 兼容性好

**劣势：**
- ⚠️ 比WebRTC延迟稍高

---

### 方案C：HLS（备选 - 兼容性优先）

**填写地址：**
```
http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**优势：**
- ✅ 兼容性最好
- ✅ 标准直播价格

**劣势：**
- ⚠️ 延迟10-30秒（较高）
- ⚠️ 实时性差

---

## 📱 玩家端使用建议

### 移动端H5

**推荐：HLS**
```html
<video controls autoplay>
  <source src="http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8" type="application/x-mpegURL">
</video>
```

**原因：**
- iOS原生支持HLS
- Android兼容性好
- 不需要额外插件

---

### 移动端APP

**推荐：FLV > HLS**
```kotlin
// Android - 使用腾讯云播放器SDK
TXLivePlayer player = new TXLivePlayer(context);
player.startPlay("http://tencent2.kkcnw.cn/live/mojiangjuan.flv", TXLivePlayer.PLAY_TYPE_LIVE_FLV);
```

**原因：**
- FLV延迟更低
- SDK性能优化好
- HLS作为备选

---

### PC网页

**推荐：WebRTC > FLV > HLS**
```javascript
// 使用TCPlayer
const player = TCPlayer('player', {
  webrtc: 'webrtc://tencent2.kkcnw.cn/live/mojiangjuan',  // 优先WebRTC
  flv: 'http://tencent2.kkcnw.cn/live/mojiangjuan.flv',    // 降级到FLV
  m3u8: 'http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8'   // 最后降级到HLS
});
```

**原因：**
- WebRTC延迟最低
- FLV兼容性好
- HLS作为最后保底

---

## 🔐 地址鉴权参数说明

你的所有地址都包含两个鉴权参数：

### txSecret（防盗链签名）
```
txSecret=0a48fffa903df7c98470003936a1e85b
```

**作用：** 防止未授权访问

**生成方法：**
```
MD5(key + StreamName + txTime)
```

---

### txTime（过期时间）
```
txTime=6A34E266
```

**作用：** 设置地址有效期

**转换：** 十六进制时间戳
```javascript
parseInt('6A34E266', 16)  // 转为Unix时间戳
// 结果：1781793382（2026-06-14 左右）
```

**检查是否过期：**
```bash
# 当前时间
date +%s
# 输出：1718679600（举例）

# 地址过期时间
echo $((0x6A34E266))
# 输出：1781793382

# 如果 当前时间 > 过期时间，则地址已过期
```

---

## ⏰ 地址过期处理

### 检查地址是否过期

**方法1：在线工具**
```
访问：https://tool.lu/timestamp/
输入：6A34E266（16进制）
查看：对应的日期时间
```

**方法2：JavaScript**
```javascript
const txTime = parseInt('6A34E266', 16);
const expireDate = new Date(txTime * 1000);
console.log('地址过期时间：', expireDate);

if (Date.now() / 1000 > txTime) {
  console.log('❌ 地址已过期');
} else {
  console.log('✅ 地址仍有效');
}
```

---

### 重新生成地址

**登录腾讯云控制台：**
1. 访问：https://console.cloud.tencent.com/live/tools/address
2. 填写：
   - 推流域名：tencent2.kkcnw.cn
   - AppName：live
   - StreamName：mojiangjuan
   - 过期时间：选择未来时间（如30天后）
3. 点击「生成地址」
4. 获取新的4种播放地址（含新的txSecret和txTime）

---

## 🧪 测试步骤

### 1. 测试WebRTC地址

**进入后台：**
```
活动列表 → 更多操作 → 添加直播地址
```

**填入：**
```
webrtc://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**预览：**
```
活动列表 → 更多操作 → 预览直播
```

**预期：**
- ✅ 播放器显示「WebRTC（超低延遲 <1秒）」
- ✅ 加载提示：🚀 WebRTC 超低延遲直播
- ✅ 2秒内开始播放（如果直播流已推送）
- ✅ 延迟 < 1秒

---

### 2. 测试FLV地址

**填入：**
```
http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**预期：**
- ✅ 播放器显示「HTTP-FLV（低延遲 2-5秒）」
- ✅ 3秒内开始播放
- ✅ 延迟 2-5秒

---

### 3. 测试HLS地址

**填入：**
```
http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8?txSecret=0a48fffa903df7c98470003936a1e85b&txTime=6A34E266
```

**预期：**
- ✅ 播放器显示「HLS（高兼容性 10-30秒）」
- ✅ 5秒内开始播放
- ✅ 延迟 10-30秒

---

### 4. 对比测试（推荐）

**同时打开3个浏览器窗口：**
- 窗口1：WebRTC地址
- 窗口2：FLV地址
- 窗口3：HLS地址

**观察：**
- WebRTC最快（几乎实时）
- FLV稍慢（2-5秒）
- HLS最慢（10-30秒）

**选择：**
根据实际需求选择合适的协议。

---

## 💰 费用对比

### 腾讯云直播计费（参考价格）

**标准直播（FLV/HLS）：**
- 流量计费：0.26元/GB（中国大陆）
- 带宽峰值计费：0.64元/Mbps/天

**快直播（WebRTC）：**
- 流量计费：0.52元/GB（约为标准直播2倍）
- 带宽峰值计费：1.28元/Mbps/天

**示例：**
```
假设1小时直播：
- 码率：2Mbps
- 观看人数：100人
- 流量：2Mbps × 3600秒 × 100人 / 8 / 1024 = 约88GB

标准直播费用：88GB × 0.26元 ≈ 23元
快直播费用：88GB × 0.52元 ≈ 46元
```

**建议：**
- 预算充足：使用WebRTC（最佳体验）
- 预算有限：使用FLV（性价比高）
- 观众多：考虑CDN加速（降低流量成本）

---

## 🎯 最终推荐配置

### 后台管理界面（本次配置）

**推荐地址：**
```
http://tencent2.kkcnw.cn/live/mojiangjuan.flv
```

**理由：**
- ✅ 延迟低（2-5秒），满足管理员实时监控需求
- ✅ 费用标准，性价比高
- ✅ 兼容性好，浏览器直接播放

**备选地址（如需极致体验）：**
```
webrtc://tencent2.kkcnw.cn/live/mojiangjuan
```

---

### 玩家端H5/APP（未来对接）

**推荐地址：**
```
移动端：http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8
PC端：  http://tencent2.kkcnw.cn/live/mojiangjuan.flv
```

**理由：**
- 移动端HLS兼容性最好
- PC端FLV延迟更低

---

## 📞 技术支持

### 腾讯云文档

- **直播播放**：https://cloud.tencent.com/document/product/267/32733
- **地址生成器**：https://console.cloud.tencent.com/live/tools/address
- **快直播（WebRTC）**：https://cloud.tencent.com/document/product/454/55880
- **播放器SDK**：https://cloud.tencent.com/document/product/881

### 问题排查

**播放失败：**
1. 检查地址是否过期（txTime）
2. 确认直播流是否正在推送
3. 查看控制台错误日志

**延迟过高：**
1. 检查使用的协议（WebRTC最低）
2. 优化推流参数（降低GOP）
3. 考虑升级到快直播

**卡顿：**
1. 检查网络带宽
2. 降低码率
3. 使用CDN加速

---

## ✅ 下一步操作

1. **选择协议**：
   - 预算充足：WebRTC
   - 通用场景：FLV ⭐⭐⭐
   - 移动优先：HLS

2. **填写地址**：
   ```
   后台管理 → 摸奖券活动列表 → 添加直播地址
   ```

3. **预览测试**：
   ```
   点击「预览直播」确认播放正常
   ```

4. **玩家端集成**（可选）：
   - H5页面使用HLS
   - APP使用腾讯云播放器SDK

---

*最后更新：2026-06-17*  
*版本：v1.0*  
*作者：Claude Code*
