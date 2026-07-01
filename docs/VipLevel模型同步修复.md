# VipLevel 模型同步修复

## 问题说明

在添加"创建玩家时自动设置最低VIP等级"功能时，发现 gk_api 和 gk_work 项目的 VipLevel 模型缺少必要的常量定义。

---

## 错误信息

```php
// 在 Player.php 的 booted() 方法中
->where('status', VipLevel::STATUS_ENABLED)

// 报错：
Use of undefined constant VipLevel::STATUS_ENABLED
```

---

## 问题原因

### 1. gk_api 项目

**问题**: `D:/gk_api/app/model/VipLevel.php` 缺少状态常量

**原模型代码**:
```php
class VipLevel extends Model
{
    use HasDateTimeFormatter;
    protected $table = 'vip_level';
    
    // ❌ 没有定义 STATUS_ENABLED 和 STATUS_DISABLED 常量
}
```

### 2. gk_work 项目

**问题**: `D:/gk_work/app/model/VipLevel.php` 文件不存在

---

## 修复内容

### 1. ✅ 修复 gk_api 的 VipLevel 模型

**文件**: `D:/gk_api/app/model/VipLevel.php`

**添加的内容**:
```php
class VipLevel extends Model
{
    use HasDateTimeFormatter;

    protected $table = 'vip_level';

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ENABLED = 1;  // 启用

    protected $guarded = [];

    // ... 其他代码
}
```

---

### 2. ✅ 创建 gk_work 的 VipLevel 模型

**文件**: `D:/gk_work/app/model/VipLevel.php`（新建）

**完整代码**:
```php
<?php

namespace app\model;

use app\traits\HasDateTimeFormatter;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Class VipLevel
 * @property int id 主键
 * @property string name 等级名称
 * @property int upgrade_limit_days 升级限制时间（天数）
 * @property int retain_level_days 保级时间（天数）
 * @property float retain_level_bet_amount 保级所需打码量
 * @property float upgrade_bet_amount 升级所需打码量
 * @property float min_claim_amount 最小领取额
 * @property float birthday_bonus 生日礼金
 * @property int sort 排序
 * @property int status 状态（0=禁用，1=启用）
 * @property int department_id 部门/渠道ID
 * @property string created_at 创建时间
 * @property string updated_at 更新时间
 * @package app\model
 */
class VipLevel extends Model
{
    use HasDateTimeFormatter;

    protected $table = 'vip_level';

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ENABLED = 1;  // 启用

    protected $guarded = [];

    /**
     * 时间转换
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
```

---

## 三个项目的 VipLevel 模型对比

### gk_admin (参考模型)

**文件**: `D:/gk_admin/addons/webman/model/VipLevel.php`

**特点**:
- ✅ 有 `STATUS_DISABLED` 和 `STATUS_ENABLED` 常量
- ✅ 使用 `DataPermissions` trait（数据权限）
- ✅ 使用 `SoftDeletes` trait（软删除）
- ✅ 完整的关系定义

### gk_api (已修复)

**文件**: `D:/gk_api/app/model/VipLevel.php`

**特点**:
- ✅ 添加了 `STATUS_DISABLED` 和 `STATUS_ENABLED` 常量
- ✅ 使用 `HasDateTimeFormatter` trait
- ✅ 添加了 `protected $guarded = []`（允许批量赋值）

### gk_work (已创建)

**文件**: `D:/gk_work/app/model/VipLevel.php`

**特点**:
- ✅ 新建文件，结构与 gk_api 一致
- ✅ 添加了 `STATUS_DISABLED` 和 `STATUS_ENABLED` 常量
- ✅ 使用 `HasDateTimeFormatter` trait
- ✅ 添加了 `protected $guarded = []`

---

## 使用场景

### 在 Player 模型中使用

**三个项目都会用到**:

```php
// D:/gk_admin/addons/webman/model/Player.php
// D:/gk_api/app/model/Player.php
// D:/gk_work/app/model/Player.php

protected static function booted()
{
    static::created(function (Player $player) {
        // ...

        if (empty($player->vip_level_id) || $player->vip_level_id == 0) {
            $lowestVipLevel = VipLevel::query()
                ->where('department_id', $player->department_id)
                ->where('status', VipLevel::STATUS_ENABLED) // ⭐ 使用常量
                ->orderBy('sort', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($lowestVipLevel) {
                $player->vip_level_id = $lowestVipLevel->id;
                $player->saveQuietly();
            }
        }
    });
}
```

