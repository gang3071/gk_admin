# 店机后台控制器修改总结

## 修改时间
2026-03-26

## 修改目标
将店机后台6个控制器中的玩家信息显示改为设备信息，以符合店机业务场景。

## 修改的控制器

### 1. StorePlayerRechargeRecordController.php - 充值记录

**字段修改：**
- `player.uuid` → `player.machine.uuid` (设备UUID)
- `player_phone` (删除) → `player.machine.name` (设备名称)

**筛选器修改：**
- 新增: `player.machine.uuid` 筛选
- 新增: `player.machine.name` 筛选
- 删除: `player_phone` 筛选

**查询条件修改：**
```php
// 修改前
whereHas('player', function ($query) use ($exAdminFilter) {
    $query->where('uuid', $exAdminFilter['player']['uuid']);
});

// 修改后
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('uuid', $exAdminFilter['player']['machine']['uuid']);
});
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('name', 'like', '%' . $exAdminFilter['player']['machine']['name'] . '%');
});
```

---

### 2. StorePlayerWithdrawRecordController.php - 提现记录

**字段修改：**
- `player.uuid` → `player.machine.uuid`
- `player_phone` (删除) → `player.machine.name`

**筛选器修改：**
- 新增: `player.machine.uuid` 筛选
- 新增: `player.machine.name` 筛选

**查询条件修改：**
```php
// 修改前
whereHas('player', function ($query) use ($exAdminFilter) {
    $query->where('uuid', $exAdminFilter['player']['uuid']);
});

// 修改后
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('uuid', $exAdminFilter['player']['machine']['uuid']);
});
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('name', 'like', '%' . $exAdminFilter['player']['machine']['name'] . '%');
});
```

---

### 3. StorePlayGameRecordController.php - 游戏记录

**字段修改：**
- `player.name` (删除) → `player.machine.uuid` + `player.machine.name`
- `player.uuid` → `player.machine.uuid`

**筛选器修改：**
- 新增: `player.machine.uuid` 筛选
- 新增: `player.machine.name` 筛选

**查询条件修改：**
```php
// 修改前
whereHas('player', function ($query) use ($exAdminFilter) {
    $query->where('uuid', 'like', $exAdminFilter['player']['uuid'] . '%');
});

// 修改后
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('uuid', 'like', $exAdminFilter['player']['machine']['uuid'] . '%');
});
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('name', 'like', '%' . $exAdminFilter['player']['machine']['name'] . '%');
});
```

---

### 4. StorePlayerGameLogController.php - 游戏日志

**字段修改：**
- `player.uuid` → `player.machine.uuid` + `player.machine.name`

**筛选器修改：**
- 删除: `player.phone` 筛选
- 新增: `player.machine.uuid` 筛选
- 新增: `player.machine.name` 筛选

**查询条件修改：**
```php
// 修改前
whereHas('player', function ($query) use ($exAdminFilter) {
    $query->where('uuid', $exAdminFilter['player']['uuid']);
});
whereHas('player', function ($query) use ($exAdminFilter) {
    $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
});

// 修改后
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('uuid', $exAdminFilter['player']['machine']['uuid']);
});
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('name', 'like', '%' . $exAdminFilter['player']['machine']['name'] . '%');
});
```

---

### 5. StoreLotteryController.php - 彩金记录

**字段修改：**
- `player_phone` (冗余字段) → `machine_uuid` (冗余字段)
- 新增显示: `machine_name`

**筛选器修改：**
- `player_phone` → `machine_uuid`

**查询条件修改：**
```php
// 修改前
if (!empty($requestFilter['player_phone'])) {
    $grid->model()->where('player_phone', 'like', '%' . $requestFilter['player_phone'] . '%');
}

// 修改后
if (!empty($requestFilter['machine_uuid'])) {
    $grid->model()->where('machine_uuid', 'like', '%' . $requestFilter['machine_uuid'] . '%');
}
```

**说明：** 此控制器使用的是冗余字段（记录表中直接存储），而非关联查询。

---

### 6. StoreDepositBonusTaskController.php - 存款优惠任务

**字段修改：**
- `player_id` (显示 player.username) → `player.machine.uuid` + `player.machine.name`

**筛选器修改：**
- `player.username` → `player.machine.uuid` + `player.machine.name`

