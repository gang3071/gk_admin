-- ================================================================================
-- 代理/店家数据迁移验证脚本
-- ================================================================================
-- 执行时机: 在migrate_promoter_to_admin_system.sql执行后
-- 用途: 详细验证数据迁移的准确性和完整性
-- ================================================================================

SET @line = '================================================================================';

-- ================================================================================
-- 1. 总体统计对比
-- ================================================================================

SELECT @line AS '';
SELECT '1. 总体统计对比' AS '验证项目';
SELECT @line AS '';

-- 旧架构统计
SELECT
    '旧架构(Player+PlayerPromoter)' AS '数据源',
    COUNT(CASE WHEN p.player_type = 2 THEN 1 END) AS '代理数量',
    COUNT(CASE WHEN p.player_type = 3 THEN 1 END) AS '店家数量',
    COUNT(CASE WHEN p.player_type = 1 AND p.is_promoter = 0 THEN 1 END) AS '普通玩家数量'
FROM player p
LEFT JOIN player_promoter pp ON p.id = pp.player_id
WHERE p.deleted_at IS NULL;

-- 新架构统计
SELECT
    '新架构(AdminUser)' AS '数据源',
    COUNT(CASE WHEN au.type = 3 THEN 1 END) AS '代理数量',
    COUNT(CASE WHEN au.type = 4 THEN 1 END) AS '店家数量',
    COUNT(CASE WHEN p.player_type = 1 AND p.is_promoter = 0 THEN 1 END) AS '普通玩家数量'
FROM admin_users au
LEFT JOIN player p ON 1=1
WHERE au.deleted_at IS NULL AND p.deleted_at IS NULL
GROUP BY NULL;

-- ================================================================================
-- 2. 代理数据详细对比
-- ================================================================================

SELECT @line AS '';
SELECT '2. 代理数据详细对比' AS '验证项目';
SELECT @line AS '';

SELECT
    au.id AS '代理后台ID',
    au.username AS '代理账号',
    au.player_id AS '关联Player_ID',
    p.phone AS '原玩家手机号',
    CONCAT(au.ratio, '%') AS '新架构分润比例',
    CONCAT(pp.ratio, '%') AS '旧架构分润比例',
    CASE
        WHEN au.ratio = pp.ratio THEN '✓ 一致'
        ELSE '✗ 不一致'
    END AS '比例校验',
    au.adjust_amount AS '新调整金额',
    pp.adjust_amount AS '旧调整金额',
    CASE
        WHEN COALESCE(au.adjust_amount, 0) = COALESCE(pp.adjust_amount, 0) THEN '✓'
        ELSE '✗'
    END AS '金额校验',
    FROM_UNIXTIME(au.last_settlement_timestamp) AS '新结算时间',
    pp.last_settlement_timestamp AS '旧结算时间',
    (SELECT COUNT(*) FROM admin_users WHERE parent_admin_id = au.id) AS '新架构下级店家数',
    (SELECT COUNT(*) FROM player_promoter WHERE recommend_id = pp.player_id) AS '旧架构下级店家数'
FROM admin_users au
JOIN player p ON au.player_id = p.id AND p.player_type = 2
LEFT JOIN player_promoter pp ON p.id = pp.player_id
WHERE au.type = 3 AND au.deleted_at IS NULL AND p.deleted_at IS NULL
ORDER BY au.id;

-- ================================================================================
-- 3. 店家数据详细对比
-- ================================================================================

SELECT @line AS '';
SELECT '3. 店家数据详细对比' AS '验证项目';
SELECT @line AS '';

SELECT
    store_au.id AS '店家后台ID',
    store_au.username AS '店家账号',
    store_au.player_id AS '关联Player_ID',
    store_p.phone AS '原玩家手机号',
    agent_au.username AS '新架构上级代理',
    agent_p.phone AS '旧架构上级代理手机号',
    CASE
        WHEN agent_au.player_id = store_pp.recommend_id THEN '✓ 一致'
        ELSE '✗ 不一致'
    END AS '上级校验',
    CONCAT(store_au.ratio, '%') AS '新分润比例',
    CONCAT(store_pp.ratio, '%') AS '旧分润比例',
    (SELECT COUNT(*) FROM player WHERE store_admin_id = store_au.id) AS '新架构下级玩家数',
    (SELECT COUNT(*) FROM player WHERE recommend_id = store_p.id) AS '旧架构下级玩家数'
