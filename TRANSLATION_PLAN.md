# 控制器多语言翻译计划

## 概述

**目标：** 将所有 125+ 个控制器文件中的硬编码中文替换为 `admin_trans()` 调用

**语言优先级：** 繁体中文 (zh-TW) > 简体中文 (zh-CN) > 英文 (en) > 日文 (jp)

**估计工作量：** ~1850+ 处硬编码中文需要处理

## 统计数据

### 按控制器类型分类

| 类型 | 文件数 | 中文出现次数 | 优先级 |
|------|--------|--------------|--------|
| Channel*Controller | 46 | 1417 | 高 |
| Store*Controller | 12 | 196 | 高 |
| Agent*Controller | 11 | 240 | 高 |
| 其他核心Controller | 30+ | ~200 | 中 |
| 系统配置/日志类 | 26+ | ~100 | 低 |
| **总计** | **125+** | **~2150+** | - |

### 常见硬编码模式

1. **Grid 标题** - `$grid->title('中文标题')`
2. **Grid 列名** - `$grid->column('field', '中文列名')`
3. **Form 标题** - `$form->title('中文标题')`
4. **Form 字段标签** - `$form->text('field', '中文标签')`
5. **Form 占位符** - `->placeholder('中文占位符')`
6. **Form 帮助文本** - `->help('中文帮助')`
7. **下拉选项** - `->options([1 => '中文选项'])`
8. **标签/状态** - `Tag::create('中文标签')`
9. **提示消息** - `message_success('中文消息')`
10. **HTML 内容** - `Html::markdown('><font>中文</font>')`

## 三阶段执行计划

### 阶段 1: 核心业务控制器 (高优先级)

**文件数：** ~40 个
**预估翻译键：** ~850 个
**目标：** 处理最常用的店家、代理、渠道管理功能

#### Store 系列 (12 个文件)

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| StoreShiftHandoverRecordController.php | 25 | shift_handover | ✅ 已完成 |
| StoreMachineController.php | 79 | store_machine | P0 |
| StorePlayerController.php | 13 | store_player | P0 |
| StorePlayerRechargeRecordController.php | 5 | player_recharge_record | P1 |
| StorePlayerWithdrawRecordController.php | 5 | player_withdraw_record | P1 |
| StoreLotteryController.php | 9 | lottery | P1 |
| StorePlayerGameLogController.php | 4 | player_game_log | P1 |
| StoreDepositBonusOrderController.php | 19 | deposit_bonus_order | P2 |
| StoreDepositBonusActivityController.php | 8 | deposit_bonus_activity | P2 |
| StoreDepositBonusTaskController.php | 5 | deposit_bonus_task | P2 |
| StorePlayGameRecordController.php | 7 | play_game_record | P2 |
| StoreSettingController.php | 17 | store_setting | P2 |

**翻译键模块：**
- `store_machine.*` (新建)
- `store_player.*` (新建)
- `store_setting.*` (新建)
- `player_recharge_record.*` (扩展)
- `player_withdraw_record.*` (扩展)
- `lottery.*` (扩展)
- `deposit_bonus.*` (扩展)

#### Agent 系列 (11 个文件)

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| AgentPromoterController.php | 79 | agent_promoter | P0 |
| AgentController.php | 55 | agent | P0 |
| AgentStoreProfitReportController.php | 31 | agent_store_profit | P1 |
| AgentLotteryController.php | 12 | lottery | P1 |
| AgentDepositBonusActivityController.php | 12 | deposit_bonus_activity | P2 |
| AgentDepositBonusOrderController.php | 16 | deposit_bonus_order | P2 |
| AgentPlayGameRecordController.php | 9 | play_game_record | P2 |
| AgentPlayerWithdrawRecordController.php | 7 | player_withdraw_record | P2 |
| AgentPlayerRechargeRecordController.php | 8 | player_recharge_record | P2 |
| AgentPlayerGameLogController.php | 6 | player_game_log | P2 |
| AgentDepositBonusTaskController.php | 5 | deposit_bonus_task | P2 |

