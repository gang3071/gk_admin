# 📡 摸奖券直播流名称实现方案

## 🎯 设计理念

**核心思想：** 只存储流名称，播放地址实时生成

### 为什么这样设计？

1. **避免地址过期** - txTime参数有有效期，存储流名称可以实时生成新地址
2. **简化操作** - 管理员只需填写流名称（如：`mojiangjuan`），无需手动复制腾讯云地址
3. **配置集中** - 腾讯云配置统一在`machine_tencent_play`表管理，修改配置自动生效

---

## 📋 实现方案

### 1. 数据存储

**lottery_ticket_activity.live_url字段：**
```sql
-- 旧方案（已废弃）
live_url = 'http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=xxx&txTime=xxx'

-- ✅ 新方案
live_url = 'mojiangjuan'  -- 只存储流名称
```

---

### 2. 添加直播流名称

#### 前端界面

**Modal标题：** "设置直播流名称"

**输入框：**
```vue
<a-input
    v-model:value="liveUrlInput"
    placeholder="例如：mojiangjuan"
    allow-clear
>
  <template #prefix>
    <video-camera-outlined/>
  </template>
</a-input>
```

**提示信息：**
- 💡 只需填写流名称，系统会自动生成腾讯云直播地址
- 建议使用英文、数字、下划线，此名称需与OBS推流配置一致

---

#### 验证规则

```javascript
// 1. 非空验证
if (!streamName) {
  this.$message.error('请输入直播流名称');
  return;
}

// 2. 格式验证（只允许英文、数字、下划线）
if (!/^[a-zA-Z0-9_]+$/.test(streamName)) {
  this.$message.error('流名称只能包含英文、数字和下划线');
  return;
}

// 3. 长度验证
if (streamName.length > 50) {
  this.$message.error('流名称不能超过50个字符');
  return;
}
```

---

#### 提交数据

```javascript
const res = await this.$request({
  url: 'ex-admin/.../updateLiveUrl',
  method: 'post',
  data: {
    id: activity.id,
    live_url: streamName  // ⭐ 只提交流名称，不是完整URL
  }
});
```

---

### 3. 预览直播

#### 工作流程

```
用户点击"预览直播"
    ↓
检查是否设置流名称
    ↓
调用API获取播放器配置
    ↓
API返回FLV播放地址
    ↓
打开播放器Modal
    ↓
播放器加载并播放
```

---

#### Vue实现

```javascript
async previewLive(activity) {
  // 1. 验证流名称
  if (!activity.live_url) {
    this.$message.warning('该活动尚未设置直播流名称');
    return;
  }

  // 2. 调用API获取播放器配置
  const res = await this.$request({
    url: 'ex-admin/.../getLivePlayerConfig',
    method: 'post',
    data: {
      stream_name: activity.live_url  // 传递流名称
    }
  });

  // 3. 打开播放器
  if (res.code === 0) {
    this.livePreviewUrl = res.data.play_url; // FLV地址
    this.livePreviewVisible = true;
  }
}
```

---

### 4. 后端API

#### getLivePlayerConfig() - 获取播放器配置

**接口路径：**
```
POST /ex-admin/channel-lottery-ticket-activity/getLivePlayerConfig
```

**请求参数：**
```json
{
  "stream_name": "mojiangjuan"
}
```

**返回数据：**
```json
{
  "code": 0,
  "message": "获取播放器配置成功",
  "data": {
    "stream_name": "mojiangjuan",
    "play_url": "http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=xxx&txTime=xxx",
    "urls": {
      "flv": "http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=xxx&txTime=xxx",
      "hls": "http://tencent2.kkcnw.cn/live/mojiangjuan.m3u8?txSecret=xxx&txTime=xxx",
      "webrtc": "webrtc://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx"
    },
    "push_url": "rtmp://tencent2.kkcnw.cn/live/mojiangjuan?txSecret=xxx&txTime=xxx",
    "expire_time": "2026-06-18 12:00:00",
    "player_config": {
      "autoplay": true,
      "live": true,
      "language": "zh-TW",
      "license": "https://license.vod2.myqcloud.com/license/v2/xxx/v_cube.license",
      "licenseKey": "xxxxxxxxxxxxxx"
    }
  }
}
```

---

#### 实现代码

