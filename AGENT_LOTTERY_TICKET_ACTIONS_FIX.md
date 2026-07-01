# AgentLotteryTicketActivityController - Actions 使用修正

## 🐛 发现的问题

### 1️⃣ **Actions.button() 方法不存在**

**错误代码:**
```php
$actions->button(admin_trans('lottery_ticket.action.prize_config'))
    ->type('link')
    ->size('small')
    ->modal([$this, 'prizeConfig'], ['activity_id' => $data->id])
    ->width('80%');
```

**问题:** `Actions` 类没有 `button()` 方法

---

### 2️⃣ **abort() 函数未定义**

**错误代码:**
```php
if (!$activity) {
    abort(403, admin_trans('common.no_permission'));
}
```

**问题:** Webman 框架中没有 Laravel 的 `abort()` 全局辅助函数

---

## ✅ 修复方案

### 1️⃣ **Actions 正确用法**

**修复前 (错误):**
```php
use ExAdmin\ui\component\grid\grid\Actions;

$grid->actions(function (Actions $actions, LotteryTicketActivity $data) {
    $actions->button(admin_trans('lottery_ticket.action.prize_config'))  // ❌ 错误
        ->type('link')
        ->size('small')
        ->modal([$this, 'prizeConfig'], ['activity_id' => $data->id])
        ->width('80%');
});
```

**修复后 (正确):**
```php
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\grid\grid\Actions;

$grid->actions(function (Actions $actions, LotteryTicketActivity $data) {
    $actions->prepend(  // ✅ 使用 prepend() 方法
        Button::create(admin_trans('lottery_ticket.action.prize_config'))
            ->type('link')
            ->size('small')
            ->modal([$this, 'prizeConfig'], ['activity_id' => $data->id])
            ->width('80%')
    );
});
```

**关键变化:**
1. ✅ 导入 `Button` 类
2. ✅ 使用 `Button::create()` 创建按钮
3. ✅ 使用 `$actions->prepend()` 添加按钮（而不是 `button()`）
4. ✅ 注意语法：`prepend()` 方法接收一个完整的 Button 对象

---

### 2️⃣ **权限验证失败处理**

**修复前 (错误):**
```php
if (!$activity) {
    abort(403, admin_trans('common.no_permission'));  // ❌ 函数不存在
}
```

**修复后 (正确):**
```php
if (!$activity) {
    throw new \Exception(admin_trans('common.no_permission'));  // ✅ 抛出异常
}
```

**其他可选方案:**

**方案1: 返回错误消息（适用于 Form/Grid 方法）**
```php
if (!$activity) {
    return message_error(admin_trans('common.no_permission'));
}
```

**方案2: 抛出异常（适用于任何方法）**
```php
if (!$activity) {
    throw new \Exception(admin_trans('common.no_permission'));
}
```

**推荐:** 在返回 Grid 的方法中，使用 `throw new \Exception()` 更合适

---

## 📊 ExAdmin Actions API 参考

### Actions 类的方法

**添加按钮:**
```php
$actions->prepend(Button $button)   // 在前面添加按钮
$actions->append(Button $button)    // 在后面添加按钮
```

**隐藏默认按钮:**
```php
$actions->hideEdit()     // 隐藏编辑按钮
$actions->hideDelete()   // 隐藏删除按钮
$actions->hideView()     // 隐藏查看按钮
```

**❌ 不存在的方法:**
```php
$actions->button()       // ❌ 错误
$actions->add()          // ❌ 错误
$actions->create()       // ❌ 错误
```

---

### Button 创建方式

**基础按钮:**
```php
Button::create('按钮文本')
    ->type('link')              // 按钮类型: primary, link, dashed, default
    ->size('small')             // 尺寸: large, middle, small
    ->icon('IconName')          // 图标
```

**带模态框的按钮:**
```php
Button::create('查看详情')
    ->type('link')
    ->size('small')
    ->modal([$this, 'methodName'], ['param' => $value])
    ->width('80%')              // 模态框宽度
    ->title('标题')              // 模态框标题
```

**带确认框的按钮:**
```php
Button::create('删除')
    ->type('link')
    ->size('small')
    ->confirm('确定要删除吗？')
    ->request(admin_url([$this, 'delete']), ['id' => $id])
```

---

## 🔍 渠道后台参考实现

**完整示例 (ChannelLotteryTicketActivityController.php):**

```php
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\grid\grid\Actions;

$grid->actions(function (Actions $actions, $data) {
    // ✅ 正确用法：使用 prepend() 添加 Button
    $actions->prepend(
        Button::create(admin_trans('lottery_ticket.action.view'))
            ->type('link')
            ->size('small')
            ->modal([$this, 'getActivityDetail'], ['activity_id' => $data['id']])
            ->width('90%')
    );

    // 添加多个按钮
    $actions->prepend(
        Button::create(admin_trans('lottery_ticket.action.edit'))
            ->type('primary')
            ->size('small')
            ->modal([$this, 'saveActivity'], ['id' => $data['id']])
    );

    // 隐藏默认按钮
    $actions->hideEdit();
    $actions->hideDelete();
});
```

---

## 📋 修改文件清单

**修改文件:**
- `D:/gk_admin/addons/webman/controller/AgentLotteryTicketActivityController.php`

**修改内容:**
1. ✅ 导入 `Button` 类
2. ✅ 修改 `$actions->button()` 为 `$actions->prepend(Button::create())`
3. ✅ 修改 `abort()` 为 `throw new \Exception()`

---

## ✅ 验证清单

- [x] 导入 `Button` 类
- [x] 使用 `prepend()` 方法
- [x] 使用 `Button::create()` 创建按钮
- [x] 权限验证使用 `throw new \Exception()`
- [ ] 测试：点击"查看奖品配置"按钮能打开模态框
- [ ] 测试：无权限时能正确显示错误提示

---

## 🎯 总结

**错误根源:**
- 误用了 `$actions->button()` 方法（不存在）
- 误用了 Laravel 的 `abort()` 函数（Webman 中不存在）

**正确方式:**
- ✅ 使用 `$actions->prepend(Button::create())`
- ✅ 使用 `throw new \Exception()` 或 `return message_error()`

**参考实现:**
- 查看 `ChannelLotteryTicketActivityController.php` 中的 Actions 用法
- 查看其他 ExAdmin 控制器的权限验证方式

修复完成！🎉
