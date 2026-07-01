# 🔥 模型常量不一致问题报告

**严重性:** 🔴 **CRITICAL**  
**发现时间:** 2026-06-11  
**影响范围:** gk_admin 和 gk_api 项目  
**问题类型:** 模型常量定义不一致  

---

## ⚠️ 问题概述

**gk_admin** 和 **gk_api** 两个项目共享同一个 MySQL 数据库，但模型常量定义**严重不一致**，导致：

1. **数据不一致风险**: 两个项目操作同一张表时，状态值含义不同
2. **业务逻辑错误**: 一个项目写入的数据，另一个项目可能误读
3. **维护困难**: 修改一处需要同步修改另一处，容易遗漏

---

## 🔍 不一致的模型

### 1. LotteryTicket (摸奖券模型) - 🔴 严重不一致

#### gk_admin 定义

**文件:** `D:\gk_admin\addons\webman\model\LotteryTicket.php`

```php
// 状态常量
const STATUS_UNUSED = 0;    // 未使用
const STATUS_USED = 1;      // 已使用
const STATUS_EXPIRED = 2;   // 已过期

// 来源常量
const SOURCE_RECHARGE = 'recharge';  // 充值赠送 (字符串)
const SOURCE_ACTIVITY = 'activity';  // 活动赠送 (字符串)
const SOURCE_MANUAL = 'manual';      // 手动发放 (字符串)
```

#### gk_api 定义

**文件:** `D:\gk_api\app\model\LotteryTicket.php`

```php
// 状态常量
const STATUS_VALID = 0;      // 有效 ❌ 与 gk_admin 的 STATUS_UNUSED 冲突
const STATUS_USED = 1;       // 已使用 ✅ 一致
const STATUS_EXPIRED = 2;    // 已过期 ✅ 一致
const STATUS_WINNING = 3;    // 中奖 ❌ gk_admin 没有此状态
const STATUS_LOSING = 4;     // 未中奖 ❌ gk_admin 没有此状态

// 来源常量
const SOURCE_BET = 1;        // 打码获得 (整数) ❌ 与 gk_admin 类型不同
const SOURCE_MANUAL = 2;     // 手动发放 (整数) ❌ 与 gk_admin 类型不同
const SOURCE_ACTIVITY = 3;   // 活动赠送 (整数) ❌ 与 gk_admin 类型不同
```

#### 差异分析

| 常量 | gk_admin | gk_api | 冲突 |
|------|----------|--------|------|
| **状态值=0** | STATUS_UNUSED (未使用) | STATUS_VALID (有效) | 🔴 名称不同 |
| **状态值=1** | STATUS_USED (已使用) | STATUS_USED (已使用) | ✅ 一致 |
| **状态值=2** | STATUS_EXPIRED (已过期) | STATUS_EXPIRED (已过期) | ✅ 一致 |
| **状态值=3** | ❌ 不存在 | STATUS_WINNING (中奖) | 🔴 gk_admin 缺失 |
| **状态值=4** | ❌ 不存在 | STATUS_LOSING (未中奖) | 🔴 gk_admin 缺失 |
| **来源类型** | 字符串 (recharge/activity/manual) | 整数 (1/2/3) | 🔴 数据类型不同 |

**风险：**
- gk_api 写入 `status=0`，含义是"有效"
- gk_admin 读取 `status=0`，理解为"未使用"
- 两者虽然语义相近，但常量名不同，代码维护时容易混淆

---

### 2. LotteryTicketActivity (摸奖券活动模型) - 🔴 严重不一致

#### gk_admin 定义

```php
const STATUS_NOT_STARTED = 0; // 未开始
const STATUS_ONGOING = 1;     // 进行中
const STATUS_ENDED = 2;       // 已结束
const STATUS_CLOSED = 3;      // 已关闭
const STATUS_PREHEATING = 4;  // 预热期
const STATUS_BETTING = 5;     // 打码中
const STATUS_DRAWING = 6;     // 开奖中
const STATUS_DRAWN = 7;       // 已开奖待发放
```

