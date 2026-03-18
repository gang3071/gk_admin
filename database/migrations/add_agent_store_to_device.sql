-- 设备表添加代理和店家字段
-- 创建时间: 2026-03-16
-- 用途: 为设备管理添加代理和店家绑定功能

-- 检查字段是否存在，如果不存在则添加
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'device'
    AND COLUMN_NAME = 'agent_admin_id');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE `device` ADD COLUMN `agent_admin_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''所属代理ID（AdminUser.type=3）'' AFTER `department_id`',
    'SELECT ''Column agent_admin_id already exists.'' AS msg');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加店家字段
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'device'
    AND COLUMN_NAME = 'store_admin_id');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE `device` ADD COLUMN `store_admin_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''所属店家ID（AdminUser.type=4）'' AFTER `agent_admin_id`',
    'SELECT ''Column store_admin_id already exists.'' AS msg');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加索引
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'device'
    AND INDEX_NAME = 'idx_agent_admin_id');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE `device` ADD INDEX `idx_agent_admin_id` (`agent_admin_id`)',
    'SELECT ''Index idx_agent_admin_id already exists.'' AS msg');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加店家索引
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'device'
    AND INDEX_NAME = 'idx_store_admin_id');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE `device` ADD INDEX `idx_store_admin_id` (`store_admin_id`)',
    'SELECT ''Index idx_store_admin_id already exists.'' AS msg');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 显示结果
SELECT
    'agent_admin_id' AS field_name,
    CASE WHEN COUNT(*) > 0 THEN '✅ 已添加' ELSE '❌ 未添加' END AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'device'
    AND COLUMN_NAME = 'agent_admin_id'
UNION ALL
SELECT
    'store_admin_id' AS field_name,
    CASE WHEN COUNT(*) > 0 THEN '✅ 已添加' ELSE '❌ 未添加' END AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'device'
    AND COLUMN_NAME = 'store_admin_id';
