# Machine Action 翻译修复说明

## 问题描述

**路径：** `/ex-admin/addons-webman-controller-MachineController/mediaPlay`

**错误现象：**
- 在机台媒体播放页面中，操作按钮显示翻译键而不是翻译文本
- 例如显示 `machine_action.1.all` 而不是 `機台狀態`

## 根本原因

### 1. 翻译键格式不匹配

**问题代码位置：** `addons/webman/helpers.php` 第1692行

```php
// ❌ 错误的翻译键格式
'action' => admin_trans('machine_action.machine_action.' . $type . '.' . $item)
```

**翻译文件结构：** `addons/webman/lang/*/machine_action.php`

```php
'function' => [
    GameType::TYPE_SLOT . '_' . Machine::CONTROL_TYPE_MEI => [
        Slot::ALL => '機台狀態',
        // ...
    ],
    GameType::TYPE_SLOT . '_' . Machine::CONTROL_TYPE_SONG => [
        SongSlot::ALL => '機台狀態',
        // ...
    ],
]
```

**不匹配分析：**
- 原翻译键：`machine_action.machine_action.{type}.{item}`
- 实际结构：`machine_action.function.{type}_{controlType}.{item}`
- 缺少 `controlType` 参数，无法区分不同工控类型（双美/小淞）

### 2. 翻译条目缺失

`SongSlot::ALL` 翻译在所有4个语言文件中都缺失。

## 修复方案

### ✅ 修复1：更正翻译键格式

**修改文件：** `addons/webman/helpers.php`

```diff
 function getMachineAction($type, $controlType): array
 {
     // ... 省略其他代码 ...
     
     if (!empty($data)) {
+        // 翻译键修正：machine_action.function.{type}_{controlType}.{item}
         foreach ($data as $item) {
             $optionList[] = [
                 'key' => $item,
-                'action' => admin_trans('machine_action.machine_action.' . $type . '.' . $item),
+                'action' => admin_trans('machine_action.function.' . $type . '_' . $controlType . '.' . $item),
             ];
         }
     }
     return $optionList;
 }
```

**修复说明：**
- 从 `machine_action.machine_action.{type}.{item}` 改为 `machine_action.function.{type}_{controlType}.{item}`
- 新格式包含 `controlType` 参数，可以正确区分双美工控（MEI）和小淞工控（SONG）

### ✅ 修复2：添加缺失的翻译条目

**修改文件：**
- `addons/webman/lang/zh-TW/machine_action.php`
- `addons/webman/lang/zh-CN/machine_action.php`
- `addons/webman/lang/en/machine_action.php`
- `addons/webman/lang/jp/machine_action.php`

**添加内容：**

```diff
 GameType::TYPE_SLOT . '_' . Machine::CONTROL_TYPE_SONG => [
     /** 小淞工控 */
+    SongSlot::ALL => '機台狀態',  // zh-TW
+    SongSlot::ALL => '机台状态',  // zh-CN
+    SongSlot::ALL => 'Machine Status',  // en
+    SongSlot::ALL => 'マシンステータス',  // jp
     SongSlot::OPEN_ANY_POINT => '開任意分',
     // ...
 ]
```

## 影响范围

### 受影响的页面

1. **机台媒体播放页面**
   - URL: `/ex-admin/addons-webman-controller-MachineController/mediaPlay/{id}`
   - 受影响控件：操作按钮列表（`action_list`）

2. **可能的其他页面**
   - 所有调用 `getMachineAction()` 函数的地方
   - 包括渠道管理、机台管理等可能显示工控操作的页面

### 受影响的机台类型

| 机台类型 | 工控类型 | 翻译键前缀 |
|---------|---------|-----------|
| Slot (斯洛) | MEI (双美) | `1_1` (GameType::TYPE_SLOT . '_' . Machine::CONTROL_TYPE_MEI) |
| Slot (斯洛) | SONG (小淞) | `1_2` (GameType::TYPE_SLOT . '_' . Machine::CONTROL_TYPE_SONG) |
| Steel Ball (钢珠) | MEI (双美) | `2_1` (GameType::TYPE_STEEL_BALL . '_' . Machine::CONTROL_TYPE_MEI) |
| Steel Ball (钢珠) | SONG (小淞) | `2_2` (GameType::TYPE_STEEL_BALL . '_' . Machine::CONTROL_TYPE_SONG) |

