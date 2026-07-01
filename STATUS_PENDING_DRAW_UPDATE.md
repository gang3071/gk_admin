# 摸奖券"待开奖"状态更新文档

## 📅 更新日期
2026-06-18

---

## 🎯 更新内容

### 新增状态：STATUS_PENDING_DRAW (5)

**状态值：** `5`  
**中文名称：** 待开奖  
**英文名称：** Pending Draw  
**日文名称：** 抽選待ち

**触发时机：** 活动 `end_time` 到达时自动流转  
**状态说明：** 活动已结束，停止发券，等待管理员手动开奖

---

## 📊 新的状态流程

```
0. 未开始 (STATUS_NOT_STARTED)
   ↓ start_time 到达（定时任务自动）
   
1. 进行中 (STATUS_ONGOING)
   ↓ end_time 到达（定时任务自动）
   
2. 待开奖 (STATUS_PENDING_DRAW) ⭐ 新增
   ↓ 管理员点击"开奖"按钮（手动）
   
3. 开奖中 (STATUS_DRAWING)
   ↓ 管理员点击"停止开奖"按钮（手动）
   
4. 已结束 (STATUS_ENDED)
```

---

## 📝 修改文件清单

### 1. gk_admin 项目（管理后台）

#### 模型文件
**`addons/webman/model/LotteryTicketActivity.php`**
- ✅ 添加常量：`const STATUS_PENDING_DRAW = 5`
- ✅ 更新 `getStatusText()` 方法

#### 翻译文件（4个语言）
- ✅ `addons/webman/lang/zh-TW/lottery_ticket.php` - 繁體中文：`'pending_draw' => '待開獎'`
- ✅ `addons/webman/lang/zh-CN/lottery_ticket.php` - 简体中文：`'pending_draw' => '待开奖'`
- ✅ `addons/webman/lang/en/lottery_ticket.php` - English：`'pending_draw' => 'Pending Draw'`
- ✅ `addons/webman/lang/jp/lottery_ticket.php` - 日本語：`'pending_draw' => '抽選待ち'`

#### 定时任务
**`process/LotteryActivityStatusTransitionTask.php`**
- ✅ 更新状态流转规则说明
- ✅ 查询条件包含 `STATUS_PENDING_DRAW`
- ✅ `determineNewStatus()` 逻辑：
  - `end_time` 到达 → `STATUS_PENDING_DRAW`（而不是直接 `STATUS_DRAWING`）
  - `STATUS_DRAWING` 不再自动流转
- ✅ 新增 `onPendingDraw()` 方法：
  - 停止打码进度
  - 推送待开奖通知

#### 控制器
**`addons/webman/controller/ChannelLotteryTicketActivityController.php`**
- ✅ 前端翻译数据添加 `'pendingDraw'` 键
- ✅ `recordWinByTickets()` 方法允许在 `STATUS_PENDING_DRAW` 状态录入中奖

---

### 2. gk_api 项目（客户端API）

#### 模型文件
**`app/model/LotteryTicketActivity.php`**
- ✅ 添加常量：`const STATUS_PENDING_DRAW = 5`
- ✅ 注释更新：说明6个核心状态

#### 控制器
**`app/api/controller/v1/LotteryTicketController.php`**
- ✅ `getSmartActivity()` 优先级调整：
  - 优先级1：开奖中 (STATUS_DRAWING)
  - **优先级2：待开奖 (STATUS_PENDING_DRAW)** ⭐ 新增
  - 优先级3：进行中 (STATUS_ONGOING)
  - 优先级4：即将开始 (7天内)
  - 优先级5：刚结束

---

## 🎯 业务逻辑变化

### 自动流转（定时任务 LotteryActivityStatusTransitionTask）

| 时间节点 | 旧状态 | 新状态 | 说明 |
|---------|--------|--------|------|
| `start_time` 到达 | `STATUS_NOT_STARTED` | `STATUS_ONGOING` | 活动开始，玩家可打码获券 |
| `end_time` 到达 | `STATUS_ONGOING` | `STATUS_PENDING_DRAW` | ⭐ 停止发券，等待开奖 |

**执行频率：** 每分钟检查一次（Crontab: `0 */1 * * * *`）

---

### 手动操作（管理员）

| 操作 | 旧状态 | 新状态 | 说明 |
|------|--------|--------|------|
| 点击"开奖" | `STATUS_PENDING_DRAW` | `STATUS_DRAWING` | 开始开奖流程 |
| 点击"停止开奖" | `STATUS_DRAWING` | `STATUS_ENDED` | 完全结束 |

---

### 状态特点对比

| 状态 | 能否打码获券 | 能否录入中奖 | 能否发放奖励 | 前端显示优先级 |
|------|------------|------------|------------|---------------|
| 未开始 | ❌ | ❌ | ❌ | 低（7天内显示） |
| 进行中 | ✅ | ✅ | ❌ | 中等 |
| **待开奖** | ❌ | ✅ | ❌ | **高（仅次于开奖中）** |
| 开奖中 | ❌ | ✅ | ✅ | 最高 |
| 已结束 | ❌ | ❌ | ✅ | 低 |
| 已关闭 | ❌ | ❌ | ❌ | 最低 |

---

## 🔄 待开奖状态的作用

### 1. 停止发券
- 调用 `LotteryTicketBetProgressService::endActivityProgress()`
- 所有玩家的打码进度被标记为结束
- 即使继续打码也不会再发放摸奖券

