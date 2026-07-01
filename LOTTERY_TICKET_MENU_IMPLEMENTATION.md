# 摸奖券菜单权限实施方案（简化版）

## 📋 实施方案说明

**设计理念：**
- ✅ **菜单层过滤**：在Menu::all()中通过channel.lottery_ticket_enabled字段过滤菜单
- ✅ **权限层检查**：通过channel_node.php配置 + Permission中间件检查访问权限
- ❌ **不需要**：额外的功能检查中间件（LotteryTicketFeatureCheck已删除）

**优势：**
- 简单高效，与现有系统风格一致
- 不需要额外的中间件
- 菜单和权限统一管理

---

## 🔧 实施步骤

### 步骤1：插入菜单记录到admin_menu表

**执行前检查：**
```sql
-- 查看最大排序值
SELECT MAX(sort) FROM admin_menu WHERE type = 2;

-- 查看渠道类型菜单
SELECT id, title, sort FROM admin_menu WHERE type = 2 ORDER BY sort DESC LIMIT 10;
```

**插入SQL脚本：**

```sql
-- ==========================================
-- 摸奖券菜单插入脚本（渠道后台）
-- ==========================================

-- 【1】父菜单：摸奖券管理
INSERT INTO admin_menu (
    pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at
)
VALUES (
    0,                                                          -- 顶级菜单
    2,                                                          -- 菜单类型
    '摸奖券管理',
    'gift',                                                     -- 礼物图标
    '',
    '',
    'ChannelLotteryTicketActivityController-',
    110,                                                        -- 排序值（请根据实际调整）
    1,
    NOW(), 
    NOW()
);

SET @lottery_parent_id = LAST_INSERT_ID();

-- 【2】子菜单：进行中的活动
INSERT INTO admin_menu (pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at)
VALUES (@lottery_parent_id, 2, '进行中的活动', '', '/lottery-ticket/dashboard', '', 'ChannelLotteryTicketActivityController\\dashboard', 1, 1, NOW(), NOW());

-- 【3】子菜单：历史活动记录
INSERT INTO admin_menu (pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at)
VALUES (@lottery_parent_id, 2, '历史活动记录', '', '/lottery-ticket/activity', '', 'ChannelLotteryTicketActivityController\\index', 2, 1, NOW(), NOW());

-- 【4】子菜单：中奖记录
INSERT INTO admin_menu (pid, type, title, icon, url, component, permission, sort, status, created_at, updated_at)
VALUES (@lottery_parent_id, 2, '中奖记录', '', '/lottery-ticket/records', '', 'ChannelLotteryTicketRecordController\\index', 3, 1, NOW(), NOW());

-- ==========================================
-- 查询插入结果（记录ID）
-- ==========================================

SELECT id, pid, title, permission, sort 
FROM admin_menu 
WHERE permission LIKE '%LotteryTicket%' 
ORDER BY id;

-- 预期输出：
-- +-----+-----+------------------+---------------------------------------------+------+
-- | id  | pid | title            | permission                                  | sort |
-- +-----+-----+------------------+---------------------------------------------+------+
-- | 190 |   0 | 摸奖券管理       | ChannelLotteryTicketActivityController-     |  110 |
-- | 191 | 190 | 进行中的活动     | ChannelLotteryTicketActivityController\...  |    1 |
-- | 192 | 190 | 历史活动记录     | ChannelLotteryTicketActivityController\...  |    2 |
-- | 193 | 190 | 中奖记录         | ChannelLotteryTicketRecordController\...    |    3 |
-- +-----+-----+------------------+---------------------------------------------+------+
```

**⚠️ 重要：记录这些菜单ID，用于步骤2的配置。**

---

### 步骤2：更新Menu.php中的菜单ID

**文件位置：** `D:\gk_admin\addons\webman\service\Menu.php`

**当前代码（第50-56行）：**

```php
->when(!empty($channel) && $channel->is_offline == 0, function ($query) {
    $query->whereNotIn('id', [176, 177, 178, 186, 187]);
})
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    // 摸奖券功能未开启，隐藏摸奖券菜单
    // ⚠️ 注意：这里的菜单ID需要在插入admin_menu记录后更新
    // 假设菜单ID为：190(父菜单), 191(进行中的活动), 192(历史活动), 193(中奖记录)
    $query->whereNotIn('id', [190, 191, 192, 193]);
})
->orderBy('sort')->get()->toArray();
```

**需要修改的内容：**

将`[190, 191, 192, 193]`替换为步骤1中实际获得的菜单ID。

**示例：如果实际ID为 [210, 211, 212, 213]，则修改为：**

```php
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    // 摸奖券功能未开启，隐藏摸奖券菜单（ID: 210, 211, 212, 213）
    $query->whereNotIn('id', [210, 211, 212, 213]);
})
```

---

### 步骤3：添加权限节点配置

**文件位置：** `D:\gk_admin\config\channel_node.php`

**在return数组的末尾添加以下配置：**

