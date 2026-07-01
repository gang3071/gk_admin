# 控制器多语言翻译指南

## 概述

本指南说明如何将控制器中硬编码的中文文本替换为多语言翻译函数调用。

## 翻译函数

使用 `admin_trans()` 函数进行翻译：

```php
// 基本用法
admin_trans('common.player_not_exist')

// 带参数的翻译
admin_trans('common.username_exists', null, ['username' => $username])
admin_trans('common.agent_create_success', null, ['name' => $name, 'username' => $adminUsername])
```

## 常见翻译模式

### 1. 错误消息 (message_error)

**修改前：**
```php
return message_error('玩家不存在');
return message_error('代理抽成比例必须在 0-100 之间');
return message_error("登录账号 {$adminUsername} 已存在");
```

**修改后：**
```php
return message_error(admin_trans('common.player_not_exist'));
return message_error(admin_trans('common.agent_commission_range_error'));
return message_error(admin_trans('common.username_exists', null, ['username' => $adminUsername]));
```

### 2. 成功消息 (message_success)

**修改前：**
```php
return message_success('结算成功');
return message_success("代理 {$name} 创建成功！登录账号：{$adminUsername}");
$message = "成功生成 {$successCount} 个玩家账号";
return message_success($message);
```

**修改后：**
```php
return message_success(admin_trans('common.settlement_success'));
return message_success(admin_trans('common.agent_create_success', null, [
    'name' => $name,
    'username' => $adminUsername
]));
$message = admin_trans('common.batch_generate_success', null, ['count' => $successCount]);
return message_success($message);
```

### 3. 帮助文本 (->help())

**修改前：**
```php
$form->text('username')
    ->help('账号格式：前缀+编号，例如：P0001');

$form->text('start_number')
    ->help('编号将自动补齐为4位数字，例如：1 → 0001');

$form->image('avatar')
    ->help('支持jpg、png格式，建议尺寸200x200');

$form->password('password')
    ->help('代理后台登录密码，至少6位');
```

**修改后：**
```php
$form->text('username')
    ->help(admin_trans('common.help.account_format'));

$form->text('start_number')
    ->help(admin_trans('common.help.number_auto_padding'));

$form->image('avatar')
    ->help(admin_trans('common.help.avatar_format'));

$form->password('password')
    ->help(admin_trans('common.help.agent_login_password'));
```

### 4. 提示内容 (->content() / Html::markdown())

**修改前：**
```php
$form->push(Html::markdown('><font size=3 color="#ff4d4f">此功能仅限线下渠道使用</font>'));

$form->push(Html::markdown('><font size=2 color="#1890ff">批量生成的账号将自动绑定到指定的店家</font>'));
```

**修改后：**
```php
$form->push(Html::markdown(admin_trans('common.tips.offline_channel_only_notice')));

$form->push(Html::markdown(admin_trans('common.tips.batch_generate_bind_notice')));
```

### 5. 分隔线内容 (divider()->content())

**修改前：**
```php
$form->divider()->content('抽成设置');
```

**修改后：**
```php
$form->divider()->content(admin_trans('common.divider.commission_settings'));
```

### 6. 默认值

**修改前：**
```php
$name = $data->user_name ?? '管理员';
$storeSetting->content = '欢迎使用代理后台系统！';
```

**修改后：**
```php
$name = $data->user_name ?? admin_trans('common.default.admin');
$storeSetting->content = admin_trans('common.default.welcome_agent_system');
```

### 7. 数组数据

**修改前：**
```php
[
    'title' => '早班',
    'shift_time' => '08:00-16:00',
    'description' => '早班自动交班（08:00-16:00）'
]
```

**修改后：**
```php
[
    'title' => admin_trans('common.shift.morning'),
    'shift_time' => '08:00-16:00',
    'description' => admin_trans('common.shift.morning_desc')
]
```

### 8. 列名称（Grid columns）

**修改前：**
```php
$grid->column('recharge_amount', '累计开分')->display(function ($value) {
    return format_currency($value);
});
```

**修改后：**
对于Grid列名称，通常已经在模型的语言文件中定义，使用模块特定的翻译：
```php
// 如果在 player.php 语言文件中有定义
$grid->column('recharge_amount', admin_trans('player.fields.recharge_amount'))->display(function ($value) {
    return format_currency($value);
});

// 或者如果使用了自动翻译功能，可能不需要修改
$grid->column('recharge_amount')->display(function ($value) {
    return format_currency($value);
});
```

## 翻译键命名规范

在 `common.php` 中的翻译键按功能分组：

- **错误/成功消息**: 直接放在根级别，使用动词_名词形式
  - `player_not_exist`, `settlement_success`, `operation_failed`

- **帮助文本**: 放在 `help` 数组下
  - `help.account_format`, `help.avatar_format`