### 在 VipLevelService 中使用

**gk_admin 项目**:

```php
// D:/gk_admin/addons/webman/service/VipLevelService.php

public static function syncPlayersVipLevel(int $departmentId): array
{
    $lowestVipLevel = VipLevel::query()
        ->where('department_id', $departmentId)
        ->where('status', VipLevel::STATUS_ENABLED) // ⭐ 使用常量
        ->orderBy('sort', 'asc')
        ->orderBy('id', 'asc')
        ->first();
    
    // ...
}
```

---

## 状态常量的作用

### STATUS_ENABLED (值 = 1)

**含义**: VIP等级启用状态

**使用场景**:
- 查询可用的VIP等级
- 自动分配VIP等级时只选择启用的等级
- 同步玩家VIP等级时只选择启用的等级

**示例**:
```php
// 只获取启用的VIP等级
$vipLevels = VipLevel::where('status', VipLevel::STATUS_ENABLED)->get();
```

### STATUS_DISABLED (值 = 0)

**含义**: VIP等级禁用状态

**使用场景**:
- 临时禁用某个VIP等级
- 后台管理中切换VIP等级状态

**示例**:
```php
// 禁用某个VIP等级
$vipLevel->status = VipLevel::STATUS_DISABLED;
$vipLevel->save();
```

---

## 为什么需要常量？

### ❌ 不使用常量的问题

```php
// 硬编码数字
->where('status', 1)  // 1 是什么意思？启用？禁用？

// 容易出错
->where('status', 0)  // 0 是启用还是禁用？记不清了

// 不易维护
// 如果以后改变状态值，需要全局搜索替换所有的 0 和 1
```

### ✅ 使用常量的好处

```php
// 代码清晰易读
->where('status', VipLevel::STATUS_ENABLED)  // 一眼就知道是"启用"

// 不容易出错
->where('status', VipLevel::STATUS_DISABLED)  // 明确是"禁用"

// 易于维护
// 如果以后改变状态值，只需要修改常量定义即可
const STATUS_ENABLED = 2;  // 改成 2，所有引用都会自动更新
```

---

## 验证修复

### 测试1: 创建玩家时自动分配VIP等级

**gk_api 测试**:
```bash
# 通过API注册玩家
curl -X POST http://localhost:8787/api/v1/player/register \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "13800138000",
    "password": "123456"
  }'

# 查看玩家是否自动获得VIP等级
# 应该看到 vip_level_id 不为 NULL
```

**gk_admin 测试**:
```php
// 后台创建玩家
$player = Player::create([
    'name' => '测试玩家',
    'phone' => '13800138001',
    'department_id' => 1,
]);

// 检查VIP等级
var_dump($player->vip_level_id); // 应该自动分配了VIP1的ID
```

### 测试2: 同步玩家VIP等级

**gk_admin 测试**:
```php
// 在渠道后台执行同步
$result = VipLevelService::syncPlayersVipLevel(1);

// 应该成功，不报错
var_dump($result);
// ['success' => true, 'message' => '...', 'updated' => 150, 'skipped' => 20]
```

---

## 总结

### ✅ 修复完成

| 项目 | 文件 | 修复内容 |
|------|------|---------|
| gk_admin | `addons/webman/model/VipLevel.php` | ✅ 已有常量（参考） |
| gk_api | `app/model/VipLevel.php` | ✅ 添加了常量 |
| gk_work | `app/model/VipLevel.php` | ✅ 新建了模型 |

### 📝 添加的常量

```php
const STATUS_DISABLED = 0; // 禁用
const STATUS_ENABLED = 1;  // 启用
```

### 🎯 影响的功能

1. ✅ 创建玩家时自动设置最低VIP等级
2. ✅ 同步玩家VIP等级（批量操作）
3. ✅ 查询启用的VIP等级
4. ✅ 所有使用 `VipLevel::STATUS_ENABLED` 的地方

---

**修复日期**: 2026-06-17  
**修复人**: 后端开发团队
