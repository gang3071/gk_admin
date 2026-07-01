# VIP等级菜单迁移指南

**迁移时间**: 2026-06-04  
**目的**: 将VIP等级菜单从主站后台移到渠道后台

---

## 📋 迁移内容

### 删除（主站后台 type=1）

- ❌ 删除所有主站后台的VIP等级相关菜单
  - 菜单名包含 `vip_level`
  - 标题包含 `VIP等级` 或 `会员等级`
  - URL包含 `vip-level`

### 创建（渠道后台 type=2）

- ✅ **父菜单**: VIP等级管理
  - name: `vip_level_manage`
  - icon: `CrownOutlined`
  - type: `2` (渠道后台)

- ✅ **子菜单1**: VIP等级列表
  - name: `vip_level_list`
  - url: `ex-admin/vip-level/index`
  
- ✅ **子菜单2**: VIP返水配置
  - name: `vip_level_cashback`
  - url: `ex-admin/vip-level/cashback`

---

## 🚀 执行步骤

### Step 1: 执行迁移

```bash
cd D:\gk_api
php vendor/bin/phinx migrate
```

**预期输出**:
```
 == 20260604110000 MoveVipLevelMenuToChannel: migrating
========================================
VIP等级菜单迁移完成
========================================
✓ 已删除主站后台（type=1）的VIP等级菜单
✓ 已在渠道后台（type=2）创建VIP等级菜单
  - 父菜单ID: 123
  - 排序值: 105

后续操作：
1. 登录渠道管理员账号
2. 进入角色管理 → 编辑渠道管理员角色
3. 勾选 VIP等级管理 相关权限
4. 保存并刷新页面
========================================

 == 20260604110000 MoveVipLevelMenuToChannel: migrated 0.1234s
```

---

### Step 2: 配置角色权限

#### 方式1: 后台操作（推荐）

1. 登录主站后台管理员账号
2. 进入：系统管理 → 角色管理
3. 找到"渠道管理员"角色（或其他需要VIP权限的角色）
4. 点击编辑
5. 在权限树中勾选：
   ```
   ✅ VIP等级管理
     ✅ VIP等级列表
     ✅ VIP返水配置
   ```
6. 保存

#### 方式2: SQL直接插入

```sql
-- 获取渠道管理员角色ID
SELECT id, name FROM admin_role WHERE name LIKE '%渠道%';

-- 假设渠道管理员角色ID为 17（根据实际情况调整）
SET @role_id = 17;

-- 获取VIP等级菜单ID
SET @vip_manage_id = (SELECT id FROM admin_menu WHERE name = 'vip_level_manage' AND type = 2);
SET @vip_list_id = (SELECT id FROM admin_menu WHERE name = 'vip_level_list' AND type = 2);
SET @vip_cashback_id = (SELECT id FROM admin_menu WHERE name = 'vip_level_cashback' AND type = 2);

-- 插入角色菜单关联
INSERT IGNORE INTO admin_role_menu (role_id, menu_id, created_at, updated_at) VALUES
(@role_id, @vip_manage_id, NOW(), NOW()),
(@role_id, @vip_list_id, NOW(), NOW()),
(@role_id, @vip_cashback_id, NOW(), NOW());

-- 验证
SELECT 
    r.name AS role_name,
    m.title AS menu_title,
    m.name AS menu_name
FROM admin_role_menu rm
LEFT JOIN admin_role r ON rm.role_id = r.id
LEFT JOIN admin_menu m ON rm.menu_id = m.id
WHERE r.id = @role_id AND m.name LIKE 'vip_level%';
```

---

### Step 3: 重启服务

```bash
cd D:\gk_admin
php windows.php stop
php windows.php start
```

---

### Step 4: 验证功能

#### 4.1 主站后台验证

1. 用主站管理员登录
2. 检查左侧菜单 → **应该看不到** "VIP等级管理"
3. 访问 `/ex-admin/vip-level/index` → **应该没有权限或404**

#### 4.2 渠道后台验证

1. 用渠道管理员登录
2. 检查渠道是否开启VIP功能：
   ```sql
   SELECT department_id, name, vip_level_status 
   FROM channel 
   WHERE department_id = {your_department_id};
   ```
   如果 `vip_level_status = 0`，需要先开启

3. 开启VIP功能后，刷新页面
4. 左侧菜单应该显示：
   ```
   📊 VIP等级管理
     └─ VIP等级列表
     └─ VIP返水配置
   ```

5. 点击"VIP等级列表" → 应该看到10个默认VIP等级（VIP0~VIP9）

---

## 🔍 验证SQL

### 检查菜单创建情况

```sql
-- 查看VIP等级菜单（渠道后台）
SELECT 
    id,
    name,
    pid,
    title,
    url,
    type,
    sort,
    status
FROM admin_menu
WHERE type = 2
AND (
    name = 'vip_level_manage'
    OR name = 'vip_level_list'
    OR name = 'vip_level_cashback'
)
ORDER BY pid, sort;
```

**预期输出**:

| id | name | pid | title | url | type | sort | status |
|----|------|-----|-------|-----|------|------|--------|
| 123 | vip_level_manage | 0 | VIP等级管理 | | 2 | 105 | 1 |
| 124 | vip_level_list | 123 | VIP等级列表 | ex-admin/vip-level/index | 2 | 1 | 1 |
| 125 | vip_level_cashback | 123 | VIP返水配置 | ex-admin/vip-level/cashback | 2 | 2 | 1 |

---

### 检查主站是否已删除

