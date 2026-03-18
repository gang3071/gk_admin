-- ================================================================================
-- 代理/店家数据迁移脚本：从PlayerPromoter体系迁移到AdminUser体系
-- ================================================================================
-- 执行日期: 2026-03-12
-- 说明:
--   1. 将代理/店家的分润配置从player_promoter迁移到admin_users
--   2. 更新店家的parent_admin_id关联关系
--   3. 更新玩家的agent_admin_id和store_admin_id关联
--
-- 依赖说明:
--   本脚本依赖 admin_users.player_id 字段来关联旧数据
--   如果该字段不存在，请先创建或确保 admin_users 与 player 有其他关联方式
--
-- 重要提示:
--   - 执行前请务必备份数据库！
--   - 建议在测试环境先执行验证
--   - 执行后通过验证SQL检查数据准确性
-- ================================================================================

-- ================================================================================
-- 第一步：备份相关表（可选但强烈建议）
-- ================================================================================

-- 创建备份表
DROP TABLE IF EXISTS `admin_users_backup_20260312`;
CREATE TABLE `admin_users_backup_20260312` LIKE `admin_users`;
INSERT INTO `admin_users_backup_20260312` SELECT * FROM `admin_users`;

DROP TABLE IF EXISTS `player_backup_20260312`;
CREATE TABLE `player_backup_20260312` LIKE `player`;
INSERT INTO `player_backup_20260312` SELECT * FROM `player`;

DROP TABLE IF EXISTS `player_promoter_backup_20260312`;
CREATE TABLE `player_promoter_backup_20260312` LIKE `player_promoter`;
INSERT INTO `player_promoter_backup_20260312` SELECT * FROM `player_promoter`;

SELECT '备份完成' AS status;

-- ================================================================================
-- 第二步：确保admin_users表已添加必要字段
-- ================================================================================

-- 如果字段不存在则添加（幂等操作）
-- 注意：如果已执行add_agent_fields_to_admin_users.sql，可跳过此步骤

SELECT '检查字段是否存在...' AS status;

SET @dbname = DATABASE();
SET @tablename = 'admin_users';

