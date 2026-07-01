# 摸奖券开奖发放流程重新设计

**设计日期:** 2026-06-11  
**设计人员:** AI Assistant  
**需求来源:** 用户反馈  

---

## 🎯 新流程设计目标

### 核心需求

1. ✅ **开奖和发放分离** - 开奖只确定中奖号码，不立即发放
2. ✅ **支持二次调整** - 管理员可以修改中奖券号
3. ✅ **人工审核确认** - 确认无误后才发放奖励
4. ✅ **推送通知** - 发放后玩家才收到中奖通知

---

## 📋 新流程图

### 完整流程

```
┌─────────────────────────────────────────────────────────────────┐
│                      摸奖券开奖发放完整流程                        │
└─────────────────────────────────────────────────────────────────┘

步骤1: 活动进行中
├─ 玩家打码
├─ 自动发券
└─ 活动结束（管理员手动或定时自动）

步骤2: 管理员执行开奖 [后管操作]
├─ 后管: 点击"开奖"按钮
├─ 系统: 摇球生成中奖号码
├─ 系统: 自动匹配中奖券
├─ 系统: 创建中奖记录（status=PENDING 待发放）
├─ 活动状态: DRAWING(4) → DRAWN(7) [新增状态]
└─ ❌ 不发放奖励，不推送通知

步骤3: 管理员审核中奖名单 [后管操作]
├─ 后管: 查看中奖记录列表
├─ 后管: 检查中奖券号是否正确
├─ 后管: 如需调整 → 点击"修改"
│   ├─ 修改中奖券号
│   ├─ 修改奖品等级
│   ├─ 修改奖金金额
│   └─ 保存修改
├─ 后管: 如需删除 → 点击"删除"
├─ 后管: 如需新增 → 点击"新增中奖记录"
└─ 支持多次调整

步骤4: 管理员发放奖励 [后管操作]
├─ 后管: 点击"发放奖励"按钮（单个或批量）
├─ 系统: 弹窗确认"确定发放XXX名中奖玩家的奖励？"
├─ 后管: 确认
├─ 系统: 批量转账（增加玩家余额）
├─ 系统: 更新中奖记录（status=PENDING → CLAIMED）
├─ 系统: 推送中奖通知（WebSocket）
├─ 玩家: 收到中奖弹窗
└─ 日志: 记录发放明细

步骤5: 活动结束
├─ 所有中奖记录发放完毕
├─ 活动状态: DRAWN(7) → ENDED(5)
└─ 归档活动数据
```

---

## 🔄 活动状态扩展

### 原状态定义（6个）

| 状态值 | 常量名 | 说明 |
|--------|--------|------|
| 0 | NOT_STARTED | 未开始 |
| 1 | PREHEATING | 预热中 |
| 2 | BETTING | 打码中 |
| 3 | ONGOING | 进行中 |
| 4 | DRAWING | 开奖中 |
| 5 | ENDED | 已结束 |
| 6 | CLOSED | 已关闭 |

### 新增状态（1个）

| 状态值 | 常量名 | 说明 |
|--------|--------|------|
| **7** | **DRAWN** | **已开奖待发放** ⭐ 新增 |

### 新的状态流转

```
NOT_STARTED(0) → PREHEATING(1) → BETTING(2) → ONGOING(3) 
    → DRAWING(4) → DRAWN(7) → ENDED(5) → CLOSED(6)
                      ↑           ↓
                      └───────────┘
                    可重复开奖（重新摇球）
```

**状态说明:**

- **DRAWING(4)**: 开奖中 - 正在执行摇球操作
- **DRAWN(7)**: 已开奖待发放 - 摇球完成，中奖记录已创建，等待管理员审核和发放 ⭐
- **ENDED(5)**: 已结束 - 所有奖励已发放，活动完结

---

## 🗄️ 中奖记录状态扩展

### 原状态定义

```php
// LotteryTicketRecord.php
const STATUS_PENDING = 0;   // 待领取
const STATUS_CLAIMED = 1;   // 已领取
const STATUS_EXPIRED = 2;   // 已过期
const STATUS_CANCELLED = 3; // 已取消
```

### 新增状态

