# 代理后台摸奖券功能 - 只读模式设计

## 🎯 设计原则

**代理后台对摸奖券活动的权限:**
- ✅ **查看** - 可以查看活动列表、活动详情、奖品配置
- ❌ **创建** - 不能创建活动（由渠道创建）
- ❌ **编辑** - 不能编辑活动信息
- ❌ **删除** - 不能删除活动
- ❌ **批量操作** - 不能批量删除/导出

**原因:** 摸奖券活动是渠道级别的功能，由渠道统一创建和管理。代理只能查看活动信息，方便下属店家参与活动。

---

## ✅ 实现方案

### 1️⃣ AgentLotteryTicketActivityController (活动列表)

**完全只读配置:**

```php
public function index(): Grid
{
    return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
        // ... 列定义 ...

        // 操作栏 - 清空默认操作，只保留"查看"
        $grid->actions(function (Actions $actions, LotteryTicketActivity $data) {
            // ⭐ 清空默认操作（删除默认的编辑/删除按钮）
            $actions->clear();

            // 只保留"查看奖品配置"按钮
            $actions->prepend(
                Button::create(admin_trans('lottery_ticket.action.prize_config'))
                    ->type('link')
                    ->size('small')
                    ->modal([$this, 'prizeConfig'], ['activity_id' => $data->id])
                    ->width('80%')
            );
        });

        // ⭐ 隐藏批量操作和创建按钮
        $grid->hideBatchActions();
        $grid->hideCreateButton();
    });
}
```

**关键点:**
1. `$actions->clear()` - 清空默认的编辑/删除按钮
2. `$actions->prepend()` - 只添加"查看奖品配置"按钮
3. `hideBatchActions()` - 隐藏批量删除等批量操作
4. `hideCreateButton()` - 隐藏"创建活动"按钮

---

### 2️⃣ prizeConfig() 方法 (奖品配置弹窗)

**只读 Grid 配置:**

```php
public function prizeConfig(int $activity_id): Grid
{
    // 验证活动是否属于当前代理
    $admin = Admin::user();
    $activity = LotteryTicketActivity::where('id', $activity_id)
        ->where('department_id', $admin->department_id)
        ->first();

    if (!$activity) {
        throw new \Exception(admin_trans('common.no_permission'));
    }

    return Grid::create(new LotteryTicketPrizeLevel(), function (Grid $grid) use ($activity) {
        $grid->model()->where('activity_id', $activity->id)
            ->orderBy('level_rank', 'asc');

        $grid->title(admin_trans('lottery_ticket.fields.prize_level_config'));
        $grid->bordered(true);
        $grid->autoHeight();

        // ⭐ 完全只读：隐藏所有操作
        $grid->hideCreateButton();  // 隐藏创建按钮
        $grid->hideActions();       // 隐藏操作列
        $grid->hideBatchActions();  // 隐藏批量操作

        // 列定义...
    });
}
```

**关键点:**
1. `hideCreateButton()` - 隐藏"添加奖品等级"按钮
2. `hideActions()` - 隐藏整个操作列（编辑/删除按钮）
3. `hideBatchActions()` - 隐藏批量操作
4. 数据权限验证：确保活动属于当前代理所在渠道

---

### 3️⃣ AgentLotteryTicketController (摸奖券列表)

**只读配置:**

```php
public function index(): Grid
{
    return Grid::create(new LotteryTicket(), function (Grid $grid) {
        // 数据过滤：只显示当前代理下玩家的摸奖券
        $admin = Admin::user();
        $grid->model()->whereExists(function ($query) use ($admin) {
            $query->selectRaw(1)
                ->from('player')
                ->whereColumn('player.id', 'lottery_ticket.player_id')
                ->where('player.agent_admin_id', $admin->id);
        });

        // ... 列定义 ...

        // ⭐ 只读模式：隐藏所有操作
        $grid->hideActions();
        $grid->hideBatchActions();
        $grid->hideCreateButton();
    });
}
```

---

### 4️⃣ AgentLotteryTicketRecordController (中奖记录列表)

**只读配置:**

```php
public function index(): Grid
{
    return Grid::create(new LotteryTicketRecord(), function (Grid $grid) {
        // 数据过滤：只显示当前代理下玩家的中奖记录
        $admin = Admin::user();
        $grid->model()->whereExists(function ($query) use ($admin) {
            $query->selectRaw(1)
                ->from('player')
                ->whereColumn('player.id', 'lottery_ticket_record.player_id')
                ->where('player.agent_admin_id', $admin->id);
        });

        // ... 列定义 ...

        // ⭐ 只读模式：隐藏所有操作
        $grid->hideActions();
        $grid->hideBatchActions();
        $grid->hideCreateButton();
    });
}
```

---

## 📊 UI 效果对比

### 渠道后台（完整权限）