```php
public function getLivePlayerConfig()
{
    try {
        $streamName = Request::input('stream_name');

        if (empty($streamName)) {
            return message_error('流名称不能为空');
        }

        // 获取腾讯云配置（包含license信息）
        $config = \addons\webman\model\MachineTencentPlay::query()->find(1);

        if (!$config) {
            return message_error('腾讯云配置不存在');
        }

        // 生成播放地址
        $urls = generateLotteryLiveUrls(1, $streamName, 1);

        // 返回播放器配置
        return Response::success([
            'stream_name' => $streamName,
            'play_url' => $urls['flv'], // 默认FLV
            'urls' => [
                'flv' => $urls['flv'],
                'hls' => $urls['hls'],
                'webrtc' => $urls['webrtc'],
            ],
            'push_url' => $urls['rtmp'], // OBS推流地址
            'expire_time' => $urls['expire_time'],
            'player_config' => [
                'autoplay' => true,
                'live' => true,
                'language' => 'zh-TW',
                'license' => $config->license, // TCPlayer许可证URL
                'licenseKey' => $config->license_key, // TCPlayer许可证KEY
            ],
        ], '获取播放器配置成功');

    } catch (\Exception $e) {
        \support\Log::error('[摸奖券] 获取播放器配置失败', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        return message_error($e->getMessage());
    }
}
```

---

### 5. 播放器页面

**文件：** `public/lottery-live-player.html`

**当前实现：** 使用TCPlayer v5，通过URL参数接收播放地址

```javascript
// 获取URL参数
liveUrl = getUrlParam('url');

// 初始化播放器
player = TCPlayer('player-container-id', {
  flv: liveUrl,  // FLV地址
  autoplay: true,
  live: true,
  ...
});
```

**未来增强：** 可以让播放器直接调用API获取配置

---

## 🔄 完整流程示例

### 场景：管理员设置并预览直播

#### 步骤1：设置流名称

```
管理员操作：
1. 进入摸奖券活动列表
2. 点击"更多操作" → "添加直播地址"
3. 输入：mojiangjuan
4. 点击"确定"

后端操作：
UPDATE lottery_ticket_activity 
SET live_url = 'mojiangjuan' 
WHERE id = 10;
```

---

#### 步骤2：预览直播

```
管理员操作：
1. 点击"更多操作" → "预览直播"

前端处理：
1. 检查activity.live_url = 'mojiangjuan' ✅
2. 调用API：getLivePlayerConfig({ stream_name: 'mojiangjuan' })

后端处理：
1. 读取machine_tencent_play配置（ID=1）
2. 生成防盗链：
   - txTime = 当前时间 + 1天（十六进制）
   - txSecret = MD5(pull_key + 'mojiangjuan' + txTime)
3. 返回FLV地址：
   http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=新生成&txTime=新生成

前端处理：
4. 打开Modal
5. iframe加载：/lottery-live-player.html?url=http://...
6. TCPlayer播放
```

---

#### 步骤3：OBS推流

**管理员操作：**
1. 可选：在预览Modal点击"复制地址"
2. 打开OBS
3. 配置推流：
   - 服务器：`rtmp://tencent2.kkcnw.cn/live/`
   - 串流密钥：`mojiangjuan?txSecret=xxx&txTime=xxx`
4. 开始推流

---

## 💡 优势对比

### 旧方案（手动填写完整URL）

**❌ 缺点：**
1. 需要手动复制腾讯云地址（繁琐）
2. 地址过期后需要重新填写
3. txSecret/txTime需要手动生成
4. 容易填错

**示例：**
```
管理员需要填写：
http://tencent2.kkcnw.cn/live/mojiangjuan.flv?txSecret=0a48f...&txTime=6A34E266
```

---

### 新方案（只填流名称）

**✅ 优点：**
1. 简单：只需填写`mojiangjuan`
2. 永不过期：每次预览实时生成新地址
3. 自动化：txSecret/txTime自动计算
4. 灵活：切换腾讯云配置自动生效

**示例：**
```
管理员只需填写：
mojiangjuan
```

---

## 🔧 技术细节

### 防盗链签名生成

**算法：**
```
txTime = 十六进制(当前时间戳 + 有效期)
txSecret = MD5(pull_key + stream_name + txTime)
```

