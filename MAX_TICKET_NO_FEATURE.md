# 活动面板显示最大券号功能

## 📋 需求说明

**用户需求:** 店家在摸奖券活动面板上需要看到最大券号，方便抽奖时知道应该放多少号码的球。

**业务场景:**
- 店家举办实体摸奖活动
- 使用物理摇球机抽奖
- 需要根据已发放的券数，准备对应号码的球
- 例如：发了 15 张券（000000~000014），就准备 0~14 号球

---

## ✅ 功能实现

### 1️⃣ **后端计算最大券号**

**文件:** `D:/gk_admin/addons/webman/controller/ChannelLotteryTicketActivityController.php`

**位置:** `getActivities()` 方法（208-225 行）

**逻辑:**
```php
// 计算最大券号（当前已发放的最后一张券号）
// current_ticket_no 表示下一张券从哪里开始，所以最大券号是 current_ticket_no - 1
$maxTicketNo = $activity->current_ticket_no > 0 ? $activity->current_ticket_no - 1 : 0;
$activityArray['max_ticket_no'] = str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT);
```

**示例:**

| current_ticket_no | 已发放券数 | 券号范围 | max_ticket_no |
|-------------------|-----------|---------|---------------|
| 0 | 0 张 | - | `000000` |
| 1 | 1 张 | 000000 | `000000` |
| 15 | 15 张 | 000000~000014 | `000014` |
| 100 | 100 张 | 000000~000099 | `000099` |
| 1000 | 1000 张 | 000000~000999 | `000999` |

---

### 2️⃣ **多语言翻译**

**新增翻译键:** `lottery_ticket.fields.max_ticket_no`

| 语言 | 文件 | 翻译 |
|------|------|------|
| 繁体中文 | `lang/zh-TW/lottery_ticket.php` | `最大券號` |
| 简体中文 | `lang/zh-CN/lottery_ticket.php` | `最大券号` |
| English | `lang/en/lottery_ticket.php` | `Max Ticket No` |
| 日本語 | `lang/jp/lottery_ticket.php` | `最大チケット番号` |

**注释说明:** "抽奖时放球的最大号码"

---

### 3️⃣ **前端显示**

**文件:** `D:/gk_admin/addons/webman/views/lottery_ticket_activities.vue`

#### A. 活动卡片统计信息（133-169 行）

**布局调整:**
- 原来：2 列（总发放、待发放）
- 现在：3 列（总发放、**最大券号**、待发放）
- 列宽：`:span="8"` (每列占 1/3)

**显示效果:**
```vue
<a-col :span="8">
  <a-statistic
      :title="trans.maxTicketNo || '最大券号'"
      :value="activity.max_ticket_no || '000000'"
      :value-style="{ fontSize: '18px', color: '#52c41a', fontFamily: 'monospace' }"
      style="text-align: center;"
  >
    <template #prefix>
      <number-outlined/>
    </template>
  </a-statistic>
</a-col>
```

**样式特点:**
- ✅ 绿色显示（`#52c41a`）- 醒目
- ✅ 等宽字体（`monospace`）- 数字对齐清晰
- ✅ 18px 字号 - 易读
- ✅ 图标前缀 - 视觉识别

#### B. 活动详情抽屉（452-465 行）

**显示位置:** 插入在"已使用数量"和"使用率"之间

**显示效果:**
```vue
<a-descriptions-item :label="trans.maxTicketNo">
  <a-tag color="green" style="font-family: monospace; font-size: 16px;">
    {{ currentActivity.max_ticket_no || '000000' }}
  </a-tag>
  <span style="margin-left: 8px; color: #999; font-size: 12px;">
    (抽奖时放球的最大号码)
  </span>
</a-descriptions-item>
```

**样式特点:**
- ✅ 绿色标签 - 突出显示
- ✅ 等宽字体 16px - 清晰易读
- ✅ 附带说明文字 - 用途明确

---

## 📊 业务逻辑说明

### 券号生成规则

**发券顺序:** 严格递增，从 `000000` 开始

```php
// 第 1 张券: 000000 (current_ticket_no = 1, max = 0)
// 第 2 张券: 000001 (current_ticket_no = 2, max = 1)
// 第 3 张券: 000002 (current_ticket_no = 3, max = 2)
// ...
// 第 100 张券: 000099 (current_ticket_no = 100, max = 99)
```

**数据库字段:**
- `current_ticket_no`: 下一张券从哪里开始（计数器）
- `max_ticket_no`: 配置的最大允许发放数（上限）
- `total_tickets`: 已发放总数（统计）

---

### 抽奖时如何使用

**场景 1: 发了 15 张券**

```
已发放券号: 000000 ~ 000014
最大券号显示: 000014

店家准备摇球:
- 个位球: 0, 1, 2, 3, 4
- 十位球: 0, 1
- 百位球: 0
- 千位球: 0
- 万位球: 0
- 十万位球: 0

总共需要: 0~4 (5个) + 0~1 (2个) + 0 (1个) × 4 = 11 个球
```

**场景 2: 发了 123 张券**

```
已发放券号: 000000 ~ 000122
最大券号显示: 000122

店家准备摇球:
- 个位球: 0, 1, 2
- 十位球: 0, 1, 2
- 百位球: 0, 1
- 千位球: 0
- 万位球: 0
- 十万位球: 0

总共需要: 0~2 (3个) + 0~2 (3个) + 0~1 (2个) + 0 (1个) × 3 = 11 个球
```

---

## 🎯 用户价值

### ✅ 解决的问题

1. **准备摇球更精确**
   - 不用手动查数据库
   - 一眼看清需要准备哪些号码的球

