# 摸奖券菜单迁移指南

## 📋 概述

本文档说明如何为YJB系统添加摸奖券管理功能的菜单和权限。

## 🗂️ 涉及的文件

### 1. 数据库迁移文件
**位置**: `D:\gk_api\db\migrations\20260602100000_add_lottery_ticket_menus.php`

**功能**:
- 在 `admin_menus` 表中添加摸奖券管理菜单
- 包含1个父菜单和3个子菜单
- 仅针对渠道后台（type=2）

**菜单结构**:
```
摸奖券管理 (lottery_ticket_manage)
├── 进行中的活动 (lottery_ticket_dashboard)
├── 历史活动记录 (lottery_ticket_history)
└── 中奖记录 (lottery_ticket_records)
```

### 2. 权限配置文件
**位置**: `D:\gk_admin\config\channel_node.php`

**功能**:
- 定义摸奖券管理的功能权限节点
- 包含所有CRUD操作和业务逻辑权限

**权限节点**:
```php
- 进行中的活动
  - index (查看列表)
  - index-delete (删除活动)
  - form-post (创建活动)
  - form-put (编辑活动)
  - closeActivity (关闭活动)
  - prizeConfig (奖品配置)
  - savePrizeConfig (保存奖品配置)

- 历史活动记录
  - historyList (查看历史)

- 中奖记录
  - index (查看列表)
  - grantPrize (发放奖品)
  - exportRecords (导出记录)
```

### 3. 菜单常量配置
**位置**: `D:\gk_admin\addons\webman\constant\MenuConstant.php`

**已配置**:
```php
const LOTTERY_TICKET_MENUS = [
    'lottery_ticket_manage',         // 摸奖券管理（父菜单）
    'lottery_ticket_dashboard',      // 进行中的活动
    'lottery_ticket_history',        // 历史活动记录
    'lottery_ticket_records',        // 中奖记录
];
```

### 4. 菜单过滤逻辑
**位置**: `D:\gk_admin\addons\webman\service\Menu.php`

**已配置**:
```php
// 摸奖券功能开关
->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
    $query->whereNotIn('name', MenuConstant::LOTTERY_TICKET_MENUS);
})
```

## 🚀 执行迁移

### 步骤1: 执行数据库迁移

**在 gk_api 项目中执行**:

```bash
cd D:\gk_api

# 检查迁移状态
php vendor/bin/phinx status

# 执行迁移
php vendor/bin/phinx migrate

# 或使用自定义脚本
php migrate.php
```

**预期输出**:
```
✓ 菜单创建成功: lottery_ticket_manage
✓ 菜单创建成功: lottery_ticket_dashboard
✓ 菜单创建成功: lottery_ticket_history
✓ 菜单创建成功: lottery_ticket_records

摸奖券菜单迁移完成！
⚠️  注意：需要在角色管理中为渠道管理员角色分配这些菜单权限
```

### 步骤2: 重启服务

**gk_admin 项目**:
```bash
cd D:\gk_admin
php start.php restart
```

**gk_api 项目**:
```bash
cd D:\gk_api
php start.php restart
```

### 步骤3: 分配菜单权限

1. 登录管理后台
2. 进入 **权限管理 → 角色管理**
3. 编辑 **渠道管理员角色**
4. 勾选以下菜单权限：
   - ☑️ 摸奖券管理
     - ☑️ 进行中的活动
     - ☑️ 历史活动记录
     - ☑️ 中奖记录
5. 保存

### 步骤4: 分配功能权限

1. 在角色编辑页面
2. 展开 **摸奖券管理** 功能组
3. 根据需要勾选以下权限：
   - ☑️ 查看列表
   - ☑️ 创建活动
   - ☑️ 编辑活动
   - ☑️ 删除活动
   - ☑️ 关闭活动
   - ☑️ 奖品配置
   - ☑️ 保存奖品配置
   - ☑️ 查看历史
   - ☑️ 发放奖品
   - ☑️ 导出记录
4. 保存

## 🎯 功能开关

摸奖券功能通过渠道表的 `lottery_ticket_enabled` 字段控制：

