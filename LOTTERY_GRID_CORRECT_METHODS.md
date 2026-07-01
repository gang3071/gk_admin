# Grid正确方法对照表

**修复日期:** 2026-06-10  
**最终版本**

---

## ✅ Grid对象上的正确方法

### 隐藏操作按钮

| 功能 | 正确方法 | 错误方法 ❌ |
|------|---------|-----------|
| 隐藏新增按钮 | `$grid->hideAdd()` | ~~`$grid->hideCreate()`~~ |
| 隐藏批量删除 | `$grid->hideDeleteSelection()` | ~~`$grid->hideBatchDel()`~~ |
| 隐藏删除按钮 | `$grid->hideDelete()` | ~~`$grid->hideDel()`~~ |
| 隐藏批量选择框 | `$grid->hideSelection()` | - |
| 隐藏回收站 | `$grid->hideTrashed()` | - |
| 隐藏操作列 | `$grid->hideAction()` | - |

---

## ✅ Actions对象上的正确方法

在 `$grid->actions()` 回调中使用：

```php
$grid->actions(function ($actions) {
    $actions->hideEdit();  // ✅ 隐藏编辑
    $actions->hideDel();   // ✅ 隐藏删除
    $actions->hideView();  // ✅ 隐藏查看
});
```

---

## 📋 完整示例

### 示例1: 只读列表（禁用所有操作）

```php
Grid::create($model, function (Grid $grid) {
    // 列定义...
    
    // 隐藏新增、批量删除、回收站
    $grid->hideAdd();
    $grid->hideDeleteSelection();
    $grid->hideTrashed();
    
    // 隐藏每行的编辑和删除按钮
    $grid->actions(function ($actions) {
        $actions->hideEdit();
        $actions->hideDel();
    });
    
    // 或者直接隐藏整个操作列
    // $grid->hideAction();
});
```

### 示例2: 只允许查看

```php
Grid::create($model, function (Grid $grid) {
    // 列定义...
    
    $grid->hideAdd();
    $grid->hideDeleteSelection();
    $grid->hideDelete();
    
    $grid->actions(function ($actions) {
        $actions->hideEdit();
        $actions->hideDel();
        // 保留 View 按钮（默认显示）
    });
});
```

### 示例3: 允许编辑但不允许删除

```php
Grid::create($model, function (Grid $grid) {
    // 列定义...
    
    $grid->hideAdd();
    $grid->hideDeleteSelection();
    $grid->hideDelete();
    
    $grid->actions(function ($actions) {
        $actions->hideDel();
        // 保留 Edit 和 View 按钮
    });
});
```

---

## 🔍 系统中的实际用法统计

从代码库中统计的实际使用情况：

```bash
$grid->hideAdd()              - 使用最多 ✅
$grid->hideDelete()           - 常用 ✅
$grid->hideDeleteSelection()  - 常用 ✅
$grid->hideSelection()        - 常用 ✅
$grid->hideTrashed()          - 少用 ✅
$grid->hideAction()           - 偶尔使用 ✅

$actions->hideEdit()          - 使用最多 ✅
$actions->hideDel()           - 使用最多 ✅
$actions->hideView()          - 少用 ✅
```

---

## ⚠️ 常见错误

### 错误1: 方法名错误

```php
// ❌ 错误
$grid->hideCreate();      // 不存在！
$grid->hideBatchDel();    // 不存在！
$grid->hideEdit();        // Grid上不存在！
$grid->hideDel();         // Grid上不存在！

// ✅ 正确
$grid->hideAdd();
$grid->hideDeleteSelection();
$grid->actions(function ($actions) {
    $actions->hideEdit();
    $actions->hideDel();
});
```

### 错误2: 在错误的对象上调用

```php
// ❌ 错误 - Edit和Del应该在Actions上
$grid->hideEdit();
$grid->hideDel();

// ✅ 正确
$grid->actions(function ($actions) {
    $actions->hideEdit();
    $actions->hideDel();
});
```

### 错误3: 想隐藏操作列但用错方法

```php
// ❌ 错误 - 每个都隐藏很麻烦
$grid->actions(function ($actions) {
    $actions->hideEdit();
    $actions->hideDel();
    $actions->hideView();
});

// ✅ 正确 - 直接隐藏整个操作列
$grid->hideAction();
```

---

## 📊 修复记录

### ChannelLotteryTicketActivityController.php

**Line 298:**
```php
// 修复前
$grid->hideCreate();           // ❌
$grid->hideDeleteSelection();  // ✅

// 修复后
$grid->hideAdd();              // ✅
$grid->hideDeleteSelection();  // ✅
```

**Line 325-330:**
```php
// 修复前
$grid->hideCreate();       // ❌
$grid->hideDeleteSelection();  // ✅
$grid->hideDelete();       // ✅
$grid->actions(function ($actions) {
    $actions->hideEdit();  // ✅
});

// 修复后
$grid->hideAdd();          // ✅
$grid->hideDeleteSelection();  // ✅
$grid->hideDelete();       // ✅
$grid->actions(function ($actions) {
    $actions->hideEdit();  // ✅
});
```

---

## ✅ 验证方法

### 1. 语法检查

```bash
php -l addons/webman/controller/ChannelLotteryTicketActivityController.php
# 预期: No syntax errors detected ✅
```

### 2. 搜索错误方法

```bash
# 检查是否还有错误的方法名
grep -rn "hideCreate\|hideBatchDel" addons/webman/controller/ChannelLotteryTicket*.php
# 预期: 无结果 ✅

grep -rn "grid.*hideEdit\|grid.*hideDel" addons/webman/controller/ChannelLotteryTicket*.php  
# 预期: 无结果 ✅
```

### 3. 功能测试

访问相关页面，确认：
- 新增按钮已隐藏
- 批量选择框已隐藏
- 删除按钮已隐藏
- 编辑按钮已隐藏（如配置）

---

## 🎯 快速参考

**需要隐藏什么？找对应方法：**

- 新增 → `$grid->hideAdd()`
- 批量删除 → `$grid->hideDeleteSelection()`
- 批量选择 → `$grid->hideSelection()`
- 删除 → `$grid->hideDelete()`
- 编辑 → `$actions->hideEdit()`（在回调中）
- 查看 → `$actions->hideView()`（在回调中）
- 整个操作列 → `$grid->hideAction()`

---

**修复状态:** ✅ 已完成  
**语法检查:** ✅ 通过  
**所有错误方法:** ✅ 已修正  