## 测试验证

### 1. 功能测试

**测试步骤：**

```bash
# 1. 重启Webman服务（使修改生效）
php start.php restart

# 2. 访问机台媒体播放页面
# http://localhost:8789/admin

# 3. 进入机台管理 → 选择任意机台 → 点击"播放视频流"

# 4. 检查操作按钮列表
```

**预期结果：**
- ✅ 操作按钮显示正确的翻译文本（如"機台狀態"）
- ✅ 不再显示翻译键（如`machine_action.1.all`）
- ✅ 不同工控类型显示对应的操作列表

### 2. 多语言测试

**切换语言测试：**

| 语言 | `SongSlot::ALL` 翻译 | 验证状态 |
|------|---------------------|----------|
| 繁体中文 (zh-TW) | 機台狀態 | ✅ 已添加 |
| 简体中文 (zh-CN) | 机台状态 | ✅ 已添加 |
| English (en) | Machine Status | ✅ 已添加 |
| 日本語 (jp) | マシンステータス | ✅ 已添加 |

**测试步骤：**
1. 登录后台
2. 切换到不同语言
3. 访问机台媒体播放页面
4. 验证所有操作按钮都正确翻译

### 3. 翻译完整性检查

**检查所有操作的翻译：**

```bash
# 检查Slot双美工控操作翻译
grep -A30 "GameType::TYPE_SLOT.*CONTROL_TYPE_MEI" addons/webman/lang/zh-TW/machine_action.php

# 检查Slot小淞工控操作翻译
grep -A20 "GameType::TYPE_SLOT.*CONTROL_TYPE_SONG" addons/webman/lang/zh-TW/machine_action.php

# 检查钢珠双美工控操作翻译
grep -A30 "GameType::TYPE_STEEL_BALL.*CONTROL_TYPE_MEI" addons/webman/lang/zh-TW/machine_action.php

# 检查钢珠小淞工控操作翻译
grep -A20 "GameType::TYPE_STEEL_BALL.*CONTROL_TYPE_SONG" addons/webman/lang/zh-TW/machine_action.php
```

## 相关函数调用链

```
MachineController::mediaPlay()
    ↓
getMachineAction($machine->type, $machine->control_type)
    ↓
MachineServices::getSlotAction($controlType) 或 MachineServices::getJackpotAction($controlType)
    ↓
返回操作常量数组 [SongSlot::ALL, SongSlot::OPEN_ANY_POINT, ...]
    ↓
遍历并生成翻译：admin_trans('machine_action.function.{type}_{controlType}.{item}')
    ↓
查找翻译文件：addons/webman/lang/{locale}/machine_action.php
    ↓
返回翻译文本
```

## 操作常量说明

### Slot 双美工控（Slot::class）

| 常量 | 值 | 翻译（zh-TW） |
|------|-----|-------------|
| Slot::ALL | 'all' | 機台狀態 |
| Slot::WASH_ZERO | '4F' | 洗分&清零 |
| Slot::OPEN_ANY_POINT | '4A' | 開任意分 |
| Slot::OUT_ON | 'AA5708000001150D' | 開啟自動 |
| ... | ... | ... |

### Slot 小淞工控（SongSlot::class）

| 常量 | 值 | 翻译（zh-TW） |
|------|-----|-------------|
| SongSlot::ALL | 'all' | 機台狀態 ✅ 新增 |
| SongSlot::OPEN_ANY_POINT | 'afca' | 開任意分 |
| SongSlot::WASH_ZERO | 'afcc' | 洗分&清零 |
| SongSlot::START | 'afce' | 啟動/停止自動 |
| ... | ... | ... |

### Steel Ball 双美工控（Jackpot::class）

| 常量 | 值 | 翻译（zh-TW） |
|------|-----|-------------|
| Jackpot::ALL | 'all' | 機台狀態 |
| Jackpot::WASH_ZERO | '4F' | 洗分&清零 |
| Jackpot::OPEN_ANY_POINT | '4A' | 開任意分數 |
| ... | ... | ... |

### Steel Ball 小淞工控（SongJackpot::class）

| 常量 | 值 | 翻译（zh-TW） |
|------|-----|-------------|
| SongJackpot::ALL | 'all' | 機台狀態 |
| SongJackpot::WASH_ZERO | 'afcc' | 洗分&清零 |
| SongJackpot::OPEN_ANY_POINT | 'afca' | 開任意分數 |
| ... | ... | ... |