```php
// LotteryTicketRecord.php
const STATUS_PENDING = 0;      // 待发放 ⭐ 含义变更
const STATUS_PROCESSING = 4;   // 发放中 ⭐ 新增
const STATUS_CLAIMED = 1;      // 已发放 ⭐ 含义变更
const STATUS_FAILED = 5;       // 发放失败 ⭐ 新增
const STATUS_CANCELLED = 3;    // 已取消
const STATUS_EXPIRED = 2;      // 已过期
```

### 状态流转

```
开奖后创建: STATUS_PENDING(0) 待发放
              ↓
管理员点击发放: STATUS_PROCESSING(4) 发放中
              ↓
发放成功: STATUS_CLAIMED(1) 已发放
发放失败: STATUS_FAILED(5) 发放失败
              ↓
管理员取消: STATUS_CANCELLED(3) 已取消
```

---

## 🛠️ 数据库设计调整

### 活动表 (lottery_ticket_activity)

**新增字段:**

```sql
ALTER TABLE `lottery_ticket_activity` 
ADD COLUMN `draw_completed_at` DATETIME NULL COMMENT '开奖完成时间' AFTER `ball_result`,
ADD COLUMN `prize_distributed_at` DATETIME NULL COMMENT '奖励发放完成时间' AFTER `draw_completed_at`,
ADD COLUMN `total_prize_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '总奖金金额' AFTER `prize_distributed_at`,
ADD COLUMN `distributed_prize_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '已发放奖金金额' AFTER `total_prize_amount`;
```

**字段说明:**
- `draw_completed_at`: 记录开奖完成时间（进入DRAWN状态）
- `prize_distributed_at`: 记录全部奖励发放完成时间（进入ENDED状态）
- `total_prize_amount`: 本期活动总奖金金额
- `distributed_prize_amount`: 已发放的奖金金额

---

### 中奖记录表 (lottery_ticket_record)

**新增字段:**

```sql
ALTER TABLE `lottery_ticket_record` 
ADD COLUMN `distributed_by` INT NULL COMMENT '发放操作人ID（admin_user_id）' AFTER `status`,
ADD COLUMN `distributed_at` DATETIME NULL COMMENT '发放时间' AFTER `distributed_by`,
ADD COLUMN `distribution_note` VARCHAR(500) NULL COMMENT '发放备注' AFTER `distributed_at`,
ADD COLUMN `modified_by` INT NULL COMMENT '最后修改人ID' AFTER `distribution_note`,
ADD COLUMN `modified_at` DATETIME NULL COMMENT '最后修改时间' AFTER `modified_by`,
ADD COLUMN `modification_reason` VARCHAR(500) NULL COMMENT '修改原因' AFTER `modified_at`;
```

**字段说明:**
- `distributed_by`: 发放操作的管理员ID（可追溯）
- `distributed_at`: 实际发放时间
- `distribution_note`: 发放时的备注说明
- `modified_by`: 修改中奖记录的管理员ID
- `modified_at`: 修改时间
- `modification_reason`: 修改原因（便于审计）

---

## 🎨 后管界面设计

### 界面1: 活动列表（已开奖待发放）

**新增筛选条件:**

```
活动状态筛选:
[ ] 全部
[ ] 进行中 (ONGOING)
[ ] 开奖中 (DRAWING)
[✓] 已开奖待发放 (DRAWN) ⭐ 新增
[ ] 已结束 (ENDED)
```

**列表字段:**

| 活动名称 | 开奖时间 | 中奖数 | 待发放 | 已发放 | 总奖金 | 已发放金额 | 操作 |
|---------|---------|--------|--------|--------|--------|-----------|------|
| 春節摸獎券 | 2026-06-11 10:00 | 100 | 100 | 0 | ¥1,000,000 | ¥0 | [查看中奖名单] [批量发放] |

**操作按钮:**
- **查看中奖名单**: 跳转到中奖记录管理页面
- **批量发放**: 一键发放所有待发放记录

---

### 界面2: 中奖记录管理（核心界面）⭐

**页面路径:** `/ex-admin/channel-lottery-ticket-record/index?activity_id={id}`

**顶部统计卡片:**

```
┌─────────────────────────────────────────────────────────────────┐
│  本期中奖统计                                                      │
├─────────────────────────────────────────────────────────────────┤
│  中奖总数: 100人  |  待发放: 100人  |  已发放: 0人  |  总奖金: ¥1,000,000  │
└─────────────────────────────────────────────────────────────────┘
```

**工具栏:**