- **提示文本**: 放在 `tips` 数组下
  - `tips.offline_channel_only_notice`

- **分隔线**: 放在 `divider` 数组下
  - `divider.commission_settings`

- **默认值**: 放在 `default` 数组下
  - `default.admin`, `default.not_filled`

- **特定功能**: 使用功能名称分组
  - `shift.morning`, `shift.morning_desc`

## 需要修改的控制器文件列表

根据检查，以下控制器包含需要翻译的硬编码中文：

### 高优先级（含较多硬编码文本）：
1. `ChannelPlayerController.php` - 玩家管理
2. `ChannelAgentController.php` - 代理管理
3. `AgentController.php` - 代理控制器
4. `AgentPromoterController.php` - 代理推广员
5. `ChannelAgentPromoterController.php` - 渠道代理推广员
6. `LotteryController.php` - 彩池管理
7. `GameLotteryController.php` - 游戏彩池
8. `RoleController.php` - 角色管理
9. `ChannelIndexController.php` - 渠道首页
10. `StoreMachineController.php` - 店家机台管理

### 中等优先级：
11. `StoreDepositBonusOrderController.php`
12. `AgentDepositBonusOrderController.php`
13. `MachineCategoryController.php`
14. `MachineLabelController.php`
15. `MachineTencentPlayController.php`

## 批量查找需要翻译的文本

使用以下命令查找控制器中的硬编码中文：

```bash
# 查找 message_error/message_success 中的中文
grep -n "message_error\|message_success" controller/*.php | grep "[\u4e00-\u9fa5]"

# 查找 ->help() 中的中文
grep -n "->help" controller/*.php | grep "[\u4e00-\u9fa5]"

# 查找 ->content() 中的中文
grep -n "->content" controller/*.php | grep "[\u4e00-\u9fa5]"
```

## 实际修改示例

### 示例1: ChannelPlayerController.php (部分)

**修改行 4246-4249:**
```php
// 修改前
if ($agentCommission < 0 || $agentCommission > 100) {
    return message_error('代理抽成比例必须在 0-100 之间');
}
if ($channelCommission < 0 || $channelCommission > 100) {
    return message_error('渠道抽成比例必须在 0-100 之间');
}

// 修改后
if ($agentCommission < 0 || $agentCommission > 100) {
    return message_error(admin_trans('common.agent_commission_range_error'));
}
if ($channelCommission < 0 || $channelCommission > 100) {
    return message_error(admin_trans('common.channel_commission_range_error'));
}
```

**修改行 3694-3773 (表单帮助文本):**
```php
// 修改前
$form->text('username_prefix')
    ->help('账号格式：前缀+编号，例如：P0001');

// 修改后
$form->text('username_prefix')
    ->help(admin_trans('common.help.account_format'));
```

**修改行 3902-3911:**
```php
// 修改前
$message = "成功生成 {$successCount} 个玩家账号";
if (!empty($failedAccounts)) {
    $message .= "，失败 " . count($failedAccounts) . " 个：" . implode(', ', $failedAccounts);
}
return message_success($message);
catch (\Exception $e) {
    return message_error('批量生成失败：' . $e->getMessage());
}

// 修改后
if (empty($failedAccounts)) {
    $message = admin_trans('common.batch_generate_success', null, ['count' => $successCount]);
} else {
    $message = admin_trans('common.batch_generate_partial_success', null, [
        'success' => $successCount,
        'failed' => count($failedAccounts),
        'accounts' => implode(', ', $failedAccounts)
    ]);
}
return message_success($message);
catch (\Exception $e) {
    return message_error(admin_trans('common.batch_generation_failed', null, ['message' => $e->getMessage()]));
}
```

## 注意事项

1. **保持变量替换**: 确保原文中的变量在翻译后仍然正确替换
2. **异常消息**: `$e->getMessage()` 通常保持原样，因为这些是系统级异常
3. **已翻译的代码**: 如果代码已经使用 `admin_trans()`, 请勿重复修改
4. **测试**: 修改后测试功能确保翻译正确显示

## 扩展新翻译

如果需要添加新的翻译文本，在四个语言文件中添加相同的键：
- `addons/webman/lang/zh-CN/common.php`
- `addons/webman/lang/en/common.php`
- `addons/webman/lang/jp/common.php`
- `addons/webman/lang/zh-TW/common.php`

## 完成清单

修改控制器时，检查以下项：
- [ ] 所有 `message_error()` 中的中文已替换
- [ ] 所有 `message_success()` 中的中文已替换
- [ ] 所有 `->help()` 中的中文已替换
- [ ] 所有 `->content()` / `Html::markdown()` 中的中文已替换
- [ ] 所有默认值中的中文已替换
- [ ] 数组数据中的中文已替换
- [ ] 测试页面功能正常
- [ ] 切换语言测试翻译正确
