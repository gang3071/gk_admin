# VIP等级渠道隔离功能实现文档

**实现日期**: 2026-06-04  
**功能**: VIP等级渠道隔离、自动创建默认等级、菜单权限控制

---

## ✅ 已完成的功能

### 1. 数据库迁移

**文件**: `D:\gk_api\db\migrations\20260604100000_add_department_id_to_vip_level.php`

```sql
-- 为 vip_level 表添加 department_id 字段
ALTER TABLE `vip_level`
ADD COLUMN `department_id` INT(11) NOT NULL DEFAULT 0 COMMENT '所属渠道部门ID(0=全局)',
ADD INDEX `idx_department_id` (`department_id`);
```

**执行方式**:
```bash
cd D:\gk_api
php vendor/bin/phinx migrate
```

---

### 2. VipLevelService 服务类

**文件**: `D:\gk_admin\addons\webman\service\VipLevelService.php`

**核心功能**:

#### 2.1 创建默认VIP等级
```php
VipLevelService::createDefaultLevelsForChannel(int $departmentId)
```

**默认创建10个VIP等级**:

| 等级 | 升级打码量 | 保级打码量 | 保级天数 | 生日礼金 | 最小领取额 |
|------|-----------|-----------|---------|---------|----------|
| VIP0 | 1,000 | 0 | 30天 | ¥0 | ¥0 |
| VIP1 | 5,000 | 1,000 | 30天 | ¥50 | ¥10 |
| VIP2 | 20,000 | 5,000 | 30天 | ¥100 | ¥20 |
| VIP3 | 50,000 | 20,000 | 30天 | ¥200 | ¥50 |
| VIP4 | 100,000 | 50,000 | 30天 | ¥500 | ¥100 |
| VIP5 | 200,000 | 100,000 | 30天 | ¥1,000 | ¥200 |
| VIP6 | 500,000 | 200,000 | 30天 | ¥2,000 | ¥500 |
| VIP7 | 1,000,000 | 500,000 | 30天 | ¥5,000 | ¥1,000 |
| VIP8 | 2,000,000 | 1,000,000 | 30天 | ¥10,000 | ¥2,000 |
| VIP9 | - | 2,000,000 | 30天 | ¥20,000 | ¥5,000 |

**升级冷却期**: 所有等级（除VIP0）都有7天升级冷却期

#### 2.2 检查渠道是否有VIP等级
```php
VipLevelService::hasVipLevels(int $departmentId): bool
```

#### 2.3 获取VIP等级数量
```php
VipLevelService::getVipLevelCount(int $departmentId): int
```

#### 2.4 删除渠道所有VIP等级（谨慎使用）
```php
VipLevelService::deleteAllLevelsForChannel(int $departmentId)
```

---

### 3. 渠道控制器修改

**文件**: `D:\gk_admin\addons\webman\controller\ChannelController.php`

#### 3.1 新增渠道时自动创建VIP等级

```php
// 在saving钩子中，创建渠道后（事务提交前）
if ($channel->vip_level_status == 1) {
    $vipResult = VipLevelService::createDefaultLevelsForChannel($adminDepartment->id);
    // 创建失败不影响渠道创建，只记录日志
}
```

#### 3.2 编辑渠道时检查并创建VIP等级

```php
// 如果VIP等级状态从禁用改为启用，且该渠道下没有VIP等级
if ($oldVipLevelStatus == 0 && $channel->vip_level_status == 1) {
    if (!VipLevelService::hasVipLevels($channel->department_id)) {
        VipLevelService::createDefaultLevelsForChannel($channel->department_id);
    }
}
```

---

### 4. VIP等级模型修改

**文件**: `D:\gk_admin\addons\webman\model\VipLevel.php`

**变更**:
1. ✅ 添加 `department_id` 属性注释
2. ✅ 添加 `department_id` 到 `$fillable` 数组

