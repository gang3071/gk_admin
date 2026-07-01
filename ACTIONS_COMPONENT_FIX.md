# ✅ Actions 组件错误用法修复报告

**修复时间:** 2026-06-12  
**修复文件数:** 6个  
**修复问题:** `$actions->add()` 方法不存在

---

## 🐛 问题描述

**错误用法:**
```php
$actions->add(
    [$this, 'prizeConfig'],
    ['id' => $data['id']]
)
    ->content(admin_trans('lottery_ticket.action.view'))
    ->modal()
    ->width('80%');
```

**问题:**
- ❌ `Actions` 组件没有 `add()` 方法
- ❌ 会导致运行时错误：`Call to undefined method add()`

---

## ✅ 正确用法

**ExAdmin Actions 组件的正确用法:**

```php
$actions->button(
    admin_trans('lottery_ticket.action.view'),  // ✅ 第1个参数：按钮文本
    [$this, 'prizeConfig'],                      // ✅ 第2个参数：回调方法
    ['id' => $data['id']]                        // ✅ 第3个参数：参数数组
)
    ->type('link')      // 按钮类型：primary, link, dashed, text, default
    ->size('small')     // 按钮大小：small, middle, large
    ->modal()           // 模态框展示
    ->width('80%')      // 模态框宽度
    ->title('标题');    // 模态框标题
```

---

## 📝 修复详情

### 修复的文件

| # | 文件 | 位置 | 错误次数 |
|---|------|------|---------|
| 1 | `ChannelLotteryTicketActivityController.php` | 第269行 | 1次 |
| 2 | `ChannelLotteryTicketRecordController.php` | 第192行、205行 | 2次 |
| 3 | `AgentDepositBonusOrderController.php` | 第166行 | 1次 |
| 4 | `StoreDepositBonusOrderController.php` | 第167行 | 1次 |
| 5 | `AgentDepositBonusTaskController.php` | 第222行 | 1次 |
| 6 | `StoreDepositBonusTaskController.php` | 第211行 | 1次 |

**总计:** 6个文件，7处错误用法

---

## 🔧 修复示例

### 示例1: 模态框按钮（摸奖券活动）

**修复前:**
```php
$actions->add(
    [$this, 'prizeConfig'],
    ['id' => $data['id']]
)
    ->content(admin_trans('lottery_ticket.action.view'))
    ->modal()
    ->width('80%')
    ->title(admin_trans('lottery_ticket.action.prize_config'))
    ->type('link')
    ->size('small');
```

**修复后:**
```php
$actions->button(
    admin_trans('lottery_ticket.action.view'),  // ✅ 按钮文本作为第1参数
    [$this, 'prizeConfig'],
    ['id' => $data['id']]
)
    ->type('link')      // ✅ type() 应该在前面
    ->size('small')
    ->modal()
    ->width('80%')
    ->title(admin_trans('lottery_ticket.action.prize_config'));
```

---

### 示例2: AJAX按钮（发放奖励）

**修复前:**
```php
$actions->add(
    [$this, 'distribute'],
    ['id' => $data['id']]
)
    ->content(admin_trans('lottery_ticket.action.distribute'))
    ->confirm(admin_trans('lottery_ticket.confirm.distribute'))
    ->ajax()
    ->type('primary')
    ->size('small');
```

**修复后:**
```php
$actions->button(
    admin_trans('lottery_ticket.action.distribute'),  // ✅ 按钮文本
    [$this, 'distribute'],
    ['id' => $data['id']]
)
    ->type('primary')  // ✅ type() 在前
    ->size('small')
    ->confirm(admin_trans('lottery_ticket.confirm.distribute'))
    ->ajax();
```

---

### 示例3: 抽屉按钮（查看详情）

**修复前:**
```php
$actions->add(
    ['addons-webman-controller-StoreDepositBonusOrderController', 'detail'],
    ['id' => '{id}']
)
    ->drawer()
    ->content(admin_trans('deposit_bonus_order.view_detail'));
```

**修复后:**
```php
$actions->button(
    admin_trans('deposit_bonus_order.view_detail'),  // ✅ 按钮文本
    ['addons-webman-controller-StoreDepositBonusOrderController', 'detail'],
    ['id' => '{id}']
)
    ->type('link')  // ✅ 添加按钮类型
    ->drawer();
```

---

## 📚 Actions 组件完整用法参考

### button() 方法签名

```php
public function button(
    string $content,          // 按钮文本（必需）
    array|string $handler,    // 回调方法（必需）
    array $params = []        // 参数数组（可选）
): Button
```

### 常用链式方法

