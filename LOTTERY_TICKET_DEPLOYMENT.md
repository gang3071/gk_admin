# 摸奖券功能部署指南

## ✅ 已完成的工作

### 1. 代码实现 ✅
- [x] 控制器创建 (`ChannelLotteryTicketActivityController`, `ChannelLotteryTicketRecordController`)
- [x] 模型创建 (`LotteryTicketActivity`, `LotteryTicketPrizeLevel`, `LotteryTicketRecord`)
- [x] 翻译文件 (4种语言完整翻译)
- [x] 权限配置 (`config/channel_node.php`)
- [x] 菜单管理优化 (MenuConstant 常量类)
- [x] Bug修复 (3个修复)

### 2. 数据库迁移文件 ✅
- [x] `20260602100000_add_lottery_ticket_menus.php` (菜单迁移)
- [x] `20260604000001_create_lottery_ticket_tables.php` (数据表迁移)

---

## 🚀 部署步骤

### Step 1: 执行数据库迁移

**⚠️ 重要：迁移文件在 gk_api 项目中，但所有三个项目共享同一个数据库**

```bash
# 进入 gk_api 项目目录
cd D:\gk_api

# 执行迁移
php vendor/bin/phinx migrate

# 验证迁移状态
php vendor/bin/phinx status
```

**预期输出：**
```
Status  Migration ID    Migration Name
--------------------------------------
  up    20260602100000  AddLotteryTicketMenus
  up    20260604000001  CreateLotteryTicketTables
```

**创建的数据表：**
1. `lottery_ticket_activity` - 摸奖券活动表
2. `lottery_ticket_prize_level` - 奖品等级配置表
3. `lottery_ticket_record` - 中奖记录表

**创建的菜单记录（admin_menu表）：**
1. lottery_ticket_manage (ID: 待定)
   - lottery_ticket_dashboard (进行中的活动)
   - lottery_ticket_history (历史活动记录)
   - lottery_ticket_records (中奖记录)

---

### Step 2: 重启服务

**Windows 环境：**
```bash
# gk_admin
cd D:\gk_admin
php windows.php stop
php windows.php start

# gk_api (如果有变更)
cd D:\gk_api
php windows.php stop
php windows.php start

# gk_work (如果有变更)
cd D:\gk_work
php windows.php stop
php windows.php start
```

**Linux 环境：**
```bash
# gk_admin
cd /path/to/gk_admin
php start.php restart

# gk_api
cd /path/to/gk_api
php start.php restart

# gk_work
cd /path/to/gk_work
php start.php restart
```

---

### Step 3: 配置权限

**3.1 分配菜单权限给渠道管理员角色**

1. 登录后台管理系统
2. 进入：系统管理 → 角色管理
3. 找到"渠道管理员"角色并编辑
4. 在权限树中勾选：
   - ✅ 摸奖券管理
     - ✅ 进行中的活动
     - ✅ 历史活动记录
     - ✅ 中奖记录

**3.2 分配功能权限**

在权限树中勾选以下节点（共12个权限）：

**摸奖券活动管理 (`ChannelLotteryTicketActivityController`)**
- ✅ index (进行中的活动列表)
- ✅ index-delete (删除活动)
- ✅ form-post (创建活动)
- ✅ form-put (编辑活动)
- ✅ closeActivity (关闭活动)
- ✅ prizeConfig (奖品配置弹窗)
- ✅ savePrizeConfig (保存奖品配置)
- ✅ historyList (历史活动记录)

**中奖记录管理 (`ChannelLotteryTicketRecordController`)**
- ✅ index (中奖记录列表)
- ✅ grantPrize (手动发放奖品)
- ✅ exportRecords (导出记录)

5. 保存角色权限

---

### Step 4: 启用摸奖券功能

**为指定渠道启用摸奖券功能：**

1. 进入：渠道管理 → 渠道列表
2. 编辑需要开启摸奖券功能的渠道
3. 找到"摸奖券功能"字段
4. 选择"启用" (`lottery_ticket_enabled = 1`)
5. 保存

**SQL 方式批量启用：**
```sql
-- 启用特定渠道的摸奖券功能
UPDATE yjb_channel SET lottery_ticket_enabled = 1 WHERE id IN (1, 2, 3);

-- 或启用所有渠道
UPDATE yjb_channel SET lottery_ticket_enabled = 1;
```

---

### Step 5: 验证功能

**5.1 检查菜单显示**

1. 使用渠道管理员账号登录
2. 左侧菜单应显示"摸奖券管理"及其子菜单
3. 如果看不到菜单：
   - 检查 `lottery_ticket_enabled` 字段是否为 1
   - 检查角色是否有菜单权限
   - 清除浏览器缓存并刷新

**5.2 测试活动创建**

1. 点击"进行中的活动"
2. 点击"创建"按钮
3. 填写活动信息：
   - 活动名称：测试活动
   - 活动说明：这是一个测试活动
   - 开始时间：2026-06-04 00:00:00
   - 结束时间：2026-06-30 23:59:59
