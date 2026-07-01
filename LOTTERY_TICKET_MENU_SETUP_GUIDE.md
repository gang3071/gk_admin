# 摸奖券菜单与权限设置指南

## 📋 执行步骤

按照以下步骤完成摸奖券菜单和权限的配置。

---

## 第1步：插入菜单记录（5分钟）

### 1.1 查询admin_menu表结构

```sql
DESC admin_menu;
```

**预期字段：**
- id
- pid (父菜单ID)
- type (菜单类型：1=目录,2=菜单)
- title (菜单标题)
- icon (图标)
- url (前端路由)
- component (前端组件路径)
- permission (权限标识)
- sort (排序)
- status (状态：1=启用,0=禁用)
- created_at
- updated_at

---

### 1.2 插入摸奖券菜单

**⚠️ 重要：先检查最大排序值**

```sql
SELECT MAX(sort) FROM admin_menu WHERE type = 2;
-- 假设返回 100，则摸奖券菜单使用 110
```

**SQL脚本：**

```sql
-- ==========================================
-- 摸奖券菜单插入脚本
-- 执行前请确认：
-- 1. 当前数据库是否正确
-- 2. admin_menu表结构是否匹配
-- ==========================================

-- 【1】插入父级菜单：摸奖券管理
INSERT INTO admin_menu (
    pid, 
    type, 
    title, 
    icon, 
    url, 
    component, 
    permission, 
    sort, 
    status, 
    created_at, 
    updated_at
)
VALUES (
    0,                                                          -- pid: 顶级菜单
    2,                                                          -- type: 2=菜单
    '摸奖券管理',                                              -- title
    'gift',                                                    -- icon: 礼物图标
    '',                                                        -- url: 父菜单无URL
    '',                                                        -- component
    'ChannelLotteryTicketActivityController-',                 -- permission: 权限标识
    110,                                                       -- sort: 排序值（根据实际调整）
    1,                                                         -- status: 1=启用
    NOW(), 
    NOW()
);

-- 获取刚插入的父菜单ID
SET @lottery_parent_id = LAST_INSERT_ID();

-- 【2】插入子菜单1：进行中的活动
INSERT INTO admin_menu (
    pid, 
    type, 
    title, 
    icon, 
    url, 
    component, 
    permission, 
    sort, 
    status, 
    created_at, 
    updated_at
)
VALUES (
    @lottery_parent_id,                                        -- pid: 父菜单ID
    2,                                                         -- type: 2=菜单
    '进行中的活动',                                            -- title
    '',                                                        -- icon: 子菜单通常无图标
    '/lottery-ticket/dashboard',                               -- url: 前端路由
    '',                                                        -- component
    'ChannelLotteryTicketActivityController\\dashboard',       -- permission
    1,                                                         -- sort
    1,                                                         -- status
    NOW(), 
    NOW()
);

-- 【3】插入子菜单2：历史活动记录
INSERT INTO admin_menu (
    pid, 
    type, 
    title, 
    icon, 
    url, 
    component, 
    permission, 
    sort, 
    status, 
    created_at, 
    updated_at
)
VALUES (
    @lottery_parent_id,
    2,
    '历史活动记录',
    '',
    '/lottery-ticket/activity',
    '',
    'ChannelLotteryTicketActivityController\\index',
    2,
    1,
    NOW(), 
    NOW()
);

-- 【4】插入子菜单3：中奖记录
INSERT INTO admin_menu (
    pid, 
    type, 
    title, 
    icon, 
    url, 
    component, 
    permission, 
    sort, 
    status, 
    created_at, 
    updated_at
)
VALUES (
    @lottery_parent_id,
    2,
    '中奖记录',
    '',
    '/lottery-ticket/records',
    '',
    'ChannelLotteryTicketRecordController\\index',
    3,
    1,
    NOW(), 
    NOW()
);

-- ==========================================
-- 查询插入结果（记录ID用于后续配置）
-- ==========================================

SELECT 
    id AS menu_id,
    pid,
    title,
    permission,
    sort,
    status
FROM admin_menu 
WHERE permission LIKE '%LotteryTicket%' 
ORDER BY id;

-- 预期输出示例：
-- +----------+-----+------------------+---------------------------------------------+------+--------+
-- | menu_id  | pid | title            | permission                                  | sort | status |
-- +----------+-----+------------------+---------------------------------------------+------+--------+
-- |      190 |   0 | 摸奖券管理       | ChannelLotteryTicketActivityController-     |  110 |      1 |
-- |      191 | 190 | 进行中的活动     | ChannelLotteryTicketActivityController\... |    1 |      1 |
-- |      192 | 190 | 历史活动记录     | ChannelLotteryTicketActivityController\... |    2 |      1 |
-- |      193 | 190 | 中奖记录         | ChannelLotteryTicketRecordController\...   |    3 |      1 |
-- +----------+-----+------------------+---------------------------------------------+------+--------+

-- ⭐ 重要：记录这些ID，用于步骤2的Menu.php配置
```