#### gk_api 定义

```php
const STATUS_NOT_STARTED = 0;  // 未开始
const STATUS_PREHEATING = 1;   // 预热中 ❌ 值不同
const STATUS_BETTING = 2;      // 打码中 ❌ 值不同
const STATUS_ONGOING = 3;      // 进行中 ❌ 值不同
const STATUS_DRAWING = 4;      // 开奖中 ❌ 值不同
const STATUS_ENDED = 5;        // 已结束 ❌ 值不同
const STATUS_CLOSED = 6;       // 已关闭 ❌ 值不同
```

#### 差异对比表

| 状态名称 | gk_admin 值 | gk_api 值 | 冲突 |
|---------|-----------|---------|------|
| NOT_STARTED | 0 | 0 | ✅ 一致 |
| **ONGOING** | **1** | **3** | 🔴 值不同 |
| **ENDED** | **2** | **5** | 🔴 值不同 |
| **CLOSED** | **3** | **6** | 🔴 值不同 |
| **PREHEATING** | **4** | **1** | 🔴 值不同 |
| **BETTING** | **5** | **2** | 🔴 值不同 |
| **DRAWING** | **6** | **4** | 🔴 值不同 |
| DRAWN | 7 | ❌ 不存在 | 🔴 gk_api 缺失 |

**严重风险示例：**
- gk_admin 写入活动状态 `status=1`，表示"进行中"
- gk_api 读取 `status=1`，理解为"预热中"
- **完全不同的业务状态！**

---

### 3. LotteryTicketRecord (中奖记录模型) - 🟡 部分不一致

#### gk_admin 定义

```php
const STATUS_PENDING = 0;      // 待发放
const STATUS_CLAIMED = 1;      // 已发放
const STATUS_EXPIRED = 2;      // 已过期
const STATUS_CANCELLED = 3;    // 已取消
const STATUS_PROCESSING = 4;   // 发放中
const STATUS_FAILED = 5;       // 发放失败

// 兼容旧常量
const STATUS_GRANTED = 1;      // 已发放（兼容旧代码）
```

#### gk_api 定义

```php
const STATUS_PENDING = 0;   // 待发放
const STATUS_GRANTED = 1;   // 已发放
const STATUS_EXPIRED = 2;   // 已过期
```

#### 差异分析

| 常量 | gk_admin | gk_api | 冲突 |
|------|----------|--------|------|
| STATUS_PENDING | ✅ 0 | ✅ 0 | ✅ 一致 |
| STATUS_GRANTED/CLAIMED | 1 (两个名称) | 1 | 🟡 名称不统一 |
| STATUS_EXPIRED | ✅ 2 | ✅ 2 | ✅ 一致 |
| STATUS_CANCELLED | 3 | ❌ 不存在 | 🟡 gk_api 缺失 |
| STATUS_PROCESSING | 4 | ❌ 不存在 | 🟡 gk_api 缺失 |
| STATUS_FAILED | 5 | ❌ 不存在 | 🟡 gk_api 缺失 |

---

## 📊 影响范围

### gk_admin 受影响文件

**LotteryTicket 相关：**
- `addons/webman/model/LotteryTicket.php`
- `addons/webman/service/LotteryTicketIssueService.php`
- `addons/webman/service/LotteryBallDrawService.php`
- `process/LotteryTicketExpireProcess.php`
- `addons/webman/controller/ChannelLotteryTicketRecordController.php`
- `addons/webman/controller/ChannelLotteryTicketActivityController.php`

**LotteryTicketActivity 相关：**
- `addons/webman/model/LotteryTicketActivity.php`
- `addons/webman/service/LotteryTicketBetProgressService.php`
- 其他控制器...

### gk_api 受影响文件

**LotteryTicket 相关：**
- `app/model/LotteryTicket.php`
- `app/api/controller/v1/*` (待扫描)