FROM admin_users store_au
JOIN player store_p ON store_au.player_id = store_p.id AND store_p.player_type = 3
LEFT JOIN player_promoter store_pp ON store_p.id = store_pp.player_id
LEFT JOIN admin_users agent_au ON store_au.parent_admin_id = agent_au.id
LEFT JOIN player agent_p ON agent_au.player_id = agent_p.id
WHERE store_au.type = 4 AND store_au.deleted_at IS NULL AND store_p.deleted_at IS NULL
ORDER BY store_au.id;

-- ================================================================================
-- 4. 玩家关联验证
-- ================================================================================

SELECT @line AS '';
SELECT '4. 玩家关联验证' AS '验证项目';
SELECT @line AS '';

-- 按店家统计玩家数量对比
SELECT
    store_au.username AS '店家账号',
    COUNT(DISTINCT CASE WHEN p.store_admin_id = store_au.id THEN p.id END) AS '新架构玩家数',
    COUNT(DISTINCT CASE WHEN p.recommend_id = store_p.id THEN p.id END) AS '旧架构玩家数',
    CASE
        WHEN COUNT(DISTINCT CASE WHEN p.store_admin_id = store_au.id THEN p.id END) =
             COUNT(DISTINCT CASE WHEN p.recommend_id = store_p.id THEN p.id END)
        THEN '✓ 一致'
        ELSE '✗ 不一致'
    END AS '玩家数校验'
FROM admin_users store_au
JOIN player store_p ON store_au.player_id = store_p.id AND store_p.player_type = 3
LEFT JOIN player p ON p.deleted_at IS NULL AND p.player_type = 1 AND p.is_promoter = 0
WHERE store_au.type = 4 AND store_au.deleted_at IS NULL
GROUP BY store_au.id, store_au.username, store_p.id
ORDER BY store_au.username;

-- ================================================================================
-- 5. 分润金额汇总对比
-- ================================================================================

SELECT @line AS '';
SELECT '5. 分润金额汇总对比' AS '验证项目';
SELECT @line AS '';

SELECT
    '代理分润金额统计' AS '统计项',
    SUM(au.total_profit_amount) AS '新架构总分润',
    SUM(pp.total_profit_amount) AS '旧架构总分润',
    SUM(au.profit_amount) AS '新架构当前分润',
    SUM(pp.profit_amount) AS '旧架构当前分润',
    SUM(au.settlement_amount) AS '新架构已结算',
    SUM(pp.settlement_amount) AS '旧架构已结算'
FROM admin_users au
JOIN player p ON au.player_id = p.id AND p.player_type = 2
LEFT JOIN player_promoter pp ON p.id = pp.player_id
WHERE au.type = 3 AND au.deleted_at IS NULL;

-- ================================================================================
-- 6. 异常数据检查
-- ================================================================================

SELECT @line AS '';
SELECT '6. 异常数据检查' AS '验证项目';
SELECT @line AS '';

-- 6.1 检查没有迁移成功的代理
SELECT
    '未迁移的代理' AS '异常类型',
    p.id AS 'Player_ID',
    p.phone AS '手机号',
    pp.ratio AS '分润比例',
    '未找到对应的AdminUser记录' AS '异常原因'
FROM player p
JOIN player_promoter pp ON p.id = pp.player_id
LEFT JOIN admin_users au ON p.id = au.player_id AND au.type = 3
WHERE p.player_type = 2 AND p.deleted_at IS NULL AND au.id IS NULL;

-- 6.2 检查没有迁移成功的店家
SELECT
    '未迁移的店家' AS '异常类型',
    p.id AS 'Player_ID',
    p.phone AS '手机号',
    pp.ratio AS '分润比例',
    '未找到对应的AdminUser记录' AS '异常原因'
FROM player p
JOIN player_promoter pp ON p.id = pp.player_id
LEFT JOIN admin_users au ON p.id = au.player_id AND au.type = 4
WHERE p.player_type = 3 AND p.deleted_at IS NULL AND au.id IS NULL;

