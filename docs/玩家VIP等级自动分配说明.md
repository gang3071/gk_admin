# 玩家VIP等级自动分配功能说明

## 功能概述

在创建玩家时，系统会自动为玩家分配该渠道的最低VIP等级（如果渠道已配置VIP等级）。

---

## 修改文件

### 1. gk_admin 项目
**文件**: `D:/gk_admin/addons/webman/model/Player.php`

**修改位置**: `booted()` 方法中的 `created` 事件监听器

### 2. gk_api 项目
**文件**: `D:/gk_api/app/model/Player.php`

**修改位置**: `booted()` 方法中的 `created` 事件监听器

### 3. gk_work 项目
**文件**: `D:/gk_work/app/model/Player.php`

**修改位置**: `booted()` 方法中的 `created` 事件监听器

---

## 实现逻辑

### 核心代码

```php
protected static function booted()
{
    static::created(function (Player $player) {
        // 1. 创建玩家扩展信息（原有逻辑）
        $playerExtend = new PlayerExtend();
        $playerExtend->player_id = $player->id;
        $playerExtend->save();

        // 2. 自动设置最低VIP等级（新增逻辑）⭐
        if (empty($player->vip_level_id) || $player->vip_level_id == 0) {
            // 查找该渠道的最低VIP等级
            $lowestVipLevel = VipLevel::query()
                ->where('department_id', $player->department_id)
                ->where('status', VipLevel::STATUS_ENABLED)
                ->orderBy('sort', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            // 如果找到了VIP等级，则设置为玩家的等级
            if ($lowestVipLevel) {
                $player->vip_level_id = $lowestVipLevel->id;
                $player->saveQuietly(); // 使用saveQuietly避免触发updated事件
            }
        }
    });
}
```

---

## 触发条件

### ✅ 自动分配VIP等级的条件

1. **创建新玩家时**（触发 `created` 事件）
2. **玩家的 `vip_level_id` 为空或为 0**
3. **该渠道有可用的VIP等级**（`status = 1` 启用状态）

### ❌ 不会自动分配的情况

1. 创建玩家时**已明确指定**了 `vip_level_id`（不为NULL且不为0）
2. 该渠道**没有配置任何VIP等级**
3. 该渠道的所有VIP等级都是**禁用状态**（`status = 0`）

---

## 最低等级判断规则

**查询条件**:
```php
VipLevel::query()
    ->where('department_id', $player->department_id)  // 1. 同一个渠道
    ->where('status', VipLevel::STATUS_ENABLED)       // 2. 启用状态
    ->orderBy('sort', 'asc')                          // 3. 按sort升序
    ->orderBy('id', 'asc')                            // 4. sort相同时按id升序
    ->first();                                        // 5. 取第一个 = 最低等级
```

**示例数据**:
```
渠道ID: 1
VIP等级配置:
- VIP1: sort=1, status=1  ← ✅ 最低等级（优先级最高）
- VIP2: sort=2, status=1
- VIP3: sort=3, status=1
...
```

---

## 使用场景

### 场景1: 后台创建玩家（gk_admin）

**操作路径**: 渠道后台 → 玩家管理 → 添加玩家

**流程**:
```
1. 管理员填写玩家信息（姓名、手机号等）
2. 不填写VIP等级字段（或保持为空）
3. 点击"保存"
   ↓
4. 系统创建玩家记录
   ↓
5. 触发 created 事件
   ↓
6. 自动查找最低VIP等级（VIP1）
   ↓
7. 设置 player.vip_level_id = VIP1的ID
   ↓
8. 保存玩家（使用saveQuietly）
```

**结果**: 新玩家的VIP等级 = VIP1 ✅

---

### 场景2: API注册玩家（gk_api）

**API路径**: `POST /api/v1/player/register`

**流程**:
```
1. 玩家在APP/网站注册
2. 提交手机号、密码等信息
3. API创建玩家记录（vip_level_id未设置）
   ↓
4. 触发 created 事件
   ↓
5. 自动分配VIP1等级
```

**结果**: 新注册玩家自动获得VIP1等级 ✅

---

### 场景3: 渠道未配置VIP等级

**流程**:
```
1. 创建玩家
   ↓
2. 触发 created 事件
   ↓
3. 查找VIP等级 → 未找到（该渠道没有VIP等级）
   ↓
4. 不设置vip_level_id（保持为NULL或0）
```

**结果**: 玩家的 `vip_level_id` = NULL（等待管理员手动设置或执行"同步玩家等级"） ⚠️