**示例：**
```php
// 当前时间：2026-06-17 12:00:00
// 有效期：1天
$expireTimestamp = time() + 86400;  // 1718793600
$txTime = strtoupper(base_convert($expireTimestamp, 10, 16));  // "6A5C9A80"

// 配置：pull_key = "abc123"
// 流名称："mojiangjuan"
$txSecret = md5("abc123" . "mojiangjuan" . "6A5C9A80");
// 结果："f7c2e1a4..."
```

---

### 默认配置选择

**当前实现：** 固定使用`machine_tencent_play.id = 1`

```php
// 硬编码方式
$urls = generateLotteryLiveUrls(1, $streamName, 1);
```

**未来改进：** 可以增加配置选择

```php
// 从系统配置读取默认ID
$configId = admin_sysconf('lottery_live_default_config_id', 1);

// 或从渠道配置读取
$configId = Admin::user()->department->tencent_play_config_id ?? 1;

$urls = generateLotteryLiveUrls($configId, $streamName, 1);
```

---

### 地址有效期策略

**预览地址：** 1天
```php
// 只用于临时预览，1天足够
generateLotteryLiveUrls(1, $streamName, 1);
```

**正式直播：** 30天
```php
// 活动可能持续数天，给足够的有效期
generateLotteryLiveUrls(1, $streamName, 30);
```

**最佳实践：** 每次预览都重新生成，确保地址始终有效

---

## 📊 数据流图

```
┌─────────────────────────────────────────────────────────────┐
│                       数据流向图                             │
└─────────────────────────────────────────────────────────────┘

                    管理员填写
                        │
                        ▼
                 ┌──────────────┐
                 │  mojiangjuan │ (流名称)
                 └──────────────┘
                        │
                        ▼ 存储
        ┌───────────────────────────────┐
        │ lottery_ticket_activity表      │
        │ live_url = 'mojiangjuan'      │
        └───────────────────────────────┘
                        │
                        ▼ 点击预览
        ┌───────────────────────────────┐
        │ getLivePlayerConfig API       │
        │ ← stream_name: mojiangjuan    │
        └───────────────────────────────┘
                        │
                        ▼ 查询配置
        ┌───────────────────────────────┐
        │ machine_tencent_play表         │
        │ pull_domain: tencent2.kkcnw.cn│
        │ pull_key: 防盗链密钥           │
        └───────────────────────────────┘
                        │
                        ▼ 生成地址
        ┌───────────────────────────────┐
        │ generateLotteryLiveUrls()     │
        │ ├─ 计算txTime (1天后)         │
        │ ├─ 计算txSecret (MD5)         │
        │ └─ 生成4种协议地址            │
        └───────────────────────────────┘
                        │
                        ▼ 返回
        ┌───────────────────────────────┐
        │ http://tencent2.kkcnw.cn/live/│
        │ mojiangjuan.flv?txSecret=xxx  │
        │ &txTime=6A5C9A80              │
        └───────────────────────────────┘
                        │
                        ▼ 播放
        ┌───────────────────────────────┐
        │ TCPlayer v5播放器              │
        │ ├─ 协议检测：FLV              │
        │ ├─ 连接直播流                 │
        │ └─ 开始播放                   │
        └───────────────────────────────┘
```

---

## ✅ 验收标准

### 功能测试

- [ ] 填写流名称（如`mojiangjuan`）并保存成功
- [ ] 流名称格式验证正常（只允许英文、数字、下划线）
- [ ] 点击"预览直播"能正常打开播放器
- [ ] 播放地址为FLV格式
- [ ] 播放地址包含正确的txSecret和txTime
- [ ] OBS推流后播放器能正常显示画面
- [ ] 地址1天后重新预览，生成新的txTime（不会过期）

---

### 错误处理

- [ ] 未填写流名称时显示错误提示
- [ ] 流名称格式错误时显示提示
- [ ] 腾讯云配置不存在时显示错误
- [ ] 直播流未推送时播放器显示友好错误信息

---

## 📞 相关文档

- **快速开始：** `docs/QUICK_START_LOTTERY_LIVE.md`
- **详细集成：** `docs/LOTTERY_LIVE_INTEGRATION_GUIDE.md`
- **腾讯云配置：** `docs/TENCENT_CLOUD_LIVE_GUIDE.md`

---

*实现日期：2026-06-17*  
*版本：v2.0*  
*作者：Claude Code*