```php
protected $fillable = [
    'department_id',  // 新增
    'name',
    'upgrade_limit_days',
    // ...
];
```

---

### 5. VIP等级控制器修改

**文件**: `D:\gk_admin\addons\webman\controller\VipLevelController.php`

**变更**:

#### 5.1 列表数据隔离
```php
public function index(): Grid
{
    // 只显示当前渠道的VIP等级
    $departmentId = Admin::user()->department_id;
    $grid->model()
        ->where('department_id', $departmentId)
        ->orderBy('sort', 'asc');
}
```

#### 5.2 表单自动设置渠道ID
```php
public function form(): Form
{
    // 隐藏字段：自动设置当前渠道department_id
    $form->hidden('department_id')->default(Admin::user()->department_id);
}
```

---

### 6. 菜单权限控制

#### 6.1 MenuConstant 常量类

**文件**: `D:\gk_admin\addons\webman\constant\MenuConstant.php`

**新增常量**:
```php
const VIP_LEVEL_MENUS = [
    'vip_level_manage',       // VIP等级管理（父菜单）
    'vip_level_list',         // VIP等级列表
    'vip_level_cashback',     // VIP返水配置
];
```

**控制字段**: `channel.vip_level_status`
- 0: 禁用VIP等级功能 → 隐藏菜单
- 1: 启用VIP等级功能 → 显示菜单

#### 6.2 Menu 服务类

**文件**: `D:\gk_admin\addons\webman\service\Menu.php`

**新增过滤逻辑**:
```php
// VIP等级功能开关
->when(!empty($channel) && $channel->vip_level_status == 0, function ($query) {
    $query->whereNotIn('name', MenuConstant::VIP_LEVEL_MENUS);
})
```

---

## 📋 部署步骤

### Step 1: 执行数据库迁移

```bash
cd D:\gk_api
php vendor/bin/phinx migrate
```

**预期输出**:
```
 == 20260604100000 AddDepartmentIdToVipLevel: migrating
 == 20260604100000 AddDepartmentIdToVipLevel: migrated 0.1234s
```

---

### Step 2: 重启服务

**Windows**:
```bash
cd D:\gk_admin
php windows.php stop
php windows.php start
```

**Linux**:
```bash
php start.php restart
```

---

### Step 3: 测试功能

#### 3.1 新建渠道测试

1. 登录后台 → 渠道管理 → 创建渠道
2. 开启"会员等级功能"开关
3. 保存渠道
4. 检查日志：应该看到 "渠道创建成功，已自动创建 10 个VIP等级"
5. 进入 VIP等级管理 → 应该看到 VIP0 ~ VIP9 共10个等级

#### 3.2 编辑渠道测试

**测试场景1**: 禁用 → 启用（有等级）
1. 编辑已有VIP等级的渠道
2. 关闭"会员等级功能"
3. 保存 → VIP等级菜单消失
4. 再次编辑，开启"会员等级功能"
5. 保存 → VIP等级菜单恢复（不会重复创建）

**测试场景2**: 禁用 → 启用（无等级）
1. 编辑没有VIP等级的渠道
2. 开启"会员等级功能"
3. 保存 → 自动创建10个默认VIP等级

#### 3.3 数据隔离测试

1. 创建2个渠道A和B，都开启VIP功能
2. 用渠道A管理员登录 → VIP等级管理 → 只看到渠道A的10个等级
3. 用渠道B管理员登录 → VIP等级管理 → 只看到渠道B的10个等级
4. 渠道A创建新等级VIP10 → 渠道B看不到

#### 3.4 菜单权限测试

1. 渠道管理 → 编辑渠道 → 关闭"会员等级功能"
2. 刷新页面 → 左侧菜单中"VIP等级管理"消失
3. 再次开启"会员等级功能"
4. 刷新页面 → 左侧菜单中"VIP等级管理"恢复

---

## 🔍 验证SQL

### 检查department_id字段是否添加成功