```
┌─────────────────────────────────────────────────────────────────┐
│  [➕ 新增中奖记录]  [🔄 重新开奖]  [📤 批量发放]  [📊 导出Excel]      │
└─────────────────────────────────────────────────────────────────┘
```

**筛选条件:**

```
状态: [全部 ▼] [待发放] [已发放] [发放失败]
奖品等级: [全部 ▼] [特等奖] [一等奖] [二等奖]...
玩家ID: [_________]
券号: [_________]
[搜索]
```

**列表表格:**

| 选择 | ID | 玩家ID | 玩家名称 | 券号 | 奖品等级 | 奖金金额 | 状态 | 发放时间 | 操作 |
|-----|----|----|--------|------|---------|---------|------|---------|------|
| ☑️ | 1 | 12345 | player001 | 102458 | 特等奖 | ¥88,888 | 待发放 | - | [修改] [删除] [发放] |
| ☑️ | 2 | 12346 | player002 | 102457 | 一等奖 | ¥8,888 | 待发放 | - | [修改] [删除] [发放] |
| ☐ | 3 | 12347 | player003 | 102456 | 二等奖 | ¥888 | 已发放 | 2026-06-11 10:30 | [查看] |

**批量操作按钮:**
- **批量发放**: 发放选中的所有待发放记录
- **批量删除**: 删除选中的待发放记录

**单条操作按钮（待发放状态）:**
- **修改**: 弹窗修改券号、奖品等级、奖金金额
- **删除**: 删除此条中奖记录
- **发放**: 单独发放此条记录

**单条操作按钮（已发放状态）:**
- **查看**: 查看发放详情（发放时间、操作人、备注）

---

### 界面3: 修改中奖记录弹窗

**弹窗标题:** 修改中奖记录

**表单字段:**

```
┌─────────────────────────────────────────────────────────────────┐
│  玩家ID: 12345 (只读)                                             │
│  玩家名称: player001 (只读)                                       │
│                                                                   │
│  券号: [102458____]  ⭐ 可修改                                    │
│  提示: 修改券号前请确认该券属于此玩家                                │
│                                                                   │
│  奖品等级: [特等奖 ▼]  ⭐ 可修改                                  │
│  选项: 特等奖、一等奖、二等奖、三等奖...                            │
│                                                                   │
│  奖金金额: [88888.00]  ⭐ 可修改                                  │
│  提示: 金额会根据奖品等级自动填充，也可手动修改                       │
│                                                                   │
│  修改原因: [_____________________________________________]         │
│  必填，便于后续审计                                                │
│                                                                   │
│  [取消]  [保存]                                                   │
└─────────────────────────────────────────────────────────────────┘
```

**验证规则:**
- 券号必须是6位数字
- 券号必须存在于本期活动
- 券号不能重复中奖（同一券不能有多条中奖记录）
- 奖金金额必须 > 0
- 修改原因必填

---

### 界面4: 新增中奖记录弹窗

**弹窗标题:** 手动新增中奖记录

**表单字段:**