2. **避免操作失误**
   - 准备球太少 → 可能摇出无效券号
   - 准备球太多 → 浪费时间

3. **提升活动效率**
   - 店家能快速准备抽奖道具
   - 减少活动前的准备时间

### 📈 使用场景

**实体店摸奖活动流程:**

1. **活动前准备**
   - 登录管理后台
   - 查看活动面板
   - 看到"最大券号: 000099"
   - 准备 0~9 (10个球) × 2 位 = 实际需要的球

2. **开始抽奖**
   - 使用物理摇球机
   - 摇出 6 个数字
   - 组成券号（例如: 000078）
   - 查询对应玩家
   - 发放奖品

3. **确认中奖**
   - 输入券号 `78`
   - 系统自动补0为 `000078`
   - 验证券号有效性
   - 记录中奖信息

---

## 🔍 数据示例

### 活动面板显示

**活动卡片:**
```
┌──────────────────────────────────┐
│  摸奖券活动 #1                     │
│  进行中 ●                          │
├──────────────────────────────────┤
│  📅 2026-06-01 ~ 2026-06-30      │
├──────────────────────────────────┤
│  📊 统计信息                       │
│  ┌──────┬──────┬──────┐          │
│  │ 总发放│ 最大 │ 待发放│          │
│  │  150 │000149│  5   │          │
│  └──────┴──────┴──────┘          │
└──────────────────────────────────┘
```

**活动详情:**
```
活动名称: 六月摸奖活动
活动状态: 进行中 ●
活动说明: ...
活动时间: 2026-06-01 00:00:00 ~ 2026-06-30 23:59:59
总发放数量: 150
已使用数量: 145
最大券号: 000149 (抽奖时放球的最大号码)
使用率: 96.67%
```

---

## 📝 技术细节

### API 返回数据结构

```json
{
  "id": 1,
  "name": "六月摸奖活动",
  "status": 1,
  "total_tickets": 150,
  "used_tickets": 145,
  "current_ticket_no": 150,
  "max_ticket_no": "000149",  // ⭐ 新增字段
  "pending_count": 5,
  "has_prize_config": true
}
```

### 前端数据流

```
1. 页面加载
   ↓
2. 调用 getActivities() API
   ↓
3. 后端计算 max_ticket_no
   ↓
4. 返回活动列表（含 max_ticket_no）
   ↓
5. Vue 渲染活动卡片
   ↓
6. 显示最大券号（绿色标签，等宽字体）
```

---

## 🧪 测试清单

### ✅ 后端测试

- [ ] 未发放任何券 → max_ticket_no = `000000`
- [ ] 发放 1 张券 → max_ticket_no = `000000`
- [ ] 发放 15 张券 → max_ticket_no = `000014`
- [ ] 发放 100 张券 → max_ticket_no = `000099`
- [ ] 发放 1000 张券 → max_ticket_no = `000999`

### ✅ 前端测试

- [ ] 活动卡片显示最大券号（绿色、等宽字体）
- [ ] 活动详情显示最大券号（带说明）
- [ ] 切换语言显示正确翻译
- [ ] 数字 0 补齐到 6 位
- [ ] 布局调整为 3 列（8:8:8）

### ✅ 业务测试

- [ ] 发券后立即刷新页面，最大券号更新
- [ ] 多个活动同时显示，各自券号独立
- [ ] 未发券活动显示 `000000`

---

## 🚀 部署清单

**修改的文件:**

1. **后端控制器:**
   - `D:/gk_admin/addons/webman/controller/ChannelLotteryTicketActivityController.php`
     - `getActivities()` 方法：添加 max_ticket_no 计算
     - 翻译数据：添加 `maxTicketNo` 键

2. **翻译文件 (4 个):**
   - `D:/gk_admin/addons/webman/lang/zh-TW/lottery_ticket.php` (繁中)
   - `D:/gk_admin/addons/webman/lang/zh-CN/lottery_ticket.php` (简中)
   - `D:/gk_admin/addons/webman/lang/en/lottery_ticket.php` (英语)
   - `D:/gk_admin/addons/webman/lang/jp/lottery_ticket.php` (日语)

3. **前端 Vue 文件:**
   - `D:/gk_admin/addons/webman/views/lottery_ticket_activities.vue`
     - 活动卡片统计：调整为 3 列布局
     - 活动详情：添加最大券号显示

**无需迁移:** 数据库字段 `current_ticket_no` 已存在，无需新建

---

## 💡 扩展建议

### 未来优化方向

1. **球号范围提示**
   - 显示每位球的范围（例如: "个位 0-4, 十位 0-1"）
   - 店家准备球时更直观

2. **打印功能**
   - 生成"准备球清单" PDF
   - 店家打印后按清单准备

3. **摇球动画**
   - 管理后台集成摇球动画
   - 店家可在电子屏幕上展示抽奖过程

---

## 📚 相关文档

- **券号生成逻辑:** `LotteryTicketBetProgressService.php` (339-426 行)
- **摇球开奖服务:** `LotteryBallDrawService.php`
- **活动数据模型:** `LotteryTicketActivity.php`

---

## ✅ 总结

**核心价值:**
- 🎯 **精准准备**: 店家知道需要准备哪些号码的球
- ⚡ **快速查看**: 活动面板一眼看清最大券号
- 🌍 **多语言支持**: 4 种语言完整翻译
- 💻 **前后端完整**: API + 显示完整实现

**用户体验:**
- 绿色醒目标签，易识别
- 等宽字体，数字对齐清晰
- 附带说明文字，用途明确
- 活动卡片和详情都显示，方便查看
