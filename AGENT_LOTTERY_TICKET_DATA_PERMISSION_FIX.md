# 代理后台摸奖券功能 - 数据权限和字段修复

## 🐛 问题总结

### 问题1: 数据权限过滤错误
**控制器:** `AgentLotteryTicketController`, `AgentLotteryTicketRecordController`

**错误逻辑:**
```php
// ❌ 错误：直接过滤 department_id
$grid->model()->where('department_id', $departmentId);
```

**问题:**
- 这样会显示渠道下所有的摸奖券/中奖记录
- 代理应该只能看到自己下属玩家的数据，而不是整个渠道的数据

**正确逻辑:**
```php
// ✅ 正确：通过玩家ID过滤
$playerIds = Player::where('department_id', $departmentId)->pluck('id')->toArray();
$grid->model()->whereIn('player_id', $playerIds);
```

---

### 问题2: 数据库字段名错误
**控制器:** `AgentLotteryTicketActivityController`, `AgentLotteryTicketController`, `AgentLotteryTicketRecordController`

**错误字段名:**
```php
// ❌ 错误：activity_name 字段不存在
$grid->column('activity.activity_name', '...')
$grid->model()->where('activity_name', 'like', '...')
$activities = LotteryTicketActivity::get(['id', 'activity_name'])
```

**数据库实际字段:**
```php
// ✅ 正确：实际字段名是 name
@property string $name 活动名称  // LotteryTicketActivity 模型
```