```
┌─────────────────────────────────────────────────────────────────┐
│  玩家ID: [_________]  [搜索玩家]                                  │
│  或 券号: [_________]  [根据券号自动填充玩家]                      │
│                                                                   │
│  玩家名称: player001 (自动填充)                                   │
│                                                                   │
│  券号: [102458____]  ⭐                                          │
│  提示: 必须是本期活动的有效券号                                     │
│                                                                   │
│  奖品等级: [特等奖 ▼]  ⭐                                         │
│                                                                   │
│  奖金金额: [88888.00]  ⭐                                         │
│  根据奖品等级自动填充                                              │
│                                                                   │
│  新增原因: [_____________________________________________]         │
│  必填，说明为何手动新增                                            │
│                                                                   │
│  [取消]  [保存]                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

### 界面5: 发放确认弹窗

**弹窗标题:** 确认发放奖励

**单个发放:**

```
┌─────────────────────────────────────────────────────────────────┐
│  确定要发放以下奖励吗？                                            │
│                                                                   │
│  玩家: player001 (ID: 12345)                                     │
│  券号: 102458                                                    │
│  奖品: 特等奖                                                     │
│  奖金: ¥88,888.00                                                │
│                                                                   │
│  发放备注(可选): [_________________________________________]       │
│                                                                   │
│  ⚠️ 发放后将增加玩家余额，且无法撤销，请确认无误！                  │
│                                                                   │
│  [取消]  [确认发放]                                               │
└─────────────────────────────────────────────────────────────────┘
```

**批量发放:**

```
┌─────────────────────────────────────────────────────────────────┐
│  确定要批量发放奖励吗？                                            │
│                                                                   │
│  发放数量: 100 人                                                 │
│  总奖金: ¥1,000,000.00                                           │
│                                                                   │
│  明细:                                                            │
│  - 特等奖 1人: ¥88,888                                           │
│  - 一等奖 10人: ¥88,880                                          │
│  - 二等奖 20人: ¥17,760                                          │
│  - ...                                                           │
│                                                                   │
│  发放备注(可选): [_________________________________________]       │
│                                                                   │
│  ⚠️ 批量发放后将增加所有中奖玩家的余额，且无法撤销！                │
│                                                                   │
│  [取消]  [确认发放]                                               │
└─────────────────────────────────────────────────────────────────┘
```

---

### 界面6: 重新开奖确认弹窗

**弹窗标题:** 重新开奖

```
┌─────────────────────────────────────────────────────────────────┐
│  ⚠️ 警告：重新开奖将清空当前所有待发放的中奖记录！                  │
│                                                                   │
│  当前中奖记录:                                                     │
│  - 待发放: 100 条                                                 │
│  - 已发放: 0 条                                                   │
│                                                                   │
│  重新开奖后:                                                       │
│  1. 清空所有【待发放】的中奖记录                                    │
│  2. 保留所有【已发放】的记录                                        │
│  3. 重新摇球生成新的中奖号码                                        │
│  4. 创建新的中奖记录                                               │
│                                                                   │
│  原因(必填): [_____________________________________________]        │
│                                                                   │
│  [取消]  [确认重新开奖]                                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔧 后端API设计

### API 1: 获取中奖记录列表

**接口:** `GET /ex-admin/channel-lottery-ticket-record/index`

**权限:** @auth true, @group channel

**参数:**
```json
{
  "activity_id": 123,
  "status": 0,  // 0=待发放, 1=已发放, 4=发放中, 5=发放失败, null=全部
  "prize_level": "",  // 奖品等级筛选
  "player_id": "",
  "ticket_no": "",
  "page": 1,
  "size": 20
}
```

