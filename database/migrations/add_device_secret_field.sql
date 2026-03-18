-- 添加设备密钥字段
-- 用途: 为每个设备生成唯一密钥，用于HMAC签名和JWT验证
-- 日期: 2026-03-16

-- 添加 device_secret 字段
ALTER TABLE `device`
ADD COLUMN `device_secret` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '设备密钥（固化到硬件固件）' AFTER `device_model`,
ADD INDEX `idx_device_secret` (`device_secret`);

-- 为现有设备生成密钥（可选，建议重新手动生成）
-- UPDATE `device`
-- SET `device_secret` = SHA2(CONCAT(`device_no`, '_', UUID()), 256)
-- WHERE `device_secret` = '';

-- 验证字段
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'device'
AND COLUMN_NAME = 'device_secret';

-- 使用说明：
-- 1. 新增设备时，系统自动生成64位随机密钥
-- 2. 密钥只显示一次，管理员需保存到设备固件
-- 3. 密钥用于HMAC签名验证，绝不能泄露
-- 4. 如密钥泄露，需立即重新生成并更新设备固件