```sql
-- 开启摸奖券功能
UPDATE yjb_channel SET lottery_ticket_enabled = 1 WHERE id = ?;

-- 关闭摸奖券功能
UPDATE yjb_channel SET lottery_ticket_enabled = 0 WHERE id = ?;
```

**效果**:
- `lottery_ticket_enabled = 1`: 渠道后台显示摸奖券菜单
- `lottery_ticket_enabled = 0`: 渠道后台隐藏摸奖券菜单

## 📝 菜单详情

### 1. 父菜单：摸奖券管理

| 字段 | 值 |
|------|-----|
| name | lottery_ticket_manage |
| icon | GiftOutlined |
| url | (空) |
| pid | 33 (channel_manage) |
| sort | 5 |
| type | 2 (渠道后台) |
| status | 1 (启用) |
| open | 0 (默认折叠) |

### 2. 子菜单：进行中的活动

| 字段 | 值 |
|------|-----|
| name | lottery_ticket_dashboard |
| icon | far fa-circle |
| url | ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/index |
| pid | lottery_ticket_manage |
| sort | 1 |
| type | 2 |
| status | 1 |
| open | 1 |

### 3. 子菜单：历史活动记录

| 字段 | 值 |
|------|-----|
| name | lottery_ticket_history |
| icon | far fa-circle |
| url | ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/historyList |
| pid | lottery_ticket_manage |
| sort | 2 |
| type | 2 |
| status | 1 |
| open | 1 |

### 4. 子菜单：中奖记录

| 字段 | 值 |
|------|-----|
| name | lottery_ticket_records |
| icon | far fa-circle |
| url | ex-admin/addons-webman-controller-ChannelLotteryTicketRecordController/index |
| pid | lottery_ticket_manage |
| sort | 3 |
| type | 2 |
| status | 1 |
| open | 1 |

## 🔄 回滚迁移

如果需要回滚菜单迁移：

```bash
cd D:\gk_api
php vendor/bin/phinx rollback -t 20260602100000
```

**效果**:
- 删除所有摸奖券菜单记录
- 清理孤立的角色菜单关联

## ⚠️ 注意事项

1. **迁移文件在 gk_api 项目中**
   - 数据库迁移统一在 `D:\gk_api\db\migrations\` 目录
   - 三个项目共享同一个数据库

2. **权限配置在 gk_admin 项目中**
   - 功能权限定义在 `D:\gk_admin\config\channel_node.php`
   - 需要重启 gk_admin 服务生效

3. **菜单显示逻辑**
   - 由 `Menu.php` 的 `all()` 方法控制
   - 根据 `channel.lottery_ticket_enabled` 字段动态过滤

4. **控制器尚未创建**
   - 迁移添加菜单，但控制器需要另外实现
   - 控制器名称：
     - `ChannelLotteryTicketActivityController`
     - `ChannelLotteryTicketRecordController`

## 📚 相关文档

- [摸奖券实现文档](./LOTTERY_TICKET_MENU_IMPLEMENTATION.md)
- [菜单常量配置](./addons/webman/constant/MenuConstant.php)
- [多语言翻译](./addons/webman/lang/zh-TW/lottery_ticket.php)

## ✅ 验证清单

迁移完成后，请验证以下项目：

- [ ] 数据库中存在4条摸奖券菜单记录（1父3子）
- [ ] `config/channel_node.php` 包含摸奖券权限节点
- [ ] `MenuConstant.php` 已定义 `LOTTERY_TICKET_MENUS` 常量
- [ ] `Menu.php` 已配置 `lottery_ticket_enabled` 过滤逻辑
- [ ] 渠道管理员角色已分配菜单权限
- [ ] 渠道管理员角色已分配功能权限
- [ ] 开启功能的渠道可以看到菜单
- [ ] 关闭功能的渠道不显示菜单
- [ ] gk_admin 服务已重启
- [ ] gk_api 服务已重启

---

**创建时间**: 2026-06-02  
**作者**: AI Assistant  
**版本**: 1.0
