# 设备管理功能数据库迁移说明

## 📋 迁移文件

### Phinx 迁移文件（推荐）
- 📁 位置：`db/migrations/20260316120000_create_device_management_tables.php`
- 🎯 功能：使用 Phinx 创建设备管理相关表
- ✅ 优点：可回滚、版本控制、自动管理

### SQL 迁移文件（备选）
- 📁 位置：`database/migrations/create_device_tables.sql`
- 🎯 功能：直接执行 SQL 创建表
- ✅ 优点：简单直接、适合手动执行

---

## 🚀 方法一：使用 Phinx 执行迁移（推荐）

### 1. 检查迁移状态
```bash
# Windows
php vendor/bin/phinx.bat status

# Linux/Mac
php vendor/bin/phinx status
```

### 2. 执行迁移
```bash
# Windows
php vendor/bin/phinx.bat migrate

# Linux/Mac
php vendor/bin/phinx migrate
```

### 3. 验证迁移
```bash
# 查看迁移状态，确保设备管理迁移已执行
php vendor/bin/phinx.bat status
```

### 4. 回滚迁移（如需要）
```bash
# 回滚最后一次迁移
php vendor/bin/phinx.bat rollback

# 回滚到指定版本
php vendor/bin/phinx.bat rollback -t 20260316120000
```

---

## 🔧 方法二：直接执行 SQL（简单快速）

### 1. 使用命令行
```bash
# Windows (cmd)
mysql -u your_username -p your_database < database/migrations/create_device_tables.sql

# Windows (PowerShell)
Get-Content database/migrations/create_device_tables.sql | mysql -u your_username -p your_database

# Linux/Mac
mysql -u your_username -p your_database < database/migrations/create_device_tables.sql
```

### 2. 使用 Navicat/phpMyAdmin
1. 打开 Navicat 或 phpMyAdmin
2. 选择数据库
3. 打开 SQL 执行窗口
4. 复制 `database/migrations/create_device_tables.sql` 内容
5. 执行 SQL

### 3. 使用 MySQL Workbench
1. 打开 MySQL Workbench
2. 连接到数据库
3. File → Open SQL Script
4. 选择 `database/migrations/create_device_tables.sql`
5. 执行脚本

---

## 📊 创建的数据库表

### 1. device（设备表）
```sql
CREATE TABLE `device` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `channel_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `department_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `device_name` VARCHAR(100) NOT NULL DEFAULT '',
    `device_no` VARCHAR(100) NOT NULL,
    `device_model` VARCHAR(100) NOT NULL DEFAULT '',
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    UNIQUE KEY `uk_device_no` (`device_no`, `deleted_at`),
    KEY `idx_channel_id` (`channel_id`),
    KEY `idx_department_id` (`department_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明：**
- `id` - 主键
- `channel_id` - 所属渠道ID
- `department_id` - 所属部门ID
- `device_name` - 设备名称
- `device_no` - 设备号（唯一）
- `device_model` - 设备型号
- `status` - 状态（0:禁用, 1:启用）
- `remark` - 备注
- `deleted_at` - 软删除时间

### 2. device_ip（设备IP绑定表）
```sql
CREATE TABLE `device_ip` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id` BIGINT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `ip_type` TINYINT(1) NOT NULL DEFAULT 1,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `last_used_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    UNIQUE KEY `uk_device_ip` (`device_id`, `ip_address`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_device_ip_device` FOREIGN KEY (`device_id`)
        REFERENCES `device` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明：**
- `id` - 主键
- `device_id` - 设备ID（外键）
- `ip_address` - IP地址（支持IPv4/IPv6）
- `ip_type` - IP类型（1:IPv4, 2:IPv6）
- `status` - 状态（0:禁用, 1:启用）
- `last_used_at` - 最后使用时间

