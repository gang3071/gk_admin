# 摸奖券功能翻译文件总结

## ✅ 已完成的翻译

### 📁 翻译文件列表

#### 1. 摸奖券专用翻译文件 (`lottery_ticket.php`)

| 语言 | 文件路径 | 状态 |
|------|---------|------|
| 繁体中文 | `addons/webman/lang/zh-TW/lottery_ticket.php` | ✅ 完成 |
| 简体中文 | `addons/webman/lang/zh-CN/lottery_ticket.php` | ✅ 完成 |
| 英文 | `addons/webman/lang/en/lottery_ticket.php` | ✅ 完成 |
| 日文 | `addons/webman/lang/jp/lottery_ticket.php` | ✅ 完成 |

#### 2. 通用翻译文件更新 (`common.php`)

| 语言 | 新增键 | 状态 |
|------|--------|------|
| 繁体中文 | `start_time`, `end_time`, `no_permission` | ✅ 完成 |
| 简体中文 | `start_time`, `end_time`, `no_permission` | ✅ 完成 |
| 英文 | `start_time`, `end_time`, `no_permission` | ✅ 完成 |
| 日文 | `start_time`, `end_time`, `no_permission` | ✅ 完成 |

#### 3. 菜单翻译文件更新 (`menu.php`)

| 语言 | 新增键 | 状态 |
|------|--------|------|
| 繁体中文 | `lottery_ticket_manage` 等4个 | ✅ 完成 |
| 简体中文 | `lottery_ticket_manage` 等4个 | ✅ 完成 |
| 英文 | `lottery_ticket_manage` 等4个 | ✅ 完成 |
| 日文 | `lottery_ticket_manage` 等4个 | ✅ 完成 |

---

## 📋 翻译键对照表

### 菜单翻译 (`menu.titles`)

| 键名 | 繁中 | 简中 | 英文 | 日文 |
|------|------|------|------|------|
| `lottery_ticket_manage` | 摸獎券管理 | 摸奖券管理 | Lottery Ticket Management | 抽選券管理 |
| `lottery_ticket_dashboard` | 進行中的活動 | 进行中的活动 | Active Campaigns | 実施中のキャンペーン |
| `lottery_ticket_history` | 歷史活動記錄 | 历史活动记录 | Campaign History | キャンペーン履歴 |
| `lottery_ticket_records` | 中獎記錄 | 中奖记录 | Winning Records | 当選記録 |

### 奖品类型翻译 (`lottery_ticket.prize_type`)

| 键名 | 繁中 | 简中 | 英文 | 日文 |
|------|------|------|------|------|
| `cash` | 現金 | 现金 | Cash | 現金 |
| `bonus` | 紅利 | 红利 | Bonus | ボーナス |
| `item` | 實物 | 实物 | Physical Item | 実物 |
| `points` | 積分 | 积分 | Points | ポイント |
| `empty` | 未中獎 | 未中奖 | No Prize | ハズレ |

### 活动状态翻译 (`lottery_ticket.status`)

| 键名 | 繁中 | 简中 | 英文 | 日文 |
|------|------|------|------|------|
| `not_started` | 未開始 | 未开始 | Not Started | 未開始 |
| `ongoing` | 進行中 | 进行中 | Ongoing | 実施中 |
| `ended` | 已結束 | 已结束 | Ended | 終了 |
| `closed` | 已關閉 | 已关闭 | Closed | 閉鎖 |

### 发放状态翻译 (`lottery_ticket.record_status`)

| 键名 | 繁中 | 简中 | 英文 | 日文 |
|------|------|------|------|------|
| `pending` | 待發放 | 待发放 | Pending | 付与待ち |
| `granted` | 已發放 | 已发放 | Granted | 付与済み |
| `failed` | 發放失敗 | 发放失败 | Failed | 失敗 |

### 通用翻译 (`common`)

| 键名 | 繁中 | 简中 | 英文 | 日文 |
|------|------|------|------|------|
| `start_time` | 開始時間 | 开始时间 | Start Time | 開始時間 |
| `end_time` | 結束時間 | 结束时间 | End Time | 終了時間 |
| `no_permission` | 沒有權限 | 没有权限 | No Permission | 権限がありません |

---

## 🎯 翻译覆盖范围

### lottery_ticket.php 包含的翻译分类

1. **菜单** (`menu`)
   - 4个菜单项翻译