### 2. 推送通知
- 推送类型：`pending_draw`
- 通知内容：活动已结束，等待开奖
- 目标：
  - 前端：`player-channel-{department_id}`
  - 后台：`private-admin_group-channel-{department_id}`

### 3. 允许录入中奖
- 管理员可以在"待开奖"状态下提前录入中奖券号
- 避免开奖时手忙脚乱

### 4. API优先返回
- 客户端API优先返回"待开奖"活动（优先级2）
- 玩家可以查看活动详情、查询自己的摸奖券

---

## 🎯 完整示例

### 测试场景

**活动配置：**
```
活动名称：测试活动
开始时间：2026-06-18 14:00:00
结束时间：2026-06-18 14:10:00
```

**时间轴：**

```
13:59 │ STATUS_NOT_STARTED (未开始)
      │ - 活动未开始
      │ - 玩家无法获得摸奖券
      │
14:00 │ ⏰ 定时任务检测到 start_time 到达
      │ ↓ 自动流转
      │ STATUS_ONGOING (进行中)
      │ - 开始发放摸奖券
      │ - 玩家打码可获得摸奖券
      │ - 推送：activity_start
      │
14:10 │ ⏰ 定时任务检测到 end_time 到达
      │ ↓ 自动流转
      │ STATUS_PENDING_DRAW (待开奖) ⭐ 新状态
      │ - 停止发放摸奖券
      │ - 等待管理员开奖
      │ - 推送：pending_draw
      │ - 管理员可录入中奖券号
      │
14:30 │ 👤 管理员点击"开奖"按钮
      │ ↓ 手动操作
      │ STATUS_DRAWING (开奖中)
      │ - 开始摇球/抽奖流程
      │ - 推送：drawing_start
      │ - 管理员录入中奖结果
      │
15:00 │ 👤 管理员点击"停止开奖"
      │ ↓ 手动操作
      │ STATUS_ENDED (已结束)
      │ - 活动完全结束
      │ - 推送：ended
      │ - 店家可继续发放奖励
```

---

## ⚠️ 注意事项

### 1. 数据库迁移
**不需要创建迁移文件！**
- 状态字段 `status` 已经是 `tinyint`，可以容纳 0-255
- 只是增加了一个新的枚举值 `5`
- 现有数据不受影响

### 2. 现有活动的兼容性
**已有的活动如何处理？**
- 已经是 `STATUS_DRAWING (6)` 的活动：保持不变
- 已经是 `STATUS_ENDED (2)` 的活动：保持不变
- **新创建的活动：** 使用新的流程（自动进入待开奖）

### 3. 前端UI更新（需要单独开发）
**管理后台需要调整：**
- ✅ 活动列表显示"待开奖"状态
- ✅ "待开奖"状态显示"开奖"按钮（而不是"录入中奖"）
- ✅ 状态筛选器添加"待开奖"选项

**客户端需要调整：**
- ✅ 显示"待开奖"状态的活动
- ✅ 提示用户"活动已结束，等待开奖"
- ✅ 允许查看自己的摸奖券

### 4. 推送服务配置
**确保推送类型正确：**
```php
// process/LotteryActivityStatusTransitionTask.php:198
\addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'pending_draw');
```

推送服务需要识别 `pending_draw` 类型。

---

## 🔧 重启服务生效

```bash
# gk_admin
cd /path/to/gk_admin
php start.php restart

# gk_api
cd /path/to/gk_api
php start.php restart
```

---

## 📊 验证方法

### 1. 创建测试活动

```sql
INSERT INTO lottery_ticket_activity (
    name, 
    department_id, 
    start_time, 
    end_time, 
    status
) VALUES (
    '测试活动-待开奖',
    34,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 2 MINUTE),
    0  -- 未开始
);
```

### 2. 观察状态流转

**2分钟后（start_time）：**
```bash
# 查看日志
tail -f runtime/logs/webman.log | grep "摸奖券活动状态自动流转"

# 应该看到：
# 摸奖券活动状态自动流转 {
#     "old_status": "未开始",
#     "new_status": "进行中"
# }
```

**4分钟后（end_time）：**
```bash
# 应该看到：
# 摸奖券活动状态自动流转 {
#     "old_status": "进行中",
#     "new_status": "待开奖"  ← ⭐ 新状态
# }
```

### 3. 验证API返回

```bash
# 客户端API - 获取当前活动
curl -X POST http://localhost:8787/api/v1/lottery-ticket/get-current-activity \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json"

# 应该返回：
# {
#     "code": 0,
#     "data": {
#         "activity": {
#             "status": 5,  ← ⭐ 待开奖状态
#             "name": "测试活动-待开奖"
#         }
#     }
# }
```

### 4. 验证管理后台

```
1. 登录管理后台
2. 进入"摸奖券活动管理"
3. 查看活动列表
4. 状态列应该显示"待开奖"（繁体：待開獎）
5. 点击"操作" → 应该有"开奖"按钮
```

---

## 🎉 更新完成

**状态常量：** ✅  
**翻译文件：** ✅  
**定时任务：** ✅  
**API接口：** ✅  
**管理后台：** ✅  

**待完成（需要前端配合）：**
- ⏳ Vue组件更新（活动列表、状态筛选）
- ⏳ 开奖按钮UI
- ⏳ 客户端状态显示

---

**更新者：** Claude Code  
**更新日期：** 2026-06-18  
**版本：** v1.0 - 新增待开奖状态
