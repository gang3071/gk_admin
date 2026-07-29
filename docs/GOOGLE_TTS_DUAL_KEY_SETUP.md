# Google TTS 双 Key 配置指南

本系统支持为 Gemini TTS 和 Wavenet TTS 配置**两个独立的 API Key**，以实现更安全的权限隔离。

## 📋 为什么使用两个 Key？

| 方案 | 优点 | 缺点 |
|------|------|------|
| **单 Key** | 简单方便，一个 Key 通用 | Key 泄露风险较大 |
| **双 Key** | 权限隔离，安全性更高 | 需要创建和管理两个 Key |

**推荐场景：**
- ✅ 生产环境（安全优先）
- ✅ 多人协作项目
- ✅ 需要精细权限控制

## 🔑 创建两个 API Key

### Key 1: Gemini TTS 专用 Key

**用途：** 调用 Gemini 3.1 Flash TTS API

**创建步骤：**

1. 访问 [Google Cloud Console - Credentials](https://console.cloud.google.com/apis/credentials)
2. 点击 **CREATE CREDENTIALS** → **API key**
3. 创建后立即点击 **编辑**（铅笔图标）
4. 设置 **Application restrictions**（推荐）：
   ```
   ● IP addresses (servers)
   添加服务器 IP：xxx.xxx.xxx.xxx
   ```
5. 设置 **API restrictions**：
   ```
   ● Restrict key
   
   Select APIs:
   ☑️ Generative Language API
   ☑️ Cloud Storage API
   ```
6. 点击 **Save**
7. 复制 Key，添加到 `.env`：
   ```bash
   GOOGLE_TTS_GEMINI_API_KEY=你的Gemini_API_Key
   ```

---

### Key 2: Wavenet TTS 专用 Key

**用途：** 调用传统 Cloud Text-to-Speech API（Wavenet）

**创建步骤：**

1. 访问 [Google Cloud Console - Credentials](https://console.cloud.google.com/apis/credentials)
2. 点击 **CREATE CREDENTIALS** → **API key**
3. 创建后立即点击 **编辑**（铅笔图标）
4. 设置 **Application restrictions**（推荐）：
   ```
   ● IP addresses (servers)
   添加服务器 IP：xxx.xxx.xxx.xxx
   ```
5. 设置 **API restrictions**：
   ```
   ● Restrict key
   
   Select APIs:
   ☑️ Cloud Text-to-Speech API
   ☑️ Cloud Storage API
   ```
6. 点击 **Save**
7. 复制 Key，添加到 `.env`：
   ```bash
   GOOGLE_TTS_WAVENET_API_KEY=你的Wavenet_API_Key
   ```

## ⚙️ 配置 .env 文件

完整配置示例：

```bash
# ============================
# Google Cloud Text-to-Speech 配置（双 Key 模式）
# ============================

# Gemini TTS API Key（Gemini 3.1 Flash TTS 专用）
GOOGLE_TTS_GEMINI_API_KEY=AIzaSyAbc123...你的Gemini_Key

# Wavenet TTS API Key（传统 Wavenet TTS 专用）
GOOGLE_TTS_WAVENET_API_KEY=AIzaSyDef456...你的Wavenet_Key

# 启用 Gemini TTS（true = Gemini，false = Wavenet）
GOOGLE_TTS_USE_GEMINI=true

# Gemini TTS 语音模型
GOOGLE_TTS_GEMINI_VOICE=Kore

# Gemini TTS 风格指令
GOOGLE_TTS_GEMINI_STYLE="Read aloud in a clear, professional customer service voice, warm and attentive, as if a waitress is politely announcing a customer request."

# 传统 Wavenet TTS 配置（备用）
GOOGLE_TTS_LANGUAGE=cmn-TW
GOOGLE_TTS_VOICE=cmn-TW-Wavenet-A
GOOGLE_TTS_SPEAKING_RATE=1.0
GOOGLE_TTS_PITCH=0.0
GOOGLE_TTS_VOLUME_GAIN=0.0
GOOGLE_TTS_AUDIO_ENCODING=MP3
```

## 🔄 工作原理

系统会根据 `GOOGLE_TTS_USE_GEMINI` 的值自动选择对应的 API Key：

### 使用 Gemini TTS
```bash
GOOGLE_TTS_USE_GEMINI=true
```
↓
```php
// 系统自动使用
$apiKey = env('GOOGLE_TTS_GEMINI_API_KEY');
// 调用 Gemini API
POST https://generativelanguage.googleapis.com/v1beta/...
Authorization: {$apiKey}
```

### 使用 Wavenet TTS
```bash
GOOGLE_TTS_USE_GEMINI=false
```
↓
```php
// 系统自动使用
$apiKey = env('GOOGLE_TTS_WAVENET_API_KEY');
// 调用 Wavenet API
POST https://texttospeech.googleapis.com/v1/text:synthesize?key={$apiKey}
```

## 🔍 验证配置

### 1. 检查 API 是否启用

访问以下链接，确认两个 API 都已启用：

- Gemini API: https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com
- Wavenet API: https://console.cloud.google.com/apis/api/texttospeech.googleapis.com

状态应为 **"已启用"（Enabled）**

### 2. 测试 Gemini TTS

```bash
# 在 .env 中设置
GOOGLE_TTS_USE_GEMINI=true
GOOGLE_TTS_GEMINI_API_KEY=你的Key
```

后台点击设备的 **"重新生成语音"** 按钮，应该成功生成。

### 3. 测试 Wavenet TTS

```bash
# 在 .env 中设置
GOOGLE_TTS_USE_GEMINI=false
GOOGLE_TTS_WAVENET_API_KEY=你的Key
```

后台点击设备的 **"重新生成语音"** 按钮，应该成功生成。

## 🔐 安全最佳实践

### 1. 设置 IP 限制
每个 Key 都应该限制只能从服务器 IP 访问：
```
Application restrictions:
● IP addresses (servers)
  xxx.xxx.xxx.xxx（服务器 IP）
```

### 2. 定期轮换 Key
建议每 3-6 个月更换一次 API Key：
1. 创建新 Key
2. 更新 `.env` 文件
3. 重启服务
4. 删除旧 Key

### 3. 监控使用量
在 Google Cloud Console 设置配额监控：
- **APIs & Services** → **Quotas**
- 设置每日请求上限
- 设置预算提醒

### 4. Key 泄露处理
如果 Key 泄露：
1. 立即在 Console 中删除该 Key
2. 创建新 Key 并更新配置
3. 检查账单，确认没有异常消费
4. 考虑限制该 Key 所在项目的权限

## 🆘 常见问题

### Q1: 只配置了一个 Key 会怎样？

**A:** 系统会报错并提示缺少对应的 Key。

例如，如果只配置了 `GOOGLE_TTS_GEMINI_API_KEY`：
- ✅ `GOOGLE_TTS_USE_GEMINI=true` - 正常工作
- ❌ `GOOGLE_TTS_USE_GEMINI=false` - 报错：Wavenet TTS API Key 未配置

**兼容处理：** 如果只配置了旧的 `GOOGLE_TTS_API_KEY`，Wavenet 会尝试使用它。

### Q2: 两个 Key 可以是同一个吗？

**A:** 技术上可以，但**不推荐**。这样做失去了权限隔离的意义。

如果想用一个 Key，建议直接使用旧的单 Key 模式：
```bash
GOOGLE_TTS_API_KEY=通用Key
```
然后不设置 `GOOGLE_TTS_GEMINI_API_KEY` 和 `GOOGLE_TTS_WAVENET_API_KEY`。

### Q3: 如何切换回单 Key 模式？

**A:** 有两种方式：

**方式 1：使用兼容配置**
```bash
# 只设置旧的通用 Key
GOOGLE_TTS_API_KEY=通用Key

# 不设置新的专用 Key
# GOOGLE_TTS_GEMINI_API_KEY=
# GOOGLE_TTS_WAVENET_API_KEY=
```

**方式 2：两个专用 Key 设置为同一个值**
```bash
GOOGLE_TTS_GEMINI_API_KEY=通用Key
GOOGLE_TTS_WAVENET_API_KEY=通用Key
```

但这样会失去权限隔离的安全优势。

### Q4: Key 的权限设置错了会怎样？

**A:** 会返回 403 错误，提示 API key not valid。

**解决方法：**
1. 访问 Console 编辑对应的 Key
2. 检查 API restrictions 是否正确勾选
3. 保存后等待 1-2 分钟生效

## 📊 成本对比

| 配置 | 安全性 | 管理复杂度 | Key 数量 |
|------|--------|-----------|---------|
| **双 Key（推荐）** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | 2 个 |
| **单 Key** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | 1 个 |

**生产环境推荐使用双 Key 模式！**

## 🔗 相关链接

- [Google Cloud Console](https://console.cloud.google.com/)
- [API Credentials 管理](https://console.cloud.google.com/apis/credentials)
- [Generative Language API](https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com)
- [Cloud Text-to-Speech API](https://console.cloud.google.com/apis/api/texttospeech.googleapis.com)
- [Gemini API 文档](https://ai.google.dev/gemini-api/docs/models/gemini-3.1-flash-tts-preview)
- [Wavenet TTS 文档](https://cloud.google.com/text-to-speech/docs)
