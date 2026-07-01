# 数据库迁移文件位置修正说明

**修正时间:** 2026-06-11  
**问题:** 迁移文件错误放置在gk_admin中  
**原因:** 三个项目共享同一数据库，迁移应统一管理  

---

## ❌ 错误的做法

```
D:/gk_admin/database/migrations/
├── 20260611000002_lottery_activity_status_and_fields.php
├── 20260611000003_lottery_record_distribution_fields.php
└── manual_migration_20260611.sql
```

**为什么错误:**
- gk_admin、gk_api、gk_work 三个项目共享同一个MySQL数据库
- 数据库迁移应该统一在一个项目中管理
- 根据CLAUDE.md，gk_api有 `db/migrations` 目录

---

## ✅ 正确的做法

```
D:/gk_api/db/migrations/
├── 20260611000002_lottery_activity_status_and_fields.php
├── 20260611000003_lottery_record_distribution_fields.php
└── manual_migration_20260611.sql
```

**为什么正确:**
- 所有数据库迁移文件统一在gk_api项目中
- 避免迁移文件分散导致的混乱
- 便于版本控制和团队协作

---

## 🔧 已执行的修正操作

### 步骤1: 复制文件到正确位置
```bash
cp D:/gk_admin/database/migrations/20260611000002_lottery_activity_status_and_fields.php \
   D:/gk_api/db/migrations/

cp D:/gk_admin/database/migrations/20260611000003_lottery_record_distribution_fields.php \
   D:/gk_api/db/migrations/

cp D:/gk_admin/database/migrations/manual_migration_20260611.sql \
   D:/gk_api/db/migrations/
```

### 步骤2: 删除错误位置的文件
```bash
rm D:/gk_admin/database/migrations/20260611000002_lottery_activity_status_and_fields.php
rm D:/gk_admin/database/migrations/20260611000003_lottery_record_distribution_fields.php
rm D:/gk_admin/database/migrations/manual_migration_20260611.sql
```

### 步骤3: 验证
```bash
# gk_api中的迁移文件
ls -la D:/gk_api/db/migrations/202606*

# gk_admin中应该为空（或只有旧的迁移文件）
ls -la D:/gk_admin/database/migrations/
```

---

## 📋 执行迁移的正确方式

### 方式1: 手动执行SQL（推荐）⭐

由于gk_api项目没有Phinx配置文件，**推荐使用手动SQL方式**：

```bash
# 在gk_api目录下执行
cd D:/gk_api
mysql -u root -p yjb_platform < db/migrations/manual_migration_20260611.sql
```

**优点:**
- 简单直接
- 不依赖PHP工具
- 适合生产环境

### 方式2: 使用Phinx（如果配置了）

如果gk_api配置了Phinx：

```bash
cd D:/gk_api
vendor/bin/phinx migrate
```

**注意:** 当前gk_api **没有phinx.php配置文件**，因此不能使用此方式。

---

## 🎯 三个项目的职责划分

### gk_admin (D:/gk_admin)
- **职责:** 后台管理界面、业务逻辑控制器
- **不包含:** 数据库迁移文件 ❌
- **包含:** Model、Controller、Service、Lang、Config

### gk_api (D:/gk_api)
- **职责:** API接口、WebSocket推送、**数据库迁移** ⭐
- **包含:** 
  - `db/migrations/` - **所有数据库迁移文件**
  - Model、Controller、WebSocket服务

### gk_work (D:/gk_work)
- **职责:** 后台任务、单一钱包API、游戏平台代理
- **不包含:** 数据库迁移文件 ❌
- **包含:** Process、Wallet Controller、Game Services

---

## 📝 项目规范更新

### 新增规范

**数据库迁移文件位置规范:**
1. ✅ 所有数据库迁移文件统一放在 `D:/gk_api/db/migrations/`
2. ✅ 迁移文件命名格式: `YYYYMMDDHHMMSS_description.php` 或 `.sql`
3. ✅ 手动SQL文件命名格式: `manual_migration_YYYYMMDD.sql`
4. ❌ 禁止在gk_admin或gk_work中创建迁移文件

**执行迁移规范:**
1. ✅ 开发环境：使用手动SQL或Phinx（如配置）
2. ✅ 生产环境：使用手动SQL（更可控）
3. ✅ 迁移前必须备份数据库
4. ✅ 迁移后验证字段和索引

---

## 🔄 更新后的部署流程

### 数据库迁移步骤（更新）

**步骤1: 备份数据库**
```bash
mysqldump -u root -p yjb_platform > backup_$(date +%Y%m%d_%H%M%S).sql
```

**步骤2: 执行迁移（从gk_api项目）**
```bash
cd D:/gk_api
mysql -u root -p yjb_platform < db/migrations/manual_migration_20260611.sql
```

**步骤3: 验证迁移**
```sql
-- 检查活动表字段
DESC lottery_ticket_activity;
-- 应该看到: draw_completed_at, prize_distributed_at, 
--          total_prize_amount, distributed_prize_amount

-- 检查中奖记录表字段
DESC lottery_ticket_record;
-- 应该看到: distributed_by, distributed_at, distribution_note,
--          modified_by, modified_at, modification_reason

-- 检查索引
SHOW INDEX FROM lottery_ticket_record WHERE Key_name LIKE 'idx_%';
-- 应该看到: idx_status_distributed, idx_distributed_by
```

**步骤4: 重启服务**
```bash
# gk_admin
cd D:/gk_admin
php windows.php restart

# gk_api
cd D:/gk_api
php windows.php restart

# gk_work
cd D:/gk_work
php windows.php restart
```

---

## 📂 文件位置对照表

| 文件类型 | gk_admin | gk_api | gk_work |
|---------|----------|--------|---------|
| **数据库迁移** | ❌ | ✅ db/migrations/ | ❌ |
| Model | ✅ addons/webman/model/ | ✅ app/model/ | ✅ app/model/ |
| Controller | ✅ addons/webman/controller/ | ✅ app/api/controller/ | ✅ app/api/controller/ |
| Service | ✅ addons/webman/service/ | ✅ app/service/ | ✅ app/service/ |
| Config | ✅ config/ | ✅ config/ | ✅ config/ |
| Lang | ✅ addons/webman/lang/ | ✅ resource/lang/ | ✅ resource/lang/ |

---

## ⚠️ 重要提醒

### 对开发人员

1. **永远不要在gk_admin中创建迁移文件**
2. **所有数据库变更必须在gk_api/db/migrations/中创建**
3. **迁移文件必须包含回滚逻辑（如使用Phinx）**
4. **手动SQL必须包含字段存在性检查（IF NOT EXISTS）**

### 对部署人员

1. **迁移文件从gk_api项目获取**
2. **执行迁移前必须备份数据库**
3. **三个项目共享一个数据库，迁移只需执行一次**
4. **迁移后必须重启所有三个项目的服务**

---

## ✅ 修正完成确认

- [x] 迁移文件已移动到 `D:/gk_api/db/migrations/`
- [x] gk_admin中的迁移文件已删除
- [x] 文档已更新（本文件）
- [x] 部署流程已更新
- [x] 团队已通知（待执行）

---

**修正完成时间:** 2026-06-11  
**影响范围:** 数据库迁移流程  
**后续行动:** 更新团队文档和开发规范  

**修正人员:** AI Assistant
