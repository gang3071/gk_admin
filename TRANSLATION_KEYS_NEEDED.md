# 需要添加的翻译键清单

## lottery_ticket.error (错误消息)

```php
'error' => [
    // 已有
    'record_not_found' => '记录不存在',
    
    // 新增 - 输入验证
    'invalid_record_id' => '参数错误：记录ID无效',
    'invalid_activity_id' => '参数错误：活动ID无效',
    'invalid_record_ids' => '参数错误：记录ID必须是数组',
    'invalid_record_id_value' => '参数错误：记录ID包含非法值',
    'note_too_long' => '发放备注不能超过255个字符',
    'no_selection' => '请指定活动ID或选择记录',
    'no_pending_records' => '没有待发放的记录',
    
    // 新增 - 业务逻辑验证
    'invalid_status' => '记录状态不正确，只能发放待发放的记录',
    'status_changed' => '状态已变更',
    'empty_prize' => '空奖无需发放',
    'invalid_amount' => '奖品金额必须大于0',
    'player_not_found' => '玩家不存在',
    'player_disabled' => '玩家已被禁用，无法发放奖励',
    'activity_not_found' => '活动不存在',
    'activity_invalid_status' => '活动状态错误，只能发放已开奖待发放的活动奖励',
    'amount_exceeded' => '发放金额超出总奖金额度',
],
```

## lottery_ticket.message (成功/信息消息)

```php
'message' => [
    // 已有
    'create_success' => '创建成功',
    'update_success' => '更新成功',
    
    // 新增
    'distribute_success' => '发放成功',
    'distribute_failed' => '发放失败',
    'batch_complete' => '批量发放完成：成功 {success} 条，失败 {fail} 条',
    'export_in_development' => '导出功能开发中',
],
```

## lottery_ticket.view (详情视图标签)

```php
'view' => [
    'detail_title' => '中奖记录详情',
    'basic_info' => '基本信息',
    'prize_info' => '奖品信息',
    'distribution_info' => '发放信息',
    
    'activity_name' => '活动名称',
    'ticket_no' => '券号',
    'player_name' => '玩家',
    'player_phone' => '手机号',
    'prize_name' => '奖品名称',
    'prize_type' => '奖品类型',
    'prize_amount' => '奖品金额',
    'status' => '状态',
    'distributed_at' => '发放时间',
    'distributed_by' => '发放人',
    'distribution_note' => '发放备注',
    'created_at' => '创建时间',
    'updated_at' => '更新时间',
],
```

---

## 需要修复的文件位置

### ChannelLotteryTicketRecordController.php

| 行号 | 当前硬编码 | 翻译键 |
|------|-----------|--------|
| 256 | '参数错误：记录ID无效' | `lottery_ticket.error.invalid_record_id` |
| 260 | '发放备注不能超过255个字符' | `lottery_ticket.error.note_too_long` |
| 281 | '记录状态不正确...' | `lottery_ticket.error.invalid_status` |
| 286 | '空奖无需发放' | `lottery_ticket.error.empty_prize` |
| 291 | '奖品金额必须大于0' | `lottery_ticket.error.invalid_amount` |
| 301 | '玩家不存在' | `lottery_ticket.error.player_not_found` |
| 306 | '玩家已被禁用...' | `lottery_ticket.error.player_disabled` |
| 326 | '活动不存在' | `lottery_ticket.error.activity_not_found` |
| 331 | '活动状态错误...' | `lottery_ticket.error.activity_invalid_status` |
| 337 | '发放金额超出...' | `lottery_ticket.error.amount_exceeded` |
| 372 | '发放成功' | `lottery_ticket.message.distribute_success` |
| 381 | '发放失败:' | `lottery_ticket.message.distribute_failed` |
| 395 | '发放失败:' | `lottery_ticket.message.distribute_failed` |
| 415-447 | 批量发放验证消息 | 同上error键 |
| 465-514 | 批量发放业务验证 | 同上error键 |
| 553 | '批量发放完成...' | `lottery_ticket.message.batch_complete` |
| 608 | '记录不存在' | `lottery_ticket.error.record_not_found` |
| 618-690 | view()方法中所有strong标签文本 | `lottery_ticket.view.*` |
| 752 | '请选择要发放的记录' | `lottery_ticket.error.no_selection` |
| 774 | '导出功能开发中' | `lottery_ticket.message.export_in_development` |

---

## 修复状态

- ✅ distribute() 方法 - 已修复
- ✅ batchDistribute() 方法 - 已修复  
- ✅ view() 方法 - 部分修复（608行）
- ⏳ view() 方法HTML标签 - **待修复**
- ⏳ batchDistributeSelected() 方法 - **待修复**
- ⏳ exportRecords() 方法 - **待修复**
- ⏳ 翻译文件 - **待添加所有键**

---

## 下一步操作

1. 添加所有翻译键到4个语言文件（zh-TW, zh-CN, en, jp）
2. 修复view()方法中的HTML硬编码
3. 修复剩余方法的硬编码
4. 测试所有翻译显示正确
