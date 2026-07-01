# 代理后台摸奖券菜单翻译修复

## 🐛 发现的问题

### 1️⃣ **菜单名称错误**

**问题:** 数据库中的菜单名称使用了旧的 `agent_lottery_ticket_win_record_list`

**正确:** 应该是 `agent_lottery_ticket_record_list`（与控制器类名一致）

---

### 2️⃣ **菜单翻译缺失**

**问题:** 4个语言文件中都缺少代理后台摸奖券菜单的翻译

**菜单名称:**
- `agent_lottery_ticket_management` - 父级菜单
- `agent_lottery_ticket_activity_list` - 摸奖券活动
- `agent_lottery_ticket_list` - 摸奖券列表
- `agent_lottery_ticket_record_list` - 中奖记录

---

## ✅ 修复内容

### 1️⃣ **菜单迁移文件修正**

**文件:** `D:/gk_api/db/migrations/20260614120000_add_agent_lottery_ticket_menus.php`

**修改前:**
```php
'agent_lottery_ticket_win_record_list'  // ❌ 错误
```

**修改后:**
```php
'agent_lottery_ticket_record_list'  // ✅ 正确
```

---

### 2️⃣ **SQL文件修正**

**文件:** `D:/gk_admin/20260614120000_add_agent_lottery_ticket_menus.sql`

**修改前:**
```sql
'agent_lottery_ticket_win_record_list'  -- ❌ 错误
```

**修改后:**
```sql
'agent_lottery_ticket_record_list'  -- ✅ 正确
```

---

### 3️⃣ **添加菜单翻译**

#### 繁体中文 (zh-TW)

**文件:** `addons/webman/lang/zh-TW/menu.php`

```php
//代理后台摸奖券管理
'agent_lottery_ticket_management' => '摸獎券管理',
'agent_lottery_ticket_activity_list' => '摸獎券活動',
'agent_lottery_ticket_list' => '摸獎券列表',
'agent_lottery_ticket_record_list' => '中獎記錄',
```

---

#### 简体中文 (zh-CN)

**文件:** `addons/webman/lang/zh-CN/menu.php`

```php
//代理后台摸奖券管理
'agent_lottery_ticket_management' => '摸奖券管理',
'agent_lottery_ticket_activity_list' => '摸奖券活动',
'agent_lottery_ticket_list' => '摸奖券列表',
'agent_lottery_ticket_record_list' => '中奖记录',
```

---

#### 英文 (en)

**文件:** `addons/webman/lang/en/menu.php`

```php
//Agent Backend Lottery Ticket Management
'agent_lottery_ticket_management' => 'Lottery Ticket Management',
'agent_lottery_ticket_activity_list' => 'Lottery Activities',
'agent_lottery_ticket_list' => 'Lottery Tickets',
'agent_lottery_ticket_record_list' => 'Winning Records',
```

---

#### 日文 (jp)

**文件:** `addons/webman/lang/jp/menu.php`

```php
//代理店バックエンド抽選券管理
'agent_lottery_ticket_management' => '抽選券管理',
'agent_lottery_ticket_activity_list' => '抽選券キャンペーン',
'agent_lottery_ticket_list' => '抽選券リスト',
'agent_lottery_ticket_record_list' => '当選記録',
```

---

## 📋 修改文件清单

**迁移文件 (2个):**
1. `D:/gk_api/db/migrations/20260614120000_add_agent_lottery_ticket_menus.php` - 修正菜单名称
2. `D:/gk_admin/20260614120000_add_agent_lottery_ticket_menus.sql` - 修正菜单名称

**翻译文件 (4个):**
1. `addons/webman/lang/zh-TW/menu.php` - 新增4个翻译
2. `addons/webman/lang/zh-CN/menu.php` - 新增4个翻译
3. `addons/webman/lang/en/menu.php` - 新增4个翻译
4. `addons/webman/lang/jp/menu.php` - 新增4个翻译

---

## 🔍 菜单名称对照表

| 菜单名称 | 繁中 | 简中 | English | 日本語 |
|---------|------|------|---------|--------|
| agent_lottery_ticket_management | 摸獎券管理 | 摸奖券管理 | Lottery Ticket Management | 抽選券管理 |
| agent_lottery_ticket_activity_list | 摸獎券活動 | 摸奖券活动 | Lottery Activities | 抽選券キャンペーン |
| agent_lottery_ticket_list | 摸獎券列表 | 摸奖券列表 | Lottery Tickets | 抽選券リスト |
| agent_lottery_ticket_record_list | 中獎記錄 | 中奖记录 | Winning Records | 当選記録 |

---

## 🎯 菜单层级结构

