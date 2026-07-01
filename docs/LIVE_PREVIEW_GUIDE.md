# 📺 后台直播预览功能使用指南

## 功能概述

后台管理员可以在摸奖券活动列表中直接预览直播，无需离开管理界面。

---

## 🎯 使用步骤

### 1. 设置直播地址

在活动列表中，点击活动卡片右上角的「更多操作」按钮：

```
活动列表 → 更多操作 → 添加直播地址 / 编辑直播地址
```

**支持的直播地址格式：**

| 协议 | 格式 | 兼容性 | 推荐度 |
|------|------|--------|--------|
| HLS | http://xxx.com/live/xxx.m3u8 | ⭐⭐⭐⭐⭐ | 强烈推荐 |
| HTTP-FLV | http://xxx.com/live/xxx.flv | ⭐⭐⭐⭐ | 推荐 |
| RTMP | rtmp://xxx.com/live/xxx | ⭐⭐ | 不推荐 |
| HTTP | http://xxx.com/video.mp4 | ⭐⭐⭐⭐ | 适用于录播 |

**示例地址：**
```
RTMP: rtmp://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx
HLS:  http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8?txSecret=xxx&txTime=xxx
FLV:  http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=xxx&txTime=xxx
```

---

### 2. 预览直播

设置直播地址后，「预览直播」菜单项会自动显示：

```
活动列表 → 更多操作 → 预览直播
```

点击后会弹出全屏播放器窗口（90%宽度 × 70vh高度）。

---

### 3. 播放器功能

**自动功能：**
- ✅ 自动检测协议类型（RTMP/HLS/FLV/HTTP）
- ✅ 自动播放直播流
- ✅ 显示加载状态和播放状态
- ✅ 心跳检测（防止长时间无响应）

**手动控制：**
- 🔊 音量调节
- ⏸️ 暂停/播放
- 🖥️ 全屏播放
- 🔄 错误重试

**信息栏显示：**
- 🟢 直播状态指示灯（绿色=直播中，红色=错误）
- 📡 协议类型显示（RTMP/HLS/FLV/HTTP）
- 📋 活动名称
- 🔗 直播地址

**工具按钮：**
- 📋 **复制地址**：一键复制直播地址到剪贴板
- 🔗 **新窗口打开**：在独立窗口中打开播放器（1280×720）

---

## ⚠️ RTMP 协议特别说明

### 问题

你填写的是RTMP格式的直播地址：
```
rtmp://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx
```

**RTMP协议在现代浏览器中存在兼容性问题：**
- ❌ Chrome、Firefox、Edge 等浏览器已移除 Flash 插件支持
- ❌ 无法直接播放 RTMP 流
- ❌ 移动端浏览器完全不支持

当使用RTMP地址时，播放器会显示黄色警告条：

```
⚠️ RTMP 协议播放提示

RTMP协议在现代浏览器中可能无法播放（需要Flash支持）。
建议联系腾讯云客服获取 HLS播放地址（.m3u8格式）或 HTTP-FLV格式，
以获得更好的兼容性。

查看腾讯云直播播放文档 →
```

---

### 解决方案

#### 方案1：获取HLS播放地址（强烈推荐）⭐

联系腾讯云客服或登录腾讯云控制台，获取同一直播流的HLS播放地址。

**腾讯云直播地址转换规则：**

```
RTMP推流地址：
rtmp://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx

对应的HLS播放地址（将RTMP改为HTTP，添加.m3u8）：
http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8?txSecret=xxx&txTime=xxx

对应的FLV播放地址：
http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=xxx&txTime=xxx
```

**获取步骤：**

1. 登录腾讯云控制台：https://console.cloud.tencent.com/live
2. 进入「云直播」→「流管理」
3. 找到你的直播流：`mojiangjuan`
4. 点击「播放地址」，获取多种协议地址

**或者使用腾讯云提供的地址生成器：**
https://console.cloud.tencent.com/live/tools/address

---

#### 方案2：使用OBS转推（临时方案）

如果无法获取HLS地址，可以用OBS软件转推：

```
RTMP输入 → OBS → HLS输出
```

**步骤：**
1. 下载安装OBS Studio
2. 添加「媒体源」，输入RTMP地址
3. 设置「流」输出为HLS格式
4. 将生成的.m3u8地址填入后台

---

#### 方案3：使用第三方转码服务

使用 FFmpeg 或云服务（如阿里云、七牛云）进行实时转码：

```bash
# 使用FFmpeg转码
ffmpeg -i "rtmp://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx" \
  -c copy -f hls -hls_time 2 -hls_list_size 5 \
  /path/to/output.m3u8
```

---

## 🎬 播放器技术细节

### 支持的协议

播放器会自动检测URL协议类型：

```javascript
// 检测逻辑
if (url.startsWith('rtmp://')) → RTMP协议
if (url.includes('.m3u8'))    → HLS协议
if (url.includes('.flv'))     → FLV协议
if (url.startsWith('http'))   → HTTP协议
```

### 腾讯云播放器配置

使用的是腾讯云官方播放器 **TCPlayer 4.7.2**：

```javascript
player = TCPlayer('player-container', {
  autoplay: true,      // 自动播放
  preload: 'auto',     // 预加载
  live: true,          // 直播模式
  rtmp: liveUrl,       // RTMP地址
  m3u8: liveUrl,       // HLS地址
  flv: liveUrl,        // FLV地址
  // ...
});
```

---

### 播放状态监听

```javascript
player.on('playing', () => {
  // 播放中
  显示「直播中」状态
});

player.on('waiting', () => {
  // 缓冲中
  显示「缓冲中...」状态
});

player.on('error', (err) => {
  // 播放错误
  显示错误提示和重试按钮
});
```

---

## 🔧 常见问题