```php
// ==========================================
// 摸奖券管理权限节点
// ==========================================

// 父级节点
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'pid' => 0,
    'url' => '',
    'group' => 'channel',
    'title' => '摸奖券管理',
    'children' => []
],

// 进行中的活动 - 查看
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\dashboard',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'dashboard',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard',
    'title' => '进行中的活动',
],

// 活动列表 - 查看
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'index',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/index',
    'title' => '活动列表',
],

// 活动列表 - 删除
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index-delete',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'index',
    'method' => 'delete',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/index',
    'title' => '删除活动',
],

// 创建活动
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\save-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'save',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/save',
    'title' => '创建活动',
],

// 编辑活动
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\save-put',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'save',
    'method' => 'put',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/save',
    'title' => '编辑活动',
],

// 保存中奖等级
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\savePrizeLevel-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'savePrizeLevel',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/savePrizeLevel',
    'title' => '保存中奖等级',
],

// 删除中奖等级
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController\deletePrizeLevel-delete',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController\index',
    'action' => 'deletePrizeLevel',
    'method' => 'delete',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/deletePrizeLevel',
    'title' => '删除中奖等级',
],

// 中奖记录 - 列表
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
    'action' => 'index',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/index',
    'title' => '中奖记录',
],

// 中奖记录 - 详情
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\detail',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'detail',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/detail',
    'title' => '查看详情',
],

// 录入中奖号码
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\inputWinners-post',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'inputWinners',
    'method' => 'post',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/inputWinners',
    'title' => '录入中奖号码',
],

// 导出中奖记录
[
    'id' => 'addons\webman\controller\ChannelLotteryTicketRecordController\export',
    'pid' => 'addons\webman\controller\ChannelLotteryTicketRecordController\index',
    'action' => 'export',
    'method' => 'get',
    'group' => 'channel',
    'url' => 'ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/export',
    'title' => '导出记录',
],
```

---

### 步骤4：分配菜单权限到角色

**方式1：SQL直接插入（快速）**

```sql
-- 查询渠道角色ID
SELECT id, name FROM admin_role WHERE name LIKE '%渠道%' OR group = 'channel';
-- 假设返回：id = 17

-- 插入角色菜单关联（使用步骤1中的实际菜单ID）
INSERT INTO admin_role_menu (role_id, menu_id, created_at, updated_at)
VALUES 
(17, 190, NOW(), NOW()),  -- 父菜单
(17, 191, NOW(), NOW()),  -- 进行中的活动
(17, 192, NOW(), NOW()),  -- 历史活动记录
(17, 193, NOW(), NOW());  -- 中奖记录

-- 验证
SELECT r.name, m.title, m.id 
FROM admin_role_menu rm
JOIN admin_role r ON rm.role_id = r.id
JOIN admin_menu m ON rm.menu_id = m.id
WHERE m.permission LIKE '%LotteryTicket%';
```

**方式2：后台界面分配**

1. 登录后台
2. 权限管理 → 角色管理
3. 编辑"渠道管理员"角色
4. 勾选"摸奖券管理"及子菜单
5. 保存

---

### 步骤5：清除缓存并重启

```bash
cd D:\gk_admin
php start.php restart
```

---

## ✅ 测试验证

### 测试1：功能开启的渠道

```sql
-- 开启摸奖券功能
UPDATE channel SET lottery_ticket_enabled = 1 WHERE department_id = 1001;
```

**预期结果：**
- 登录该渠道管理员
- 左侧菜单显示"摸奖券管理"
- 可以看到三个子菜单

---

### 测试2：功能关闭的渠道

```sql
-- 关闭摸奖券功能
UPDATE channel SET lottery_ticket_enabled = 0 WHERE department_id = 1002;
```

**预期结果：**
- 登录该渠道管理员
- 左侧菜单**不显示**"摸奖券管理"
- 直接访问URL会返回405（无权限）

---

### 测试3：直接访问URL

**功能已开启 + 有权限：**
```
访问：/ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard
结果：正常显示页面（控制器存在的话）或404（控制器不存在）
```

**功能已开启 + 无权限：**
```
访问：/ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard
结果：返回405 "无权访问"
```

**功能未开启：**
```
访问：/ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/dashboard
结果：菜单不显示，但如果直接访问URL，会因为菜单权限（admin_role_menu）未分配而返回405
```

---

## 📊 权限检查流程

```
用户请求 → Permission中间件 → Admin::check()
    ↓
检查权限节点是否存在（channel_node.php）
    ↓
检查用户角色是否有该权限（admin_role_permission）
    ↓
是：放行 → 执行Controller
否：返回405

菜单显示 → Menu::all()
    ↓
检查角色菜单权限（admin_role_menu）
    ↓
检查渠道功能开关（channel.lottery_ticket_enabled）
    ↓
过滤菜单列表 → 返回给前端
```

**两层防护：**
1. **菜单层**：Menu::all()过滤，未开启功能的渠道看不到菜单
2. **权限层**：Permission中间件检查，无权限的用户无法访问

---

## 📝 配置总结

### 已完成的配置

✅ **Menu.php（第50-58行）：**
```php
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    $query->whereNotIn('id', [190, 191, 192, 193]);  // 需要替换为实际ID
})
```

✅ **channel.lottery_ticket_enabled字段：**
- 已通过迁移添加到channel表
- ChannelController已支持保存该字段
- gk_api已返回该配置给客户端

✅ **权限节点配置模板：**
- 已准备channel_node.php配置代码
- 包含11个权限节点（父节点 + 子节点）

---

### 待完成的工作

⏳ **步骤1：** 执行SQL插入菜单记录（获取实际菜单ID）

⏳ **步骤2：** 更新Menu.php中的菜单ID（替换占位ID）

⏳ **步骤3：** 添加权限节点到channel_node.php

⏳ **步骤4：** 分配菜单权限到渠道角色

⏳ **步骤5：** 重启服务清除缓存

⏳ **步骤6：** 测试验证

---

## 🎯 下一步行动

**最优先：执行步骤1**

1. 打开MySQL客户端连接到数据库
2. 复制步骤1的SQL脚本
3. 执行并记录返回的菜单ID
4. 将实际ID更新到Menu.php

**预计时间：** 总计30分钟
- 步骤1：5分钟（SQL执行）
- 步骤2：2分钟（修改代码）
- 步骤3：5分钟（添加配置）
- 步骤4：3分钟（分配权限）
- 步骤5：2分钟（重启服务）
- 步骤6：10分钟（测试验证）

---

**文档更新时间：** 2026-06-02 17:00:00
