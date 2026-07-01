-- =====================================================
-- 代理后台摸奖券管理菜单 - SQL 迁移脚本
-- 文件: 20260614120000_add_agent_lottery_ticket_menus.sql
-- 日期: 2026-06-14
-- 说明: 添加代理后台的摸奖券管理菜单（三个子菜单）
-- =====================================================

-- 1. 插入父级菜单：摸奖券管理
-- type = 3 表示代理菜单 (AdminDepartment::TYPE_AGENT)
INSERT INTO `admin_menus` (`name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `type`, `created_at`, `updated_at`)
VALUES ('agent_lottery_ticket_management', 'el-icon-present', '', 'webman', 0, 150, 1, 0, 3, NOW(), NOW());

-- 获取刚插入的父级菜单ID（用于后续插入）
SET @parent_menu_id = LAST_INSERT_ID();

-- 2. 插入子菜单：摸奖券活动
INSERT INTO `admin_menus` (`name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `type`, `created_at`, `updated_at`)
VALUES (
    'agent_lottery_ticket_activity_list',
    '',
    'ex-admin/addons-webman-controller-AgentLotteryTicketActivityController/index',
    'webman',
    @parent_menu_id,
    1,
    1,
    0,
    3,
    NOW(),
    NOW()
);

-- 3. 插入子菜单：摸奖券列表
INSERT INTO `admin_menus` (`name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `type`, `created_at`, `updated_at`)
VALUES (
    'agent_lottery_ticket_list',
    '',
    'ex-admin/addons-webman-controller-AgentLotteryTicketController/index',
    'webman',
    @parent_menu_id,
    2,
    1,
    0,
    3,
    NOW(),
    NOW()
);

-- 4. 插入子菜单：中奖记录
INSERT INTO `admin_menus` (`name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `type`, `created_at`, `updated_at`)
VALUES (
    'agent_lottery_ticket_record_list',
    '',
    'ex-admin/addons-webman-controller-AgentLotteryTicketRecordController/index',
    'webman',
    @parent_menu_id,
    3,
    1,
    0,
    3,
    NOW(),
    NOW()
);

-- =====================================================
-- 验证插入结果
-- =====================================================
SELECT
    m1.id AS parent_id,
    m1.name AS parent_name,
    m1.icon,
    m1.type,
    m2.id AS child_id,
    m2.name AS child_name,
    m2.url AS child_url,
    m2.sort
FROM admin_menus m1
LEFT JOIN admin_menus m2 ON m1.id = m2.pid
WHERE m1.name = 'agent_lottery_ticket_management'
ORDER BY m2.sort;

-- =====================================================
-- 回滚脚本（如需删除）
-- =====================================================
/*
-- 删除子菜单
DELETE FROM `admin_menus` WHERE `name` = 'agent_lottery_ticket_record_list' AND `plugin` = 'webman';
DELETE FROM `admin_menus` WHERE `name` = 'agent_lottery_ticket_list' AND `plugin` = 'webman';
DELETE FROM `admin_menus` WHERE `name` = 'agent_lottery_ticket_activity_list' AND `plugin` = 'webman';

-- 删除父级菜单
DELETE FROM `admin_menus` WHERE `name` = 'agent_lottery_ticket_management' AND `plugin` = 'webman';
*/
