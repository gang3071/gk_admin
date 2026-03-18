-- 代理/店家体系重构：将代理相关字段从player_promoter迁移到admin_users
-- 执行日期: 2026-03-12

-- 1. 添加用户类型和层级关系字段（如果不存在）
ALTER TABLE `admin_users`
ADD COLUMN IF NOT EXISTS `type` tinyint(4) NOT NULL DEFAULT 1 COMMENT '用户类型：1=主站，2=渠道，3=代理，4=店家' AFTER `status`,
ADD COLUMN IF NOT EXISTS `parent_admin_id` int(11) DEFAULT NULL COMMENT '上级管理员ID（店家的上级代理ID）' AFTER `department_id`,
ADD COLUMN IF NOT EXISTS `is_super` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否渠道超级管理员：0=否，1=是' AFTER `parent_admin_id`;

-- 2. 添加代理分润相关字段
ALTER TABLE `admin_users`
ADD COLUMN IF NOT EXISTS `ratio` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '分润比例（百分比）' AFTER `is_super`,
ADD COLUMN IF NOT EXISTS `adjust_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '分润调整金额' AFTER `ratio`,
ADD COLUMN IF NOT EXISTS `last_settlement_timestamp` int(11) DEFAULT NULL COMMENT '上次结算时间戳' AFTER `adjust_amount`,
ADD COLUMN IF NOT EXISTS `settlement_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '已结算金额' AFTER `last_settlement_timestamp`,
ADD COLUMN IF NOT EXISTS `total_profit_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '总分润金额（历史累计）' AFTER `settlement_amount`,
ADD COLUMN IF NOT EXISTS `profit_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '当前分润金额（待结算）' AFTER `total_profit_amount`;

-- 3. 添加索引优化查询性能
ALTER TABLE `admin_users`
ADD INDEX IF NOT EXISTS `idx_type` (`type`),
ADD INDEX IF NOT EXISTS `idx_parent_admin_id` (`parent_admin_id`),
ADD INDEX IF NOT EXISTS `idx_department_id` (`department_id`);

-- 4. 数据迁移说明
-- 注意：此脚本只添加字段结构，不做数据迁移
-- 如果需要从旧的player_promoter表迁移数据，请执行 migrate_promoter_to_admin_system.sql
--
-- 迁移依赖说明：
--   如果 admin_users 表中已有 player_id 字段（来自之前的迁移），
--   可以通过它关联到 player 和 player_promoter 表进行数据迁移。
--   迁移完成后，player_id 字段仅用于历史追溯，新架构不再使用。