---

### 场景4: 明确指定VIP等级

**代码示例**:
```php
// 创建玩家时明确指定VIP等级
$player = Player::create([
    'name' => '测试玩家',
    'phone' => '13800138000',
    'department_id' => 1,
    'vip_level_id' => 5,  // ⭐ 明确指定为VIP5
    // ...
]);

// 结果：player.vip_level_id = 5（不会被自动修改为VIP1）✅
```

**结果**: 尊重手动指定的VIP等级，不会被自动覆盖 ✅

---

## 与"同步玩家等级"功能的关系

### 两个功能的分工

| 功能 | 触发时机 | 作用对象 | 用途 |
|------|---------|---------|------|
| **自动分配VIP等级** | 创建玩家时 | 新创建的单个玩家 | 确保新玩家有初始等级 |
| **同步玩家等级** | 手动点击按钮 | 所有存量玩家 | 批量处理历史数据 |

### 配合使用场景

**时间线**:
```
2026-01-01: 渠道创建，没有VIP等级
  ↓
2026-01-15: 创建了100个玩家（vip_level_id = NULL）
  ↓
2026-02-01: 导入VIP等级配置（VIP1-VIP10）
  ↓
2026-02-01: 点击"同步玩家等级"
            → 100个存量玩家获得VIP1等级 ✅
  ↓
2026-02-02: 新注册1个玩家
            → 自动获得VIP1等级 ✅（自动分配功能）
```

**互补关系**:
- **自动分配**: 解决增量问题（新玩家）
- **同步功能**: 解决存量问题（历史玩家）

---

## 性能考虑

### 1. 查询优化

**问题**: 每次创建玩家都要查询一次VIP等级表？

**优化方案** (可选):
```php
// 使用缓存减少数据库查询
protected static function booted()
{
    static::created(function (Player $player) {
        // ...

        if (empty($player->vip_level_id) || $player->vip_level_id == 0) {
            $cacheKey = "lowest_vip_level:dept_{$player->department_id}";
            
            $lowestVipLevel = \support\Cache::remember($cacheKey, 3600, function () use ($player) {
                return VipLevel::query()
                    ->where('department_id', $player->department_id)
                    ->where('status', VipLevel::STATUS_ENABLED)
                    ->orderBy('sort', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();
            });

            if ($lowestVipLevel) {
                $player->vip_level_id = $lowestVipLevel->id;
                $player->saveQuietly();
            }
        }
    });
}
```

**缓存策略**:
- 缓存key: `lowest_vip_level:dept_{department_id}`
- 缓存时间: 1小时（3600秒）
- 清除时机: 修改VIP等级时清除对应渠道的缓存

**注意**: 当前实现**未使用缓存**，每次创建玩家都查询数据库。如果并发创建玩家量大，可以考虑添加缓存。

---

### 2. saveQuietly() 的作用

**为什么使用 `saveQuietly()`？**

```php
$player->vip_level_id = $lowestVipLevel->id;
$player->saveQuietly(); // ⭐ 静默保存
```

**作用**:
- 保存数据到数据库
- **不触发** `updated` 事件
- **不触发** `saving`、`saved` 等模型事件

**为什么不用 `save()`？**

```php
// ❌ 如果使用 save()
$player->save(); // 会触发 updated 事件

// updated 事件中会记录操作日志
static::updated(function (Player $player) {
    // 创建操作日志...
    // 这会在创建玩家时产生一条"修改玩家VIP等级"的日志
    // 不符合业务逻辑（应该是"创建玩家"而不是"修改"）
});
```

**使用 `saveQuietly()` 的好处**:
- ✅ 静默设置VIP等级，不产生额外的操作日志
- ✅ 避免触发不必要的事件监听器
- ✅ 性能更好（减少事件调用）

---

## 测试验证

### 测试用例1: 创建玩家（渠道有VIP等级）

**前置条件**:
```sql
-- 确保渠道1有VIP等级
SELECT * FROM vip_level WHERE department_id = 1 AND status = 1 ORDER BY sort ASC;
```

**操作**:
```php
$player = Player::create([
    'name' => '测试玩家A',
    'phone' => '13800138001',
    'department_id' => 1,
    // 不设置 vip_level_id
]);
```

**验证**:
```php
// 检查玩家的VIP等级
var_dump($player->vip_level_id); // 应该等于VIP1的ID
```

**预期结果**: ✅ 玩家自动获得VIP1等级

---

