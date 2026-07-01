# 代理后台摸奖券功能 - 功能开关控制

## 🎯 需求

**问题:** 没有开启摸奖券功能的渠道，下属代理无法看到摸奖券菜单

**解决方案:** 通过渠道的 `lottery_ticket_enabled` 字段控制代理后台的摸奖券菜单显示

---

## ✅ 实现方案

### 1️⃣ **菜单控制常量更新**

**文件:** `addons/webman/constant/MenuConstant.php`

**修改:**
```php
/**
 * 摸奖券功能菜单
 * 控制字段：channel.lottery_ticket_enabled
 */
const LOTTERY_TICKET_MENUS = [
    // 渠道后台
    'lottery_ticket_manage',                // 摸奖券管理（父菜单）
    'lottery_ticket_dashboard',             // 进行中的活动
    'lottery_ticket_history',               // 历史活动记录
    'lottery_ticket_records',               // 中奖记录
    
    // ⭐ 新增：代理后台
    'agent_lottery_ticket_management',      // 摸奖券管理（父菜单）
    'agent_lottery_ticket_activity_list',   // 摸奖券活动
    'agent_lottery_ticket_list',            // 摸奖券列表
    'agent_lottery_ticket_record_list',     // 中奖记录
];
```

---

### 2️⃣ **菜单服务逻辑更新**

**文件:** `addons/webman/service/Menu.php`

**修改前 (代理用户不检查渠道功能开关):**
```php
public function all(): array
{
    $channel = null;
    if (Admin::user()->type == AdminDepartment::TYPE_CHANNEL) {
        // ❌ 只有渠道用户才获取渠道信息
        $channel = Channel::where('department_id', $departmentId)->first();
    }
    
    // 功能开关检查
    ->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
        $query->whereNotIn('name', MenuConstant::LOTTERY_TICKET_MENUS);
    })
}
```

**修改后 (代理用户也检查所属渠道的功能开关):**
```php
public function all(): array
{
    $channel = null;
    
    // ✅ 获取渠道信息（渠道用户和代理用户都需要检查）
    if (Admin::user()->type == AdminDepartment::TYPE_CHANNEL) {
        // 渠道用户：直接获取当前渠道
        $channel = Channel::where('department_id', $departmentId)->first();
    } elseif (Admin::user()->type == AdminDepartment::TYPE_AGENT) {
        // ✅ 代理用户：获取所属渠道（代理的 department_id 就是渠道的 department_id）
        $channel = Channel::where('department_id', $departmentId)->first();
    }
    
    // 功能开关检查（对渠道和代理用户都生效）
    ->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
        $query->whereNotIn('name', MenuConstant::LOTTERY_TICKET_MENUS);
    })
}
```

---

## 📊 数据结构

### Channel 表字段

```sql
-- 摸奖券功能开关
lottery_ticket_enabled TINYINT(1) DEFAULT 0 COMMENT '摸奖券功能（0:关闭，1:开启）'
```

### AdminUser 表字段

```sql
-- 用户类型
type TINYINT(1) COMMENT '用户类型（1:总站，2:渠道，3:代理，4:店家）'

-- 所属部门ID
department_id INT COMMENT '所属部门ID（渠道/代理的组织ID）'
```

---

## 🔍 逻辑说明

### 数据关系

```
Channel (渠道)
  department_id: 1001
  lottery_ticket_enabled: 1  ← 功能开关
    ↓
Agent (代理)
  department_id: 1001  ← 继承渠道的 department_id
  type: 3 (TYPE_AGENT)
```

**关键点:**
- 代理的 `department_id` 等于所属渠道的 `department_id`
- 通过 `department_id` 可以查询到渠道的功能开关配置
- 代理后台的菜单显示受所属渠道的功能开关控制

---

### 菜单过滤流程

```
用户登录
  ↓
获取用户信息 (Admin::user())
  ↓
判断用户类型
  ├─ TYPE_CHANNEL (渠道用户)
  │    ↓
  │  查询渠道: Channel::where('department_id', $departmentId)
  │    ↓
  │  检查 lottery_ticket_enabled
  │    ├─ = 0 → 隐藏渠道后台摸奖券菜单
  │    └─ = 1 → 显示渠道后台摸奖券菜单
  │
  └─ TYPE_AGENT (代理用户) ⭐ 新增逻辑
       ↓
     查询所属渠道: Channel::where('department_id', $departmentId)
       ↓
     检查 lottery_ticket_enabled
       ├─ = 0 → 隐藏代理后台摸奖券菜单
       └─ = 1 → 显示代理后台摸奖券菜单
```

---

## 🧪 测试场景

### 场景1: 渠道开启摸奖券功能

**数据准备:**
```sql
-- 渠道配置
UPDATE channel
SET lottery_ticket_enabled = 1
WHERE department_id = 1001;
```

**预期结果:**
- ✅ 渠道用户登录 → 能看到渠道后台摸奖券菜单
- ✅ 代理用户登录 → 能看到代理后台摸奖券菜单

---

### 场景2: 渠道关闭摸奖券功能

**数据准备:**
```sql
-- 渠道配置
UPDATE channel
SET lottery_ticket_enabled = 0
WHERE department_id = 1001;
```

