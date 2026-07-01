-- 设备列表调试查询语句
-- 用于检查 StorePlayerController/index 为什么没有设备

-- ============================================
-- 1. 检查当前店家的基本信息
-- ============================================
-- 替换 {admin_id} 为当前登录的管理员ID
SELECT
    id,
    username,
    department_id,
    status
FROM admin_user
WHERE id = {admin_id};

-- ============================================
-- 2. 检查部门下所有玩家（设备）
-- ============================================
-- 替换 {department_id} 为上一步查到的 department_id
SELECT
    id,
    name,
    phone,
    department_id,
    store_admin_id,
    is_promoter,
    status,
    created_at,
    deleted_at
FROM player
WHERE department_id = {department_id}
  AND is_promoter = 0
ORDER BY id DESC
LIMIT 20;

-- ============================================
-- 3. 检查分配给当前店家的设备
-- ============================================
-- 替换 {department_id} 和 {admin_id}
SELECT
    id,
    name,
    phone,
    department_id,
    store_admin_id,
    is_promoter,
    status,
    created_at
FROM player
WHERE department_id = {department_id}
  AND store_admin_id = {admin_id}
  AND is_promoter = 0
  AND deleted_at IS NULL
ORDER BY id DESC;

-- ============================================
-- 4. 统计各种情况的设备数量
-- ============================================
-- 替换 {department_id} 和 {admin_id}
SELECT
    '该部门下总设备数' as description,
    COUNT(*) as count
FROM player
WHERE department_id = {department_id}
  AND is_promoter = 0
  AND deleted_at IS NULL

UNION ALL

SELECT
    '分配给当前店家的设备' as description,
    COUNT(*) as count
FROM player
WHERE department_id = {department_id}
  AND store_admin_id = {admin_id}
  AND is_promoter = 0
  AND deleted_at IS NULL

UNION ALL

SELECT
    '未分配店家的设备(store_admin_id=0或NULL)' as description,
    COUNT(*) as count
FROM player
WHERE department_id = {department_id}
  AND (store_admin_id = 0 OR store_admin_id IS NULL)
  AND is_promoter = 0
  AND deleted_at IS NULL

UNION ALL

SELECT
    '已软删除的设备' as description,
    COUNT(*) as count
FROM player
WHERE department_id = {department_id}
  AND is_promoter = 0
  AND deleted_at IS NOT NULL;

-- ============================================
-- 5. 检查设备关联的扩展数据和钱包
-- ============================================
-- 替换 {department_id} 和 {admin_id}
SELECT
    p.id,
    p.name,
    p.phone,
    p.store_admin_id,
    pe.present_in_amount,
    pe.present_out_amount,
    pc.money as wallet_money
FROM player p
LEFT JOIN player_extend pe ON p.id = pe.player_id
LEFT JOIN player_platform_cash pc ON p.id = pc.player_id AND pc.platform_id = 1
WHERE p.department_id = {department_id}
  AND p.store_admin_id = {admin_id}
  AND p.is_promoter = 0
  AND p.deleted_at IS NULL
ORDER BY p.id DESC
LIMIT 20;

-- ============================================
-- 6. 检查 store_admin_id 的分布情况
-- ============================================
-- 替换 {department_id}
SELECT
    store_admin_id,
    COUNT(*) as device_count,
    (SELECT username FROM admin_user WHERE id = player.store_admin_id LIMIT 1) as store_name
FROM player
WHERE department_id = {department_id}
  AND is_promoter = 0
  AND deleted_at IS NULL
GROUP BY store_admin_id
ORDER BY device_count DESC;

-- ============================================
-- 7. 完整的列表查询（与代码一致）
-- ============================================
-- 替换 {department_id} 和 {admin_id}
SELECT
    player.*,
    cash.money as wallet_money,
    player_extend.present_in_amount,
    player_extend.present_out_amount
FROM player
LEFT JOIN player_platform_cash as cash
    ON player.id = cash.player_id
    AND cash.platform_id = 1
LEFT JOIN player_extend
    ON player.id = player_extend.player_id
WHERE player.department_id = {department_id}
  AND player.store_admin_id = {admin_id}
  AND player.is_promoter = 0
  AND player.deleted_at IS NULL
ORDER BY player.id DESC
LIMIT 20;

-- ============================================
-- 使用示例
-- ============================================
-- 假设当前登录的管理员ID是 10，department_id 是 84
-- 将所有 {admin_id} 替换为 10
-- 将所有 {department_id} 替换为 84
-- 然后在数据库中执行这些查询