4. 保存

**5.3 测试奖品配置**

1. 在活动列表中点击"奖品配置"按钮
2. 添加奖品等级：
   - 等级排名：1
   - 等级名称：一等奖
   - 奖品类型：现金
   - 奖品金额：100.00
   - 奖品数量：10
   - 中奖概率：5.00
3. 保存

**5.4 测试记录查看**

1. 点击"中奖记录"菜单
2. 验证筛选器工作正常
3. 验证各列显示正确

---

## 🔍 故障排查

### 问题1：菜单不显示

**症状：** 摸奖券管理菜单不显示

**排查步骤：**

1. 检查数据库菜单记录：
```sql
SELECT * FROM admin_menu WHERE name IN (
    'lottery_ticket_manage',
    'lottery_ticket_dashboard',
    'lottery_ticket_history',
    'lottery_ticket_records'
);
```

2. 检查渠道配置：
```sql
SELECT id, name, lottery_ticket_enabled FROM yjb_channel WHERE id = {your_department_id};
```

3. 检查角色权限：
```sql
SELECT ar.name, arm.menu_id, am.name as menu_name
FROM admin_role ar
LEFT JOIN admin_role_menu arm ON ar.id = arm.role_id
LEFT JOIN admin_menu am ON arm.menu_id = am.id
WHERE ar.id = {role_id}
AND am.name LIKE 'lottery_ticket%';
```

4. 清除缓存：
```sql
DELETE FROM redis_cache WHERE key LIKE 'ADMIN_PERMISSIONS_%';
```

---

### 问题2：数据库字段不存在

**症状：** 错误 "Unknown column 'activity_name' in 'field list'"

**解决方案：**

1. 确认迁移已执行：
```bash
cd D:\gk_api
php vendor/bin/phinx status
```

2. 手动检查表结构：
```sql
SHOW CREATE TABLE lottery_ticket_activity;
SHOW CREATE TABLE lottery_ticket_prize_level;
SHOW CREATE TABLE lottery_ticket_record;
```

3. 如果表不存在，重新执行迁移：
```bash
php vendor/bin/phinx migrate
```

---

### 问题3：权限拒绝

**症状：** 访问页面时提示"没有权限"

**解决方案：**

1. 检查 `config/channel_node.php` 中是否包含摸奖券权限节点

2. 重启服务以加载新权限配置：
```bash
php start.php restart
```

3. 在后台重新分配权限给角色

4. 清除用户权限缓存：
```bash
# Redis CLI
redis-cli
> DEL ADMIN_PERMISSIONS_{user_id}
```

---

### 问题4：翻译显示异常

**症状：** 界面显示翻译键而不是翻译文本（如 "lottery_ticket.fields.activity_name"）

**解决方案：**

1. 检查翻译文件是否存在：
```bash
ls addons/webman/lang/zh-TW/lottery_ticket.php
ls addons/webman/lang/zh-CN/lottery_ticket.php
ls addons/webman/lang/en/lottery_ticket.php
ls addons/webman/lang/jp/lottery_ticket.php
```

2. 验证翻译文件语法正确（无PHP语法错误）

3. 重启服务加载新翻译文件

---

## 📊 数据库表结构

### lottery_ticket_activity (活动表)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT | 主键 |
| department_id | INT | 所属渠道部门ID |
| activity_name | VARCHAR(100) | 活动名称 |
| description | VARCHAR(500) | 活动说明 |
| start_time | DATETIME | 开始时间 |
| end_time | DATETIME | 结束时间 |
| status | INT | 状态(0:未开始,1:进行中,2:已结束,3:已关闭) |
| total_tickets | INT | 总发放摸奖券数量 |
| used_tickets | INT | 已使用摸奖券数量 |
| prize_config | TEXT | 奖品配置JSON |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间(软删除) |

### lottery_ticket_prize_level (奖品等级表)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT | 主键 |
| activity_id | INT | 活动ID |
| level_rank | INT | 等级排名(1-10) |
| level_name | VARCHAR(50) | 等级名称 |
| prize_type | VARCHAR(20) | 奖品类型 |
| prize_amount | DECIMAL(10,2) | 奖品金额 |
| prize_item_name | VARCHAR(100) | 实物奖品名称 |
| prize_item_image | VARCHAR(255) | 实物奖品图片 |
| prize_count | INT | 奖品数量 |
| win_probability | DECIMAL(5,2) | 中奖概率(%) |
| sort_order | INT | 排序 |
| status | INT | 状态(0:禁用,1:启用) |
| description | VARCHAR(255) | 奖品描述 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### lottery_ticket_record (中奖记录表)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT | 主键 |
| activity_id | INT | 活动ID |
| player_id | INT | 玩家ID |
| department_id | INT | 所属渠道部门ID |
| ticket_id | INT | 使用的摸奖券ID |
| ticket_no | VARCHAR(50) | 摸奖券编号 |
| draw_time | TIMESTAMP | 抽奖时间 |
| prize_type | VARCHAR(20) | 奖品类型 |
| prize_name | VARCHAR(100) | 奖品名称 |
| prize_amount | DECIMAL(10,2) | 奖品金额 |
| status | INT | 状态(0:待发放,1:已发放,2:发放失败) |
| remark | VARCHAR(255) | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