**翻译键模块：**
- `agent.*` (新建)
- `agent_promoter.*` (新建)
- `agent_store_profit.*` (新建)

#### Channel 核心系列 (17 个文件 - 高优先级)

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| ChannelPlayerController.php | 328 | channel_player | P0 |
| ChannelAgentController.php | 194 | channel_agent | P0 |
| ChannelIndexController.php | 259 | channel_index | P0 |
| ChannelAgentPromoterController.php | 79 | channel_agent_promoter | P0 |
| ChannelPlayerReportController.php | 52 | channel_player_report | P1 |
| ChannelStoreAgentProfitRecordController.php | 44 | channel_store_agent_profit | P1 |
| ChannelDeviceController.php | 39 | channel_device | P1 |
| ChannelRechargeRecordController.php | 31 | channel_recharge_record | P1 |
| ChannelWithdrawRecordController.php | 29 | channel_withdraw_record | P1 |
| ChannelController.php | 27 | channel | P1 |
| ChannelPlayerLotteryRecordController.php | 27 | channel_player_lottery | P1 |
| ChannelAutoShiftController.php | 26 | auto_shift | P1 |
| ChannelPlayerActivityRecordController.php | 24 | channel_player_activity | P2 |
| ChannelPlatformReverseWaterController.php | 20 | channel_platform_reverse_water | P2 |
| ChannelPlayerDeliveryRecordController.php | 13 | channel_player_delivery | P2 |
| ChannelMachineController.php | 14 | channel_machine | P2 |
| ChannelRechargeController.php | 11 | channel_recharge | P2 |

**翻译键模块：**
- `channel_player.*` (新建)
- `channel_agent.*` (新建)
- `channel_index.*` (新建)
- `channel_agent_promoter.*` (新建)
- `channel_player_report.*` (新建)
- `channel_device.*` (新建)

### 阶段 2: 通用管理控制器 (中优先级)

**文件数：** ~40 个
**预估翻译键：** ~600 个

#### 玩家管理系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| PlayerController.php | 多 | player | P0 |
| PlayerPromoterController.php | 42 | player_promoter | P1 |
| PlayerLotteryRecordController.php | 多 | player_lottery_record | P1 |
| PlayerReportController.php | 多 | player_report | P1 |
| PlayerDeliveryRecordController.php | 多 | player_delivery_record | P2 |
| PlayerActivityRecordController.php | 多 | player_activity_record | P2 |
| PlayerWalletTransferController.php | 5 | player_wallet_transfer | P2 |
| PlayerMoneyEditLogController.php | 3 | player_money_edit_log | P2 |
| PlayerEditLogController.php | 3 | player_edit_log | P2 |
| PlayerGameLogController.php | 多 | player_game_log | P2 |
| PlayerGiftRecord.php | 多 | player_gift_record | P2 |

#### 机台管理系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| MachineController.php | 多 | machine | P0 |
| MachineReportController.php | 2 | machine_report | P1 |
| MachineOperationLogController.php | 多 | machine_operation_log | P1 |
| MachineLotteryRecordController.php | 多 | machine_lottery_record | P1 |
| MachineKeepingLogController.php | 多 | machine_keeping_log | P2 |
| MachineReceiveLogController.php | 多 | machine_receive_log | P2 |
| MachineEditLogController.php | 多 | machine_edit_log | P2 |
| MachineTencentPlayController.php | 多 | machine_tencent_play | P2 |
| MachineStrategyController.php | 多 | machine_strategy | P2 |
| MachineProducerController.php | 多 | machine_producer | P2 |
| MachineLabelController.php | 多 | machine_label | P2 |
| MachineCategoryController.php | 多 | machine_category | P2 |