**活动列表:**
```
┌─────────────────────────────────────────────────────────┐
│ 摸奖券活动列表                    [+ 创建活动]  [导出]   │
├─────────────────────────────────────────────────────────┤
│ ☑ │ ID │ 活动名称 │ ... │ 操作                         │
├─────────────────────────────────────────────────────────┤
│ ☐ │ 1  │ 春节活动 │ ... │ [编辑] [删除] [查看配置]     │
│ ☐ │ 2  │ 国庆活动 │ ... │ [编辑] [删除] [查看配置]     │
└─────────────────────────────────────────────────────────┘
│ [批量删除]  [批量导出]                                   │
└─────────────────────────────────────────────────────────┘
```

---

### 代理后台（只读模式）

**活动列表:**
```
┌─────────────────────────────────────────────────────────┐
│ 摸奖券活动列表                                           │
├─────────────────────────────────────────────────────────┤
│    │ ID │ 活动名称 │ ... │ 操作                         │
├─────────────────────────────────────────────────────────┤
│    │ 1  │ 春节活动 │ ... │ [查看奖品配置]               │
│    │ 2  │ 国庆活动 │ ... │ [查看奖品配置]               │
└─────────────────────────────────────────────────────────┘
```

**差异:**
- ❌ 没有选择框（批量操作）
- ❌ 没有"创建活动"按钮
- ❌ 没有"编辑"/"删除"按钮
- ✅ 只有"查看奖品配置"按钮

---

## 🔒 数据权限验证

### 活动列表权限

**数据范围验证:**
```php
// 只显示当前代理所属渠道的活动
$grid->model()->where('department_id', $departmentId);
```

**说明:**
- 活动是渠道级别的
- 同一渠道下的所有代理都能看到相同的活动列表
- 但不同渠道的代理看不到对方的活动

---

### 奖品配置权限

**访问权限验证:**
```php
public function prizeConfig(int $activity_id): Grid
{
    $admin = Admin::user();
    $activity = LotteryTicketActivity::where('id', $activity_id)
        ->where('department_id', $admin->department_id)
        ->first();

    if (!$activity) {
        throw new \Exception(admin_trans('common.no_permission'));
    }
    
    // ... 展示奖品配置
}
```

**防护措施:**
1. 验证活动是否属于当前代理的渠道
2. 不属于则抛出权限错误
3. 防止代理查看其他渠道的活动配置

---

### 摸奖券/中奖记录权限

**数据范围验证:**
```php
// 只显示当前代理下玩家的数据
$grid->model()->whereExists(function ($query) use ($admin) {
    $query->selectRaw(1)
        ->from('player')
        ->whereColumn('player.id', 'lottery_ticket.player_id')
        ->where('player.agent_admin_id', $admin->id);
});
```

**说明:**
- 代理只能看到自己下属玩家的摸奖券和中奖记录
- 同渠道其他代理的数据看不到
- 使用 `agent_admin_id` 字段精确过滤

---

## 🧪 测试场景

### 测试1: 尝试创建活动

**操作:** 访问代理后台 → 摸奖券管理 → 摸奖券活动

**预期结果:**
- ✅ 没有"创建活动"按钮
- ✅ 无法创建新活动
- ✅ 列表正常显示（查看功能正常）

---

### 测试2: 尝试编辑活动

**操作:** 在活动列表中查找编辑按钮

**预期结果:**
- ✅ 操作列中没有"编辑"按钮
- ✅ 只有"查看奖品配置"按钮
- ✅ 无法修改活动信息

---

### 测试3: 尝试删除活动

**操作:** 在活动列表中查找删除按钮

**预期结果:**
- ✅ 操作列中没有"删除"按钮
- ✅ 没有批量操作选择框
- ✅ 没有批量删除按钮

---

### 测试4: 查看奖品配置

**操作:** 点击"查看奖品配置"按钮

**预期结果:**
- ✅ 弹窗正常打开
- ✅ 显示奖品等级列表
- ✅ 没有"添加奖品等级"按钮
- ✅ 没有编辑/删除按钮
- ✅ 只读查看模式

---

### 测试5: 跨渠道访问

**数据准备:**
```sql
-- 渠道A
INSERT INTO channel (department_id, name) VALUES (1001, '渠道A');
INSERT INTO lottery_ticket_activity (id, department_id, name) VALUES (1, 1001, '渠道A活动');

-- 渠道B
INSERT INTO channel (department_id, name) VALUES (1002, '渠道B');
INSERT INTO lottery_ticket_activity (id, department_id, name) VALUES (2, 1002, '渠道B活动');

-- 代理A（属于渠道A）
INSERT INTO admin_users (id, department_id) VALUES (10, 1001);
```

**操作:** 代理A登录后访问活动列表