---

## 📁 相关文件清单

### 控制器
- `addons/webman/controller/ChannelLotteryTicketActivityController.php`
- `addons/webman/controller/ChannelLotteryTicketRecordController.php`

### 模型
- `addons/webman/model/LotteryTicketActivity.php`
- `addons/webman/model/LotteryTicketPrizeLevel.php`
- `addons/webman/model/LotteryTicketRecord.php`

### 配置
- `addons/webman/constant/MenuConstant.php` (菜单常量)
- `config/channel_node.php` (权限配置)

### 翻译文件 (4种语言 × 3个文件)
**lottery_ticket.php:**
- `addons/webman/lang/zh-TW/lottery_ticket.php`
- `addons/webman/lang/zh-CN/lottery_ticket.php`
- `addons/webman/lang/en/lottery_ticket.php`
- `addons/webman/lang/jp/lottery_ticket.php`

**common.php (新增3个键):**
- `addons/webman/lang/zh-TW/common.php`
- `addons/webman/lang/zh-CN/common.php`
- `addons/webman/lang/en/common.php`
- `addons/webman/lang/jp/common.php`

**menu.php (新增4个菜单):**
- `addons/webman/lang/zh-TW/menu.php`
- `addons/webman/lang/zh-CN/menu.php`
- `addons/webman/lang/en/menu.php`
- `addons/webman/lang/jp/menu.php`

### 迁移文件
- `D:\gk_api\db\migrations\20260602100000_add_lottery_ticket_menus.php`
- `D:\gk_api\db\migrations\20260604000001_create_lottery_ticket_tables.php`

### 文档
- `LOTTERY_TICKET_DEPLOYMENT.md` (本文件 - 部署指南)
- `LOTTERY_TICKET_BUGFIX.md` (Bug修复记录)
- `LOTTERY_TICKET_IMPLEMENTATION_SUMMARY.md` (实现总结)
- `LOTTERY_TICKET_TRANSLATIONS.md` (翻译总结)
- `LOTTERY_TICKET_MENU_MIGRATION_GUIDE.md` (菜单迁移指南)

---

## ✅ 部署检查清单

- [ ] **数据库迁移**
  - [ ] 执行 `php vendor/bin/phinx migrate`
  - [ ] 验证 3 个表已创建
  - [ ] 验证菜单记录已插入

- [ ] **服务重启**
  - [ ] gk_admin 重启完成
  - [ ] gk_api 重启完成 (如有变更)
  - [ ] gk_work 重启完成 (如有变更)

- [ ] **权限配置**
  - [ ] 菜单权限已分配给渠道管理员角色
  - [ ] 功能权限已分配 (12个权限节点)
  - [ ] 权限缓存已清除

- [ ] **功能启用**
  - [ ] 目标渠道 `lottery_ticket_enabled = 1`
  - [ ] 菜单在前端显示正常

- [ ] **功能测试**
  - [ ] 创建测试活动成功
  - [ ] 配置奖品等级成功
  - [ ] 查看历史活动成功
  - [ ] 查看中奖记录成功
  - [ ] 手动发放奖品成功 (如有测试数据)

- [ ] **多语言测试**
  - [ ] 繁体中文显示正常
  - [ ] 简体中文显示正常
  - [ ] 英文显示正常
  - [ ] 日文显示正常

---

## 📝 后续开发建议

### 1. 导出功能完善
当前 `exportRecords` 方法为占位符，需要实现：

```php
public function exportRecords(Request $request)
{
    // 参考: StoreShiftHandoverRecordController 的导出实现
    // 使用: ShiftReportExporter 模式创建 LotteryTicketExporter
}
```

### 2. 实时抽奖API (可选)
如需实现玩家端抽奖功能，建议在 `gk_api` 项目中添加：

**API 路由：**
- `POST /api/v1/lottery/draw` - 抽奖接口
- `GET /api/v1/lottery/my-records` - 我的中奖记录
- `GET /api/v1/lottery/activities` - 当前可参与活动

**实现位置：**
`D:\gk_api\app\api\controller\v1\LotteryController.php`

### 3. 自动发放奖品任务 (可选)
如需自动发放现金/红利/积分奖品，建议在 `gk_work` 项目中添加：

**任务进程：**
`D:\gk_work\process\LotteryPrizeGrantProcess.php`

**配置：**
`D:\gk_work\config\process.php`

```php
'lottery_prize_grant' => [
    'handler' => process\LotteryPrizeGrantProcess::class,
    'reloadable' => false,
    'count' => 1
]
```

---

**创建时间**: 2026-06-04  
**版本**: 1.0  
**状态**: 📋 待部署
