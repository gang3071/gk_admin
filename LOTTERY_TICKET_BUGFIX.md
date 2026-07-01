# 摸奖券功能 Bug 修复

## 🐛 修复的问题

### 1. Filter 类引用错误 ✅

**问题**: 使用了错误的命名空间路径
```php
// ❌ 错误
use ExAdmin\ui\component\grid\grid\filter\Filter;

// ✅ 正确
use ExAdmin\ui\component\grid\grid\Filter;
```

**影响文件**:
- `ChannelLotteryTicketActivityController.php`
- `ChannelLotteryTicketRecordController.php`

**修复**: 移除了多余的 `filter\` 路径层级

---

### 2. 状态设置逻辑错误 ✅

**问题**: 直接修改 `$form->data` 数组不会生效

```php
// ❌ 错误的方式
$form->data['status'] = LotteryTicketActivity::STATUS_NOT_STARTED;

// ✅ 正确的方式
if ($form->isEdit()) {
    $form->model()->status = $status;
} else {
    $form->input('status', $status);
}
```

**影响文件**: `ChannelLotteryTicketActivityController.php`

**修复前的代码**:
```php
$form->saving(function (Form $form) {
    // ...
    $now = time();
    if ($now < $startTime) {
        $form->data['status'] = LotteryTicketActivity::STATUS_NOT_STARTED;
    } elseif ($now >= $startTime && $now <= $endTime) {
        $form->data['status'] = LotteryTicketActivity::STATUS_ONGOING;
    } else {
        $form->data['status'] = LotteryTicketActivity::STATUS_ENDED;
    }
    
    return true;
});
```

**修复后的代码**:
```php
$form->saving(function (Form $form) {
    // 验证时间
    $startTime = strtotime($form->input('start_time'));
    $endTime = strtotime($form->input('end_time'));

    if ($endTime <= $startTime) {
        return message_error('结束时间必须大于开始时间');
    }

    // 自动设置状态
    $now = time();
    $status = LotteryTicketActivity::STATUS_NOT_STARTED;

    if ($now < $startTime) {
        $status = LotteryTicketActivity::STATUS_NOT_STARTED;
    } elseif ($now >= $startTime && $now <= $endTime) {
        $status = LotteryTicketActivity::STATUS_ONGOING;
    } else {
        $status = LotteryTicketActivity::STATUS_ENDED;
    }

    // 设置到模型属性，而不是 $form->data
    if ($form->isEdit()) {
        $form->model()->status = $status;
    } else {
        $form->input('status', $status);
    }

    return true;
});
```

**原因**:
- ExAdmin 的 Form 组件在保存时不会直接使用 `$form->data` 数组
- 需要通过 `$form->model()` 获取模型实例并设置属性
- 或者使用 `$form->input()` 方法设置表单输入值

---

### 3. 缺少 PRIZE_TYPE_POINTS 常量 ✅

**问题**: 控制器中使用了 `LotteryTicketRecord::PRIZE_TYPE_POINTS`，但模型中未定义

**影响文件**: 
- `LotteryTicketRecord.php` (模型)
- `ChannelLotteryTicketRecordController.php` (控制器)

**修复**: 在模型中添加积分奖品类型常量

```php
// 修复前
const PRIZE_TYPE_CASH = 'cash';       // 现金
const PRIZE_TYPE_BONUS = 'bonus';     // 红利
const PRIZE_TYPE_ITEM = 'item';       // 实物
const PRIZE_TYPE_EMPTY = 'empty';     // 未中奖

// 修复后
const PRIZE_TYPE_CASH = 'cash';       // 现金
const PRIZE_TYPE_BONUS = 'bonus';     // 红利
const PRIZE_TYPE_ITEM = 'item';       // 实物
const PRIZE_TYPE_POINTS = 'points';   // 积分 ✅ 新增
const PRIZE_TYPE_EMPTY = 'empty';     // 未中奖
```

**控制器中使用的地方**:
1. 奖品类型筛选选项
2. 奖品类型显示标签
3. 发放奖品逻辑中的 switch case

---

## ✅ 验证结果

### 语法检查

```bash
# 活动管理控制器
php -l ChannelLotteryTicketActivityController.php
# ✅ No syntax errors detected

# 中奖记录控制器
php -l ChannelLotteryTicketRecordController.php
# ✅ No syntax errors detected
```

### 功能验证清单

- [x] Filter 类正确引用
- [x] 状态自动设置逻辑修正
- [x] 积分奖品类型常量添加
- [x] 控制器语法检查通过
- [x] 模型常量完整

---

## 📋 修复详情

| 问题 | 文件 | 行号 | 严重性 | 状态 |
|------|------|------|--------|------|
| Filter 命名空间错误 | ChannelLotteryTicketActivityController.php | 11 | 高 | ✅ 已修复 |
| Filter 命名空间错误 | ChannelLotteryTicketRecordController.php | 11 | 高 | ✅ 已修复 |
| 状态设置逻辑错误 | ChannelLotteryTicketActivityController.php | 219-230 | 中 | ✅ 已修复 |
| 缺少常量定义 | LotteryTicketRecord.php | 43-46 | 中 | ✅ 已修复 |

---

## 🔍 技术细节

### ExAdmin Form 数据设置最佳实践

**场景1: 新建记录时设置字段值**
```php
$form->saving(function (Form $form) {
    if (!$form->isEdit()) {
        // 方法1: 使用 input() 设置
        $form->input('field_name', $value);
        
        // 方法2: 使用 hidden 字段的 default
        // 在表单定义时: $form->hidden('field')->default($value);
    }
});
```

**场景2: 编辑记录时修改字段值**
```php
$form->saving(function (Form $form) {
    if ($form->isEdit()) {
        // 方法1: 直接修改模型属性
        $form->model()->field_name = $value;
        
        // 方法2: 使用 setAttribute
        $form->model()->setAttribute('field_name', $value);
    }
});
```

**场景3: 新建和编辑都适用**
```php
$form->saving(function (Form $form) {
    // 通用方式
    if ($form->isEdit()) {
        $form->model()->field_name = $value;
    } else {
        $form->input('field_name', $value);
    }
});
```

**❌ 错误方式**:
```php
// 这不会生效！
$form->data['field_name'] = $value;
```

---

## 🚀 后续步骤

1. **重启服务**
   ```bash
   cd D:\gk_admin
   php start.php restart
   ```

2. **测试功能**
   - 访问 **摸奖券管理 → 进行中的活动**
   - 创建新活动，验证状态自动设置
   - 配置奖品，确保积分类型可选
   - 查看中奖记录，验证筛选功能

3. **验证状态自动设置**
   - 创建未开始的活动（开始时间 > 当前时间）
   - 创建进行中的活动（当前时间在开始和结束时间之间）
   - 创建已结束的活动（结束时间 < 当前时间）
   - 检查数据库中的 status 字段是否正确

---

## 📚 相关文档

- [ExAdmin Form 文档](https://exadmin.com/docs/form)
- [摸奖券实现总结](./LOTTERY_TICKET_IMPLEMENTATION_SUMMARY.md)
- [迁移指南](./LOTTERY_TICKET_MENU_MIGRATION_GUIDE.md)

---

**修复时间**: 2026-06-04  
**版本**: 1.0  
**状态**: ✅ 所有问题已修复