```sql
-- 查看主站后台（type=1）是否还有VIP等级菜单
SELECT 
    id,
    name,
    title,
    url,
    type
FROM admin_menu
WHERE type = 1
AND (
    name LIKE '%vip%level%'
    OR title LIKE '%VIP%等级%'
    OR title LIKE '%会员等级%'
    OR url LIKE '%vip-level%'
);
```

**预期输出**: 空（0行）

---

### 检查角色权限配置

```sql
-- 查看哪些角色有VIP等级权限
SELECT 
    r.id AS role_id,
    r.name AS role_name,
    r.type AS role_type,
    GROUP_CONCAT(m.title SEPARATOR ', ') AS vip_menus
FROM admin_role r
LEFT JOIN admin_role_menu rm ON r.id = rm.role_id
LEFT JOIN admin_menu m ON rm.menu_id = m.id
WHERE m.name IN ('vip_level_manage', 'vip_level_list', 'vip_level_cashback')
GROUP BY r.id, r.name, r.type;
```

---

## 🚨 故障排查

### 问题1: 迁移执行失败

**症状**: `php vendor/bin/phinx migrate` 报错

**可能原因**:
- 菜单表结构不同
- 已有冲突的菜单记录

**解决方案**:

1. **检查admin_menu表结构**:
```sql
SHOW CREATE TABLE admin_menu;
```

2. **检查是否有冲突菜单**:
```sql
SELECT * FROM admin_menu 
WHERE name IN ('vip_level_manage', 'vip_level_list', 'vip_level_cashback');
```

3. **手动清理冲突**:
```sql
DELETE FROM admin_menu 
WHERE name IN ('vip_level_manage', 'vip_level_list', 'vip_level_cashback');
```

4. **重新执行迁移**

---

### 问题2: 渠道后台看不到VIP菜单

**排查步骤**:

1. **检查菜单是否创建成功**:
```sql
SELECT * FROM admin_menu 
WHERE type = 2 AND name = 'vip_level_manage';
```
如果没有记录，说明迁移未成功执行

2. **检查VIP功能是否开启**:
```sql
SELECT vip_level_status FROM channel 
WHERE department_id = {your_department_id};
```
如果 `vip_level_status = 0`，需要开启

3. **检查角色菜单权限**:
```sql
-- 查看当前用户的角色
SELECT r.* FROM admin_role r
LEFT JOIN admin_role_users ru ON r.id = ru.role_id
WHERE ru.user_id = {your_user_id};

-- 查看角色的VIP菜单权限
SELECT m.* FROM admin_menu m
LEFT JOIN admin_role_menu rm ON m.id = rm.menu_id
WHERE rm.role_id = {your_role_id}
AND m.name LIKE 'vip_level%';
```

4. **清除权限缓存**:
```bash
redis-cli
> DEL ADMIN_PERMISSIONS_*
> exit
```

5. **重启服务**:
```bash
php start.php restart
```

---

### 问题3: 点击菜单报404或无权限

**可能原因**:
- 路由未注册
- 控制器不存在
- 权限配置缺失

**解决方案**:

1. **检查控制器是否存在**:
```bash
ls -la addons/webman/controller/VipLevelController.php
```

2. **检查权限节点配置**:

需要在 `config/channel_node.php` 中添加：
```php
[
    'id' => 'addons\webman\controller\VipLevelController-',
    'pid' => 0,
    'title' => 'VIP等级管理',
    'children' => [
        [
            'id' => 'addons\webman\controller\VipLevelController\index',
            'action' => 'index',
            'method' => 'get',
            'group' => 'channel',
            'title' => 'VIP等级列表',
        ],
        [
            'id' => 'addons\webman\controller\VipLevelController\cashback',
            'action' => 'cashback',
            'method' => 'get',
            'group' => 'channel',
            'title' => 'VIP返水配置',
        ],
    ]
]
```

3. **重启服务使权限配置生效**

---

## 📊 菜单结构对比

### 迁移前

```
主站后台 (type=1)
├── VIP等级管理
│   ├── VIP等级列表
│   └── VIP返水配置

渠道后台 (type=2)
（无VIP菜单）
```

### 迁移后

```
主站后台 (type=1)
（无VIP菜单）

渠道后台 (type=2)
├── VIP等级管理 ✨
│   ├── VIP等级列表 ✨
│   └── VIP返水配置 ✨
```

---

## 🔄 回滚方案

如果需要回滚迁移：

```bash
cd D:\gk_api
php vendor/bin/phinx rollback -t 20260604110000
```

**注意**: 回滚只会删除渠道后台的VIP菜单，主站后台的菜单需要手动恢复！

---

## ✅ 验收清单

- [ ] 迁移执行成功（无报错）
- [ ] 主站后台看不到VIP等级菜单
- [ ] 渠道后台能看到VIP等级管理菜单（VIP功能开启时）
- [ ] 点击VIP等级列表可以正常访问
- [ ] VIP等级列表显示当前渠道的等级（数据隔离正确）
- [ ] 可以创建/编辑VIP等级
- [ ] 可以配置VIP返水比例
- [ ] 关闭渠道VIP功能后，菜单消失
- [ ] 开启渠道VIP功能后，菜单恢复

---

## 📝 相关文档

- **实现文档**: `VIP_LEVEL_CHANNEL_ISOLATION_IMPLEMENTATION.md`
- **评审文档**: `VIP_LEVEL_SYSTEM_REVIEW.md`
- **迁移文件**: `D:\gk_api\db\migrations\20260604110000_move_vip_level_menu_to_channel.php`

---

**文档版本**: v1.0  
**创建时间**: 2026-06-04  
**更新时间**: 2026-06-04