#### 财务管理系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| RechargeRecordController.php | 多 | recharge_record | P1 |
| WithdrawRecordController.php | 多 | withdraw_record | P1 |
| ProfitRecordController.php | 多 | profit_record | P1 |
| PresentRecordController.php | 3 | present_record | P2 |
| PlatformReverseWaterController.php | 多 | platform_reverse_water | P2 |

#### 营销活动系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| DepositBonusActivityController.php | 多 | deposit_bonus_activity | P1 |
| DepositBonusQrcodeController.php | 多 | deposit_bonus_qrcode | P2 |
| ActivityController.php | 多 | activity | P2 |

#### 彩金系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| LotteryController.php | 多 | lottery | P0 |
| GameLotteryController.php | 多 | game_lottery | P1 |
| OnlinePlayerLotteryController.php | 多 | online_player_lottery | P2 |
| LotteryAddLogController.php | 多 | lottery_add_log | P2 |

#### 游戏平台系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| GamePlatformController.php | 多 | game_platform | P1 |
| GameController.php | 多 | game | P1 |
| GameTypeController.php | 多 | game_type | P2 |
| PlayGameRecordController.php | 多 | play_game_record | P2 |

#### 推广员系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| NationalPromoterController.php | 20 | national_promoter | P1 |
| NationalPromoterReportController.php | 多 | national_promoter_report | P1 |

### 阶段 3: 系统配置与日志 (低优先级)

**文件数：** ~45 个
**预估翻译键：** ~300 个

#### Channel 通用系列 (29 个文件)

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| ChannelPlayerPromoterController.php | 42 | channel_player_promoter | P1 |
| ChannelNationalPromoterController.php | 20 | channel_national_promoter | P1 |
| ChannelProfitRecordController.php | 10 | channel_profit_record | P1 |
| ChannelChannelProfitRecordController.php | 8 | channel_channel_profit | P2 |
| ChannelOpenScoreSettingController.php | 8 | channel_open_score_setting | P2 |
| ChannelNationalPromoterReportController.php | 8 | channel_national_promoter_report | P2 |
| ChannelDepositBonusActivityController.php | 12 | channel_deposit_bonus_activity | P2 |
| ChannelDepositBonusOrderController.php | 7 | channel_deposit_bonus_order | P2 |
| ChannelDepositBonusStatisticsController.php | 5 | channel_deposit_bonus_statistics | P2 |
| ChannelDepositBonusBetDetailController.php | 6 | channel_deposit_bonus_bet_detail | P2 |
| ChannelDeviceAccessLogController.php | 6 | channel_device_access_log | P2 |
| ChannelPlayerGameLogController.php | 6 | channel_player_game_log | P2 |
| ChannelPlayerWalletTransferController.php | 5 | channel_player_wallet_transfer | P2 |
| ChannelActivityController.php | 4 | channel_activity | P2 |
| ChannelAdminController.php | 4 | channel_admin | P2 |
| ChannelBankController.php | 4 | channel_bank | P2 |
| ChannelMarqueeController.php | 4 | channel_marquee | P2 |
| ChannelMachineOperationLogController.php | 4 | channel_machine_operation_log | P2 |
| ChannelSliderController.php | 4 | channel_slider | P2 |
| ChannelAnnouncementController.php | 3 | channel_announcement | P2 |
| ChannelFinancialRecordController.php | 3 | channel_financial_record | P2 |
| ChannelPlayerEditLogController.php | 3 | channel_player_edit_log | P2 |
| ChannelPlayerMoneyEditLogController.php | 3 | channel_player_money_edit_log | P2 |
| ChannelPostController.php | 3 | channel_post | P2 |
| ChannelPresentRecordController.php | 3 | channel_present_record | P2 |
| ChannelPromoterProfitGameRecordController.php | 3 | channel_promoter_profit_game | P2 |
| ChannelMachineKeepingLogController.php | 2 | channel_machine_keeping_log | P2 |
| ChannelMachineReportController.php | 2 | channel_machine_report | P2 |