---

### 1.3 验证插入结果

```sql
-- 检查菜单树结构
SELECT 
    CONCAT(REPEAT('  ', IF(pid = 0, 0, 1)), title) AS menu_tree,
    id,
    pid,
    permission,
    url
FROM admin_menu 
WHERE id IN (
    SELECT id FROM admin_menu WHERE permission LIKE '%LotteryTicket%'
    UNION
    SELECT pid FROM admin_menu WHERE permission LIKE '%LotteryTicket%'
)
ORDER BY pid, sort;

-- 检查状态
SELECT 
    COUNT(*) AS total_menus,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS enabled_menus,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS disabled_menus
FROM admin_menu 
WHERE permission LIKE '%LotteryTicket%';
```

---

## 第2步：修改Menu.php添加过滤逻辑（5分钟）

### 2.1 打开文件

```bash
D:\gk_admin\addons\webman\service\Menu.php
```

### 2.2 在all()方法中添加过滤条件

**找到这个位置（约第49行）：**

```php
->when(!empty($channel) && $channel->is_offline == 0, function ($query) {
    $query->whereNotIn('id', [176, 177, 178, 186, 187]);
})
```

**在它之后添加：**

```php
// ⭐ 摸奖券功能开关过滤
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    // ⚠️ 重要：将这里的ID替换为步骤1中获得的实际ID
    $query->whereNotIn('id', [190, 191, 192, 193]);  // 摸奖券菜单ID
})
```

**完整的添加位置示例：**

```php
public function all(): array
{
    $departmentId = Admin::user()->department_id;
    /** @var Channel $channel */
    if (Admin::user()->type == AdminDepartment::TYPE_CHANNEL) {
        $channel = Channel::where('department_id', $departmentId)->first();
    }
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
        // ⭐⭐⭐ 新增：摸奖券功能开关过滤 ⭐⭐⭐
        ->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
            // ⚠️ 替换为实际的菜单ID
            $query->whereNotIn('id', [190, 191, 192, 193]);
        })
        ->orderBy('sort')->get()->toArray();
}
```

---

## 第3步：添加权限节点配置（10分钟）

### 3.1 打开文件

```bash
D:\gk_admin\config\channel_node.php
```

### 3.2 在文件末尾添加权限配置

**找到文件最后一个配置项，在`return [...]`的最后一个`]`之前添加：**

```php
// ==========================================
// 摸奖券管理权限节点
// ==========================================

// 【父级节点】摸奖券管理
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'pid' => 0,
    'url' => '',
    'group' => 'channel',
    'title' => '摸奖券管理',
    'children' => []
],

// 【子节点】进行中的活动 - 列表查看
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\dashboard',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'dashboard',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard',
    'title' => '进行中的活动',
],

// 【子节点】历史活动记录 - 列表查看
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'index',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/index',
    'title' => '活动列表',
],

// 【子节点】历史活动记录 - 删除
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index-delete',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'index',
    'method' => 'delete',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/index',
    'title' => '删除活动',
],

// 【子节点】创建活动
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\save-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'save',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/save',
    'title' => '创建活动',
],

// 【子节点】编辑活动
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\save-put',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'save',
    'method' => 'put',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/save',
    'title' => '编辑活动',
],

// 【子节点】保存中奖等级
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\savePrizeLevel-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'savePrizeLevel',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/savePrizeLevel',
    'title' => '保存中奖等级',
],

// 【子节点】删除中奖等级
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\deletePrizeLevel-delete',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'deletePrizeLevel',
    'method' => 'delete',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/deletePrizeLevel',
    'title' => '删除中奖等级',
],

// 【子节点】中奖记录 - 列表查看
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'index',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/index',
    'title' => '中奖记录列表',
],

// 【子节点】中奖记录 - 查看详情
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\detail',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'detail',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/detail',
    'title' => '查看中奖详情',
],

// 【子节点】中奖记录 - 录入中奖号码
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\inputWinners-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'inputWinners',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/inputWinners',
    'title' => '录入中奖号码',
],

// 【子节点】中奖记录 - 导出
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\export',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'export',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/export',
    'title' => '导出中奖记录',
],
```

