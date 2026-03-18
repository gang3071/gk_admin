-- 设备管理菜单和权限配置
-- 用途: 为渠道后台添加设备管理相关菜单
-- 日期: 2026-03-16

-- 插入设备管理菜单
-- 注意: 请根据实际的父级菜单ID调整 pid 值
-- 建议将设备管理放在渠道管理模块下

-- 1. 设备管理父级菜单
INSERT INTO `admin_menus` (`name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`)
VALUES ('设备管理', 'LaptopOutlined', '', '', 0, 100, 1, 1, NOW(), NULL);

-- 获取刚插入的父级菜单ID（需要手动调整或使用变量）
SET @device_parent_id = LAST_INSERT_ID();

-- 2. 设备列表子菜单
INSERT INTO `admin_menus` (`name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`)
VALUES (
    '设备列表',
    'UnorderedListOutlined',
    'ex-admin/addons-webman-controller-ChannelDeviceController/index',
    '',
    @device_parent_id,
    1,
    1,
    1,
    NOW(),
    NULL
);

-- 3. 设备访问日志子菜单
INSERT INTO `admin_menus` (`name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`)
VALUES (
    '访问日志',
    'FileTextOutlined',
    'ex-admin/addons-webman-controller-ChannelDeviceAccessLogController/index',
    '',
    @device_parent_id,
    2,
    1,
    1,
    NOW(),
    NULL
);

-- 查询结果验证
SELECT id, name, url, pid, sort
FROM `admin_menus`
WHERE name IN ('设备管理', '设备列表', '访问日志')
ORDER BY pid, sort;
