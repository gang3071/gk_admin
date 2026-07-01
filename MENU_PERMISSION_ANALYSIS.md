# 菜单与权限检查系统分析

## 📊 系统架构分析

### 1. 双层权限控制系统

YJB Admin采用**菜单权限（Menu）+ 功能权限（Node）**的双层权限体系：

```
┌─────────────────────────────────────────────────────────┐
│                    权限检查流程                          │
└─────────────────────────────────────────────────────────┘

用户请求 /ex-admin/channel-lottery-ticket-activity/index
    │
    ├──【第1层：菜单过滤】
    │   ├─ Menu::all() 查询菜单表（admin_menu）
    │   ├─ 检查：admin_role_menu（角色菜单关联表）
    │   ├─ 检查：渠道功能开关（channel表字段）⭐ 新增
    │   └─ 返回：用户可见的菜单列表
    │
    ├──【第2层：权限节点检查】
    │   ├─ Permission中间件 → Admin::check()
    │   ├─ 读取：channel_node.php配置
    │   ├─ 检查：admin_role_permission（角色权限关联表）
    │   └─ 返回：是否有权限访问该controller/action
    │
    ├──【第3层：功能开关检查】⭐ 新增
    │   ├─ LotteryTicketFeatureCheck中间件
    │   ├─ 检查：channel.lottery_ticket_enabled
    │   └─ 返回：403 或继续处理
    │
    └──【执行】Controller方法执行
```

---

## 🔍 详细分析

### 1.1 菜单系统（Menu）

**文件位置：** `D:\gk_admin\addons\webman\service\Menu.php`

**数据表：** `admin_menu`

**核心方法：** `Menu::all()`

**菜单过滤逻辑：**

```php
public function all(): array
{
    $departmentId = Admin::user()->department_id;
    $channel = Channel::where('department_id', $departmentId)->first();
    
    return $this->model::where('status', 1)
        ->where('type', Admin::user()->type)  // 用户类型（渠道/代理/店家）
        
        // 【1】超级管理员跳过角色检查
        ->when(plugin()->webman->config('admin_auth_id') != Admin::id(), function ($query) {
            $model = plugin()->webman->config('database.role_menu_model');
            $menuIds = $model::whereIn('role_id', Admin::role())->pluck('menu_id');
            $query->whereIn('id', $menuIds);
        })
        
        // 【2】渠道功能开关过滤
        ->when(!empty($channel) && $channel->withdraw_status == 0, function ($query) {
            $query->where('id', '!=', 59);  // 隐藏提现菜单
        })
        ->when(!empty($channel) && $channel->promotion_status == 0, function ($query) {
            $query->whereNotIn('id', [74, 75, 76, 111, 73]);  // 隐藏推广菜单
        })
        ->when(!empty($channel) && $channel->coin_status == 0, function ($query) {
            $query->whereNotIn('id', [37, 38, 39, 40, 156]);  // 隐藏金币菜单
        })
        
        // 【3】线上/线下渠道菜单过滤
        ->when(!empty($channel) && $channel->is_offline == 1, function ($query) {
            $query->whereNotIn('id', [74, 75, 76, 111, 73]);  // 线下渠道隐藏推广
        })
        ->when(!empty($channel) && $channel->is_offline == 0, function ($query) {
            $query->whereNotIn('id', [176, 177, 178, 186, 187]);  // 线上渠道隐藏机台
        })
        
        ->orderBy('sort')->get()->toArray();
}
```

**关键特点：**
- ✅ 支持通过渠道字段（withdraw_status、promotion_status等）动态隐藏菜单
- ✅ 通过硬编码菜单ID进行过滤
- ❌ **问题：需要知道菜单ID**（摸奖券菜单ID尚未创建）

---

### 1.2 功能权限系统（Node/Permission）

**文件位置：** 
- `D:\gk_admin\addons\webman\middleware\Permission.php`
- `D:\gk_admin\addons\webman\Admin.php`
- `D:\gk_admin\config\channel_node.php`

**数据表：** `admin_permission`、`admin_role_permission`

**核心方法：** `Admin::check($class, $function, $method)`

**权限检查逻辑：**