#### 系统配置系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| SystemSettingController.php | 多 | system_setting | P1 |
| ConfigController.php | 多 | config | P2 |
| MenuController.php | 多 | menu | P2 |
| RoleController.php | 多 | role | P1 |
| AdminController.php | 多 | admin | P1 |
| DepartmentController.php | 多 | department | P2 |
| CurrencyController.php | 多 | currency | P2 |

#### 内容管理系列

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| AnnouncementController.php | 3 | announcement | P2 |
| SliderController.php | 多 | slider | P2 |
| PostController.php | 3 | post | P2 |
| BankController.php | 4 | bank | P2 |
| AttachmentController.php | 多 | attachment | P2 |

#### 日志与其他

| 文件名 | 中文数 | 主要模块 | 优先级 |
|--------|--------|----------|--------|
| PhoneSmsLogController.php | 多 | phone_sms_log | P2 |
| IndexController.php | 多 | index | P1 |
| CustomLoginController.php | 多 | custom_login | P2 |

## 翻译键命名规范

### 标准结构

```
{module}.{category}.{name}
```

### 类别 (category)

- `fields` - 字段名称（列名、表单标签）
- `title` - 页面/模块标题
- `label` - 带冒号的标签
- `action` - 操作按钮
- `filter` - 筛选器
- `status` - 状态选项
- `type` - 类型选项
- `error` - 错误消息
- `success` - 成功消息
- `help` - 帮助文本
- `placeholder` - 占位符文本
- `option` - 下拉选项
- `message` - 提示消息
- `validation` - 验证规则

### 示例

```php
// ❌ 错误 - 硬编码中文
$grid->column('name', '店家名称');
$form->text('phone', '联系电话')->help('选填，用于联系');
message_success('创建成功');

// ✅ 正确 - 使用翻译键
$grid->column('name', admin_trans('store_machine.fields.name'));
$form->text('phone', admin_trans('store_machine.fields.phone'))
    ->help(admin_trans('store_machine.help.phone'));
message_success(admin_trans('store_machine.success.created'));
```

## 执行步骤（每个文件）

1. **读取控制器文件**
2. **识别所有硬编码中文模式**
3. **为该模块创建翻译键列表**
4. **按语言优先级添加翻译**
   - 先添加 zh-TW (繁体中文)
   - 再添加 zh-CN (简体中文)
   - 然后添加 en (英文)
   - 最后添加 jp (日文)
5. **替换控制器中的硬编码**
6. **验证翻译完整性**

## 进度跟踪

### 已完成
- ✅ StoreShiftHandoverRecordController.php
- ✅ DeviceDetailExporter.php
- ✅ shift_handover.php (全语言)

### 进行中
- 🔄 Task #14: 分析控制器并制定计划

### 待处理
- ⏳ 阶段 1: 核心业务控制器 (40 个文件)
- ⏳ 阶段 2: 通用管理控制器 (40 个文件)
- ⏳ 阶段 3: 系统配置与日志 (45 个文件)

## 预估时间

- **阶段 1:** ~8-10 小时 (每个文件 10-15 分钟)
- **阶段 2:** ~6-8 小时
- **阶段 3:** ~4-6 小时
- **总计:** ~20-25 小时

## 注意事项

1. **繁体中文优先** - 所有翻译必须先写繁体中文
2. **保持一致性** - 相同的中文应使用相同的翻译键
3. **参数替换** - 使用 `{param}` 占位符支持动态内容
4. **嵌套结构** - 复杂模块使用嵌套数组组织翻译键
5. **验证完整性** - 每个语言文件必须有相同的键结构
6. **测试验证** - 完成后测试所有页面显示是否正确

## 下一步行动

1. 从 StoreMachineController.php 开始（79处中文）
2. 创建 store_machine.php 翻译文件（4语言）
3. 替换控制器中的硬编码
4. 继续处理 StorePlayerController.php
5. 逐步完成阶段 1 的所有文件