-- 6.3 检查店家没有上级代理的情况
SELECT
    '孤立店家' AS '异常类型',
    au.id AS '店家ID',
    au.username AS '店家账号',
    au.parent_admin_id AS '上级代理ID',
    '没有关联上级代理' AS '异常原因'
FROM admin_users au
WHERE au.type = 4 AND au.parent_admin_id IS NULL AND au.deleted_at IS NULL;

-- 6.4 检查玩家没有关联店家的情况
SELECT
    '未关联店家的玩家' AS '异常类型',
    p.id AS '玩家ID',
    p.phone AS '手机号',
    p.recommend_id AS '旧推荐ID',
    '没有关联到新的store_admin_id' AS '异常原因'
FROM player p
WHERE
    p.player_type = 1
    AND p.is_promoter = 0
    AND p.recommend_id IS NOT NULL
    AND p.store_admin_id IS NULL
    AND p.deleted_at IS NULL
LIMIT 20;

-- 6.5 检查分润比例异常（店家 < 代理）
SELECT
    '分润比例异常' AS '异常类型',
    store.username AS '店家账号',
    CONCAT(store.ratio, '%') AS '店家比例',
    agent.username AS '代理账号',
    CONCAT(agent.ratio, '%') AS '代理比例',
    '店家分润比例低于代理' AS '异常原因'
FROM admin_users store
JOIN admin_users agent ON store.parent_admin_id = agent.id
WHERE
    store.type = 4
    AND agent.type = 3
    AND store.ratio < agent.ratio
    AND store.deleted_at IS NULL
    AND agent.deleted_at IS NULL;

-- ================================================================================
-- 7. 业务数据验证（按代理统计玩家交易数据）
-- ================================================================================

SELECT @line AS '';
SELECT '7. 业务数据验证（示例：查看代理下级玩家数据）' AS '验证项目';
SELECT @line AS '';

SELECT
    agent.username AS '代理账号',
    COUNT(DISTINCT store.id) AS '下级店家数',
    COUNT(DISTINCT p.id) AS '下级玩家总数',
    SUM(CASE WHEN pdr.type = 1 THEN pdr.amount ELSE 0 END) AS '总转入金额',
    SUM(CASE WHEN pdr.type = 2 THEN pdr.amount ELSE 0 END) AS '总转出金额',
    SUM(CASE WHEN pdr.type = 3 THEN pdr.amount ELSE 0 END) AS '总投钞金额'
FROM admin_users agent
LEFT JOIN admin_users store ON agent.id = store.parent_admin_id AND store.type = 4
LEFT JOIN player p ON store.id = p.store_admin_id AND p.player_type = 1 AND p.is_promoter = 0
LEFT JOIN player_delivery_record pdr ON p.id = pdr.player_id
WHERE agent.type = 3 AND agent.deleted_at IS NULL
GROUP BY agent.id, agent.username
ORDER BY agent.id
LIMIT 10;

-- ================================================================================
-- 8. 推荐关系完整性验证（确保推广员体系未受影响）
-- ================================================================================

SELECT @line AS '';
SELECT '8. 推荐关系完整性验证' AS '验证项目';
SELECT @line AS '';

SELECT
    '推广员统计' AS '统计项',
    COUNT(*) AS '推广员总数',
    COUNT(CASE WHEN p.player_type = 1 THEN 1 END) AS '普通玩家推广员数',
    COUNT(CASE WHEN p.player_type = 2 THEN 1 END) AS '代理推广员数（应为0）',
    COUNT(CASE WHEN p.player_type = 3 THEN 1 END) AS '店家推广员数（应为0）'
FROM player_promoter pp
JOIN player p ON pp.player_id = p.id
WHERE p.deleted_at IS NULL;

-- ================================================================================
-- 验证完成总结
-- ================================================================================

SELECT @line AS '';
SELECT '验证完成总结' AS '';
SELECT @line AS '';

SELECT
    '如果所有检查项都显示"一致"或"✓"，说明迁移成功' AS '说明',
    '如果有异常数据，请检查异常原因并修复' AS '建议1',
    '确认无误后，可在应用中测试代理数据中心功能' AS '建议2',
    '测试通过后，可考虑执行migrate_promoter_to_admin_system.sql中的清理SQL' AS '建议3';