### 3. device_access_log（设备访问日志表）
```sql
CREATE TABLE `device_access_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `device_no` VARCHAR(100) NOT NULL DEFAULT '',
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
    `is_allowed` TINYINT(1) NOT NULL DEFAULT 0,
    `reject_reason` VARCHAR(200) NOT NULL DEFAULT '',
    `request_url` VARCHAR(500) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_device_id` (`device_id`),
    KEY `idx_device_no` (`device_no`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**字段说明：**
- `id` - 主键
- `device_id` - 设备ID
- `device_no` - 设备号
- `ip_address` - 访问IP地址
- `is_allowed` - 是否允许（0:拒绝, 1:允许）
- `reject_reason` - 拒绝原因
- `request_url` - 请求URL
- `user_agent` - User Agent
- `created_at` - 访问时间

---

## ✅ 验证迁移结果

### 1. 检查表是否创建成功
```sql
-- 查看所有表
SHOW TABLES LIKE 'device%';

-- 应该看到：
-- device
-- device_ip
-- device_access_log
```

### 2. 检查表结构
```sql
-- 查看 device 表结构
DESC device;

-- 查看 device_ip 表结构
DESC device_ip;

-- 查看 device_access_log 表结构
DESC device_access_log;
```

### 3. 检查索引
```sql
-- 查看 device 表索引
SHOW INDEX FROM device;

-- 查看 device_ip 表索引
SHOW INDEX FROM device_ip;

-- 查看 device_access_log 表索引
SHOW INDEX FROM device_access_log;
```

### 4. 检查外键约束
```sql
-- 查看外键
SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    TABLE_SCHEMA = 'your_database_name'
    AND TABLE_NAME IN ('device', 'device_ip', 'device_access_log')
    AND REFERENCED_TABLE_NAME IS NOT NULL;
```

---

## 🐛 常见问题

### Q1: Phinx 命令找不到？
**A:** 确保已安装 Phinx：
```bash
composer require robmorgan/phinx
```

### Q2: 表已存在错误？
**A:** 迁移文件会自动检查表是否存在，如果仍然报错：
```sql
-- 手动删除表（谨慎操作！会丢失数据）
DROP TABLE IF EXISTS device_access_log;
DROP TABLE IF EXISTS device_ip;
DROP TABLE IF EXISTS device;

-- 然后重新执行迁移
```

### Q3: 外键约束错误？
**A:** 确保按顺序创建表：
1. 先创建 `device` 表
2. 再创建 `device_ip` 表（依赖 device）
3. 最后创建 `device_access_log` 表

### Q4: 字符集问题？
**A:** 确保数据库使用 utf8mb4：
```sql
ALTER DATABASE your_database_name
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

### Q5: 迁移后如何回滚？
**A:** 使用 Phinx 回滚：
```bash
# 回滚最后一次迁移
php vendor/bin/phinx.bat rollback

# 或手动删除表
DROP TABLE IF EXISTS device_access_log;
DROP TABLE IF EXISTS device_ip;
DROP TABLE IF EXISTS device;
```

---

## 📝 迁移后续步骤

### 1. 添加测试数据（可选）
```sql
-- 插入测试设备
INSERT INTO `device`
    (`channel_id`, `department_id`, `device_name`, `device_no`, `device_model`, `status`)
VALUES
    (1, 1, '测试设备1', 'android_test_001', 'Samsung Galaxy S21', 1);

-- 获取设备ID
SET @device_id = LAST_INSERT_ID();

-- 插入测试IP
INSERT INTO `device_ip`
    (`device_id`, `ip_address`, `ip_type`, `status`)
VALUES
    (@device_id, '192.168.1.100', 1, 1),
    (@device_id, '192.168.1.101', 1, 1);
```

### 2. 添加后台菜单
参考：[快速开始指南](../../docs/device_management_quick_start.md)

### 3. 配置中间件（可选）
参考：[完整使用文档](../../docs/device_management.md)

---

## 📞 技术支持

如遇到问题，请：
1. 查看错误日志
2. 检查数据库权限
3. 确认 Phinx 配置文件正确
4. 联系技术支持团队

---

**迁移完成后，请继续阅读：**
- 📗 [快速开始指南](../../docs/device_management_quick_start.md)
- 📘 [完整使用文档](../../docs/device_management.md)