```php
// Permission中间件
public function process(Request $request, callable $handler): Response
{
    list($class, $function) = Admin::getDispatch();
    $method = $request->input('_ajax', $request->method());
    
    if (!Admin::check($class, $function, $method)) {
        return response(json_encode([
            'message' => admin_trans('admin.not_access_permission')
        ]), 405);
    }
    
    return $handler($request);
}

// Admin::check()
public static function check($class, $function, $method)
{
    // 构建权限节点ID
    $actions[] = str_replace('-', '\\', $class) . '\\' . $function;
    $actions[] = str_replace('-', '\\', $class) . '\\' . $function . '-' . strtolower($method);
    
    // 从配置文件加载所有权限节点
    $allNodeIds = array_column(Admin::node()->all(), 'id');
    
    foreach ($actions as $action) {
        // 如果该节点存在于配置中
        if (in_array($action, $allNodeIds)) {
            // 超级管理员直接通过
            if (Admin::id() == plugin()->webman->config('admin_auth_id')) {
                return true;
            }
            // 检查用户是否有该权限
            if (!in_array($action, Admin::permission())) {
                return false;
            }
        }
    }
    
    // 节点不存在或有权限，放行
    return true;
}
```

**权限节点配置：** `config/channel_node.php`

```php
return [
    // 父级菜单（不需要权限检查）
    [
        'id' => 'addons\webman\controller\ChannelAdminController-',
        'pid' => 0,
        'url' => '',
        'group' => 'channel',
        'title' => '权限管理',
        'children' => []
    ],
    
    // 具体功能（需要权限检查）
    [
        'id' => 'addons\webman\controller\ChannelAdminController\index',
        'pid' => 'addons\webman\controller\ChannelAdminController-',
        'action' => 'index',
        'method' => 'get',
        'group' => 'channel',
        'url' => 'ex-admin/addons-webman-controller-ChannelAdminController/index',
        'title' => '系统用户列表',
    ],
];
```

**关键特点：**
- ✅ 基于配置文件（channel_node.php）
- ✅ 权限节点格式：`controller\function` 或 `controller\function-method`
- ✅ 超级管理员（ID=1）跳过所有检查
- ✅ 缓存权限数据（Redis）提升性能

---

### 1.3 中间件执行顺序

**配置文件：** `D:\gk_admin\addons\webman\config\core.php`

```php
'route' => [
    'prefix' => env('ADMIN_ROUTE_PREFIX', '/admin'),
    'middleware' => [
        AuthMiddleware::class,           // 第1步：身份认证
        LoadLangPack::class,             // 第2步：加载语言包
        Permission::class,               // 第3步：权限检查 ⭐
        IpAuthMiddleware::class,         // 第4步：IP白名单
        // LotteryTicketFeatureCheck::class,  // ❌ 不应该放在这里
    ],
],
```

**执行流程：**

```
请求 → AuthMiddleware（认证）→ LoadLangPack（语言）
    → Permission（权限）→ IpAuthMiddleware（IP）→ Controller
```

**问题：**
- ❌ 全局中间件会应用到所有路由
- ❌ LotteryTicketFeatureCheck不应该放在全局中间件
- ✅ 应该在特定控制器上应用

---

## ⚠️ 摸奖券功能集成的问题

### 问题1：菜单隐藏方式

**当前Menu::all()使用硬编码菜单ID：**

```php
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    $query->whereNotIn('id', [???]);  // ❌ 需要知道摸奖券菜单的ID
})
```

**问题：**
- ❌ 菜单ID尚未创建（admin_menu表中不存在）
- ❌ 需要先插入菜单记录才能知道ID
- ❌ 硬编码ID不够灵活

**解决方案（3种）：**

#### 方案A：硬编码菜单ID（与现有模式一致）✅ 推荐

**步骤：**
1. 在admin_menu表插入摸奖券相关菜单
2. 获取插入后的菜单ID
3. 在Menu::all()中添加过滤条件

**优点：**
- 与现有代码风格一致
- 性能好（直接WHERE IN过滤）

**缺点：**
- 需要手动维护ID
- 灵活性差

**实现代码：**

```php
// Menu.php
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    // 假设摸奖券菜单ID为：190, 191, 192
    $query->whereNotIn('id', [190, 191, 192]);
})
```

#### 方案B：通过菜单标识字段过滤

**步骤：**
1. 在admin_menu表添加feature_flag字段
2. 摸奖券菜单设置feature_flag='lottery_ticket'
3. 在Menu::all()中联表或子查询过滤

**优点：**
- 灵活性高
- 不需要硬编码ID

**缺点：**
- 需要修改表结构
- 性能略差（需要额外查询）

**实现代码：**

```php
// admin_menu表添加字段
ALTER TABLE admin_menu ADD COLUMN feature_flag VARCHAR(50) NULL COMMENT '功能标识';

// Menu.php
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    $query->where(function($q) {
        $q->whereNull('feature_flag')
          ->orWhere('feature_flag', '!=', 'lottery_ticket');
    });
})
```

#### 方案C：前端动态菜单（最灵活）

**步骤：**
1. Menu::all()返回所有菜单（不过滤）
2. 同时返回渠道功能配置
3. 前端根据配置动态显示/隐藏菜单

