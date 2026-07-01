# Grid方法调用错误修复

**修复日期:** 2026-06-10  
**发现人:** 用户  
**严重性:** 🔴 高（方法不存在会报错）

---

## 🐛 问题描述

使用了不存在的Grid方法：

**错误方法:**
```php
$grid->hideAdd();         // ❌ 不存在
$grid->hideBatchDel();    // ❌ 不存在
$grid->hideEdit();        // ❌ 不存在（Grid上）
$grid->hideDel();         // ❌ 不存在（Grid上）
```

---

## ✅ 正确方法

### Grid 对象上的方法

```php
// 隐藏新增按钮
$grid->hideCreate();

// 隐藏批量删除/批量选择
$grid->hideDeleteSelection();
// 或
$grid->hideSelection();

// 隐藏删除按钮
$grid->hideDelete();
```

### Actions 对象上的方法

```php
// 在 actions 回调中调用
$grid->actions(function ($actions) {
    $actions->hideEdit();  // ✅ 隐藏编辑按钮
    $actions->hideDel();   // ✅ 隐藏删除按钮
});
```

---

## 🔧 修复详情

### 修复1: Line 298-299

**修改前:**
```php
// 禁用新增和批量删除
$grid->hideAdd();          // ❌
$grid->hideBatchDel();     // ❌
```

**修改后:**
```php
// 禁用新增和批量删除
$grid->hideCreate();       // ✅
$grid->hideDeleteSelection(); // ✅
```

---

### 修复2: Line 323-326

**修改前:**
```php
$grid->hideAdd();         // ❌
$grid->hideBatchDel();    // ❌
$grid->hideEdit();        // ❌
$grid->hideDel();         // ❌
```

**修改后:**
```php
// 禁用所有操作
$grid->hideCreate();           // ✅ 隐藏新增
$grid->hideDeleteSelection();  // ✅ 隐藏批量删除
$grid->hideDelete();           // ✅ 隐藏删除
$grid->actions(function ($actions) {
    $actions->hideEdit();      // ✅ 隐藏编辑（在actions中）
});
```

---

## 📚 Grid 常用方法参考

### 隐藏按钮/功能

```php
// 新增相关
$grid->hideCreate();           // 隐藏新增按钮
$grid->disableCreateButton();  // 禁用新增按钮

// 删除相关
$grid->hideDelete();           // 隐藏删除按钮
$grid->hideDeleteSelection();  // 隐藏批量删除
$grid->hideSelection();        // 隐藏批量选择框

// 导出相关
$grid->hideExport();           // 隐藏导出按钮

// 筛选相关
$grid->hideFilter();           // 隐藏筛选器

// 回收站相关
$grid->hideTrashed();          // 隐藏回收站
```

### Actions 方法

```php
$grid->actions(function ($actions) {
    $actions->hideEdit();      // 隐藏编辑
    $actions->hideDel();       // 隐藏删除
    $actions->hideView();      // 隐藏查看
});
```

### 条件隐藏

```php
$grid->actions(function ($actions) use ($data) {
    // 根据条件隐藏
    if ($data['status'] == 1) {
        $actions->hideEdit();
        $actions->hideDel();
    }
});
```

---

## 📊 系统中的正确示例

### 示例1: ChannelAgentController.php

```php
$grid->actions(function ($actions) {
    $actions->hideEdit();
    $actions->hideDel();
});

$grid->hideDelete();
$grid->hideSelection();
```

### 示例2: ChannelPlayerController.php

```php
$grid->hideDeleteSelection();
$grid->hideTrashed();
$grid->actions(function ($actions) {
    $actions->hideDel();
    $actions->hideEdit();
});
```

---

## ✅ 验证方法

### 1. 检查方法是否存在

```bash
# 搜索错误的方法调用
grep -rn "hideAdd\|hideBatchDel" addons/webman/controller/
# 预期: 无结果 ✅

grep -rn "grid.*hideEdit\|grid.*hideDel" addons/webman/controller/
# 预期: 无结果 ✅
```

### 2. 测试页面功能

访问奖品配置页面：
- 不应该有新增按钮
- 不应该有批量选择框
- 不应该有删除按钮
- 不应该有编辑按钮

### 3. 检查错误日志

```bash
# 查看是否有方法不存在的错误
tail -f runtime/logs/error.log | grep "Call to undefined method"
# 预期: 无相关错误
```

---

## 📋 完整对照表

| 错误方法 | 正确方法 | 作用对象 |
|---------|---------|---------|
| `$grid->hideAdd()` | `$grid->hideCreate()` | Grid |
| `$grid->hideBatchDel()` | `$grid->hideDeleteSelection()` | Grid |
| `$grid->hideEdit()` | `$actions->hideEdit()` | Actions |
| `$grid->hideDel()` | `$actions->hideDel()` | Actions |
| - | `$grid->hideDelete()` | Grid（单个删除）|
| - | `$grid->hideSelection()` | Grid（批量选择）|

---

## 🎯 最佳实践

### 1. 禁用所有操作（只读列表）

```php
// 完全只读的Grid
$grid->hideCreate();
$grid->hideDeleteSelection();
$grid->hideDelete();
$grid->hideExport();
$grid->actions(function ($actions) {
    $actions->hideEdit();
    $actions->hideDel();
    $actions->hideView();
});
```

### 2. 只允许查看

```php
// 只能查看，不能增删改
$grid->hideCreate();
$grid->hideDeleteSelection();
$grid->hideDelete();
$grid->actions(function ($actions) {
    $actions->hideEdit();
    $actions->hideDel();
    // 保留 View 按钮
});
```

### 3. 条件禁用

```php
// 根据状态条件禁用
$grid->actions(function ($actions) {
    $status = $this->row['status'];
    
    if ($status == 1) {
        // 已完成的不允许编辑删除
        $actions->hideEdit();
        $actions->hideDel();
    }
});
```

---

**修复状态:** ✅ 已完成  
**影响文件:** `ChannelLotteryTicketActivityController.php`  
**修复位置:** 2处  
**测试状态:** ⏳ 待测试  