**LotteryTicketActivity 相关：**
- `app/model/LotteryTicketActivity.php`
- `app/api/controller/v1/*` (待扫描)

---

## 🎯 建议的统一方案

### 方案 A: 以 gk_admin 为准（推荐）

**优点：**
- gk_admin 是管理后台，常量定义更完善
- 已经过近期代码审查和修复
- 状态流转更清晰（UNUSED → USED/EXPIRED）

**缺点：**
- 需要修改 gk_api 的模型和控制器代码

---

### 方案 B: 以 gk_api 为准

**优点：**
- 保持 API 端代码稳定

**缺点：**
- gk_api 常量定义不完整（缺少新增状态）
- 状态值和名称不够直观

---

### 推荐方案详情

#### 1. LotteryTicket 模型统一

```php
// ✅ 统一后的定义（以 gk_admin 为准）

// 状态常量
const STATUS_UNUSED = 0;    // 未使用
const STATUS_USED = 1;      // 已使用
const STATUS_EXPIRED = 2;   // 已过期

// 来源常量（使用字符串，更直观）
const SOURCE_RECHARGE = 'recharge';  // 充值赠送
const SOURCE_ACTIVITY = 'activity';  // 活动赠送
const SOURCE_MANUAL = 'manual';      // 手动发放
const SOURCE_BETTING = 'betting';    // 打码获得 (新增)
```

**修改内容：**
- gk_api: 删除 `STATUS_VALID/WINNING/LOSING`，改用 `STATUS_UNUSED`
- gk_api: `source` 字段从整数改为字符串
- 数据库: 需要迁移脚本转换 `source` 字段数据

---

#### 2. LotteryTicketActivity 模型统一

```php
// ✅ 统一后的定义（以 gk_admin 为准）

const STATUS_NOT_STARTED = 0; // 未开始
const STATUS_ONGOING = 1;     // 进行中
const STATUS_ENDED = 2;       // 已结束
const STATUS_CLOSED = 3;      // 已关闭
const STATUS_PREHEATING = 4;  // 预热期
const STATUS_BETTING = 5;     // 打码中
const STATUS_DRAWING = 6;     // 开奖中
const STATUS_DRAWN = 7;       // 已开奖待发放
```

**修改内容：**
- gk_api: 调整所有状态常量的值，使其与 gk_admin 一致
- **需要数据库迁移脚本转换现有数据的状态值**

---

#### 3. LotteryTicketRecord 模型统一

```php
// ✅ 统一后的定义（以 gk_admin 为准）

const STATUS_PENDING = 0;      // 待发放
const STATUS_CLAIMED = 1;      // 已发放
const STATUS_EXPIRED = 2;      // 已过期
const STATUS_CANCELLED = 3;    // 已取消
const STATUS_PROCESSING = 4;   // 发放中
const STATUS_FAILED = 5;       // 发放失败

// 兼容旧常量
const STATUS_GRANTED = 1;      // 已发放（兼容旧代码）
```

**修改内容：**
- gk_api: 新增 `STATUS_CANCELLED/PROCESSING/FAILED` 常量
- gk_api: 添加 `STATUS_GRANTED` 作为 `STATUS_CLAIMED` 的别名

---

## 🛠️ 实施步骤

### Step 1: 创建数据库迁移脚本

#### 迁移 lottery_ticket_activity.status 字段

```sql
-- 备份现有数据
CREATE TABLE lottery_ticket_activity_backup AS SELECT * FROM lottery_ticket_activity;

-- 转换状态值（gk_api → gk_admin）
UPDATE lottery_ticket_activity SET status = 
  CASE status
    WHEN 1 THEN 4  -- PREHEATING: 1 → 4
    WHEN 2 THEN 5  -- BETTING: 2 → 5
    WHEN 3 THEN 1  -- ONGOING: 3 → 1
    WHEN 4 THEN 6  -- DRAWING: 4 → 6
    WHEN 5 THEN 2  -- ENDED: 5 → 2
    WHEN 6 THEN 3  -- CLOSED: 6 → 3
    ELSE status
  END;
```