```sql
SHOW CREATE TABLE vip_level;
```

**预期包含**:
```sql
`department_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属渠道部门ID(0=全局)',
KEY `idx_department_id` (`department_id`)
```

### 查看渠道的VIP等级

```sql
-- 查看渠道ID为34的VIP等级
SELECT id, department_id, name, sort, upgrade_bet_amount, birthday_bonus, status
FROM vip_level
WHERE department_id = 34
ORDER BY sort;
```

### 查看所有渠道的VIP等级分布

```sql
SELECT 
    c.department_id,
    c.name AS channel_name,
    c.vip_level_status,
    COUNT(v.id) AS vip_level_count
FROM channel c
LEFT JOIN vip_level v ON c.department_id = v.department_id
GROUP BY c.department_id, c.name, c.vip_level_status
ORDER BY c.department_id;
```

---

## 📊 数据示例

### 默认创建的VIP等级数据

```sql
SELECT * FROM vip_level WHERE department_id = 34 ORDER BY sort;
```

**预期输出**:

| id | department_id | name | sort | upgrade_bet_amount | retain_level_bet_amount | birthday_bonus | status |
|----|--------------|------|------|-------------------|------------------------|---------------|--------|
| 1 | 34 | VIP0 | 0 | 1000.00 | 0.00 | 0.00 | 1 |
| 2 | 34 | VIP1 | 1 | 5000.00 | 1000.00 | 50.00 | 1 |
| 3 | 34 | VIP2 | 2 | 20000.00 | 5000.00 | 100.00 | 1 |
| 4 | 34 | VIP3 | 3 | 50000.00 | 20000.00 | 200.00 | 1 |
| 5 | 34 | VIP4 | 4 | 100000.00 | 50000.00 | 500.00 | 1 |
| 6 | 34 | VIP5 | 5 | 200000.00 | 100000.00 | 1000.00 | 1 |
| 7 | 34 | VIP6 | 6 | 500000.00 | 200000.00 | 2000.00 | 1 |
| 8 | 34 | VIP7 | 7 | 1000000.00 | 500000.00 | 5000.00 | 1 |
| 9 | 34 | VIP8 | 8 | 2000000.00 | 1000000.00 | 10000.00 | 1 |
| 10 | 34 | VIP9 | 9 | 0.00 | 2000000.00 | 20000.00 | 1 |

---

## 🚨 故障排查

### 问题1: 迁移执行失败

**症状**: `php vendor/bin/phinx migrate` 报错

**排查**:
```sql
-- 检查字段是否已存在
SELECT * FROM information_schema.COLUMNS
WHERE TABLE_NAME = 'vip_level'
AND COLUMN_NAME = 'department_id';
```

**解决**: 如果字段已存在，跳过此迁移

---

### 问题2: 创建渠道后没有自动创建VIP等级

**排查步骤**:

1. **检查渠道配置**:
```sql
SELECT department_id, name, vip_level_status FROM channel WHERE department_id = {id};
```
确认 `vip_level_status = 1`

2. **检查日志**:
```bash
tail -f runtime/logs/webman.log | grep -i "vip"
```
查找 "渠道创建成功，已自动创建" 或错误信息

3. **手动执行创建逻辑**:
```php
// 在任意控制器中临时测试
use addons\webman\service\VipLevelService;