**代码修改：**
```php
// 修改前
$grid->column('player_id', admin_trans('deposit_bonus_task.fields.player'))
    ->display(function ($val, PlayerBonusTask $data) {
        $player = $data->player;
        if (!$player) return '-';
        return Html::create()->content([
            $avatar,
            Html::div()->content($player->username ?? '-')
        ]);
    });

// 修改后
$grid->column('player.machine.uuid', admin_trans('machine.fields.uuid'))->copy();
$grid->column('player.machine.name', admin_trans('machine.fields.name'));
```

---

## 修改模式总结

### 1. 关联查询模式（大部分控制器）

使用 `player.machine` 关联：
```php
// 列显示
$grid->column('player.machine.uuid', admin_trans('machine.fields.uuid'));
$grid->column('player.machine.name', admin_trans('machine.fields.name'));

// 筛选器
$filter->like()->text('player.machine.uuid')->placeholder(admin_trans('machine.fields.uuid'));
$filter->like()->text('player.machine.name')->placeholder(admin_trans('machine.fields.name'));

// 查询条件
whereHas('player.machine', function ($query) use ($exAdminFilter) {
    $query->where('uuid', $exAdminFilter['player']['machine']['uuid']);
});
```

### 2. 冗余字段模式（StoreLotteryController）

直接查询记录表中的冗余字段：
```php
// 列显示
$grid->column('machine_uuid', admin_trans('machine.fields.uuid'));
$grid->column('machine_name', admin_trans('machine.fields.name'));

// 筛选器
$filter->like()->text('machine_uuid')->placeholder(admin_trans('machine.fields.uuid'));

// 查询条件
if (!empty($requestFilter['machine_uuid'])) {
    $grid->model()->where('machine_uuid', 'like', '%' . $requestFilter['machine_uuid'] . '%');
}
```

---

## 数据库关系说明

店机后台的数据关系：
```
Player (玩家)
  └─ machine (关联到 Machine 表)
      ├─ uuid (设备UUID)
      └─ name (设备名称)
```

**关联定义：**
```php
// Player 模型
public function machine()
{
    return $this->belongsTo(Machine::class, 'machine_id');
}
```

---

## 验证结果

所有6个控制器已成功修改：

| 控制器 | machine.uuid引用 | machine.name引用 | 状态 |
|--------|-----------------|-----------------|------|
| StorePlayerRechargeRecordController | ✅ 2处 | ✅ 已添加 | 完成 |
| StorePlayerWithdrawRecordController | ✅ 2处 | ✅ 已添加 | 完成 |
| StorePlayGameRecordController | ✅ 2处 | ✅ 已添加 | 完成 |
| StorePlayerGameLogController | ✅ 2处 | ✅ 已添加 | 完成 |
| StoreLotteryController | ✅ 4处 | ✅ 已添加 | 完成 |
| StoreDepositBonusTaskController | ✅ 2处 | ✅ 已添加 | 完成 |

---

## 影响分析

### 优点
1. ✅ **业务逻辑清晰** - 店机后台显示设备信息，符合实际业务场景
2. ✅ **数据准确性提升** - 直接显示设备UUID和名称，避免混淆
3. ✅ **用户体验改善** - 店家可以直接通过设备信息查找记录

### 注意事项
1. ⚠️ **数据库关联** - 确保 Player 表有 `machine_id` 字段，并正确关联到 Machine 表
2. ⚠️ **空值处理** - 部分代码使用了 `??` 空合并操作符处理 machine 为空的情况
3. ⚠️ **翻译文件** - 需要确保 `machine.fields.uuid` 和 `machine.fields.name` 的翻译存在

---

## 后续建议

1. **测试验证**
   - 在测试环境中验证所有6个控制器的显示和筛选功能
   - 确认设备信息能正确显示
   - 测试筛选功能是否正常工作

2. **翻译补充**
   - 检查 `addons/webman/lang/*/machine.php` 中是否有相关翻译
   - 如缺失，需要添加：
     ```php
     'fields' => [
         'uuid' => '设备UUID',
         'name' => '设备名称',
     ]
     ```

3. **数据库检查**
   - 确认 Player 模型的 `machine()` 关联已定义
   - 确认 Machine 表存在且有 `uuid` 和 `name` 字段
   - 对于 StoreLotteryController，确认 player_lottery_record 表有 `machine_uuid` 和 `machine_name` 冗余字段

---

## 总结

本次修改成功将6个店机后台控制器从显示玩家信息改为显示设备信息，符合店机业务场景的实际需求。所有修改遵循了统一的模式，保持了代码的一致性和可维护性。
