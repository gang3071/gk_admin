-- 设备管理表迁移脚本
-- 创建时间: 2026-03-16
-- 用途: 渠道设备管理和IP绑定，用于防御网络攻击

-- ============================================
-- 1. 创建设备表（device）
-- ============================================
CREATE TABLE IF NOT EXISTS `device` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `channel_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属渠道ID',
    `department_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属部门ID',
    `device_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '设备名称',
    `device_no` VARCHAR(100) NOT NULL COMMENT '设备号（安卓设备唯一标识）',
    `device_model` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '设备型号',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态(0:禁用,1:启用)',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
    `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT '删除时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_device_no` (`device_no`, `deleted_at`) COMMENT '设备号唯一索引',
    KEY `idx_channel_id` (`channel_id`) COMMENT '渠道索引',
    KEY `idx_department_id` (`department_id`) COMMENT '部门索引',
    KEY `idx_status` (`status`) COMMENT '状态索引'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备管理表';

-- ============================================
-- 2. 创建设备IP绑定表（device_ip）
-- ============================================
CREATE TABLE IF NOT EXISTS `device_ip` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `device_id` BIGINT UNSIGNED NOT NULL COMMENT '设备ID',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址（支持IPv4和IPv6）',
    `ip_type` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'IP类型(1:IPv4,2:IPv6)',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态(0:禁用,1:启用)',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `last_used_at` TIMESTAMP NULL DEFAULT NULL COMMENT '最后使用时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
    `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_device_ip` (`device_id`, `ip_address`) COMMENT '设备IP唯一索引',
    KEY `idx_ip_address` (`ip_address`) COMMENT 'IP地址索引',
    KEY `idx_status` (`status`) COMMENT '状态索引',
    CONSTRAINT `fk_device_ip_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备IP绑定表';

-- ============================================
-- 3. 创建设备访问日志表（device_access_log）
-- ============================================
CREATE TABLE IF NOT EXISTS `device_access_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `device_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '设备ID',
    `device_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '设备号',
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '访问IP地址',
    `is_allowed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否允许(0:拒绝,1:允许)',
    `reject_reason` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '拒绝原因',
    `request_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '请求URL',
    `user_agent` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'User Agent',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_device_id` (`device_id`) COMMENT '设备ID索引',
    KEY `idx_device_no` (`device_no`) COMMENT '设备号索引',
    KEY `idx_ip_address` (`ip_address`) COMMENT 'IP地址索引',
    KEY `idx_created_at` (`created_at`) COMMENT '创建时间索引'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备访问日志表';

-- ============================================
-- 4. 插入示例数据（可选）
-- ============================================
-- INSERT INTO `device` (`channel_id`, `department_id`, `device_name`, `device_no`, `device_model`, `status`, `remark`)
-- VALUES
--     (1, 1, '测试设备1', 'android_device_001', 'Samsung Galaxy S21', 1, '用于测试的设备'),
--     (1, 1, '测试设备2', 'android_device_002', 'Xiaomi Mi 11', 1, '备用测试设备');

-- INSERT INTO `device_ip` (`device_id`, `ip_address`, `ip_type`, `status`, `remark`)
-- VALUES
--     (1, '192.168.1.100', 1, 1, '办公室IP'),
--     (1, '192.168.1.101', 1, 1, '备用IP'),
--     (2, '10.0.0.50', 1, 1, '测试环境IP');