**优点：**
- 最灵活
- 易于扩展

**缺点：**
- 需要前端配合
- 可能泄露菜单信息（虽然无权限访问）

---

### 问题2：中间件应用方式

**❌ 错误做法：**

```php
// config/core.php
'route' => [
    'middleware' => [
        AuthMiddleware::class,
        LoadLangPack::class,
        Permission::class,
        IpAuthMiddleware::class,
        LotteryTicketFeatureCheck::class,  // ❌ 全局应用，所有路由都会检查
    ],
],
```

**✅ 正确做法：控制器级别应用**

ExAdmin支持通过注解应用中间件（如果支持），或者在控制器构造函数中应用：

**方法1：PHPDoc注解（需要ExAdmin支持）**

```php
/**
 * 摸奖券活动管理
 * @middleware addons\webman\middleware\LotteryTicketFeatureCheck
 * @auth true
 */
class ChannelLotteryTicketActivityController
{
    // ...
}
```

**方法2：构造函数中手动检查**

```php
class ChannelLotteryTicketActivityController
{
    public function __construct()
    {
        // 检查功能是否开启
        $admin = Admin::user();
        $channel = Channel::where('department_id', $admin->department_id)->first();
        
        if (!$channel || $channel->lottery_ticket_enabled != 1) {
            throw new \Exception(admin_trans('lottery_ticket.error.feature_not_enabled'));
        }
    }
}
```

**方法3：每个方法开头检查（最简单）**

```php
public function index(): Grid
{
    // 检查功能是否开启
    $this->checkFeatureEnabled();
    
    return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
        // ...
    });
}

private function checkFeatureEnabled()
{
    $admin = Admin::user();
    $channel = Channel::where('department_id', $admin->department_id)->first();
    
    if (!$channel || $channel->lottery_ticket_enabled != 1) {
        throw new \Exception(admin_trans('lottery_ticket.error.feature_not_enabled'));
    }
}
```

---

## ✅ 推荐实施方案

### 第1步：插入菜单记录到admin_menu表

**SQL脚本：**

```sql
-- 主菜单
INSERT INTO admin_menu (pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at)
VALUES (0, 2, '摸奖券管理', 'gift', '', '', 'ChannelLotteryTicketActivityController-', 100, 1, NOW(), NOW());

SET @parent_id = LAST_INSERT_ID();

-- 子菜单1：进行中的活动
INSERT INTO admin_menu (pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at)
VALUES (@parent_id, 2, '进行中的活动', '', '/lottery-ticket/dashboard', '', 'ChannelLotteryTicketActivityController\dashboard', 1, 1, NOW(), NOW());

-- 子菜单2：历史活动记录
INSERT INTO admin_menu (pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at)
VALUES (@parent_id, 2, '历史活动记录', '', '/lottery-ticket/activity', '', 'ChannelLotteryTicketActivityController\index', 2, 1, NOW(), NOW());

-- 子菜单3：中奖记录
INSERT INTO admin_menu (pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at)
VALUES (@parent_id, 2, '中奖记录', '', '/lottery-ticket/records', '', 'ChannelLotteryTicketRecordController\index', 3, 1, NOW(), NOW());

-- 记录菜单ID（用于后续过滤）
SELECT id, title FROM admin_menu WHERE permission LIKE '%LotteryTicket%' ORDER BY id;
```

**假设获得的ID：**
- 主菜单：190
- 进行中的活动：191
- 历史活动记录：192
- 中奖记录：193

---

### 第2步：修改Menu.php添加过滤逻辑

**文件：** `D:\gk_admin\addons\webman\service\Menu.php`

**在all()方法中添加：**

```php
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    // 隐藏摸奖券相关菜单（ID: 190-193）
    $query->whereNotIn('id', [190, 191, 192, 193]);
})
```

**完整示例：**

```php
public function all(): array
{
    $departmentId = Admin::user()->department_id;
    $channel = Channel::where('department_id', $departmentId)->first();
    
    return $this->model::where('status', 1)
        ->where('type', Admin::user()->type)
        ->when(plugin()->webman->config('admin_auth_id') != Admin::id(), function ($query) {
            $model = plugin()->webman->config('database.role_menu_model');
            $menuIds = $model::whereIn('role_id', Admin::role())->pluck('menu_id');
            $query->whereIn('id', $menuIds);
        })
        ->when(!empty($channel) && $channel->withdraw_status == 0, function ($query) {
            $query->where('id', '!=', 59);
        })
        ->when(!empty($channel) && $channel->promotion_status == 0, function ($query) {
            $query->whereNotIn('id', [74, 75, 76, 111, 73]);
        })
        ->when(!empty($channel) && $channel->coin_status == 0, function ($query) {
            $query->whereNotIn('id', [37, 38, 39, 40, 156]);
        })
        ->when(!empty($channel) && $channel->is_offline == 1, function ($query) {
            $query->whereNotIn('id', [74, 75, 76, 111, 73]);
        })
        ->when(!empty($channel) && $channel->is_offline == 0, function ($query) {
            $query->whereNotIn('id', [176, 177, 178, 186, 187]);
        })
        // ⭐ 新增：摸奖券功能开关
        ->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
            $query->whereNotIn('id', [190, 191, 192, 193]);  // 摸奖券菜单ID
        })
        ->orderBy('sort')->get()->toArray();
}
```