$result = VipLevelService::createDefaultLevelsForChannel(34);
var_dump($result);
```

---

### 问题3: VIP等级菜单不显示

**排查步骤**:

1. **检查渠道VIP功能是否开启**:
```sql
SELECT vip_level_status FROM channel WHERE department_id = {user_department_id};
```

2. **检查菜单常量配置**:
```php
// 在 MenuConstant.php 中确认
const VIP_LEVEL_MENUS = [
    'vip_level_manage',
    'vip_level_list',
    'vip_level_cashback',
];
```

3. **检查菜单name是否匹配**:
```sql
SELECT id, name, title FROM admin_menu WHERE name LIKE 'vip_level%';
```

4. **清除缓存**:
```bash
# 清除权限缓存
redis-cli
> DEL ADMIN_PERMISSIONS_*
> exit
```

5. **重启服务**:
```bash
php start.php restart
```

---

### 问题4: 看到其他渠道的VIP等级

**症状**: 渠道A管理员能看到渠道B的VIP等级

**排查**:

1. **检查查询条件**:
打开 `VipLevelController::index()`，确认有：
```php
$grid->model()->where('department_id', $departmentId)
```

2. **检查用户department_id**:
```sql
SELECT id, username, department_id, type FROM admin_users WHERE id = {current_user_id};
```

3. **检查Admin::user()返回**:
```php
// 在控制器中临时调试
dd([
    'user_id' => Admin::user()->id,
    'department_id' => Admin::user()->department_id,
    'type' => Admin::user()->type,
]);
```

---

## 📝 后续优化建议

### 1. VIP等级菜单创建

当前菜单常量中定义了以下菜单名称，需要在数据库中创建对应菜单记录：

```sql
INSERT INTO admin_menu (name, pid, title, icon, url, type, sort, status) VALUES
('vip_level_manage', 0, 'VIP等级管理', 'CrownOutlined', '', 2, 100, 1),
('vip_level_list', {vip_level_manage_id}, 'VIP等级列表', '', 'ex-admin/vip-level/index', 2, 1, 1),
('vip_level_cashback', {vip_level_manage_id}, 'VIP返水配置', '', 'ex-admin/vip-level/cashback', 2, 2, 1);
```

**或创建迁移文件** (推荐):
```bash
# 在 D:\gk_api\db\migrations\
php vendor/bin/phinx create AddVipLevelMenus
```

### 2. 权限节点配置

在 `config/channel_node.php` 中添加VIP等级权限节点：

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
            'title' => 'VIP等级列表',
        ],
        [
            'id' => 'addons\webman\controller\VipLevelController\form',
            'action' => 'form',
            'method' => 'post',
            'title' => '创建VIP等级',
        ],
        [
            'id' => 'addons\webman\controller\VipLevelController\form',
            'action' => 'form',
            'method' => 'put',
            'title' => '编辑VIP等级',
        ],
        [
            'id' => 'addons\webman\controller\VipLevelController\cashback',
            'action' => 'cashback',
            'method' => 'get',
            'title' => 'VIP返水配置',
        ],
    ]
]
```

### 3. 翻译文件补充

确保 `addons/webman/lang/*/channel.php` 包含以下翻译键：

```php
// zh-TW/channel.php
'fields' => [
    'vip_level_status' => 'VIP等级功能',
],
'help' => [
    'vip_level_status' => '开启后将为该渠道自动创建10个默认VIP等级',
],
```

### 4. 默认等级配置调整

如需修改默认VIP等级配置（打码量、礼金等），请编辑：
`D:\gk_admin\addons\webman\service\VipLevelService.php`

修改 `DEFAULT_VIP_LEVELS` 常量数组。

---

## ✅ 功能清单

- [x] 数据库迁移文件创建
- [x] VipLevelService 服务类实现
- [x] 默认10个VIP等级配置
- [x] 渠道创建时自动创建VIP等级
- [x] 渠道编辑时检查并创建VIP等级
- [x] VipLevel模型添加department_id支持
- [x] VipLevelController 数据隔离
- [x] MenuConstant 菜单常量定义
- [x] Menu服务菜单过滤逻辑
- [x] 日志记录（成功/失败）
- [ ] VIP等级菜单数据库记录创建（需手动或迁移）
- [ ] 权限节点配置（需添加到config）
- [ ] 翻译文件补充（需确认）

---

**文档版本**: v1.0  
**最后更新**: 2026-06-04  
**实现人**: Claude Code