### 测试用例2: 创建玩家（渠道无VIP等级）

**前置条件**:
```sql
-- 确保渠道2没有VIP等级
DELETE FROM vip_level WHERE department_id = 2;
```

**操作**:
```php
$player = Player::create([
    'name' => '测试玩家B',
    'phone' => '13800138002',
    'department_id' => 2,
]);
```

**验证**:
```php
var_dump($player->vip_level_id); // 应该为 NULL 或 0
```

**预期结果**: ✅ 玩家的VIP等级为NULL（渠道未配置）

---

### 测试用例3: 创建玩家（明确指定VIP等级）

**操作**:
```php
$player = Player::create([
    'name' => '测试玩家C',
    'phone' => '13800138003',
    'department_id' => 1,
    'vip_level_id' => 5,  // 明确指定VIP5
]);
```

**验证**:
```php
var_dump($player->vip_level_id); // 应该等于 5
```

**预期结果**: ✅ 玩家的VIP等级为5（不会被覆盖为VIP1）

---

### 测试用例4: 批量创建玩家

**操作**:
```php
for ($i = 1; $i <= 10; $i++) {
    Player::create([
        'name' => "批量玩家{$i}",
        'phone' => "1380013800{$i}",
        'department_id' => 1,
    ]);
}
```

**验证**:
```sql
SELECT id, name, vip_level_id 
FROM yjb_player 
WHERE name LIKE '批量玩家%' 
ORDER BY id;
```

**预期结果**: ✅ 所有10个玩家的vip_level_id都等于VIP1的ID

---

## 常见问题

### Q1: 为什么有些玩家没有VIP等级？

**原因**:
1. 创建玩家时，该渠道还没有配置VIP等级
2. 该渠道的所有VIP等级都是禁用状态
3. 创建玩家时明确指定了 `vip_level_id = 0`

**解决方案**: 使用"同步玩家等级"功能批量设置

---

### Q2: 修改VIP等级的sort字段后，会影响已创建的玩家吗？

**答案**: ❌ 不会

**原因**: 
- 自动分配只在**创建玩家时**执行一次
- 已创建的玩家的VIP等级不会自动变化
- 修改sort字段只影响**新创建**的玩家

**示例**:
```
初始状态:
- VIP1: sort=1 ← 最低等级
- VIP2: sort=2

创建玩家A → vip_level_id = VIP1

修改VIP等级:
- VIP1: sort=2
- VIP2: sort=1 ← 现在是最低等级

创建玩家B → vip_level_id = VIP2

但玩家A的等级仍然是VIP1（不会自动变为VIP2）✅
```

---

### Q3: 可以关闭自动分配功能吗？

**答案**: 可以，但需要修改代码

**方法1**: 注释掉自动分配代码
```php
protected static function booted()
{
    static::created(function (Player $player) {
        $playerExtend = new PlayerExtend();
        $playerExtend->player_id = $player->id;
        $playerExtend->save();

        // 注释掉自动分配逻辑
        /*
        if (empty($player->vip_level_id) || $player->vip_level_id == 0) {
            // ...
        }
        */
    });
}
```

**方法2**: 添加配置开关（推荐）
```php
// config/app.php
'auto_assign_vip_level' => env('AUTO_ASSIGN_VIP_LEVEL', true),

// Player.php
if (config('app.auto_assign_vip_level', true)) {
    if (empty($player->vip_level_id) || $player->vip_level_id == 0) {
        // 自动分配逻辑
    }
}
```

---

## 总结

### ✅ 功能优势

1. **自动化**: 新玩家自动获得初始VIP等级，无需手动设置
2. **一致性**: 确保所有新玩家都有VIP等级（只要渠道配置了）
3. **灵活性**: 支持手动指定VIP等级（不会被覆盖）
4. **跨项目**: 三个项目（gk_admin、gk_api、gk_work）统一实现

### 📝 注意事项

1. 只处理**没有VIP等级**的玩家（vip_level_id为NULL或0）
2. 使用 `saveQuietly()` 避免触发额外的事件
3. 如果渠道没有VIP等级，玩家的vip_level_id保持为NULL
4. 已有VIP等级的玩家不会被自动修改

### 🔗 相关功能

- **同步玩家等级**: 批量处理存量玩家的VIP等级
- **VIP等级管理**: 配置和管理渠道的VIP等级
- **VIP升级降级**: 根据打码量自动升级/降级玩家VIP等级

---

**文档创建日期**: 2026-06-17  
**维护者**: 后端开发团队