---

## 第4步：分配菜单权限到角色（5分钟）

### 4.1 方式1：通过后台界面（推荐）

1. 登录管理后台
2. 进入"权限管理" → "角色管理"
3. 编辑"渠道管理员"角色
4. 在菜单权限中勾选"摸奖券管理"及其子菜单
5. 保存

### 4.2 方式2：通过SQL直接插入（快速）

**查询渠道角色ID：**

```sql
SELECT id, name FROM admin_role WHERE name LIKE '%渠道%';
-- 假设返回：id = 17, name = 渠道管理员
```

**插入角色菜单关联：**

```sql
-- 假设渠道角色ID = 17
-- 假设菜单ID = 190, 191, 192, 193

INSERT INTO admin_role_menu (role_id, menu_id, created_at, updated_at)
VALUES 
(17, 190, NOW(), NOW()),  -- 父菜单
(17, 191, NOW(), NOW()),  -- 进行中的活动
(17, 192, NOW(), NOW()),  -- 历史活动记录
(17, 193, NOW(), NOW());  -- 中奖记录

-- 验证
SELECT 
    r.name AS role_name,
    m.title AS menu_title,
    m.id AS menu_id
FROM admin_role_menu rm
JOIN admin_role r ON rm.role_id = r.id
JOIN admin_menu m ON rm.menu_id = m.id
WHERE m.permission LIKE '%LotteryTicket%'
ORDER BY r.id, m.id;
```

---

## 第5步：清除权限缓存（重要！）

**Redis缓存键：**
```
ADMIN_PERMISSIONS_{admin_user_id}
data_perm:*
```

**清除方式：**

### 方式1：重启Webman（推荐）

```bash
cd D:\gk_admin
php start.php restart
```

### 方式2：手动清除Redis

```bash
redis-cli

# 查看所有权限缓存键
KEYS ADMIN_PERMISSIONS_*

# 删除所有权限缓存
DEL ADMIN_PERMISSIONS_1
DEL ADMIN_PERMISSIONS_2
# ... 或使用通配符删除（谨慎）
# EVAL "return redis.call('del', unpack(redis.call('keys', ARGV[1])))" 0 ADMIN_PERMISSIONS_*

# 退出
EXIT
```

---

## 第6步：测试验证（10分钟）

### 6.1 测试菜单显示

**测试场景1：已开启摸奖券功能的渠道**

```sql
-- 开启某个渠道的摸奖券功能
UPDATE channel SET lottery_ticket_enabled = 1 WHERE department_id = 1001;
```

**预期结果：**
- 登录该渠道管理员账号
- 左侧菜单应显示"摸奖券管理"
- 子菜单应显示"进行中的活动"、"历史活动记录"、"中奖记录"

---

**测试场景2：未开启摸奖券功能的渠道**

```sql
-- 关闭某个渠道的摸奖券功能
UPDATE channel SET lottery_ticket_enabled = 0 WHERE department_id = 1002;
```

**预期结果：**
- 登录该渠道管理员账号
- 左侧菜单**不应显示**"摸奖券管理"

---

### 6.2 测试权限检查

**测试场景3：直接访问URL（功能已开启）**

```
访问：http://localhost:8789/admin#!/ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard
```

**预期结果：**
- 如果该用户有权限：正常显示页面
- 如果该用户无权限：返回405错误"无权访问"

---

**测试场景4：直接访问URL（功能未开启）**

```sql
-- 关闭功能
UPDATE channel SET lottery_ticket_enabled = 0 WHERE department_id = 当前用户的department_id;
```

```
访问：http://localhost:8789/admin#!/ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard
```

**预期结果：**
- 返回403错误
- 错误信息："摸奖券功能未开启，请联系系统管理员..."