**预期结果:**
- ✅ 渠道用户登录 → 看不到渠道后台摸奖券菜单
- ✅ 代理用户登录 → 看不到代理后台摸奖券菜单 ⭐ 新增验证

---

### 场景3: 不同渠道配置不同

**数据准备:**
```sql
-- 渠道A：开启摸奖券
UPDATE channel
SET lottery_ticket_enabled = 1
WHERE department_id = 1001;

-- 渠道B：关闭摸奖券
UPDATE channel
SET lottery_ticket_enabled = 0
WHERE department_id = 1002;
```

**预期结果:**
- ✅ 渠道A的代理 → 能看到摸奖券菜单
- ✅ 渠道B的代理 → 看不到摸奖券菜单

---

## 📋 修改文件清单

**修改文件 (2个):**
1. `addons/webman/constant/MenuConstant.php` - 添加代理后台菜单到 `LOTTERY_TICKET_MENUS`
2. `addons/webman/service/Menu.php` - 代理用户也检查所属渠道的功能开关

---

## 🎯 功能开关管理

### 在渠道管理中开启/关闭

**位置:** 渠道管理 → 编辑渠道 → 功能配置

**字段:** 摸奖券功能 (lottery_ticket_enabled)

**选项:**
- ☐ 关闭 (0) - 渠道和下属代理都看不到摸奖券菜单
- ☑ 开启 (1) - 渠道和下属代理都能看到摸奖券菜单

---

## 📊 其他功能开关

系统中还有其他功能开关，代理用户同样会继承所属渠道的配置：

| 功能 | 字段 | 控制菜单 |
|------|------|---------|
| 提现功能 | withdraw_status | 充值渠道配置 |
| 推广功能 | promotion_status | 推广管理、分润记录等 |
| 金币功能 | coin_status | 金币商户管理 |
| 彩票功能 | lottery_status | 彩票管理 |
| 活动功能 | activity_status | 活动管理 |
| **摸奖券功能** | **lottery_ticket_enabled** | **摸奖券管理（渠道+代理）** |
| VIP等级功能 | vip_level_status | VIP等级管理 |

---

## ⚠️ 注意事项

### 1️⃣ **代理的 department_id 说明**

代理用户的 `department_id` 字段有双重含义：
- **组织ID:** 标识代理所属的组织单元
- **渠道ID:** 也是所属渠道的 `department_id`

**验证SQL:**
```sql
-- 查看代理所属渠道
SELECT
    a.id AS admin_id,
    a.username AS admin_username,
    a.type AS admin_type,
    a.department_id,
    c.id AS channel_id,
    c.channel_name,
    c.lottery_ticket_enabled
FROM admin_users a
LEFT JOIN channel c ON a.department_id = c.department_id
WHERE a.type = 3  -- TYPE_AGENT
LIMIT 10;
```

---

### 2️⃣ **菜单权限 vs 功能开关**

**两个独立的过滤机制:**

1. **角色菜单权限** (admin_role_menu)
   - 控制用户能看到哪些菜单
   - 在"角色管理"中配置

2. **功能开关** (channel.lottery_ticket_enabled)
   - 控制功能是否启用
   - 在"渠道管理"中配置

**两者关系:**
```
最终显示的菜单 = 角色菜单权限 ∩ 功能开关允许的菜单
```

**示例:**
- 角色有"摸奖券管理"权限 + 功能开启 = ✅ 显示菜单
- 角色有"摸奖券管理"权限 + 功能关闭 = ❌ 不显示菜单
- 角色无"摸奖券管理"权限 + 功能开启 = ❌ 不显示菜单

---

### 3️⃣ **店家用户说明**

店家用户（TYPE_STORE）也需要遵循相同逻辑，但目前店家后台没有摸奖券菜单。

如果将来添加店家后台摸奖券菜单，需要：
1. 在 `MenuConstant::LOTTERY_TICKET_MENUS` 中添加店家菜单
2. 在 `Menu::all()` 中添加 `TYPE_STORE` 的渠道查询逻辑

---

## ✅ 部署步骤

### 1️⃣ **更新代码**
- 修改 `MenuConstant.php`
- 修改 `Menu.php`

### 2️⃣ **重启服务器**
```bash
php start.php restart
```

### 3️⃣ **测试验证**
- 登录代理账号
- 检查所属渠道的 `lottery_ticket_enabled` 配置
- 验证菜单显示是否符合预期

---

## ✅ 修复总结

**问题:**
- 代理用户登录后，无论所属渠道是否开启摸奖券功能，都能看到摸奖券菜单

**原因:**
- `Menu::all()` 方法只检查渠道用户（TYPE_CHANNEL）的功能开关
- 代理用户（TYPE_AGENT）没有检查所属渠道的功能开关

**解决方案:**
1. ✅ 在 `MenuConstant::LOTTERY_TICKET_MENUS` 中添加代理后台菜单
2. ✅ 在 `Menu::all()` 中添加代理用户的渠道功能开关检查

**效果:**
- 代理后台的摸奖券菜单显示受所属渠道的 `lottery_ticket_enabled` 字段控制
- 渠道关闭功能时，下属代理也无法看到摸奖券菜单

修复完成！🎉