**预期结果:**
- ✅ 只能看到活动1（渠道A活动）
- ✅ 看不到活动2（渠道B活动）

**操作:** 代理A尝试直接访问活动2的奖品配置（通过URL）

**预期结果:**
- ✅ 返回权限错误："没有权限"
- ✅ 数据权限验证生效

---

## 📋 ExAdmin 只读模式 API 总结

### Grid 层级

| 方法 | 作用 | 使用场景 |
|------|------|---------|
| `hideCreateButton()` | 隐藏创建按钮 | 禁止新建记录 |
| `hideActions()` | 隐藏整个操作列 | 完全只读，无任何操作 |
| `hideBatchActions()` | 隐藏批量操作 | 禁止批量删除/导出 |
| `$actions->clear()` | 清空默认操作 | 自定义操作列，移除编辑/删除 |

---

### Actions 层级

| 方法 | 作用 | 使用场景 |
|------|------|---------|
| `$actions->clear()` | 清空所有操作按钮 | 移除默认的编辑/删除按钮 |
| `$actions->prepend()` | 在前面添加按钮 | 添加自定义只读操作（如查看） |
| `$actions->append()` | 在后面添加按钮 | 添加额外操作 |

---

### Form 层级（本项目未使用）

| 方法 | 作用 | 使用场景 |
|------|------|---------|
| `$form->disableCreating()` | 禁用创建功能 | Form 只读模式 |
| `$form->disableEditing()` | 禁用编辑功能 | Form 只读模式 |
| `$form->disableDeleting()` | 禁用删除功能 | Form 只读模式 |

---

## ⚠️ 注意事项

### 1️⃣ 前端 vs 后端权限

**双重验证:**
- ✅ 前端：隐藏按钮（用户体验）
- ✅ 后端：权限验证（安全保障）

**后端权限示例:**
```php
// 即使前端绕过，后端也会拒绝
public function save()
{
    $admin = Admin::user();
    
    // 代理后台不允许创建/编辑活动
    if ($admin->type == AdminDepartment::TYPE_AGENT) {
        return message_error(admin_trans('common.no_permission'));
    }
    
    // ... 正常保存逻辑
}
```

**说明:** 代理后台只有查看方法，没有实现 `save()` / `delete()` 等方法，从根本上阻止了修改操作。

---

### 2️⃣ 功能权限配置

**权限节点 (config/agent_node.php):**
```php
[
    'id' => 'AgentLotteryTicketActivityController-',
    'pid' => 0,
    'group' => 'agent',
    'title' => '摸奖券管理',
    'children' => [
        [
            'id' => 'AgentLotteryTicketActivityController\index',
            'action' => 'index',
            'method' => 'get',
            'title' => '摸奖券活动',
        ],
        [
            'id' => 'AgentLotteryTicketActivityController\prizeConfig',
            'action' => 'prizeConfig',
            'method' => 'get',
            'title' => '查看奖品配置',
        ],
        // ❌ 没有 save、delete 等权限节点
    ]
]
```

**说明:**
- 只配置了 `index` 和 `prizeConfig` 两个只读方法
- 没有 `save`、`delete` 等修改操作的权限节点
- 即使用户尝试访问这些方法，权限中间件也会拦截

---

### 3️⃣ 角色分离设计

**渠道后台 vs 代理后台:**

| 功能 | 渠道后台 | 代理后台 |
|------|---------|---------|
| 查看活动列表 | ✅ | ✅ |
| 创建活动 | ✅ | ❌ |
| 编辑活动 | ✅ | ❌ |
| 删除活动 | ✅ | ❌ |
| 查看奖品配置 | ✅ | ✅ |
| 编辑奖品配置 | ✅ | ❌ |
| 录入中奖记录 | ✅ | ❌ |
| 发放奖励 | ✅ | ❌ |
| 添加直播地址 | ✅ | ❌ |
| 关闭活动 | ✅ | ❌ |

**设计理念:**
- **渠道后台** = 管理者角色（完整权限）
- **代理后台** = 查看者角色（只读权限）

---

## ✅ 实现总结

**代理后台只读模式已完整实现:**

1. ✅ **活动列表** - 清空默认操作，只保留"查看奖品配置"
2. ✅ **奖品配置** - 完全只读，隐藏所有操作
3. ✅ **摸奖券列表** - 完全只读
4. ✅ **中奖记录列表** - 完全只读
5. ✅ **数据权限** - 代理只能查看所属渠道的活动，下属玩家的券/记录
6. ✅ **访问权限** - 后端验证，防止跨渠道访问

**关键代码:**
```php
// 清空默认操作
$actions->clear();

// 隐藏所有操作按钮
$grid->hideActions();
$grid->hideBatchActions();
$grid->hideCreateButton();
```

代理后台现在是完全的只读模式，符合业务需求！🎉