---

### 第3步：添加权限节点到channel_node.php

**文件：** `D:\gk_admin\config\channel_node.php`

**在文件末尾添加：**

```php
// 摸奖券管理
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'pid' => 0,
    'url' => '',
    'group' => 'channel',
    'title' => admin_trans('lottery_ticket.menu.main'),
    'children' => []
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\dashboard',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'dashboard',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard',
    'title' => admin_trans('lottery_ticket.menu.dashboard'),
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'index',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/index',
    'title' => admin_trans('lottery_ticket.menu.history'),
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\save-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'save',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/save',
    'title' => admin_trans('lottery_ticket.action.create'),
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\save-put',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'save',
    'method' => 'put',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/save',
    'title' => admin_trans('lottery_ticket.action.edit'),
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\savePrizeLevel-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'savePrizeLevel',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/savePrizeLevel',
    'title' => '保存中奖等级',
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\deletePrizeLevel-delete',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'deletePrizeLevel',
    'method' => 'delete',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/deletePrizeLevel',
    'title' => '删除中奖等级',
],

// 中奖记录管理
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'index',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/index',
    'title' => admin_trans('lottery_ticket.menu.records'),
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\detail',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'detail',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/detail',
    'title' => '查看中奖详情',
],
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\inputWinners-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'inputWinners',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/inputWinners',
    'title' => '录入中奖号码',
],
```

---

### 第4步：控制器中添加功能检查

**在控制器基类中添加通用方法：**

```php
<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;

/**
 * 摸奖券控制器基类
 */
abstract class BaseLotteryTicketController
{
    /**
     * 检查摸奖券功能是否开启
     * @throws \Exception
     */
    protected function checkFeatureEnabled()
    {
        $admin = Admin::user();
        
        if (!$admin) {
            throw new \Exception(admin_trans('admin.not_login'));
        }
        
        $channel = Channel::where('department_id', $admin->department_id)->first();
        
        if (!$channel || $channel->lottery_ticket_enabled != 1) {
            throw new \Exception(admin_trans('lottery_ticket.error.feature_not_enabled'));
        }
    }
}
```

**在具体控制器中继承并调用：**

```php
class ChannelLotteryTicketActivityController extends BaseLotteryTicketController
{
    public function index(): Grid
    {
        // 第一步：检查功能是否开启
        $this->checkFeatureEnabled();
        
        // 后续业务逻辑
        return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
            // ...
        });
    }
    
    public function save(): Form
    {
        $this->checkFeatureEnabled();
        
        return Form::create(new LotteryTicketActivity(), function (Form $form) {
            // ...
        });
    }
}
```

---

## 📝 总结

### 实施步骤清单

1. ✅ **已完成**
   - 创建LotteryTicketFeatureCheck中间件
   - 添加翻译：feature_not_enabled错误提示
   - 创建数据库迁移（5张表）
   - 创建模型类（4个）

2. ⏳ **待完成**
   - [ ] 插入菜单记录到admin_menu表（获取菜单ID）
   - [ ] 修改Menu.php添加菜单过滤逻辑
   - [ ] 添加权限节点到channel_node.php
   - [ ] 创建BaseLotteryTicketController基类
   - [ ] 创建具体控制器并继承基类
   - [ ] 测试：未开启功能的渠道是否看不到菜单
   - [ ] 测试：直接访问URL是否返回403

### 关键配置说明

**菜单过滤（Menu.php）：**
- 通过硬编码菜单ID实现
- 与现有模式一致（withdraw_status、promotion_status等）

**权限检查（channel_node.php）：**
- 配置文件定义权限节点
- Permission中间件自动检查

**功能开关（控制器）：**
- 每个方法调用checkFeatureEnabled()
- 或在构造函数中统一检查

### 三层防护

1. **菜单层**：未开启功能的渠道看不到菜单
2. **权限层**：没有权限的角色无法访问
3. **功能层**：直接访问URL返回403错误

---

**文档创建时间：** 2026-06-02 16:00:00
