# 摸奖券中奖发放完整流程文档

> **项目**: YJB Admin 摸奖券管理系统  
> **版本**: v2.0  
> **最后更新**: 2026-06-13  
> **负责模块**: 渠道后台 - 摸奖券活动管理

---

## 📋 目录

1. [业务流程概览](#业务流程概览)
2. [数据库表结构](#数据库表结构)
3. [状态流转](#状态流转)
4. [发放流程详解](#发放流程详解)
5. [记录创建流程](#记录创建流程)
6. [API 接口说明](#api-接口说明)
7. [错误处理](#错误处理)
8. [日志记录](#日志记录)

---

## 业务流程概览

### 完整业务流程图

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         摸奖券活动完整流程                                │
└─────────────────────────────────────────────────────────────────────────┘

阶段 1: 活动创建 (渠道管理员)
┌─────────────────────────────────────────────────────────────────────────┐
│ 1. 创建活动                                                              │
│    - 设置活动名称、时间、封面                                              │
│    - 配置 VIP 等级打码量 (达到打码量自动发券)                              │
│    - 配置奖品等级 (特等奖、一等奖...九等奖)                                 │
│    └─> LotteryTicketActivity (状态: NOT_STARTED)                        │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
阶段 2: 活动进行中 (自动 + 手动)
┌─────────────────────────────────────────────────────────────────────────┐
│ 2a. 玩家获得摸奖券 (自动发券)                                             │
│    - 玩家打码达标 → LotteryTicketBetProgressService 自动发券               │
│    - 充值赠送 → 系统自动发券                                              │
│    └─> LotteryTicket 创建 (status: UNUSED, ticket_no: 000001~999999)   │
│                                                                          │
│ 2b. 手动发券 (可选)                                                       │
│    - 管理员手动发券给玩家                                                  │
│    └─> LotteryTicketIssueService::issueTickets()                        │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
阶段 3: 开奖 (管理员录入中奖)
┌─────────────────────────────────────────────────────────────────────────┐
│ 3a. 录入中奖记录 (按券号批量录入)                                          │
│    - 管理员输入中奖券号 + 选择奖品等级                                      │
│    - 系统验证券号存在且未使用                                              │
│    └─> LotteryTicketRecord 创建                                         │
│        - status: PENDING (待发放)                                        │
│        - ticket_no: 中奖券号                                              │
│        - prize_level: 一等奖                                             │
│        - prize_amount: 10000.00                                          │
│        - prize_type: cash                                                │
│                                                                          │
│ 3b. 更新摸奖券状态                                                        │
│    └─> LotteryTicket.status = USED                                      │
│                                                                          │
│ 3c. 推送中奖通知                                                          │
│    └─> LotteryTicketPushService::pushWinNotification()                  │
│        - 通知玩家中奖信息                                                  │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
阶段 4: 发放奖励 (管理员操作) ⭐ 本文档重点
┌─────────────────────────────────────────────────────────────────────────┐
│ 4a. 批量发放该活动所有待发放奖励                                           │
│    - 管理员点击"发放奖励"按钮                                              │
│    - 系统确认: "确认发放该活动所有已录入但未发放的奖励？"                     │
│    └─> batchDistributeActivity()                                        │
│        ├─ 查询所有 status=PENDING 的记录                                  │
│        ├─ 逐条发放 (事务处理)                                             │
│        │   ├─ 更新 LotteryTicketRecord.status = PROCESSING               │
│        │   ├─ 转账到玩家账户 (Player.money += prize_amount)              │
│        │   ├─ 创建 PlayerMoneyLog (资金变动日志)                          │
│        │   ├─ 更新 LotteryTicketRecord.status = CLAIMED                  │
│        │   ├─ 更新 LotteryTicketActivity.distributed_prize_amount       │
│        │   └─ 推送发放成功通知                                            │
│        └─ 返回统计: 成功 X 条, 失败 Y 条                                   │
│                                                                          │
│ 4b. 单条发放 (按券号) - 保留旧功能                                         │
│    - 管理员输入中奖券号                                                    │
│    └─> distributeByTicketNo()                                           │
│        └─ 流程同上 (单条处理)                                             │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
阶段 5: 活动结束
┌─────────────────────────────────────────────────────────────────────────┐
│ 5. 活动状态更新                                                           │
│    - 时间到期 → STATUS_ENDED                                             │
│    - 管理员手动关闭 → STATUS_CLOSED                                       │
│    └─> 结束所有打码进度记录                                               │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 数据库表结构

### 1. lottery_ticket_activity (摸奖券活动表)

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| id | int | 主键 | 1 |
| name | varchar | 活动名称 | "2024春节摸奖活动" |
| department_id | int | 所属渠道部门 | 100 |
| start_time | datetime | 开始时间 | 2024-01-01 00:00:00 |
| end_time | datetime | 结束时间 | 2024-01-31 23:59:59 |
| status | tinyint | 活动状态 | 1 (进行中) |
| cover_image | varchar | 活动封面图 | /uploads/cover.jpg |
| live_url | varchar | 直播地址 | rtmp://live.xxx.com |
| live_status | tinyint | 直播状态 | 0/1/2 |
| total_prize_amount | decimal(15,2) | 总奖金额度 | 1000000.00 |
| distributed_prize_amount | decimal(15,2) | 已发放奖金 | 85000.00 |
| total_tickets | int | 总发券数量 | 1250 |
| used_tickets | int | 已使用数量 | 320 |
| current_ticket_no | int | 当前券号序列 | 1251 |
| ball_result | text | 摇球结果JSON | {...} |
| created_at | datetime | 创建时间 | - |
| updated_at | datetime | 更新时间 | - |

**活动状态 (status) 枚举:**
```php
const STATUS_NOT_STARTED = 0;  // 未开始
const STATUS_ONGOING = 1;      // 进行中
const STATUS_ENDED = 2;        // 已结束
const STATUS_CLOSED = 3;       // 已关闭
const STATUS_PREHEATING = 4;   // 预热期
const STATUS_BETTING = 5;      // 打码中
const STATUS_DRAWING = 6;      // 开奖中
const STATUS_DRAWN = 7;        // 已开奖待发放 ⭐ 重要
```

---

### 2. lottery_ticket (摸奖券表)

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| id | int | 主键 | 1 |
| activity_id | int | 所属活动ID | 1 |
| player_id | int | 玩家ID | 1001 |
| department_id | int | 所属部门 | 100 |
| ticket_no | varchar(6) | 券号 (6位) | "000123" |
| status | tinyint | 券状态 | 0/1/2 |
| source | varchar | 来源 | "recharge" |
| source_id | int | 来源记录ID | 5001 |
| issued_at | datetime | 发放时间 | 2024-01-05 10:30:00 |
| used_at | datetime | 使用时间 | 2024-01-28 20:15:00 |
| expired_at | datetime | 过期时间 | 2024-01-31 23:59:59 |
| created_at | datetime | 创建时间 | - |
| updated_at | datetime | 更新时间 | - |

**券状态 (status) 枚举:**
```php
const STATUS_UNUSED = 0;    // 未使用 (发放后初始状态)
const STATUS_USED = 1;      // 已使用 (录入中奖后更新)
const STATUS_EXPIRED = 2;   // 已过期 (活动结束后自动更新)
```

**来源 (source) 枚举:**
```php
const SOURCE_RECHARGE = 'recharge';  // 充值赠送
const SOURCE_ACTIVITY = 'activity';  // 活动赠送
const SOURCE_BETTING = 'betting';    // 打码获得 ⭐ 最常见
const SOURCE_MANUAL = 'manual';      // 手动发放
```

**券号生成规则:**
- 6位数字: 000001 ~ 999999
- 使用 Redis 原子递增: `lottery_activity:{activity_id}:ticket_sequence`
- 上限: 100万张/活动

---

### 3. lottery_ticket_record (中奖记录表) ⭐ 核心表

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| id | int | 主键 | 1 |
| activity_id | int | 活动ID | 1 |
| player_id | int | 玩家ID | 1001 |
| department_id | int | 部门ID | 100 |
| ticket_id | int | 使用的券ID | 123 |
| ticket_no | varchar(6) | 中奖券号 | "000123" |
| prize_type | varchar | 奖品类型 | "cash" |
| prize_name | varchar | 奖品名称 | "一等奖" |
| prize_amount | decimal(15,2) | 奖品金额 | 10000.00 |
| status | tinyint | 发放状态 ⭐ | 0/1/4/5 |
| distributed_by | int | 发放人ID | 2 (管理员ID) |
| distributed_at | datetime | 发放时间 | 2024-01-29 09:30:00 |
| distribution_note | varchar | 发放备注 | "批量发放活动奖励" |
| remark | text | 其他备注 | - |
| created_at | datetime | 创建时间 | 2024-01-28 20:15:00 |
| updated_at | datetime | 更新时间 | - |

**发放状态 (status) 枚举:** ⭐ 重点
```php
const STATUS_PENDING = 0;      // 待发放 (录入中奖后初始状态)
const STATUS_CLAIMED = 1;      // 已发放 (发放成功后状态)
const STATUS_EXPIRED = 2;      // 已过期
const STATUS_CANCELLED = 3;    // 已取消
const STATUS_PROCESSING = 4;   // 发放中 (发放进行时临时状态)
const STATUS_FAILED = 5;       // 发放失败 (发放异常后状态)

// 兼容旧代码
const STATUS_GRANTED = 1;      // 已发放 (同 CLAIMED)
```

**奖品类型 (prize_type) 枚举:**
```php
const PRIZE_TYPE_CASH = 'cash';       // 现金 (增加 Player.money)
const PRIZE_TYPE_BONUS = 'bonus';     // 红利 (增加 Player.bonus)
const PRIZE_TYPE_ITEM = 'item';       // 实物
const PRIZE_TYPE_POINTS = 'points';   // 积分
const PRIZE_TYPE_EMPTY = 'empty';     // 未中奖 (空奖,不发放)
```

---

### 4. lottery_ticket_prize_level (奖品等级表)

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| id | int | 主键 | 1 |
| activity_id | int | 活动ID | 1 |
| level_rank | tinyint | 等级排名 | 1 (特等奖) |
| level_name | varchar | 等级名称 | "特等奖" |
| prize_type | varchar | 奖品类型 | "cash" |
| prize_amount | decimal(15,2) | 奖品金额 | 50000.00 |
| prize_count | int | 奖品数量 | 1 |
| created_at | datetime | 创建时间 | - |
| updated_at | datetime | 更新时间 | - |

**等级排名 (level_rank):**
- 1: 特等奖
- 2: 一等奖
- 3: 二等奖
- ...
- 10: 九等奖

---

### 5. lottery_ticket_vip_config (VIP打码配置表)

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| id | int | 主键 | 1 |
| activity_id | int | 活动ID | 1 |
| vip_level_id | int | VIP等级ID | 5 |
| bet_amount_required | decimal(15,2) | 所需打码量 | 10000.00 |
| ticket_count | int | 发放券数 | 1 |
| status | tinyint | 状态 | 1 (启用) |
| created_at | datetime | 创建时间 | - |

---

### 6. lottery_ticket_bet_progress (打码进度表)

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| id | int | 主键 | 1 |
| activity_id | int | 活动ID | 1 |
| player_id | int | 玩家ID | 1001 |
| vip_level_id | int | VIP等级ID | 5 |
| bet_amount_required | decimal(15,2) | 所需打码量 | 10000.00 |
| current_bet_amount | decimal(15,2) | 当前打码量 | 8500.00 |
| progress_percent | decimal(5,2) | 进度百分比 | 85.00 |
| remaining_bet_amount | decimal(15,2) | 剩余打码量 | 1500.00 |
| cycles_completed | int | 完成周期数 | 0 |
| total_tickets_issued | int | 已发券数 | 0 |
| ticket_count_per_cycle | int | 每周期发券数 | 1 |
| status | tinyint | 状态 | 1 (进行中) |
| created_at | datetime | 创建时间 | - |
| updated_at | datetime | 更新时间 | - |

---

### 7. player_money_log (玩家资金变动日志表)

| 字段名 | 类型 | 说明 | 示例 |
|--------|------|------|------|
| id | int | 主键 | 1 |
| player_id | int | 玩家ID | 1001 |
| department_id | int | 部门ID | 100 |
| type | varchar | 变动类型 | "lottery_reward" |
| money | decimal(15,2) | 变动金额 | 10000.00 |
| before_money | decimal(15,2) | 变动前余额 | 5000.00 |
| after_money | decimal(15,2) | 变动后余额 | 15000.00 |
| remark | text | 备注 | "摸奖券中奖发放:春节活动-一等奖" |
| created_at | int | 创建时间戳 | 1706498400 |

---

## 状态流转

### 摸奖券状态流转

```
┌─────────────────────────────────────────────────────────────┐
│                 LotteryTicket 状态流转                       │
└─────────────────────────────────────────────────────────────┘

  [发券]
    ↓
┌──────────┐   [录入中奖]    ┌──────────┐
│ UNUSED   │ ──────────────> │  USED    │
│ (未使用)  │                 │ (已使用)  │
└──────────┘                 └──────────┘
    │
    │ [活动结束]
    ↓
┌──────────┐
│ EXPIRED  │
│ (已过期)  │
└──────────┘
```

---

### 中奖记录状态流转 ⭐ 核心

```
┌─────────────────────────────────────────────────────────────┐
│             LotteryTicketRecord 状态流转                     │
└─────────────────────────────────────────────────────────────┘

  [录入中奖]
    ↓
┌──────────┐   [开始发放]    ┌──────────────┐
│ PENDING  │ ──────────────> │ PROCESSING   │
│ (待发放)  │                 │ (发放中)      │
└──────────┘                 └──────────────┘
    │                              │
    │                              │ [发放成功]
    │                              ↓
    │                        ┌──────────┐
    │                        │ CLAIMED  │
    │                        │ (已发放)  │
    │                        └──────────┘
    │                              │
    │ [超时/取消]                   │ [异常]
    ↓                              ↓
┌──────────┐                 ┌──────────┐
│ EXPIRED/ │                 │ FAILED   │
│CANCELLED │                 │(发放失败) │
└──────────┘                 └──────────┘
```

**状态说明:**

1. **PENDING (待发放)**
   - 初始状态
   - 管理员录入中奖后的状态
   - 等待管理员手动发放

2. **PROCESSING (发放中)**
   - 临时状态
   - 防止重复发放
   - 如果发放失败会回滚到 FAILED

3. **CLAIMED (已发放)**
   - 终态
   - 奖励已转入玩家账户
   - 已记录资金变动日志

4. **FAILED (发放失败)**
   - 终态 (可重试)
   - 发放过程中出现异常
   - `distribution_note` 记录失败原因

5. **EXPIRED (已过期)**
   - 终态
   - 活动结束后未发放的记录

6. **CANCELLED (已取消)**
   - 终态
   - 管理员手动取消

---

## 发放流程详解

### 方案 1: 批量发放活动所有待发放记录 ⭐ 推荐

**触发条件:**
- 活动状态: `STATUS_DRAWN` (已开奖待发放) 或 `STATUS_ONGOING` (进行中)
- 存在 `status = PENDING` 的中奖记录
- 管理员点击"发放奖励"按钮

**接口:** `ChannelLotteryTicketActivityController::batchDistributeActivity()`

**请求参数:**
```json
{
  "activity_id": 1
}
```

**流程步骤:**

```php
// ========== Step 1: 参数验证 ==========
1. 验证 activity_id (必须是数字)

// ========== Step 2: 活动验证 (带锁) ==========
2. 查询活动并加悲观锁
   LotteryTicketActivity::where('id', $activityId)
       ->lockForUpdate()
       ->first()

3. 检查权限
   if ($activity->department_id != Admin::user()->department_id)
       throw '无权限'

4. 检查活动状态
   允许状态: STATUS_DRAWN (已开奖待发放) 或 STATUS_ONGOING (进行中)
   if (!in_array($activity->status, [STATUS_DRAWN, STATUS_ONGOING]))
       throw '活动状态错误'

// ========== Step 3: 查询待发放记录 ==========
5. 查询所有符合条件的记录
   LotteryTicketRecord::where('activity_id', $activityId)
       ->where('department_id', $departmentId)
       ->where('status', STATUS_PENDING)
       ->where('prize_type', '!=', PRIZE_TYPE_EMPTY)  // 排除空奖
       ->where('prize_amount', '>', 0)                 // 排除0金额
       ->get()

6. 检查是否有待发放记录
   if ($pendingRecords->isEmpty())
       throw '没有待发放的记录'

// ========== Step 4: 逐条发放 (在同一事务内) ==========
$successCount = 0;
$failCount = 0;
$failReasons = [];

foreach ($pendingRecords as $record) {
    try {
        // 4.1 锁定当前记录
        $lockedRecord = LotteryTicketRecord::where('id', $record->id)
            ->lockForUpdate()
            ->first()

        // 4.2 再次检查状态 (防止并发)
        if (!$lockedRecord || $lockedRecord->status !== STATUS_PENDING)
            throw '状态已变更'

        // 4.3 更新状态为发放中 (防止重复发放)
        $lockedRecord->status = STATUS_PROCESSING
        $lockedRecord->save()

        // 4.4 锁定玩家记录
        $player = Player::where('id', $lockedRecord->player_id)
            ->lockForUpdate()
            ->first()

        if (!$player)
            throw '玩家不存在'

        // 4.5 检查玩家状态
        if ($player->status != Player::STATUS_ENABLE)
            throw '玩家已被禁用'

        // 4.6 检查是否超额发放
        $newDistributedAmount = $activity->distributed_prize_amount + $lockedRecord->prize_amount
        if ($newDistributedAmount > $activity->total_prize_amount)
            throw '发放金额超出总奖金额度'

        // 4.7 发放奖励 (根据奖品类型)
        $oldBalance = $player->money ?? 0

        if ($lockedRecord->prize_type == PRIZE_TYPE_CASH) {
            // 现金奖励
            $player->money = ($player->money ?? 0) + $lockedRecord->prize_amount
            $player->save()

            // 记录资金变动
            PlayerMoneyLog::create([
                'player_id' => $player->id,
                'department_id' => $player->department_id,
                'type' => 'lottery_reward',
                'money' => $lockedRecord->prize_amount,
                'before_money' => $oldBalance,
                'after_money' => $player->money,
                'remark' => '摸奖券中奖批量发放：' . $activity->name . ' - ' . $lockedRecord->prize_name,
                'created_at' => time(),
            ])
        }
        elseif ($lockedRecord->prize_type == PRIZE_TYPE_BONUS) {
            // 红利奖励
            $oldBonus = $player->bonus ?? 0
            $player->bonus = ($player->bonus ?? 0) + $lockedRecord->prize_amount
            $player->save()

            // 记录红利变动
            PlayerBonusLog::create([...])
        }

        // 4.8 更新中奖记录状态为已发放
        $lockedRecord->status = STATUS_CLAIMED
        $lockedRecord->distributed_by = $adminId
        $lockedRecord->distributed_at = date('Y-m-d H:i:s')
        $lockedRecord->distribution_note = '批量发放活动奖励'
        $lockedRecord->save()

        // 4.9 更新活动已发放金额
        $activity->distributed_prize_amount = $newDistributedAmount
        $activity->save()

        // 4.10 更新摸奖券状态 (如果存在)
        if ($lockedRecord->ticket_id > 0) {
            $ticket = LotteryTicket::find($lockedRecord->ticket_id)
            if ($ticket && $ticket->status == LotteryTicket::STATUS_UNUSED) {
                $ticket->status = LotteryTicket::STATUS_USED
                $ticket->used_at = time()
                $ticket->save()
            }
        }

        $successCount++

        // 4.11 推送中奖通知 (事务外,失败不影响发放)
        try {
            LotteryTicketPushService::pushPrizeDistributed(
                $player->id,
                $activity,
                $lockedRecord->ticket_no,
                $lockedRecord->prize_name,
                $lockedRecord->prize_amount
            )
        } catch (\Exception $e) {
            // 推送失败不影响发放,仅记录日志
            Log::warning('推送中奖通知失败', [...])
        }

    } catch (\Exception $e) {
        $failCount++
        $failReasons[] = '券号 ' . $record->ticket_no . ': ' . $e->getMessage()

        // 如果状态是发放中,标记为失败
        if (isset($lockedRecord) && $lockedRecord->status === STATUS_PROCESSING) {
            $lockedRecord->status = STATUS_FAILED
            $lockedRecord->distribution_note = '批量发放失败: ' . $e->getMessage()
            $lockedRecord->save()
        }

        Log::error('批量发放单条记录失败', [...])
    }
}

Db::commit()

// ========== Step 5: 记录操作日志 ==========
Log::info('[摸奖券] 批量发放活动奖励完成', [
    'activity_id' => $activityId,
    'activity_name' => $activity->name,
    'total' => $pendingRecords->count(),
    'success' => $successCount,
    'fail' => $failCount,
    'admin_id' => $adminId
])

// ========== Step 6: 返回结果 ==========
if ($failCount > 0 && $successCount > 0) {
    // 部分成功
    return Response::success([
        'message' => "批量发放完成：成功 {$successCount} 条，失败 {$failCount} 条",
        'fail_reasons' => $failReasons,
        'success_count' => $successCount,
        'fail_count' => $failCount
    ])
}
elseif ($failCount > 0 && $successCount === 0) {
    // 全部失败
    return message_error($message . ' ' . implode('; ', $failReasons))
}

// 全部成功
return message_success("批量发放完成：成功 {$successCount} 条")
```

**响应示例:**

成功:
```json
{
  "code": 200,
  "message": "批量发放完成：成功 8 条，失败 0 条",
  "data": {
    "success_count": 8,
    "fail_count": 0
  }
}
```

部分失败:
```json
{
  "code": 200,
  "message": "批量发放完成：成功 6 条，失败 2 条",
  "data": {
    "success_count": 6,
    "fail_count": 2,
    "fail_reasons": [
      "券号 000123: 玩家已被禁用，无法发放奖励",
      "券号 000456: 发放金额超出总奖金额度"
    ]
  }
}
```

---

### 方案 2: 单条发放 (按券号)

**接口:** `ChannelLotteryTicketActivityController::distributeByTicketNo()`

**请求参数:**
```json
{
  "activity_id": 1,
  "ticket_no": "000123",
  "remark": "补发漏发奖励"
}
```

**流程步骤:**
(与批量发放类似,但只处理单条记录)

1. 验证券号格式 (必须是6位数字)
2. 查找中奖记录 (根据 activity_id + ticket_no)
3. 验证记录状态 (必须是 PENDING)
4. 发放奖励 (同批量发放的单条流程)
5. 返回结果

---

## 记录创建流程

### 1. 摸奖券发放 (LotteryTicket)

**触发场景:**

1. **打码获得** (最常见)
   - 玩家游戏打码达到配置的金额
   - `LotteryTicketBetProgressService` 自动发券

2. **充值赠送**
   - 玩家充值达到一定金额
   - 系统自动赠送摸奖券

3. **手动发放**
   - 管理员手动给玩家发券
   - 用于补偿或活动奖励

**创建流程:**

```php
// 使用 LotteryTicketIssueService::issueTickets()
$tickets = $issueService->issueTickets(
    $activityId,    // 活动ID
    $playerId,      // 玩家ID
    $count,         // 发放数量
    $source         // 来源: recharge/activity/betting/manual
);

// 内部逻辑:
1. 检查活动状态 (必须是 ONGOING)
2. 检查活动剩余容量 (Redis: lottery_activity:{id}:ticket_sequence)
3. 使用 Redis INCR 生成唯一序列号 (原子操作)
4. 生成6位券号: str_pad($sequence, 6, '0', STR_PAD_LEFT)
5. 创建 LotteryTicket 记录
   - status: UNUSED
   - ticket_no: 000123
   - expired_at: 活动结束时间
6. 清除玩家券缓存
7. 返回券列表
```

**数据示例:**
```php
LotteryTicket::create([
    'activity_id' => 1,
    'player_id' => 1001,
    'department_id' => 100,
    'ticket_no' => '000123',
    'status' => LotteryTicket::STATUS_UNUSED,
    'source' => 'betting',  // 打码获得
    'source_id' => 0,
    'issued_at' => '2024-01-05 10:30:00',
    'expired_at' => '2024-01-31 23:59:59',
    'created_at' => now(),
    'updated_at' => now(),
]);
```

---

### 2. 中奖记录创建 (LotteryTicketRecord)

**触发场景:**

1. **录入中奖 (按券号批量录入)** ⭐ 主要方式
   - 管理员在"录入中奖"页面输入中奖券号
   - 选择对应的奖品等级
   - 系统批量创建中奖记录

2. **摇球开奖 (自动创建)**
   - 系统随机抽取中奖券号
   - 自动创建中奖记录

**创建接口:** `ChannelLotteryTicketActivityController::recordWinByTickets()`

**请求参数:**
```json
{
  "activity_id": 1,
  "records": [
    {
      "prize_level_id": 2,    // 一等奖ID
      "ticket_no": "000123"
    },
    {
      "prize_level_id": 3,    // 二等奖ID
      "ticket_no": "000456"
    }
  ]
}
```

**创建流程:**

```php
foreach ($records as $record) {
    // 1. 查找摸奖券
    $ticket = LotteryTicket::where('ticket_no', $ticketNo)
        ->where('activity_id', $activityId)
        ->where('status', LotteryTicket::STATUS_UNUSED)
        ->first()

    if (!$ticket) {
        $errors[] = "券号 {$ticketNo} 不存在或已使用"
        continue
    }

    // 2. 查找奖品等级
    $prizeLevel = LotteryTicketPrizeLevel::find($prizeLevelId)

    if (!$prizeLevel) {
        $errors[] = "券号 {$ticketNo} 的奖品等级不存在"
        continue
    }

    // 3. 创建中奖记录
    LotteryTicketRecord::create([
        'activity_id' => $activityId,
        'player_id' => $ticket->player_id,
        'department_id' => $activity->department_id,
        'ticket_id' => $ticket->id,
        'ticket_no' => $ticketNo,
        'prize_type' => $prizeLevel->prize_type,     // cash
        'prize_name' => $prizeLevel->level_name,     // 一等奖
        'prize_amount' => $prizeLevel->prize_amount, // 10000.00
        'status' => LotteryTicketRecord::STATUS_PENDING,  // ⭐ 待发放
    ])

    // 4. 更新摸奖券状态为已使用
    $ticket->status = LotteryTicket::STATUS_USED
    $ticket->save()

    // 5. 推送中奖通知
    LotteryTicketPushService::pushWinNotification($record)

    $successCount++
}

return Response::success([
    'success_count' => $successCount,
    'error_count' => count($errors),
    'errors' => $errors
])
```

**数据示例:**
```php
LotteryTicketRecord::create([
    'activity_id' => 1,
    'player_id' => 1001,
    'department_id' => 100,
    'ticket_id' => 123,
    'ticket_no' => '000123',
    'prize_type' => 'cash',
    'prize_name' => '一等奖',
    'prize_amount' => 10000.00,
    'status' => 0,  // PENDING (待发放) ⭐
    'distributed_by' => null,
    'distributed_at' => null,
    'distribution_note' => null,
    'remark' => null,
    'created_at' => now(),
    'updated_at' => now(),
]);
```

---

### 3. 资金变动日志创建 (PlayerMoneyLog)

**触发时机:**
- 发放现金奖励时自动创建

**创建位置:**
- `batchDistributeActivity()` 中
- `distributeByTicketNo()` 中
- `ChannelLotteryTicketRecordController::distribute()` 中

**创建代码:**
```php
if ($record->prize_type == LotteryTicketRecord::PRIZE_TYPE_CASH) {
    // 增加玩家余额
    $oldBalance = $player->money ?? 0;
    $player->money = $oldBalance + $record->prize_amount;
    $player->save();

    // 创建资金变动日志 ⭐
    PlayerMoneyLog::create([
        'player_id' => $player->id,
        'department_id' => $player->department_id,
        'type' => 'lottery_reward',  // 资金变动类型
        'money' => $record->prize_amount,
        'before_money' => $oldBalance,
        'after_money' => $player->money,
        'remark' => '摸奖券中奖发放：' . $activity->name . ' - ' . $record->prize_name,
        'created_at' => time(),  // Unix 时间戳
    ]);
}
```

**数据示例:**
```php
[
    'id' => 1,
    'player_id' => 1001,
    'department_id' => 100,
    'type' => 'lottery_reward',
    'money' => 10000.00,
    'before_money' => 5000.00,
    'after_money' => 15000.00,
    'remark' => '摸奖券中奖发放：2024春节摸奖活动 - 一等奖',
    'created_at' => 1706498400,
]
```

---

### 4. 红利变动日志创建 (PlayerBonusLog)

**触发时机:**
- 发放红利奖励时自动创建

**创建代码:**
```php
if ($record->prize_type == LotteryTicketRecord::PRIZE_TYPE_BONUS) {
    // 增加玩家红利
    $oldBonus = $player->bonus ?? 0;
    $player->bonus = $oldBonus + $record->prize_amount;
    $player->save();

    // 创建红利变动日志 ⭐
    PlayerBonusLog::create([
        'player_id' => $player->id,
        'department_id' => $player->department_id,
        'type' => 'lottery_reward',
        'bonus' => $record->prize_amount,
        'before_bonus' => $oldBonus,
        'after_bonus' => $player->bonus,
        'remark' => '摸奖券中奖发放：' . $activity->name . ' - ' . $record->prize_name,
        'created_at' => time(),
    ]);
}
```

---

## API 接口说明

### 1. 批量发放活动所有待发放奖励

**接口:** `POST /ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/batchDistributeActivity`

**权限:** `@auth true`, `@group channel`

**请求参数:**
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| activity_id | int | 是 | 活动ID |

**响应数据:**
```json
{
  "code": 200,
  "message": "批量发放完成：成功 8 条，失败 0 条",
  "data": {
    "success_count": 8,
    "fail_count": 0,
    "fail_reasons": []
  }
}
```

---

### 2. 按券号发放奖励

**接口:** `POST /ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/distributeByTicketNo`

**权限:** `@auth true`, `@group channel`

**请求参数:**
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| activity_id | int | 是 | 活动ID |
| ticket_no | string(6) | 是 | 中奖券号 (6位数字) |
| remark | string | 否 | 发放备注 (最多255字符) |

**响应数据:**
```json
{
  "code": 200,
  "message": "发放成功",
  "data": {
    "ticket_no": "000123",
    "player_name": "玩家001",
    "prize_level": "一等奖",
    "prize_amount": 10000.00,
    "prize_type": "cash"
  }
}
```

---

### 3. 录入中奖 (按券号批量录入)

**接口:** `POST /ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/recordWinByTickets`

**权限:** `@auth true`, `@group channel`

**请求参数:**
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| activity_id | int | 是 | 活动ID |
| records | array | 是 | 中奖记录数组 |
| records[].prize_level_id | int | 是 | 奖品等级ID |
| records[].ticket_no | string(6) | 是 | 中奖券号 |

**响应数据:**
```json
{
  "code": 200,
  "data": {
    "success_count": 5,
    "error_count": 1,
    "errors": [
      "券号 000999 不存在或已使用"
    ]
  }
}
```

---

### 4. 获取活动列表

**接口:** `POST /ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getActivities`

**响应数据:**
```json
{
  "code": 200,
  "data": [
    {
      "id": 1,
      "name": "2024春节摸奖活动",
      "status": 1,
      "start_time": "2024-01-01 00:00:00",
      "end_time": "2024-01-31 23:59:59",
      "total_tickets": 1250,
      "used_tickets": 320,
      "pending_count": 8,  // ⭐ 待发放数量
      "has_prize_config": true
    }
  ]
}
```

---

### 5. 中奖记录列表

**接口:** `GET /ex-admin/channel-lottery-ticket-record/index`

**权限:** `@auth true`, `@group channel`

**筛选参数:**
| 参数名 | 类型 | 说明 |
|--------|------|------|
| activity_id | int | 活动ID |
| status | int | 发放状态 (0:待发放, 1:已发放) |
| prize_type | string | 奖品类型 |
| player_name | string | 玩家名称 (模糊搜索) |
| start_time | datetime | 开始时间 |
| end_time | datetime | 结束时间 |

**响应:** Grid 列表页面

---

## 错误处理

### 常见错误及处理方案

#### 1. 活动状态错误

**错误:** `活动状态错误，只能发放已开奖待发放的活动奖励`

**原因:**
- 活动状态不是 `STATUS_DRAWN` (已开奖待发放)
- 活动状态不是 `STATUS_ONGOING` (进行中)

**解决方案:**
1. 检查活动状态
2. 确保活动已开奖
3. 如果活动已结束,无法发放

---

#### 2. 记录状态变更

**错误:** `状态已变更`

**原因:**
- 并发发放,记录状态已被其他请求修改
- 记录已经发放过

**解决方案:**
- 使用悲观锁 (`lockForUpdate()`)
- 再次检查状态
- 跳过该记录,继续发放下一条

---

#### 3. 玩家不存在或被禁用

**错误:** `玩家不存在` 或 `玩家已被禁用，无法发放奖励`

**原因:**
- 玩家记录被删除
- 玩家状态 != `Player::STATUS_ENABLE`

**解决方案:**
1. 检查玩家状态
2. 联系管理员启用玩家账户
3. 或取消该中奖记录

---

#### 4. 超额发放

**错误:** `发放金额超出总奖金额度`

**原因:**
- `distributed_prize_amount + prize_amount > total_prize_amount`
- 活动总奖金额度不足

**解决方案:**
1. 增加活动总奖金额度
2. 或调整奖品金额
3. 或减少中奖记录

---

#### 5. 券号不存在或已使用

**错误:** `券号 000123 不存在或已使用`

**原因:**
- 券号输入错误
- 摸奖券已被使用

**解决方案:**
1. 核对券号
2. 检查 `lottery_ticket` 表的 `status` 字段
3. 确认券号未被录入过中奖

---

#### 6. 空奖无需发放

**错误:** `空奖无需发放`

**原因:**
- `prize_type = 'empty'`

**解决方案:**
- 系统会自动跳过空奖记录
- 无需手动处理

---

#### 7. 奖品金额必须大于0

**错误:** `奖品金额必须大于0`

**原因:**
- `prize_amount <= 0`

**解决方案:**
- 检查奖品等级配置
- 确保奖品金额 > 0

---

### 异常回滚机制

**事务回滚:**
- 所有数据库操作都在事务内执行
- 任何异常都会触发 `Db::rollBack()`
- 保证数据一致性

**状态回滚:**
- 发放中 (`PROCESSING`) → 发放失败 (`FAILED`)
- `distribution_note` 记录失败原因

**Redis 序列号浪费:**
- 使用 Redis INCR 生成券号,回滚会浪费序列号
- 系统会记录浪费的序列号数量
- 日志: `[摸奖券] 发放失败，序列号已浪费`

---

## 日志记录

### 日志级别

- `Log::info()` - 正常操作 (发放成功、创建记录)
- `Log::warning()` - 警告 (推送失败、容量不足)
- `Log::error()` - 错误 (发放失败、异常)

---

### 关键日志示例

#### 1. 批量发放成功

```php
Log::info('[摸奖券] 批量发放活动奖励完成', [
    'activity_id' => 1,
    'activity_name' => '2024春节摸奖活动',
    'total' => 10,
    'success' => 8,
    'fail' => 2,
    'admin_id' => 2
]);
```

---

#### 2. 单条发放成功

```php
Log::info('[摸奖券] 根据券号发放奖励成功', [
    'activity_id' => 1,
    'ticket_no' => '000123',
    'player_id' => 1001,
    'player_name' => '玩家001',
    'prize_name' => '一等奖',
    'prize_amount' => 10000.00,
    'old_balance' => 5000.00,
    'new_balance' => 15000.00,
    'admin_id' => 2,
    'remark' => '补发漏发奖励'
]);
```

---

#### 3. 发放失败

```php
Log::error('[摸奖券] 批量发放单条记录失败', [
    'record_id' => 123,
    'ticket_no' => '000456',
    'error' => '玩家已被禁用，无法发放奖励'
]);
```

---

#### 4. 推送通知失败

```php
Log::warning('[摸奖券] 推送中奖通知失败', [
    'ticket_no' => '000123',
    'error' => 'Connection timeout'
]);
```

---

#### 5. 录入中奖成功

```php
Log::info('[摸奖券] 录入中奖成功', [
    'activity_id' => 1,
    'success_count' => 5,
    'error_count' => 1
]);
```

---

## 附录

### 推荐的前端展示

**活动卡片统计:**
```
┌─────────────────────────────────┐
│  2024春节摸奖活动                 │
├─────────────────────────────────┤
│  总发放数量    │    待发放        │
│    📄 1,250    │    🎁 8         │
├─────────────────────────────────┤
│  [🎁 发放奖励 (8)]               │
│  [📋 查看发放列表]                │
└─────────────────────────────────┘
```

**中奖记录列表:**
| 券号 | 玩家 | 奖品等级 | 奖品金额 | 状态 | 操作 |
|------|------|---------|---------|------|------|
| 000123 | 玩家001 | 一等奖 | ¥10,000 | 🟢 已发放 | 查看详情 |
| 000456 | 玩家002 | 二等奖 | ¥5,000 | 🟠 待发放 | 发放 |
| 000789 | 玩家003 | 三等奖 | ¥1,000 | 🔴 发放失败 | 重试 |

---

### 性能优化建议

1. **使用悲观锁**
   - 防止并发发放重复
   - `lockForUpdate()`

2. **批量发放**
   - 一次事务处理多条记录
   - 提高发放效率

3. **异步推送通知**
   - 推送失败不影响发放
   - 使用队列处理

4. **Redis 缓存**
   - 券号序列使用 Redis INCR
   - 玩家券列表缓存

5. **数据库索引**
   ```sql
   -- lottery_ticket_record
   INDEX idx_activity_status (activity_id, status)
   INDEX idx_ticket_no (ticket_no)
   INDEX idx_player_id (player_id)

   -- lottery_ticket
   UNIQUE INDEX uk_activity_ticket (activity_id, ticket_no)
   INDEX idx_player_status (player_id, status)
   ```

---

### 安全检查清单

- [ ] 权限验证 (department_id 一致性)
- [ ] 状态检查 (PENDING → PROCESSING → CLAIMED)
- [ ] 金额验证 (prize_amount > 0)
- [ ] 玩家状态 (STATUS_ENABLE)
- [ ] 活动状态 (STATUS_DRAWN / STATUS_ONGOING)
- [ ] 超额检查 (distributed_prize_amount <= total_prize_amount)
- [ ] 并发控制 (悲观锁)
- [ ] 事务完整性 (Db::beginTransaction/commit/rollBack)
- [ ] 日志记录 (成功/失败/异常)
- [ ] 错误处理 (try-catch)

---

## 文档维护

**最后更新:** 2026-06-13  
**维护人员:** Claude (AI Assistant)  
**版本:** v2.0  
**变更记录:**
- 2026-06-13: 创建完整发放流程文档,包含批量发放和记录创建流程
- 2026-06-13: 添加状态流转图和错误处理章节
- 2026-06-13: 补充日志记录和性能优化建议

---

**End of Document**