### Q1: 点击"预览直播"后黑屏

**可能原因：**
1. 直播流尚未推送（主播未开播）
2. RTMP协议不兼容
3. 网络连接问题
4. 直播地址已过期（txTime参数）

**解决办法：**
1. 检查直播流是否已推送
2. 更换为HLS或FLV地址
3. 检查网络连接
4. 重新生成直播地址（更新txTime和txSecret）

---

### Q2: 显示"连接失败"

**排查步骤：**

1. **验证地址有效性**
   ```bash
   # 在浏览器直接访问（HLS）
   http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8
   
   # 使用VLC播放器测试（RTMP）
   rtmp://tencent2.kkcnw.cn/live/mojiangjuan
   ```

2. **检查防火墙**
   - RTMP端口：1935
   - HTTP端口：80/443

3. **检查域名解析**
   ```bash
   ping tencent2.kkcnw.cn
   ```

4. **查看浏览器控制台**
   - 按F12打开开发者工具
   - 查看Console标签页的错误信息
   - 查看Network标签页的请求状态

---

### Q3: 音视频不同步

**解决办法：**
- 刷新播放器（关闭Modal重新打开）
- 使用「新窗口打开」功能
- 检查网络带宽是否充足

---

### Q4: 延迟过高

**优化建议：**

1. **使用低延迟协议**
   - HTTP-FLV > HLS > RTMP
   - HLS延迟通常10-30秒
   - FLV延迟通常2-5秒

2. **腾讯云快直播**
   - 延迟可降至1秒以内
   - 需要额外配置

3. **优化网络**
   - 使用CDN加速
   - 选择就近节点

---

### Q5: 手机端无法播放

**原因：**
- 移动端浏览器对RTMP支持极差
- 部分浏览器禁止自动播放

**解决办法：**
1. **使用HLS地址**（移动端兼容性最好）
2. **点击播放按钮**（如果自动播放被阻止）
3. **使用原生APP**（集成播放器SDK）

---

## 📱 移动端适配

播放器已完全响应式，支持移动设备：

**特性：**
- ✅ 自适应屏幕尺寸
- ✅ 触摸控制支持
- ✅ 全屏播放支持
- ✅ iOS/Android兼容

**最佳实践：**
```html
<!-- 播放器自动适配 -->
<video
  playsinline           <!-- iOS内联播放 -->
  webkit-playsinline    <!-- iOS兼容 -->
  x5-playsinline        <!-- 微信/QQ浏览器 -->
>
```

---

## 🎨 自定义播放器页面

播放器页面位置：`D:\gk_admin\public\live-player.html`

**可自定义内容：**
1. 播放器样式（CSS）
2. 加载动画
3. 错误提示文案
4. 控制栏布局

**示例修改：**

```html
<!-- 修改加载文案 -->
<div class="loading-text">正在連接直播...</div>
<!-- 改为 -->
<div class="loading-text">直播马上开始，请稍候...</div>

<!-- 修改错误提示 -->
<div class="error-title">直播連接失敗</div>
<!-- 改为 -->
<div class="error-title">暂时无法观看直播</div>
```

---

## 📊 性能优化

### 1. 加载优化

```javascript
// 预加载设置
preload: 'auto',  // 自动预加载
autoplay: true,   // 自动播放
```

### 2. 缓存策略

```javascript
// 3秒后显示缓冲提示
setTimeout(() => {
  if (loading) {
    showText('連接時間較長，請稍候...');
  }
}, 3000);
```

### 3. 心跳检测

防止长时间无响应：

```javascript
// 每30秒检查一次
setInterval(() => {
  if (ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify({ type: 'ping' }));
  }
}, 30000);
```

---

## 🔒 安全注意事项

### 1. 直播地址加密

腾讯云直播地址包含鉴权参数：

```
?txSecret=0a48fffa903df7c98470003936a1e85b  // 防盗链签名
&txTime=6A34E266                             // 过期时间（Unix时间戳）
```

**建议：**
- 定期更新txSecret和txTime
- 不要将地址公开在前端代码中
- 使用服务端动态生成播放地址

---

### 2. 访问控制

```javascript
// 只有登录管理员才能预览
if (!Admin::user()) {
    return message_error('未授权');
}

// 只能预览自己渠道的活动
if ($activity->department_id !== Admin::user()->department_id) {
    return message_error('无权访问');
}
```

---

### 3. HTTPS加密

生产环境建议使用HTTPS：

```
http://xxx.com/live/xxx.m3u8  → https://xxx.com/live/xxx.m3u8
rtmp://xxx.com/live/xxx       → rtmps://xxx.com/live/xxx
```

---

## 📞 技术支持

### 腾讯云直播文档

- 播放配置：https://cloud.tencent.com/document/product/267/32733
- 地址生成器：https://console.cloud.tencent.com/live/tools/address
- SDK下载：https://cloud.tencent.com/document/product/267/51426

### 问题反馈

如遇到技术问题，请提供以下信息：

1. 直播地址（可脱敏）
2. 浏览器类型和版本
3. 控制台错误信息（F12 → Console）
4. 网络请求状态（F12 → Network）
5. 截图或录屏

---

## 🎉 总结

**优势：**
- ✅ 无需离开管理界面即可预览直播
- ✅ 支持多种协议自动检测
- ✅ 完整的错误处理和重试机制
- ✅ 响应式设计，支持移动端

**建议：**
- ⭐ 优先使用HLS格式（.m3u8）
- ⭐ 避免使用RTMP格式
- ⭐ 定期更新直播地址的鉴权参数

**下一步：**
1. 联系腾讯云获取HLS播放地址
2. 更新活动的直播地址
3. 点击「预览直播」测试播放效果
4. 确认无误后开始直播

---

*最后更新：2026-06-17*  
*版本：v1.0*