**SQL 错误:**
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'activity_name' in 'field list'
```

---

### 问题3: AgentLotteryTicketRecordController 返回 null
**原因:** 组合了问题1和问题2
- 数据权限过滤错误 → 查询不到数据
- 字段名错误 → SQL 报错

---

## ✅ 修复方案

### 1️⃣ 修复 AgentLotteryTicketController

**文件:** `addons/webman/controller/AgentLotteryTicketController.php`

#### 修复1: 数据权限过滤

**修改前:**
```php
public function index(): Grid
{
    return Grid::create(new LotteryTicket(), function (Grid $grid) {
        $admin = Admin::user();
        $departmentId = $admin->department_id;

        // ❌ 错误：显示整个渠道的数据
        $grid->model()->where('department_id', $departmentId);
```

**修改后:**
```php
public function index(): Grid
{
    return Grid::create(new LotteryTicket(), function (Grid $grid) {
        $admin = Admin::user();
        $departmentId = $admin->department_id;

        // ✅ 正确：只显示当前代理下玩家的摸奖券
        $playerIds = \addons\webman\model\Player::where('department_id', $departmentId)
            ->pluck('id')
            ->toArray();

        $grid->model()->whereIn('player_id', $playerIds);
```

#### 修复2: 活动名称字段

**修改前:**
```php
// ❌ 列定义 - 错误字段名
$grid->column('activity.activity_name', admin_trans('lottery_ticket.fields.activity_name'))
    ->width(200)->align('left');

// ❌ 筛选器 - 错误字段名
$activities = LotteryTicketActivity::where('department_id', $departmentId)
    ->orderBy('created_at', 'desc')
    ->get(['id', 'activity_name'])
    ->pluck('activity_name', 'id')
    ->toArray();
```

**修改后:**
```php
// ✅ 列定义 - 正确字段名
$grid->column('activity.name', admin_trans('lottery_ticket.fields.activity_name'))
    ->width(200)->align('left');

// ✅ 筛选器 - 正确字段名
$activities = LotteryTicketActivity::where('department_id', $departmentId)
    ->orderBy('created_at', 'desc')
    ->get(['id', 'name'])
    ->pluck('name', 'id')
    ->toArray();
```

---

### 2️⃣ 修复 AgentLotteryTicketRecordController

**文件:** `addons/webman/controller/AgentLotteryTicketRecordController.php`

#### 修复1: 数据权限过滤

**修改前:**
```php
public function index(): Grid
{
    return Grid::create(new LotteryTicketRecord(), function (Grid $grid) {
        $admin = Admin::user();
        $departmentId = $admin->department_id;

        // ❌ 错误：显示整个渠道的数据
        $grid->model()->where('department_id', $departmentId);
```

**修改后:**
```php
public function index(): Grid
{
    return Grid::create(new LotteryTicketRecord(), function (Grid $grid) {
        $admin = Admin::user();
        $departmentId = $admin->department_id;

        // ✅ 正确：只显示当前代理下玩家的中奖记录
        $playerIds = \addons\webman\model\Player::where('department_id', $departmentId)
            ->pluck('id')
            ->toArray();

        $grid->model()->whereIn('player_id', $playerIds);
```

#### 修复2: 活动名称字段

**修改前:**
```php
// ❌ 列定义 - 错误字段名
$grid->column('activity.activity_name', admin_trans('lottery_ticket.fields.activity_name'))
    ->width(200)->align('left');

// ❌ 筛选器 - 错误字段名
$activities = LotteryTicketActivity::where('department_id', $departmentId)
    ->orderBy('created_at', 'desc')
    ->get(['id', 'activity_name'])
    ->pluck('activity_name', 'id')
    ->toArray();
```

**修改后:**
```php
// ✅ 列定义 - 正确字段名
$grid->column('activity.name', admin_trans('lottery_ticket.fields.activity_name'))
    ->width(200)->align('left');

// ✅ 筛选器 - 正确字段名
$activities = LotteryTicketActivity::where('department_id', $departmentId)
    ->orderBy('created_at', 'desc')
    ->get(['id', 'name'])
    ->pluck('name', 'id')
    ->toArray();
```

---

### 3️⃣ 修复 AgentLotteryTicketActivityController

**文件:** `addons/webman/controller/AgentLotteryTicketActivityController.php`

#### 修复: 活动名称字段

**修改前:**
```php
// ❌ 列定义 - 错误字段名
$grid->column('activity_name', admin_trans('lottery_ticket.fields.activity_name'))
    ->width(200)->align('left')->fixed(true);

// ❌ 筛选条件 - 错误字段名
if (!empty($requestFilter['activity_name'])) {
    $grid->model()->where('activity_name', 'like', '%' . $requestFilter['activity_name'] . '%');
}

// ❌ 筛选器 - 错误字段名
$filter->like()->text('activity_name')
    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'));
```

**修改后:**
```php
// ✅ 列定义 - 正确字段名
$grid->column('name', admin_trans('lottery_ticket.fields.activity_name'))
    ->width(200)->align('left')->fixed(true);

// ✅ 筛选条件 - 正确字段名
if (!empty($requestFilter['activity_name'])) {
    $grid->model()->where('name', 'like', '%' . $requestFilter['activity_name'] . '%');
}

// ✅ 筛选器 - 保持不变（使用 activity_name 作为参数名）
$filter->like()->text('activity_name')
    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'));
```

**注意:** 筛选器的 `text('activity_name')` 是前端传参的字段名，不需要修改。只需修改 SQL 查询中的字段名。

---

## 📊 数据关系说明

### 代理 → 玩家 → 摸奖券/中奖记录

```
Channel (渠道)
  department_id: 1001
    ↓
Agent (代理)
  department_id: 1001  ← 代理属于渠道
    ↓
Player (玩家)
  department_id: 1001  ← 玩家也属于同一个渠道
  id: [101, 102, 103, ...]
    ↓
LotteryTicket (摸奖券)
  player_id: 101  ← 摸奖券属于具体玩家
  department_id: 1001
    ↓
LotteryTicketRecord (中奖记录)
  player_id: 101  ← 中奖记录属于具体玩家
  department_id: 1001
```

### 数据权限过滤逻辑

**渠道后台 (ChannelLotteryTicketController):**
```php
// ✅ 可以直接用 department_id 过滤（查看整个渠道的数据）
$grid->model()->where('department_id', $departmentId);
```

**代理后台 (AgentLotteryTicketController):**
```php
// ❌ 错误：这样会看到整个渠道的数据
$grid->model()->where('department_id', $departmentId);

// ✅ 正确：只查看当前代理下玩家的数据
$playerIds = Player::where('department_id', $departmentId)
    ->where('agent_admin_id', $admin->id)  // 如果需要更精确的过滤
    ->pluck('id')->toArray();
$grid->model()->whereIn('player_id', $playerIds);
```

**店家后台 (StoreLotteryTicketController):**
```php
// ✅ 只查看当前店家下玩家的数据
$playerIds = Player::where('store_admin_id', $admin->id)
    ->pluck('id')->toArray();
$grid->model()->whereIn('player_id', $playerIds);
```

---

## 📋 数据库字段对照表

| 模型 | 错误字段名 | 正确字段名 | 说明 |
|------|-----------|-----------|------|
| LotteryTicketActivity | `activity_name` ❌ | `name` ✅ | 活动名称 |
| LotteryTicket | - | `ticket_no` | 券号 |
| LotteryTicket | - | `player_id` | 所属玩家ID |
| LotteryTicketRecord | - | `player_id` | 所属玩家ID |
| LotteryTicketRecord | - | `prize_name` | 奖品名称 |

---

## 🔍 测试验证

### 场景1: 代理后台 - 摸奖券列表

**数据准备:**
```sql
-- 渠道 department_id = 1001
-- 代理A admin_id = 10
-- 代理A的玩家: player_id IN (101, 102, 103)
-- 代理B admin_id = 11  
-- 代理B的玩家: player_id IN (201, 202, 203)

-- 摸奖券数据
INSERT INTO lottery_ticket (player_id, department_id, ticket_no, ...)
VALUES 
    (101, 1001, '000001', ...),  -- 代理A的玩家
    (102, 1001, '000002', ...),  -- 代理A的玩家
    (201, 1001, '000003', ...),  -- 代理B的玩家
    (202, 1001, '000004', ...);  -- 代理B的玩家
```

**预期结果:**
- ✅ 代理A登录 → 只能看到 `000001`, `000002` (player_id = 101, 102)
- ✅ 代理B登录 → 只能看到 `000003`, `000004` (player_id = 201, 202)
- ❌ 如果不修复 → 都能看到全部4条记录

---

### 场景2: 代理后台 - 中奖记录

**数据准备:**
```sql
-- 中奖记录数据
INSERT INTO lottery_ticket_record (player_id, department_id, ticket_no, prize_name, ...)
VALUES 
    (101, 1001, '000001', '100元奖金', ...),  -- 代理A的玩家
    (201, 1001, '000003', '200元奖金', ...);  -- 代理B的玩家
```

**预期结果:**
- ✅ 代理A登录 → 只能看到 ticket_no = `000001`
- ✅ 代理B登录 → 只能看到 ticket_no = `000003`
- ❌ 如果不修复 → 都能看到全部2条记录

---

### 场景3: 活动名称显示

**数据准备:**
```sql
-- 活动数据
UPDATE lottery_ticket_activity
SET name = '春节摸奖活动'  -- ✅ 正确字段
WHERE id = 1;
```

**预期结果:**
- ✅ 修复后 → 活动名称正常显示 "春节摸奖活动"
- ❌ 修复前 → SQL 报错 "Unknown column 'activity_name'"

---

## ⚠️ 注意事项

### 1️⃣ 前端参数名 vs 数据库字段名

**前端传参（筛选器）:**
```php
// ✅ 保持不变：前端参数名
$filter->like()->text('activity_name')
    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'));
```

**后端SQL查询:**
```php
// ✅ 使用数据库实际字段名
if (!empty($requestFilter['activity_name'])) {
    $grid->model()->where('name', 'like', '%' . $requestFilter['activity_name'] . '%');
}
```

### 2️⃣ 关联查询字段名

**错误:**
```php
$grid->column('activity.activity_name', '...')  // ❌ activity_name 字段不存在
```

**正确:**
```php
$grid->column('activity.name', '...')  // ✅ name 字段存在
```

### 3️⃣ Player 查询优化

如果玩家数量很大，考虑优化查询：

**方法1: 添加索引**
```sql
ALTER TABLE player ADD INDEX idx_department_id (department_id);
```

**方法2: 使用 EXISTS 子查询**
```php
$grid->model()->whereExists(function ($query) use ($departmentId) {
    $query->select(\DB::raw(1))
        ->from('player')
        ->whereColumn('player.id', 'lottery_ticket.player_id')
        ->where('player.department_id', $departmentId);
});
```

---

## ✅ 修复总结

**修改文件 (3个):**
1. `AgentLotteryTicketController.php` - 数据权限 + 字段名
2. `AgentLotteryTicketRecordController.php` - 数据权限 + 字段名
3. `AgentLotteryTicketActivityController.php` - 字段名

**修复内容:**
1. ✅ 数据权限：从 `department_id` 过滤改为 `player_id IN (...)` 过滤
2. ✅ 字段名：`activity_name` → `name`
3. ✅ 列定义：`activity.activity_name` → `activity.name`
4. ✅ 筛选查询：`where('activity_name', ...)` → `where('name', ...)`
5. ✅ 活动下拉：`get(['id', 'activity_name'])` → `get(['id', 'name'])`

**效果:**
- ✅ 代理只能看到自己下属玩家的摸奖券和中奖记录
- ✅ 活动名称正常显示，不再报 SQL 错误
- ✅ 筛选器正常工作

修复完成！🎉
