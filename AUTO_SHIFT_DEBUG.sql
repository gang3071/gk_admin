-- ============================================
-- 自动交班无法关闭问题排查 SQL
-- ============================================

-- 1. 检查自动交班配置表中的所有记录
SELECT
    id,
    department_id,
    bind_admin_user_id,
    is_enabled,
    shift_time_1,
    shift_time_2,
    shift_time_3,
    last_shift_time,
    next_shift_time,
    created_at,
    updated_at
FROM yjb_store_auto_shift_config
ORDER BY department_id, bind_admin_user_id;

-- 2. 检查是否存在重复的配置记录（同一个店家有多条记录）
SELECT
    department_id,
    bind_admin_user_id,
    COUNT(*) as 记录数量,
    GROUP_CONCAT(id) as 配置ID列表,
    GROUP_CONCAT(is_enabled) as 启用状态列表
FROM yjb_store_auto_shift_config
GROUP BY department_id, bind_admin_user_id
HAVING COUNT(*) > 1;

-- 3. 检查特定店家的配置（替换 YOUR_DEPARTMENT_ID 和 YOUR_ADMIN_USER_ID）
-- SELECT
--     *
-- FROM yjb_store_auto_shift_config
-- WHERE department_id = YOUR_DEPARTMENT_ID
--   AND bind_admin_user_id = YOUR_ADMIN_USER_ID;

-- 4. 检查是否有启用状态的配置（应该为空或 is_enabled = 0）
SELECT
    id,
    department_id,
    bind_admin_user_id,
    is_enabled,
    last_shift_time,
    next_shift_time
FROM yjb_store_auto_shift_config
WHERE is_enabled = 1;

-- 5. 检查最近的交班记录
SELECT
    id,
    department_id,
    bind_admin_user_id,
    start_time,
    end_time,
    is_auto_shift,
    created_at
FROM yjb_store_agent_shift_handover_record
ORDER BY created_at DESC
LIMIT 20;