---

### 6.3 测试控制器功能检查

**创建测试控制器（临时）：**

```php
<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use support\Response;

class ChannelLotteryTicketActivityController
{
    /**
     * 测试方法：检查功能是否开启
     */
    public function dashboard(): Response
    {
        // 检查功能是否开启
        $admin = Admin::user();
        $channel = Channel::where('department_id', $admin->department_id)->first();
        
        if (!$channel || $channel->lottery_ticket_enabled != 1) {
            return response(json_encode([
                'code' => 403,
                'message' => admin_trans('lottery_ticket.error.feature_not_enabled')
            ]), 403);
        }
        
        // 功能已开启，返回测试数据
        return response(json_encode([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'feature_enabled' => true,
                'department_id' => $admin->department_id,
                'channel_name' => $channel->name ?? 'Unknown',
            ]
        ]));
    }
}
```

**访问测试：**
```
GET /ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard
```

**预期响应（功能已开启）：**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "feature_enabled": true,
    "department_id": 1001,
    "channel_name": "测试渠道"
  }
}
```

**预期响应（功能未开启）：**
```json
{
  "code": 403,
  "message": "摸奖券功能未开启，请联系系统管理员在渠道配置中开启此功能"
}
```

---

## 第7步：文档记录（5分钟）

### 7.1 记录菜单ID

在`LOTTERY_TICKET_IMPLEMENTATION_STATUS.md`中更新：

```markdown
### 菜单ID记录

**admin_menu表记录：**
- 父菜单ID：190 - 摸奖券管理
- 子菜单ID：191 - 进行中的活动
- 子菜单ID：192 - 历史活动记录
- 子菜单ID：193 - 中奖记录

**Menu.php过滤配置：**
```php
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    $query->whereNotIn('id', [190, 191, 192, 193]);
})
```
```

---

## ✅ 完成检查清单

- [ ] 步骤1：插入菜单记录到admin_menu表（记录ID）
- [ ] 步骤2：修改Menu.php添加菜单过滤逻辑
- [ ] 步骤3：添加权限节点到channel_node.php
- [ ] 步骤4：分配菜单权限到角色
- [ ] 步骤5：清除权限缓存/重启服务
- [ ] 步骤6：测试菜单显示（开启/未开启）
- [ ] 步骤6：测试权限检查（有权限/无权限）
- [ ] 步骤6：测试功能开关检查（403错误）
- [ ] 步骤7：文档记录菜单ID

---

## 🔧 故障排查

### 问题1：菜单不显示

**原因排查：**
1. 菜单状态是否启用（status = 1）
2. 角色是否分配了菜单权限（admin_role_menu）
3. 渠道是否开启功能（lottery_ticket_enabled = 1）
4. 权限缓存是否清除

**解决方法：**
```sql
-- 检查菜单状态
SELECT id, title, status FROM admin_menu WHERE permission LIKE '%LotteryTicket%';

-- 检查角色菜单关联
SELECT * FROM admin_role_menu WHERE menu_id IN (190, 191, 192, 193);

-- 检查渠道功能开关
SELECT department_id, name, lottery_ticket_enabled FROM channel;

-- 清除缓存后重启
php start.php restart
```

---

### 问题2：访问返回405无权限

**原因：**
- 权限节点配置错误
- 角色未分配功能权限（admin_role_permission）

**解决方法：**
```sql
-- 检查权限节点是否加载
-- 访问：/ex-admin/permission/nodes
-- 搜索：ChannelLotteryTicketActivityController

-- 检查用户权限
SELECT p.node_id 
FROM admin_user_role ur
JOIN admin_role_permission rp ON ur.role_id = rp.role_id
JOIN admin_permission p ON rp.permission_id = p.id
WHERE ur.admin_user_id = {当前用户ID}
  AND p.node_id LIKE '%LotteryTicket%';
```

---

### 问题3：访问返回403功能未开启

**原因：**
- 渠道的lottery_ticket_enabled = 0

**解决方法：**
```sql
-- 开启功能
UPDATE channel 
SET lottery_ticket_enabled = 1 
WHERE department_id = {当前用户的department_id};

-- 或通过后台界面：渠道管理 → 编辑渠道 → 勾选"摸奖券功能"
```

---

**文档创建时间：** 2026-06-02 16:30:00
