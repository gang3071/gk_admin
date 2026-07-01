-- =====================================================
-- 更新代理后台摸奖券菜单名称
-- 文件: update_agent_lottery_menu_name.sql
-- 日期: 2026-06-15
-- 说明: 将旧的菜单名称 agent_lottery_ticket_win_record_list
--       更新为 agent_lottery_ticket_record_list
-- =====================================================

-- 1. 检查旧菜单是否存在
SELECT
    id,
    name,
    url,
    pid,
    sort
FROM admin_menus
WHERE name = 'agent_lottery_ticket_win_record_list'
  AND plugin = 'webman';

-- 2. 更新菜单名称（如果存在旧菜单）
UPDATE admin_menus
SET
    name = 'agent_lottery_ticket_record_list',
    url = 'ex-admin/addons-webman-controller-AgentLotteryTicketRecordController/index',
    updated_at = NOW()
WHERE
    name = 'agent_lottery_ticket_win_record_list'
    AND plugin = 'webman';

-- 3. 验证更新结果
SELECT
    m1.id AS parent_id,
    m1.name AS parent_name,
    m2.id AS child_id,
    m2.name AS child_name,
    m2.url AS child_url,
    m2.sort
FROM admin_menus m1
LEFT JOIN admin_menus m2 ON m1.id = m2.pid
WHERE m1.name = 'agent_lottery_ticket_management'
  AND m1.plugin = 'webman'
ORDER BY m2.sort;

-- =====================================================
-- 预期结果：
-- parent_name: agent_lottery_ticket_management
-- child_name:
--   - agent_lottery_ticket_activity_list (sort=1)
--   - agent_lottery_ticket_list (sort=2)
--   - agent_lottery_ticket_record_list (sort=3) ✅
-- =====================================================

-- 4. 检查是否有重复的菜单
SELECT
    name,
    COUNT(*) as count
FROM admin_menus
WHERE name IN (
    'agent_lottery_ticket_management',
    'agent_lottery_ticket_activity_list',
    'agent_lottery_ticket_list',
    'agent_lottery_ticket_record_list',
    'agent_lottery_ticket_win_record_list'
)
AND plugin = 'webman'
GROUP BY name
HAVING count > 1;

-- =====================================================
-- 如果发现重复菜单，执行以下清理：
-- =====================================================

/*
-- 删除重复的菜单（保留ID最小的）
DELETE m1
FROM admin_menus m1
INNER JOIN admin_menus m2
WHERE m1.id > m2.id
  AND m1.name = m2.name
  AND m1.plugin = 'webman'
  AND m1.name LIKE 'agent_lottery_ticket%';
*/

-- =====================================================
-- 完全重建方案（可选）
-- 如果遇到问题，可以删除旧菜单后重新执行迁移
-- =====================================================

/*
-- 删除所有代理后台摸奖券菜单
DELETE FROM admin_menus
WHERE name IN (
    'agent_lottery_ticket_management',
    'agent_lottery_ticket_activity_list',
    'agent_lottery_ticket_list',
    'agent_lottery_ticket_record_list',
    'agent_lottery_ticket_win_record_list'
)
AND plugin = 'webman';

-- 然后重新执行迁移文件
-- source D:/gk_admin/20260614120000_add_agent_lottery_ticket_menus.sql
*/