2. **字段** (`fields`)
   - 19个字段名称翻译（活动、记录、奖品）

3. **状态** (`status`, `ticket_status`, `record_status`)
   - 13个状态标签翻译

4. **奖品类型** (`prize_type`)
   - 6个类型翻译（含积分）

5. **中奖等级** (`level_name`, `prize_level_fields`)
   - 9个等级名称
   - 9个等级字段

6. **操作** (`action`)
   - 6个操作按钮翻译

7. **统计** (`stats`)
   - 5个统计项翻译

8. **消息** (`message`)
   - 9个提示消息翻译

9. **错误** (`error`)
   - 6个错误消息翻译

**总计**: 约 **86个翻译键**，4种语言 = **344个翻译条目**

---

## 🔍 翻译使用示例

### 控制器中的使用

```php
// 菜单标题
$grid->title(admin_trans('lottery_ticket.menu.dashboard'));

// 字段标签
$grid->column('activity_name', admin_trans('lottery_ticket.fields.activity_name'));

// 状态标签
admin_trans('lottery_ticket.status.ongoing')

// 奖品类型
admin_trans('lottery_ticket.prize_type.points')  // "積分"

// 操作按钮
admin_trans('lottery_ticket.action.grant')  // "發放獎品"

// 提示消息
admin_trans('lottery_ticket.message.create_success')

// 错误消息
admin_trans('lottery_ticket.error.too_many_levels', null, ['max' => 10])

// 通用字段
admin_trans('common.start_time')  // "開始時間"
admin_trans('common.no_permission')  // "沒有權限"
```

### 参数替换示例

```php
// 使用 {参数} 占位符
admin_trans('lottery_ticket.error.too_many_levels', null, ['max' => 10])
// 输出: "最多只能設置 10 個獎品等級"

admin_trans('lottery_ticket.error.probability_exceed', null, ['total' => 120])
// 输出: "中獎概率總和不能超過100%，當前總和：120%"
```

---

## 📊 翻译质量保证

### 术语一致性

| 中文术语 | 繁中 | 简中 | 英文 | 日文 |
|---------|------|------|------|------|
| 摸奖券 | 摸獎券 | 摸奖券 | Lottery Ticket | 抽選券 |
| 活动 | 活動 | 活动 | Campaign | キャンペーン |
| 中奖 | 中獎 | 中奖 | Win/Prize | 当選 |
| 发放 | 發放 | 发放 | Grant | 付与 |
| 积分 | 積分 | 积分 | Points | ポイント |

### 翻译规范

1. **繁简对照**: 繁体中文保持台湾用语习惯
2. **专业术语**: 游戏行业标准用语
3. **简洁明了**: 避免冗长表达
4. **一致性**: 同一术语在不同位置保持一致
5. **参数化**: 支持动态参数替换

---

## ✅ 验证清单

- [x] 繁体中文翻译完整
- [x] 简体中文翻译完整
- [x] 英文翻译完整
- [x] 日文翻译完整
- [x] common.php 通用键已添加
- [x] menu.php 菜单键已添加
- [x] 奖品类型包含"积分"
- [x] 所有状态翻译完整
- [x] 错误消息支持参数替换
- [x] 术语使用一致

---

## 🚀 测试建议

### 切换语言测试

1. **繁体中文环境** (默认)
   - Cookie: `ex_admin_lang=zh-TW`
   - 验证所有文本显示正确

2. **简体中文环境**
   - Cookie: `ex_admin_lang=zh-CN`
   - 验证简体字和大陆用语

3. **英文环境**
   - Cookie: `ex_admin_lang=en`
   - 验证英文术语专业性

4. **日文环境**
   - Cookie: `ex_admin_lang=jp`
   - 验证日文表达自然

### 关键页面测试

- ✅ 进行中的活动列表
- ✅ 历史活动记录
- ✅ 中奖记录列表
- ✅ 活动创建表单
- ✅ 奖品配置弹窗
- ✅ 筛选器标签
- ✅ 提示消息
- ✅ 错误消息

---

## 📚 相关文档

- [Bug修复文档](./LOTTERY_TICKET_BUGFIX.md)
- [实现总结](./LOTTERY_TICKET_IMPLEMENTATION_SUMMARY.md)
- [迁移指南](./LOTTERY_TICKET_MENU_MIGRATION_GUIDE.md)

---

**创建时间**: 2026-06-04  
**版本**: 1.0  
**状态**: ✅ 所有翻译已完成