## 修改文件清单

| 文件路径 | 修改类型 | 修改内容 |
|---------|---------|---------|
| `addons/webman/helpers.php` | 代码修正 | 更正翻译键格式（第1692行） |
| `addons/webman/lang/zh-TW/machine_action.php` | 翻译添加 | 添加 `SongSlot::ALL => '機台狀態'` |
| `addons/webman/lang/zh-CN/machine_action.php` | 翻译添加 | 添加 `SongSlot::ALL => '机台状态'` |
| `addons/webman/lang/en/machine_action.php` | 翻译添加 | 添加 `SongSlot::ALL => 'Machine Status'` |
| `addons/webman/lang/jp/machine_action.php` | 翻译添加 | 添加 `SongSlot::ALL => 'マシンステータス'` |

## 注意事项

### 1. 翻译键命名规范

**正确格式：**
```php
admin_trans('machine_action.function.{type}_{controlType}.{item}')
```

**参数说明：**
- `{type}` - 机台类型常量（GameType::TYPE_SLOT = 1, GameType::TYPE_STEEL_BALL = 2）
- `{controlType}` - 工控类型常量（Machine::CONTROL_TYPE_MEI = 1, Machine::CONTROL_TYPE_SONG = 2）
- `{item}` - 操作常量值（如 `'all'`, `'afca'`, `'4F'` 等）

### 2. 添加新操作时的注意事项

如果需要添加新的机台操作：

1. **在服务类中定义常量**
   - 文件：`app/service/machine/Slot.php` 或 `Jackpot.php` 等
   - 格式：`const OPERATION_NAME = 'value';`

2. **在 `MachineServices` 中添加到操作列表**
   - 文件：`app/service/machine/MachineServices.php`
   - 方法：`getSlotAction()` 或 `getJackpotAction()`

3. **在所有4个语言文件中添加翻译**
   - `addons/webman/lang/zh-TW/machine_action.php`
   - `addons/webman/lang/zh-CN/machine_action.php`
   - `addons/webman/lang/en/machine_action.php`
   - `addons/webman/lang/jp/machine_action.php`
   - 添加到对应的 `function.{type}_{controlType}` 数组中

### 3. 翻译文件结构

```php
// addons/webman/lang/zh-TW/machine_action.php

return [
    // 系统消息
    'machine_not_found' => '機台不存在',
    
    // 机台数据翻译
    'machine_data' => [
        'auto' => '自動狀態：{data}',
        // ...
    ],
    
    // 操作翻译（关键部分）
    'function' => [
        // 类型_工控类型 => [操作常量 => 翻译]
        '1_1' => [  // Slot双美
            'all' => '機台狀態',
            // ...
        ],
        '1_2' => [  // Slot小淞
            'all' => '機台狀態',
            // ...
        ],
        '2_1' => [  // 钢珠双美
            'all' => '機台狀態',
            // ...
        ],
        '2_2' => [  // 钢珠小淞
            'all' => '機台狀態',
            // ...
        ],
    ]
];
```

## 总结

### 修复效果

| 问题 | 修复前 | 修复后 |
|------|--------|--------|
| 翻译键格式 | ❌ `machine_action.machine_action.{type}.{item}` | ✅ `machine_action.function.{type}_{controlType}.{item}` |
| `SongSlot::ALL` 翻译 | ❌ 缺失 | ✅ 已添加（4种语言） |
| 工控类型区分 | ❌ 无法区分 | ✅ 正确区分双美/小淞 |
| 操作按钮显示 | ❌ 显示翻译键 | ✅ 显示翻译文本 |

### 关键要点

1. ✅ **翻译键必须包含工控类型** - 不同工控类型有不同的操作集
2. ✅ **翻译条目必须完整** - 所有操作常量都需要对应的翻译
3. ✅ **多语言同步更新** - 修改必须应用到全部4个语言文件
4. 📝 **遵循翻译命名规范** - 使用 `function.{type}_{controlType}.{item}` 格式

---

**修复日期：** 2026-05-19
**修复人员：** Claude (Staff Engineer)
**影响模块：** 机台管理、媒体播放
**风险等级：** 低（仅修正翻译键格式和添加翻译条目）