**返回:**
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "total": 100,
    "list": [
      {
        "id": 1,
        "activity_id": 123,
        "player_id": 12345,
        "player_name": "player001",
        "ticket_id": 1001,
        "ticket_no": "102458",
        "prize_type": "cash",
        "prize_name": "特等奖",
        "prize_amount": 88888.00,
        "status": 0,  // 待发放
        "distributed_by": null,
        "distributed_at": null,
        "distribution_note": null,
        "modified_by": null,
        "modified_at": null,
        "modification_reason": null,
        "created_at": "2026-06-11 10:00:00"
      }
    ],
    "statistics": {
      "total_count": 100,
      "pending_count": 100,
      "claimed_count": 0,
      "failed_count": 0,
      "total_amount": 1000000.00,
      "distributed_amount": 0.00
    }
  }
}
```

---

### API 2: 修改中奖记录

**接口:** `POST /ex-admin/channel-lottery-ticket-record/update`

**权限:** @auth true, @group channel

**参数:**
```json
{
  "id": 1,
  "ticket_no": "102458",  // 可修改
  "prize_level": "特等奖",  // 可修改
  "prize_amount": 88888.00,  // 可修改
  "modification_reason": "券号录入错误，更正为102458"  // 必填
}
```

**业务逻辑:**
1. 检查记录是否存在
2. 检查记录状态（只能修改待发放的记录）
3. 验证券号（必须属于本活动，不能重复中奖）
4. 更新记录
5. 记录修改历史（modified_by, modified_at, modification_reason）

**返回:**
```json
{
  "code": 200,
  "msg": "修改成功"
}
```

---

### API 3: 删除中奖记录

**接口:** `POST /ex-admin/channel-lottery-ticket-record/delete`

**权限:** @auth true, @group channel

**参数:**
```json
{
  "id": 1
}
```

**业务逻辑:**
1. 检查记录状态（只能删除待发放的记录）
2. 软删除或硬删除（建议硬删除，因为还未发放）
3. 记录删除日志

**返回:**
```json
{
  "code": 200,
  "msg": "删除成功"
}
```

---

### API 4: 新增中奖记录

**接口:** `POST /ex-admin/channel-lottery-ticket-record/create`

**权限:** @auth true, @group channel

**参数:**
```json
{
  "activity_id": 123,
  "ticket_no": "102458",  // 根据券号自动查找玩家
  "prize_level": "特等奖",
  "prize_amount": 88888.00,
  "creation_reason": "人工补录漏掉的中奖记录"  // 必填
}
```

**业务逻辑:**
1. 根据券号查找券记录（LotteryTicket）
2. 验证券是否属于本活动
3. 验证券是否已中奖
4. 获取玩家信息
5. 创建中奖记录（status=PENDING）
6. 记录创建日志（包含creation_reason）

**返回:**
```json
{
  "code": 200,
  "msg": "新增成功",
  "data": {
    "id": 101,
    "player_id": 12345,
    "player_name": "player001"
  }
}
```

---

### API 5: 发放奖励（单个）⭐ 核心

**接口:** `POST /ex-admin/channel-lottery-ticket-record/distribute`

**权限:** @auth true, @group channel

**参数:**
```json
{
  "id": 1,
  "distribution_note": "正常发放"  // 可选备注
}
```

**业务逻辑:**

```php
public function distribute()
{
    $id = Request::input('id');
    $note = Request::input('distribution_note', '');
    $adminId = Admin::user()->id;
    
    Db::beginTransaction();
    try {
        // 1. 锁定中奖记录
        $record = LotteryTicketRecord::where('id', $id)
            ->lockForUpdate()
            ->first();
        
        if (!$record) {
            throw new \Exception('中奖记录不存在');
        }
        
        // 2. 检查权限
        $activity = LotteryTicketActivity::find($record->activity_id);
        if ($activity->department_id != Admin::user()->department_id) {
            throw new \Exception('无权操作此活动');
        }
        
        // 3. 检查状态
        if ($record->status !== LotteryTicketRecord::STATUS_PENDING) {
            throw new \Exception('记录状态不正确，只能发放待发放的记录');
        }
        
        // 4. 更新状态为发放中
        $record->status = LotteryTicketRecord::STATUS_PROCESSING;
        $record->save();
        
        // 5. 转账到玩家账户
        $player = Player::lockForUpdate()->find($record->player_id);
        if (!$player) {
            throw new \Exception('玩家不存在');
        }
        
        $oldBalance = $player->balance;
        $player->balance += $record->prize_amount;
        $player->save();
        
        // 6. 更新中奖记录状态
        $record->status = LotteryTicketRecord::STATUS_CLAIMED;
        $record->distributed_by = $adminId;
        $record->distributed_at = date('Y-m-d H:i:s');
        $record->distribution_note = $note;
        $record->save();
        
        // 7. 更新活动已发放金额
        $activity->distributed_prize_amount += $record->prize_amount;
        $activity->save();
        
        Db::commit();
        
        // 8. 推送中奖通知（事务外）
        try {
            LotteryTicketPushService::pushPrizeDistributed(
                $record->player_id,
                $activity,
                $record->ticket_no,
                $record->prize_name,
                $record->prize_amount
            );
        } catch (\Exception $e) {
            Log::warning('[摸奖券] 推送中奖通知失败', [
                'record_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
        
        // 9. 记录日志
        Log::info('[摸奖券] 发放奖励成功', [
            'record_id' => $id,
            'player_id' => $player->id,
            'prize_amount' => $record->prize_amount,
            'old_balance' => $oldBalance,
            'new_balance' => $player->balance,
            'admin_id' => $adminId,
            'note' => $note
        ]);
        
        return Response::success(['message' => '发放成功']);
        
    } catch (\Exception $e) {
        Db::rollBack();
        
        // 如果记录存在且状态是发放中，回滚为待发放
        if (isset($record) && $record->status === LotteryTicketRecord::STATUS_PROCESSING) {
            $record->status = LotteryTicketRecord::STATUS_FAILED;
            $record->distribution_note = '发放失败: ' . $e->getMessage();
            $record->save();
        }
        
        Log::error('[摸奖券] 发放奖励失败', [
            'record_id' => $id,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        return message_error('发放失败: ' . $e->getMessage());
    }
}
```

**返回:**
```json
{
  "code": 200,
  "msg": "发放成功"
}
```

---

### API 6: 批量发放奖励 ⭐ 核心

**接口:** `POST /ex-admin/channel-lottery-ticket-record/batch-distribute`

**权限:** @auth true, @group channel

**参数:**
```json
{
  "activity_id": 123,  // 指定活动ID，发放此活动所有待发放记录
  "record_ids": [1, 2, 3],  // 或指定记录ID列表
  "distribution_note": "批量发放"
}
```

**业务逻辑:**

```php
public function batchDistribute()
{
    $activityId = Request::input('activity_id');
    $recordIds = Request::input('record_ids', []);
    $note = Request::input('distribution_note', '批量发放');
    $adminId = Admin::user()->id;
    
    // 查询待发放的记录
    $query = LotteryTicketRecord::where('status', LotteryTicketRecord::STATUS_PENDING);
    
    if ($activityId) {
        $query->where('activity_id', $activityId);
    } elseif (!empty($recordIds)) {
        $query->whereIn('id', $recordIds);
    } else {
        return message_error('请指定活动ID或记录ID列表');
    }
    
    $records = $query->get();
    
    if ($records->isEmpty()) {
        return message_error('没有待发放的记录');
    }
    
    // 检查权限
    $departmentId = Admin::user()->department_id;
    foreach ($records as $record) {
        $activity = LotteryTicketActivity::find($record->activity_id);
        if ($activity->department_id != $departmentId) {
            return message_error('部分记录无权操作');
        }
    }
    
    $successCount = 0;
    $failCount = 0;
    $failReasons = [];
    
    // 逐条发放
    foreach ($records as $record) {
        Db::beginTransaction();
        try {
            // 锁定记录
            $record = LotteryTicketRecord::where('id', $record->id)
                ->lockForUpdate()
                ->first();
            
            // 再次检查状态（可能被其他操作改变）
            if ($record->status !== LotteryTicketRecord::STATUS_PENDING) {
                throw new \Exception('状态已变更');
            }
            
            // 转账
            $player = Player::lockForUpdate()->find($record->player_id);
            if (!$player) {
                throw new \Exception('玩家不存在');
            }
            
            $player->balance += $record->prize_amount;
            $player->save();
            
            // 更新记录
            $record->status = LotteryTicketRecord::STATUS_CLAIMED;
            $record->distributed_by = $adminId;
            $record->distributed_at = date('Y-m-d H:i:s');
            $record->distribution_note = $note;
            $record->save();
            
            // 更新活动统计
            $activity = LotteryTicketActivity::find($record->activity_id);
            $activity->distributed_prize_amount += $record->prize_amount;
            $activity->save();
            
            Db::commit();
            
            $successCount++;
            
            // 推送通知（事务外，失败不影响发放）
            try {
                LotteryTicketPushService::pushPrizeDistributed(
                    $record->player_id,
                    $activity,
                    $record->ticket_no,
                    $record->prize_name,
                    $record->prize_amount
                );
            } catch (\Exception $e) {
                // 忽略推送失败
            }
            
        } catch (\Exception $e) {
            Db::rollBack();
            $failCount++;
            $failReasons[] = "记录ID {$record->id}: " . $e->getMessage();
        }
    }
    
    // 日志
    Log::info('[摸奖券] 批量发放完成', [
        'activity_id' => $activityId,
        'total' => count($records),
        'success' => $successCount,
        'fail' => $failCount,
        'admin_id' => $adminId
    ]);
    
    $message = "批量发放完成：成功 {$successCount} 条，失败 {$failCount} 条";
    
    if ($failCount > 0) {
        return Response::success([
            'message' => $message,
            'fail_reasons' => $failReasons
        ]);
    }
    
    return Response::success(['message' => $message]);
}
```

**返回:**
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "message": "批量发放完成：成功 100 条，失败 0 条"
  }
}
```

---

### API 7: 重新开奖

**接口:** `POST /ex-admin/channel-lottery-ticket-activity/redraw`

**权限:** @auth true, @group channel

**参数:**
```json
{
  "activity_id": 123,
  "reason": "中奖号码有误，需要重新开奖"  // 必填
}
```

**业务逻辑:**

```php
public function redraw()
{
    $activityId = Request::input('activity_id');
    $reason = Request::input('reason');
    $adminId = Admin::user()->id;
    
    if (empty($reason)) {
        return message_error('请填写重新开奖原因');
    }
    
    Db::beginTransaction();
    try {
        // 1. 锁定活动
        $activity = LotteryTicketActivity::where('id', $activityId)
            ->lockForUpdate()
            ->first();
        
        if (!$activity) {
            throw new \Exception('活动不存在');
        }
        
        // 2. 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            throw new \Exception('无权操作此活动');
        }
        
        // 3. 检查状态（只能在DRAWN状态重新开奖）
        if ($activity->status !== LotteryTicketActivity::STATUS_DRAWN) {
            throw new \Exception('只能对已开奖待发放的活动重新开奖');
        }
        
        // 4. 检查是否有已发放的记录
        $distributedCount = LotteryTicketRecord::where('activity_id', $activityId)
            ->where('status', LotteryTicketRecord::STATUS_CLAIMED)
            ->count();
        
        if ($distributedCount > 0) {
            throw new \Exception("已有 {$distributedCount} 条记录已发放，无法重新开奖");
        }
        
        // 5. 删除所有待发放的中奖记录
        $deletedCount = LotteryTicketRecord::where('activity_id', $activityId)
            ->where('status', LotteryTicketRecord::STATUS_PENDING)
            ->delete();
        
        // 6. 清空开奖结果
        $activity->ball_result = null;
        $activity->draw_completed_at = null;
        $activity->total_prize_amount = 0;
        $activity->distributed_prize_amount = 0;
        $activity->status = LotteryTicketActivity::STATUS_DRAWING;
        $activity->save();
        
        // 7. 记录日志
        Log::warning('[摸奖券] 重新开奖', [
            'activity_id' => $activityId,
            'deleted_records' => $deletedCount,
            'reason' => $reason,
            'admin_id' => $adminId
        ]);
        
        Db::commit();
        
        // 8. 执行新的开奖
        $drawResult = LotteryBallDrawService::performDraw($activityId);
        
        if (!$drawResult['success']) {
            return message_error('重新开奖失败: ' . $drawResult['message']);
        }
        
        return Response::success([
            'message' => "重新开奖成功，删除了 {$deletedCount} 条旧记录，生成了新的中奖名单"
        ]);
        
    } catch (\Exception $e) {
        Db::rollBack();
        Log::error('[摸奖券] 重新开奖失败', [
            'activity_id' => $activityId,
            'error' => $e->getMessage()
        ]);
        return message_error('重新开奖失败: ' . $e->getMessage());
    }
}
```

**返回:**
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "message": "重新开奖成功，删除了100条旧记录，生成了新的中奖名单"
  }
}
```

---

## 📱 客户端推送设计

### 推送时机变更

**旧流程（自动发放）:**
- 开奖完成 → 立即推送中奖通知

**新流程（人工发放）:**
- 开奖完成 → ❌ 不推送
- 管理员发放奖励 → ✅ 推送中奖通知

---

### 推送服务方法

**新增方法:** `LotteryTicketPushService::pushPrizeDistributed()`

```php
/**
 * 推送奖励已发放通知（中奖弹窗）
 *
 * @param int $playerId 玩家ID
 * @param LotteryTicketActivity $activity 活动
 * @param string $ticketNo 券号
 * @param string $prizeName 奖品名称
 * @param float $prizeAmount 奖金金额
 */
public static function pushPrizeDistributed(
    int $playerId,
    LotteryTicketActivity $activity,
    string $ticketNo,
    string $prizeName,
    float $prizeAmount
): void
{
    try {
        $pushData = [
            'event' => 'lottery_prize_distributed',  // 奖励已发放事件
            'data' => [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'ticket_no' => $ticketNo,
                'prize_level' => $prizeName,
                'prize_amount' => $prizeAmount,
                'message' => '恭喜中獎！',
                'timestamp' => time()
            ]
        ];
        
        // 推送到指定玩家
        Client::publish(
            "lottery_ticket_player_{$playerId}",
            $pushData,
            env('PUSH_APP_KEY'),
            env('PUSH_APP_SECRET')
        );
        
        Log::info('[摸奖券] 推送中奖通知', [
            'player_id' => $playerId,
            'ticket_no' => $ticketNo,
            'prize_amount' => $prizeAmount
        ]);
        
    } catch (\Exception $e) {
        Log::error('[摸奖券] 推送中奖通知失败', [
            'player_id' => $playerId,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
```

---

## 📝 数据库迁移文件

### 迁移1: 新增活动状态和字段

**文件:** `D:\gk_api\db\migrations\20260611000002_lottery_activity_status_and_fields.php`

```php
<?php

use Phinx\Migration\AbstractMigration;

class LotteryActivityStatusAndFields extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('lottery_ticket_activity');
        
        // 新增字段
        $table->addColumn('draw_completed_at', 'datetime', [
            'null' => true,
            'comment' => '开奖完成时间',
            'after' => 'ball_result'
        ]);
        
        $table->addColumn('prize_distributed_at', 'datetime', [
            'null' => true,
            'comment' => '奖励发放完成时间',
            'after' => 'draw_completed_at'
        ]);
        
        $table->addColumn('total_prize_amount', 'decimal', [
            'precision' => 15,
            'scale' => 2,
            'default' => 0.00,
            'comment' => '总奖金金额',
            'after' => 'prize_distributed_at'
        ]);
        
        $table->addColumn('distributed_prize_amount', 'decimal', [
            'precision' => 15,
            'scale' => 2,
            'default' => 0.00,
            'comment' => '已发放奖金金额',
            'after' => 'total_prize_amount'
        ]);
        
        $table->update();
        
        // 注释: status字段新增值7 (DRAWN 已开奖待发放)
        // 需在Model中定义常量: const STATUS_DRAWN = 7;
    }
}
```

---

### 迁移2: 新增中奖记录字段

**文件:** `D:\gk_api\db\migrations\20260611000003_lottery_record_distribution_fields.php`

```php
<?php

use Phinx\Migration\AbstractMigration;

class LotteryRecordDistributionFields extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('lottery_ticket_record');
        
        // 新增发放相关字段
        $table->addColumn('distributed_by', 'integer', [
            'null' => true,
            'comment' => '发放操作人ID（admin_user_id）',
            'after' => 'status'
        ]);
        
        $table->addColumn('distributed_at', 'datetime', [
            'null' => true,
            'comment' => '发放时间',
            'after' => 'distributed_by'
        ]);
        
        $table->addColumn('distribution_note', 'string', [
            'limit' => 500,
            'null' => true,
            'comment' => '发放备注',
            'after' => 'distributed_at'
        ]);
        
        // 新增修改记录字段
        $table->addColumn('modified_by', 'integer', [
            'null' => true,
            'comment' => '最后修改人ID',
            'after' => 'distribution_note'
        ]);
        
        $table->addColumn('modified_at', 'datetime', [
            'null' => true,
            'comment' => '最后修改时间',
            'after' => 'modified_by'
        ]);
        
        $table->addColumn('modification_reason', 'string', [
            'limit' => 500,
            'null' => true,
            'comment' => '修改原因',
            'after' => 'modified_at'
        ]);
        
        $table->update();
        
        // 新增索引
        $table->addIndex(['status', 'distributed_at'], [
            'name' => 'idx_status_distributed'
        ]);
        
        $table->addIndex(['distributed_by'], [
            'name' => 'idx_distributed_by'
        ]);
        
        $table->update();
    }
}
```

---

## 🎯 实施步骤

### 阶段1: 数据库调整（30分钟）

1. ✅ 执行迁移文件，新增字段
2. ✅ 在Model中定义新常量

### 阶段2: 后端API实现（2小时）

1. ✅ 创建 `ChannelLotteryTicketRecordController.php`
2. ✅ 实现7个API接口
3. ✅ 修改 `LotteryBallDrawService::performDraw()` - 不自动发放
4. ✅ 添加推送方法 `pushPrizeDistributed()`

### 阶段3: 后管界面实现（3小时）

1. ✅ 中奖记录管理页面
2. ✅ 修改/新增/删除弹窗
3. ✅ 发放确认弹窗
4. ✅ 重新开奖功能

### 阶段4: 测试（1小时）

1. ✅ 测试开奖流程
2. ✅ 测试修改中奖记录
3. ✅ 测试发放奖励
4. ✅ 测试推送通知
5. ✅ 测试重新开奖

---

**总预计时间:** **6.5小时**

---

**设计完成时间:** 2026-06-11  
**设计人员:** AI Assistant  
**状态:** 待实施