```
摸獎券管理 (agent_lottery_ticket_management)
├─ 摸獎券活動 (agent_lottery_ticket_activity_list)
├─ 摸獎券列表 (agent_lottery_ticket_list)
└─ 中獎記錄 (agent_lottery_ticket_record_list)
```

---

## ✅ 验证方法

### 1️⃣ **检查数据库菜单**

执行迁移后，运行以下 SQL 验证菜单名称：

```sql
SELECT 
    m1.id AS parent_id,
    m1.name AS parent_name,
    m2.id AS child_id,
    m2.name AS child_name,
    m2.url
FROM admin_menus m1
LEFT JOIN admin_menus m2 ON m1.id = m2.pid
WHERE m1.name = 'agent_lottery_ticket_management'
ORDER BY m2.sort;
```

**预期结果:**
```
parent_name: agent_lottery_ticket_management
child_name:
  - agent_lottery_ticket_activity_list
  - agent_lottery_ticket_list
  - agent_lottery_ticket_record_list  ← 注意：不是 win_record
```

---

### 2️⃣ **验证菜单翻译**

**代理账号登录后:**

1. **繁体中文环境:**
   - 菜单显示: 摸獎券管理 > 摸獎券活動
   - 菜单显示: 摸獎券管理 > 摸獎券列表
   - 菜单显示: 摸獎券管理 > 中獎記錄

2. **简体中文环境:**
   - 菜单显示: 摸奖券管理 > 摸奖券活动
   - 菜单显示: 摸奖券管理 > 摸奖券列表
   - 菜单显示: 摸奖券管理 > 中奖记录

3. **英文环境:**
   - 菜单显示: Lottery Ticket Management > Lottery Activities
   - 菜单显示: Lottery Ticket Management > Lottery Tickets
   - 菜单显示: Lottery Ticket Management > Winning Records

4. **日文环境:**
   - 菜单显示: 抽選券管理 > 抽選券キャンペーン
   - 菜单显示: 抽選券管理 > 抽選券リスト
   - 菜单显示: 抽選券管理 > 当選記録

---

## 🚀 部署步骤

### 1️⃣ **如果数据库中已存在旧菜单，需要先删除**

```sql
-- 删除旧的错误菜单（如果存在）
DELETE FROM `admin_menus` 
WHERE `name` = 'agent_lottery_ticket_win_record_list' 
  AND `plugin` = 'webman';
```

---

### 2️⃣ **执行菜单迁移**

```bash
# 方式一：Phinx
cd D:/gk_api
vendor/bin/phinx migrate

# 方式二：手动SQL
mysql -u root -p your_database < D:/gk_admin/20260614120000_add_agent_lottery_ticket_menus.sql
```

---

### 3️⃣ **重启服务器**

```bash
cd D:/gk_admin
php start.php restart
```

---

### 4️⃣ **清除浏览器缓存**

翻译文件修改后，需要清除浏览器缓存才能看到新的翻译。

---

## 📝 注意事项

### 1️⃣ **菜单名称命名规范**

**格式:** `{backend_type}_{module}_{page_type}`

**示例:**
- `agent_lottery_ticket_management` - 代理后台摸奖券管理（父级）
- `agent_lottery_ticket_activity_list` - 代理后台摸奖券活动列表
- `agent_lottery_ticket_list` - 代理后台摸奖券列表
- `agent_lottery_ticket_record_list` - 代理后台中奖记录列表

**命名一致性:**
- ✅ `record` - 与 `LotteryTicketRecord` 模型一致
- ❌ `win_record` - 与 `LotteryTicketWinRecord` 模型不一致（模型已改名）

---

### 2️⃣ **翻译文件位置**

所有菜单翻译都在 `titles` 数组中：

```php
return [
    'fields' => [...],
    'type' => [...],
    'titles' => [
        // ✅ 菜单翻译在这里
        'agent_lottery_ticket_management' => '摸獎券管理',
        ...
    ]
];
```

---

### 3️⃣ **菜单类型 (type)**

**代理后台菜单:**
```sql
type = 3  -- AdminDepartment::TYPE_AGENT
```

**其他类型:**
- `type = 1` - 总站菜单
- `type = 2` - 渠道菜单
- `type = 4` - 店家菜单

---

## ✅ 修复总结

**问题根源:**
1. 菜单名称使用了旧的 `win_record` 前缀
2. 4个语言文件都缺少菜单翻译

**修复方案:**
1. ✅ 统一菜单名称为 `record`（与模型名一致）
2. ✅ 在4个语言文件中添加完整的菜单翻译

**验证标准:**
- 数据库菜单名称正确
- 4种语言环境下菜单显示正确
- 菜单层级结构正确

修复完成！🎉