#### 迁移 lottery_ticket.source 字段

```sql
-- 备份
CREATE TABLE lottery_ticket_backup AS SELECT * FROM lottery_ticket;

-- 如果 source 是整数字段，需要先修改字段类型
ALTER TABLE lottery_ticket MODIFY COLUMN source VARCHAR(20);

-- 转换来源值（整数 → 字符串）
UPDATE lottery_ticket SET source = 
  CASE source
    WHEN '1' THEN 'betting'
    WHEN '2' THEN 'manual'
    WHEN '3' THEN 'activity'
    ELSE source
  END;
```

---

### Step 2: 修改 gk_api 模型文件

#### LotteryTicket.php

```php
// 替换整个常量定义部分
const STATUS_UNUSED = 0;    // 未使用
const STATUS_USED = 1;      // 已使用
const STATUS_EXPIRED = 2;   // 已过期

const SOURCE_RECHARGE = 'recharge';
const SOURCE_ACTIVITY = 'activity';
const SOURCE_MANUAL = 'manual';
const SOURCE_BETTING = 'betting';
```

#### LotteryTicketActivity.php

```php
// 替换整个常量定义部分
const STATUS_NOT_STARTED = 0;
const STATUS_ONGOING = 1;
const STATUS_ENDED = 2;
const STATUS_CLOSED = 3;
const STATUS_PREHEATING = 4;
const STATUS_BETTING = 5;
const STATUS_DRAWING = 6;
const STATUS_DRAWN = 7;
```

#### LotteryTicketRecord.php

```php
// 新增常量
const STATUS_CANCELLED = 3;
const STATUS_PROCESSING = 4;
const STATUS_FAILED = 5;
const STATUS_GRANTED = 1;  // 别名
```

---

### Step 3: 修改 gk_api 控制器代码

搜索并替换所有使用了旧常量的地方：

```bash
# 在 gk_api 项目中
grep -r "STATUS_VALID" app/
grep -r "SOURCE_BET" app/
grep -r "STATUS_WINNING" app/
grep -r "STATUS_LOSING" app/
```

---

### Step 4: 测试验证

1. **单元测试**: 测试常量引用是否正确
2. **集成测试**: 测试两个项目操作同一数据的场景
3. **回归测试**: 测试现有功能是否正常

---

## ⚠️ 风险提示

1. **数据迁移风险**: 
   - 状态值转换可能导致数据不一致
   - 必须在生产环境维护窗口执行
   - 必须先备份数据

2. **兼容性风险**:
   - 如果有移动端或其他客户端缓存了状态值，需要强制更新
   - API 返回的状态值变化，需要通知前端

3. **回滚方案**:
   - 保留备份表
   - 准备回滚 SQL 脚本

---

## 📋 执行清单

- [ ] 创建数据库备份
- [ ] 编写迁移 SQL 脚本
- [ ] 修改 gk_api 模型常量
- [ ] 修改 gk_api 控制器代码
- [ ] 执行数据库迁移（测试环境）
- [ ] 测试验证（测试环境）
- [ ] 执行数据库迁移（生产环境）
- [ ] 部署 gk_api 代码
- [ ] 监控错误日志
- [ ] 清理备份表（7天后）

---

## 🎯 优先级建议

**P0 - 立即处理:**
- LotteryTicketActivity 状态值不一致（业务逻辑错误风险高）

**P1 - 近期处理:**
- LotteryTicket 来源类型不一致（数据类型不同）
- LotteryTicket 状态名称不一致（维护困难）

**P2 - 计划处理:**
- LotteryTicketRecord 常量补全（功能完整性）

---

**报告生成时间:** 2026-06-11  
**审查工程师:** AI Assistant  
**建议方案:** 方案 A (以 gk_admin 为准)  
**预计工作量:** 2-4小时（包括测试）  
**风险级别:** 🔴 HIGH