-- 检查并添加type字段
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'type'
);
SET @query = IF(@col_exists = 0,
    'ALTER TABLE admin_users ADD COLUMN `type` tinyint(4) NOT NULL DEFAULT 1 COMMENT ''用户类型：1=主站，2=渠道，3=代理，4=店家'' AFTER `status`',
    'SELECT ''type字段已存在'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加parent_admin_id字段
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'parent_admin_id'
);
SET @query = IF(@col_exists = 0,
    'ALTER TABLE admin_users ADD COLUMN `parent_admin_id` int(11) DEFAULT NULL COMMENT ''上级管理员ID（店家的上级代理ID）'' AFTER `department_id`',
    'SELECT ''parent_admin_id字段已存在'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加ratio字段
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'ratio'
);
SET @query = IF(@col_exists = 0,
    'ALTER TABLE admin_users ADD COLUMN `ratio` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT ''分润比例（百分比）'' AFTER `parent_admin_id`',
    'SELECT ''ratio字段已存在'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加adjust_amount字段
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'adjust_amount'
);
SET @query = IF(@col_exists = 0,
    'ALTER TABLE admin_users ADD COLUMN `adjust_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT ''分润调整金额'' AFTER `ratio`',
    'SELECT ''adjust_amount字段已存在'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加last_settlement_timestamp字段
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'last_settlement_timestamp'
);
SET @query = IF(@col_exists = 0,
    'ALTER TABLE admin_users ADD COLUMN `last_settlement_timestamp` int(11) DEFAULT NULL COMMENT ''上次结算时间戳'' AFTER `adjust_amount`',
    'SELECT ''last_settlement_timestamp字段已存在'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加settlement_amount字段
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'settlement_amount'
);
SET @query = IF(@col_exists = 0,
    'ALTER TABLE admin_users ADD COLUMN `settlement_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT ''已结算金额'' AFTER `last_settlement_timestamp`',
    'SELECT ''settlement_amount字段已存在'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT '字段检查完成' AS status;

-- ================================================================================
-- 第三步：迁移代理的分润数据到AdminUser
-- ================================================================================

SELECT '开始迁移代理数据...' AS status;

UPDATE admin_users au
JOIN player p ON au.player_id = p.id
    AND p.player_type = 2  -- 旧架构中代理的player_type
    AND p.deleted_at IS NULL
JOIN player_promoter pp ON p.id = pp.player_id
SET
    au.type = 3,  -- AdminUser::TYPE_AGENT
    au.ratio = pp.ratio,
    au.adjust_amount = COALESCE(pp.adjust_amount, 0),
    au.last_settlement_timestamp = CASE
        WHEN pp.last_settlement_timestamp IS NOT NULL
        THEN UNIX_TIMESTAMP(pp.last_settlement_timestamp)
        ELSE NULL
    END,
    au.settlement_amount = COALESCE(pp.settlement_amount, 0),
    au.total_profit_amount = COALESCE(pp.total_profit_amount, 0),
    au.profit_amount = COALESCE(pp.profit_amount, 0),
    au.updated_at = NOW()
WHERE au.player_id IS NOT NULL;

SELECT ROW_COUNT() AS '代理数据迁移数量';

-- ================================================================================
-- 第四步：迁移店家的分润数据到AdminUser
-- ================================================================================

SELECT '开始迁移店家数据...' AS status;

UPDATE admin_users au
JOIN player p ON au.player_id = p.id
    AND p.player_type = 3  -- 旧架构中店家的player_type
    AND p.deleted_at IS NULL
JOIN player_promoter pp ON p.id = pp.player_id
SET
    au.type = 4,  -- AdminUser::TYPE_STORE
    au.ratio = pp.ratio,
    au.adjust_amount = COALESCE(pp.adjust_amount, 0),
    au.last_settlement_timestamp = CASE
        WHEN pp.last_settlement_timestamp IS NOT NULL
        THEN UNIX_TIMESTAMP(pp.last_settlement_timestamp)
        ELSE NULL
    END,
    au.settlement_amount = COALESCE(pp.settlement_amount, 0),
    au.updated_at = NOW()
WHERE au.player_id IS NOT NULL;

SELECT ROW_COUNT() AS '店家数据迁移数量';

-- ================================================================================
-- 第五步：更新店家的parent_admin_id（关联到上级代理）
-- ================================================================================

SELECT '开始更新店家的上级代理关联...' AS status;

UPDATE admin_users store_admin
JOIN player store_player ON store_admin.player_id = store_player.id
    AND store_player.player_type = 3  -- 店家
    AND store_player.deleted_at IS NULL
JOIN player_promoter store_promoter ON store_player.id = store_promoter.player_id
JOIN player agent_player ON store_promoter.recommend_id = agent_player.id
    AND agent_player.player_type = 2  -- 代理
    AND agent_player.deleted_at IS NULL
JOIN admin_users agent_admin ON agent_player.id = agent_admin.player_id
    AND agent_admin.type = 3  -- 确保是代理类型
SET
    store_admin.parent_admin_id = agent_admin.id,
    store_admin.updated_at = NOW()
WHERE
    store_admin.type = 4  -- 确保是店家类型
    AND store_admin.player_id IS NOT NULL;

SELECT ROW_COUNT() AS '店家上级代理关联更新数量';

-- ================================================================================
-- 第六步：更新玩家的store_admin_id（关联到店家）
-- ================================================================================

SELECT '开始更新玩家的店家关联...' AS status;

-- 6.1 更新通过recommend_id关联到店家的玩家
UPDATE player p
JOIN player store_player ON p.recommend_id = store_player.id
    AND store_player.player_type = 3  -- 店家
    AND store_player.deleted_at IS NULL
JOIN admin_users store_admin ON store_player.id = store_admin.player_id
    AND store_admin.type = 4  -- 确保是店家类型
SET
    p.store_admin_id = store_admin.id,
    p.updated_at = NOW()
WHERE
    p.player_type = 1  -- 普通玩家
    AND p.is_promoter = 0  -- 非推广员
    AND p.deleted_at IS NULL;

SELECT ROW_COUNT() AS '玩家店家关联更新数量';

-- ================================================================================
-- 第七步：更新玩家的agent_admin_id（通过店家关联到代理）
-- ================================================================================

SELECT '开始更新玩家的代理关联...' AS status;

UPDATE player p
JOIN admin_users store_admin ON p.store_admin_id = store_admin.id
    AND store_admin.type = 4  -- 店家
JOIN admin_users agent_admin ON store_admin.parent_admin_id = agent_admin.id
    AND agent_admin.type = 3  -- 代理
SET
    p.agent_admin_id = agent_admin.id,
    p.updated_at = NOW()
WHERE
    p.player_type = 1  -- 普通玩家
    AND p.is_promoter = 0  -- 非推广员
    AND p.store_admin_id IS NOT NULL
    AND p.deleted_at IS NULL;

SELECT ROW_COUNT() AS '玩家代理关联更新数量';

-- ================================================================================
-- 第八步：数据验证
-- ================================================================================

SELECT '开始数据验证...' AS status;

-- 验证1: 检查代理数据迁移是否完整
SELECT
    '代理数据验证' AS '验证项',
    COUNT(*) AS '代理总数',
    SUM(CASE WHEN ratio > 0 THEN 1 ELSE 0 END) AS '有分润比例的代理数',
    SUM(CASE WHEN last_settlement_timestamp IS NOT NULL THEN 1 ELSE 0 END) AS '有结算记录的代理数'
FROM admin_users
WHERE type = 3 AND deleted_at IS NULL;

-- 验证2: 检查店家数据迁移是否完整
SELECT
    '店家数据验证' AS '验证项',
    COUNT(*) AS '店家总数',
    SUM(CASE WHEN parent_admin_id IS NOT NULL THEN 1 ELSE 0 END) AS '已关联上级代理的店家数',
    SUM(CASE WHEN ratio > 0 THEN 1 ELSE 0 END) AS '有分润比例的店家数'
FROM admin_users
WHERE type = 4 AND deleted_at IS NULL;

-- 验证3: 检查玩家关联是否完整
SELECT
    '玩家关联验证' AS '验证项',
    COUNT(*) AS '玩家总数',
    SUM(CASE WHEN store_admin_id IS NOT NULL THEN 1 ELSE 0 END) AS '已关联店家的玩家数',
    SUM(CASE WHEN agent_admin_id IS NOT NULL THEN 1 ELSE 0 END) AS '已关联代理的玩家数'
FROM player
WHERE player_type = 1 AND is_promoter = 0 AND deleted_at IS NULL;

-- 验证4: 检查店家与代理的层级关系
SELECT
    '层级关系验证' AS '验证项',
    agent.id AS '代理ID',
    agent.username AS '代理账号',
    agent.ratio AS '代理分润比例',
    COUNT(store.id) AS '下级店家数量',
    GROUP_CONCAT(store.username SEPARATOR ', ') AS '店家账号列表'
FROM admin_users agent
LEFT JOIN admin_users store ON agent.id = store.parent_admin_id AND store.type = 4
WHERE agent.type = 3 AND agent.deleted_at IS NULL
GROUP BY agent.id, agent.username, agent.ratio
ORDER BY agent.id
LIMIT 10;

-- 验证5: 检查分润比例合理性（店家ratio应该 >= 代理ratio）
SELECT
    '分润比例异常检查' AS '验证项',
    COUNT(*) AS '异常数量',
    GROUP_CONCAT(
        CONCAT('店家:', store.username, '(', store.ratio, '%) < 代理:', agent.username, '(', agent.ratio, '%)')
        SEPARATOR '; '
    ) AS '异常详情'
FROM admin_users store
JOIN admin_users agent ON store.parent_admin_id = agent.id AND agent.type = 3
WHERE
    store.type = 4
    AND store.ratio < agent.ratio
    AND store.deleted_at IS NULL
    AND agent.deleted_at IS NULL;

-- 验证6: 检查孤立的店家（没有上级代理）
SELECT
    '孤立店家检查' AS '验证项',
    COUNT(*) AS '孤立店家数量',
    GROUP_CONCAT(username SEPARATOR ', ') AS '孤立店家列表'
FROM admin_users
WHERE
    type = 4
    AND parent_admin_id IS NULL
    AND deleted_at IS NULL;

-- 验证7: 检查孤立的玩家（没有关联店家或代理）
SELECT
    '孤立玩家检查' AS '验证项',
    COUNT(*) AS '无店家关联的玩家数',
    (SELECT COUNT(*) FROM player
     WHERE player_type = 1 AND is_promoter = 0
     AND agent_admin_id IS NULL AND deleted_at IS NULL) AS '无代理关联的玩家数'
FROM player
WHERE
    player_type = 1
    AND is_promoter = 0
    AND store_admin_id IS NULL
    AND deleted_at IS NULL;

-- ================================================================================
-- 第九步：创建数据完整性视图（可选，用于后续对比验证）
-- ================================================================================

DROP VIEW IF EXISTS `v_agent_migration_comparison`;

CREATE VIEW `v_agent_migration_comparison` AS
SELECT
    'OLD' AS source,
    p.id AS player_id,
    p.phone AS account,
    CASE p.player_type
        WHEN 2 THEN '代理'
        WHEN 3 THEN '店家'
        ELSE '未知'
    END AS type_name,
    pp.ratio AS ratio,
    pp.adjust_amount,
    pp.last_settlement_timestamp AS settlement_time,
    (SELECT COUNT(*) FROM player_promoter pp2 WHERE pp2.recommend_id = pp.player_id) AS child_count
FROM player p
JOIN player_promoter pp ON p.id = pp.player_id
WHERE p.player_type IN (2, 3) AND p.deleted_at IS NULL

UNION ALL

SELECT
    'NEW' AS source,
    au.player_id,
    au.username AS account,
    CASE au.type
        WHEN 3 THEN '代理'
        WHEN 4 THEN '店家'
        ELSE '未知'
    END AS type_name,
    au.ratio,
    au.adjust_amount,
    FROM_UNIXTIME(au.last_settlement_timestamp) AS settlement_time,
    (SELECT COUNT(*) FROM admin_users au2 WHERE au2.parent_admin_id = au.id) AS child_count
FROM admin_users au
WHERE au.type IN (3, 4) AND au.deleted_at IS NULL;

SELECT '迁移对比视图已创建: v_agent_migration_comparison' AS status;

-- ================================================================================
-- 第十步：添加索引优化查询性能
-- ================================================================================

SELECT '开始创建索引...' AS status;

-- admin_users表索引
ALTER TABLE `admin_users` ADD INDEX IF NOT EXISTS `idx_type` (`type`);
ALTER TABLE `admin_users` ADD INDEX IF NOT EXISTS `idx_parent_admin_id` (`parent_admin_id`);
ALTER TABLE `admin_users` ADD INDEX IF NOT EXISTS `idx_player_id` (`player_id`);

-- player表索引（如果不存在）
ALTER TABLE `player` ADD INDEX IF NOT EXISTS `idx_agent_admin_id` (`agent_admin_id`);
ALTER TABLE `player` ADD INDEX IF NOT EXISTS `idx_store_admin_id` (`store_admin_id`);
ALTER TABLE `player` ADD INDEX IF NOT EXISTS `idx_player_type` (`player_type`);

SELECT '索引创建完成' AS status;

-- ================================================================================
-- 迁移完成总结
-- ================================================================================

SELECT
    '==================== 迁移完成 ====================' AS '';

SELECT
    '迁移统计' AS '类型',
    (SELECT COUNT(*) FROM admin_users WHERE type = 3 AND deleted_at IS NULL) AS '代理数量',
    (SELECT COUNT(*) FROM admin_users WHERE type = 4 AND deleted_at IS NULL) AS '店家数量',
    (SELECT COUNT(*) FROM player WHERE store_admin_id IS NOT NULL AND deleted_at IS NULL) AS '已关联店家的玩家',
    (SELECT COUNT(*) FROM player WHERE agent_admin_id IS NOT NULL AND deleted_at IS NULL) AS '已关联代理的玩家';

SELECT
    '备份表' AS '说明',
    'admin_users_backup_20260312, player_backup_20260312, player_promoter_backup_20260312' AS '表名',
    '如迁移正常，可在确认后删除备份表' AS '建议';

SELECT
    '下一步' AS '操作',
    '1. 检查上述验证结果是否正常' AS '步骤1',
    '2. 在应用中测试代理登录和数据中心功能' AS '步骤2',
    '3. 确认无误后，可考虑清理player_promoter中的代理/店家记录（保留真正的推广员）' AS '步骤3';

-- ================================================================================
-- 清理旧数据的SQL（可选，请谨慎执行）
-- ================================================================================

/*
-- 警告：仅在确认迁移完全成功后执行！
-- 这些SQL会删除player_promoter中代理和店家的记录（保留真正的推广员）

-- 删除代理的PlayerPromoter记录
DELETE pp FROM player_promoter pp
JOIN player p ON pp.player_id = p.id
WHERE p.player_type = 2 AND p.deleted_at IS NULL;

-- 删除店家的PlayerPromoter记录
DELETE pp FROM player_promoter pp
JOIN player p ON pp.player_id = p.id
WHERE p.player_type = 3 AND p.deleted_at IS NULL;

-- 删除备份表（在完全确认后）
DROP TABLE IF EXISTS admin_users_backup_20260312;
DROP TABLE IF EXISTS player_backup_20260312;
DROP TABLE IF EXISTS player_promoter_backup_20260312;
*/