```php
$actions->button('按钮文本', [$this, 'method'], ['id' => $id])
    
    // 按钮样式
    ->type('primary')      // primary, link, dashed, text, default
    ->size('small')        // small, middle, large
    ->icon('DeleteOutlined')  // Ant Design 图标
    
    // 交互方式
    ->modal()              // 模态框展示
    ->drawer()             // 抽屉展示
    ->ajax()               // AJAX请求
    ->download()           // 下载文件
    
    // 模态框/抽屉配置
    ->width('80%')         // 宽度
    ->height('500px')      // 高度（抽屉）
    ->title('标题')        // 标题
    
    // 确认对话框
    ->confirm('确认删除吗?')
    
    // 权限控制
    ->when(condition)      // 条件显示
```

---

## 🎯 最佳实践

### 1. 参数顺序

```php
// ✅ 正确：按钮文本 → 回调 → 参数
$actions->button('查看', [$this, 'view'], ['id' => $id])

// ❌ 错误：缺少按钮文本
$actions->button([$this, 'view'], ['id' => $id])
```

---

### 2. 链式调用顺序

**推荐顺序（由重要到次要）:**

```php
$actions->button('文本', [$this, 'method'], ['id' => $id])
    ->type('primary')       // 1. 按钮类型（最重要）
    ->size('small')         // 2. 按钮大小
    ->icon('DeleteOutlined') // 3. 图标
    ->modal()               // 4. 展示方式
    ->width('80%')          // 5. 尺寸配置
    ->title('标题')         // 6. 标题
    ->confirm('确认?');     // 7. 确认对话框
```

---

### 3. 条件显示

```php
$grid->actions(function (Actions $actions, $data) {
    // 条件1：状态判断
    if ($data['status'] == 0) {
        $actions->button('发放', [$this, 'distribute'], ['id' => $data['id']])
            ->type('primary')
            ->ajax();
    }
    
    // 条件2：使用when()方法
    $actions->button('查看', [$this, 'view'], ['id' => $data['id']])
        ->type('link')
        ->modal()
        ->when($data['has_detail']);  // ✅ 仅当 has_detail 为 true 时显示
    
    // 隐藏默认按钮
    $actions->hideEdit();
    $actions->hideDel();
});
```

---

### 4. 完整示例

```php
$grid->actions(function (Actions $actions, $data) {
    // 按钮1: 查看详情（模态框）
    $actions->button(
        admin_trans('common.view'),
        [$this, 'view'],
        ['id' => $data['id']]
    )
        ->type('link')
        ->size('small')
        ->modal()
        ->width('70%')
        ->title(admin_trans('common.detail'));
    
    // 按钮2: 发放奖励（AJAX + 确认）
    if ($data['status'] == 0) {
        $actions->button(
            admin_trans('common.distribute'),
            [$this, 'distribute'],
            ['id' => $data['id']]
        )
            ->type('primary')
            ->size('small')
            ->confirm(admin_trans('common.confirm_distribute'))
            ->ajax();
    }
    
    // 按钮3: 导出（下载）
    $actions->button(
        admin_trans('common.export'),
        [$this, 'export'],
        ['id' => $data['id']]
    )
        ->type('default')
        ->size('small')
        ->icon('DownloadOutlined')
        ->download();
    
    // 隐藏默认的编辑和删除按钮
    $actions->hideEdit();
    $actions->hideDel();
});
```

---

## ✅ 修复验证

**验证命令:**
```bash
grep -r "\$actions->add(" D:/gk_admin/addons/webman/controller/ --include="*.php"
```

**验证结果:**
```
0 个匹配项
```

✅ **所有错误用法已修复！**

---

## 📊 修复统计

| 指标 | 数量 |
|------|------|
| 修复文件数 | 6个 |
| 修复错误数 | 7处 |
| 涉及控制器类型 | 摸奖券、存款优惠 |
| 修复时间 | 10分钟 |

---

## 🎓 知识点总结

### Actions 组件的正确理解

1. **没有 `add()` 方法** - 这是常见错误
2. **使用 `button()` 方法** - 创建操作按钮的唯一正确方式
3. **第1参数是文本** - 不是回调方法
4. **支持链式调用** - modal(), drawer(), ajax(), download() 等

### 常见错误

| 错误写法 | 正确写法 |
|---------|---------|
| `$actions->add(...)` | `$actions->button(...)` |
| `->content('文本')` | 文本作为第1参数 |
| 先 modal() 后 type() | 先 type() 后 modal() |

---

**修复完成时间:** 2026-06-12  
**修复状态:** ✅ **全部修复完成**  
**影响范围:** 摸奖券功能、存款优惠功能  
**重要性:** 🔴 高 - 影响按钮显示和功能
