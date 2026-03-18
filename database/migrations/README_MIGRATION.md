# 代理/店家数据迁移指南

## 概述

本迁移将代理和店家从旧的 `Player + PlayerPromoter` 体系迁移到新的 `AdminUser` 独立后台体系。

## 迁移文件说明

| 文件 | 用途 | 执行时机 |
|------|------|---------|
| `add_agent_fields_to_admin_users.sql` | 添加AdminUser表字段结构 | 第一步执行 |
| `migrate_promoter_to_admin_system.sql` | 完整数据迁移脚本 | 第二步执行 |
| `verify_agent_migration.sql` | 验证迁移结果 | 第三步执行 |

## 迁移步骤

### 准备工作

**⚠️ 重要提示：请务必在生产环境执行前先在测试环境完整测试！**

1. **备份数据库**
   ```bash
   mysqldump -u用户名 -p数据库名 > backup_before_migration_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **确认当前数据量**
   ```sql
   -- 查看代理和店家数量
   SELECT
       COUNT(CASE WHEN player_type = 2 THEN 1 END) AS '代理数',
       COUNT(CASE WHEN player_type = 3 THEN 1 END) AS '店家数',
       COUNT(CASE WHEN player_type = 1 AND is_promoter = 0 THEN 1 END) AS '玩家数'
   FROM player
   WHERE deleted_at IS NULL;
   ```

### 步骤一：添加字段结构

```bash
mysql -u用户名 -p数据库名 < add_agent_fields_to_admin_users.sql
```

**预期结果**：
- AdminUser表新增 `type`, `player_id`, `parent_admin_id`, `ratio` 等字段
- 创建相关索引

**检查方法**：
```sql
SHOW COLUMNS FROM admin_users LIKE '%ratio%';
SHOW COLUMNS FROM admin_users LIKE '%parent_admin_id%';
```

### 步骤二：执行数据迁移

```bash
mysql -u用户名 -p数据库名 < migrate_promoter_to_admin_system.sql
```

**迁移内容**：
1. 自动创建备份表（`*_backup_20260312`）
2. 迁移代理的分润数据到AdminUser
3. 迁移店家的分润数据到AdminUser
4. 更新店家的`parent_admin_id`关联关系
5. 更新玩家的`store_admin_id`和`agent_admin_id`
6. 创建索引优化性能
7. 执行基础验证

**预期输出示例**：
```
备份完成
代理数据迁移数量: 15
店家数据迁移数量: 48
店家上级代理关联更新数量: 48
玩家店家关联更新数量: 1250
玩家代理关联更新数量: 1250
迁移完成
```

**如果迁移失败**：
```bash
# 恢复备份数据
mysql -u用户名 -p数据库名 < backup_before_migration_YYYYMMDD_HHMMSS.sql
```

### 步骤三：验证迁移结果

```bash
mysql -u用户名 -p数据库名 < verify_agent_migration.sql
```

**验证项目**：
1. ✅ 总体统计对比（代理/店家/玩家数量）
2. ✅ 代理数据详细对比（分润比例、调整金额、结算时间）
3. ✅ 店家数据详细对比（上级关联、下级玩家数）
4. ✅ 玩家关联验证（新旧架构玩家数对比）
5. ✅ 分润金额汇总对比
6. ⚠️ 异常数据检查（未迁移、孤立、比例异常等）
7. ✅ 业务数据验证（交易金额统计）
8. ✅ 推荐关系完整性（确保推广员体系未受影响）

**关注重点**：
- 所有"校验"列应显示 `✓ 一致`
- "异常数据检查"部分应返回0条记录或可解释的异常

### 步骤四：应用测试

1. **代理登录测试**
   - 使用代理账号（AdminUser, type=3）登录后台
   - 访问 `/channel/index/agentIndex` 代理数据中心
   - 验证数据显示正确

2. **数据准确性测试**
   - 总店家数是否正确
   - 总玩家数是否正确
   - 金额统计是否准确（转入、转出、投钞）
   - 分润计算是否正确

3. **权限测试**
   - 代理只能看到自己下级的数据
   - 不能看到其他代理的数据

4. **店家测试**
   - 使用店家账号登录（如果有店家数据中心）
   - 验证数据显示正确

### 步骤五：清理旧数据（可选）

**⚠️ 警告：仅在确认迁移完全成功，且应用运行正常至少1周后执行！**

清理内容：
1. 删除 `player_promoter` 中代理和店家的记录（保留真正的推广员）
2. 删除备份表

```sql
-- 取消注释并执行migrate_promoter_to_admin_system.sql中的清理SQL
-- 或者手动执行：

-- 1. 删除代理的PlayerPromoter记录
DELETE pp FROM player_promoter pp
JOIN player p ON pp.player_id = p.id
WHERE p.player_type = 2 AND p.deleted_at IS NULL;

-- 2. 删除店家的PlayerPromoter记录
DELETE pp FROM player_promoter pp
JOIN player p ON pp.player_id = p.id
WHERE p.player_type = 3 AND p.deleted_at IS NULL;

-- 3. 验证只剩推广员数据
SELECT COUNT(*) AS '剩余推广员数' FROM player_promoter;

-- 4. 确认后删除备份表
DROP TABLE IF EXISTS admin_users_backup_20260312;
DROP TABLE IF EXISTS player_backup_20260312;
DROP TABLE IF EXISTS player_promoter_backup_20260312;
```

## 常见问题

### Q1: 迁移后代理看不到数据？

**可能原因**：
1. AdminUser的type字段未正确设置为3
2. 下级店家的parent_admin_id未正确关联

**检查方法**：
```sql
-- 检查代理账号
SELECT id, username, type, ratio FROM admin_users WHERE id = 你的代理ID;

-- 检查下级店家
SELECT id, username, parent_admin_id, ratio
FROM admin_users
WHERE parent_admin_id = 你的代理ID;
```

### Q2: 玩家数量不一致？

**可能原因**：
1. 玩家的store_admin_id未正确更新
2. 旧架构中recommend_id指向的不是店家

**检查方法**：
```sql
-- 对比新旧架构玩家数
SELECT
    (SELECT COUNT(*) FROM player WHERE store_admin_id = 店家AdminUser_ID) AS '新架构',
    (SELECT COUNT(*) FROM player WHERE recommend_id = 店家Player_ID) AS '旧架构';
```

### Q3: 分润计算结果不对？

**可能原因**：
1. AdminUser的ratio未正确迁移
2. last_settlement_timestamp时间戳格式不对

**检查方法**：
```sql
-- 对比分润配置
SELECT
    au.ratio AS '新比例',
    pp.ratio AS '旧比例',
    FROM_UNIXTIME(au.last_settlement_timestamp) AS '新结算时间',
    pp.last_settlement_timestamp AS '旧结算时间'
FROM admin_users au
JOIN player p ON au.player_id = p.id
LEFT JOIN player_promoter pp ON p.id = pp.player_id
WHERE au.id = 你的代理ID;
```

### Q4: 推广员体系是否受影响？

**回答**：不会。推广员体系（`Player.recommend_id` → `PlayerPromoter`）是独立的，本次迁移只处理代理和店家（`player_type = 2/3`），不影响普通玩家的推荐关系。

**验证方法**：
```sql
-- 确认推广员数据未受影响
SELECT
    COUNT(*) AS '推广员总数',
    COUNT(CASE WHEN p.player_type = 1 THEN 1 END) AS '普通玩家推广员',
    COUNT(CASE WHEN p.player_type IN (2,3) THEN 1 END) AS '代理/店家推广员（应为0）'
FROM player_promoter pp
JOIN player p ON pp.player_id = p.id
WHERE p.deleted_at IS NULL;
```

## 回滚方案

如果迁移后发现问题，可以通过以下方式回滚：

```bash
# 1. 恢复数据库备份
mysql -u用户名 -p数据库名 < backup_before_migration_YYYYMMDD_HHMMSS.sql

# 2. 或者使用自动备份表恢复
mysql -u用户名 -p数据库名 <<EOF
-- 恢复admin_users
TRUNCATE TABLE admin_users;
INSERT INTO admin_users SELECT * FROM admin_users_backup_20260312;

-- 恢复player
UPDATE player p
JOIN player_backup_20260312 pb ON p.id = pb.id
SET p.store_admin_id = NULL, p.agent_admin_id = NULL;

EOF
```

## 数据对照视图

迁移脚本会自动创建一个对照视图 `v_agent_migration_comparison`，可用于随时对比新旧数据：

```sql
-- 查看迁移前后对比
SELECT * FROM v_agent_migration_comparison
ORDER BY source, player_id;

-- 按账号对比
SELECT * FROM v_agent_migration_comparison
WHERE account = '13800138000';
```

## 性能优化

迁移完成后，已自动创建以下索引：

```sql
-- admin_users
idx_type
idx_parent_admin_id
idx_player_id

-- player
idx_agent_admin_id
idx_store_admin_id
```

如果查询仍然较慢，可考虑添加复合索引：

```sql
-- 代理查询下级玩家优化
ALTER TABLE player ADD INDEX idx_store_dept (store_admin_id, department_id, is_promoter);

-- 分润统计优化
ALTER TABLE admin_users ADD INDEX idx_parent_type (parent_admin_id, type);
```

## 联系支持

如果遇到问题，请提供：
1. 迁移日志输出
2. 验证脚本的异常检查结果
3. 具体的错误信息或异常现象
4. 数据库版本和数据规模

## 附录：架构对照表

| 数据项 | 旧架构 | 新架构 |
|--------|--------|--------|
| 代理身份 | Player.player_type = 2 | AdminUser.type = 3 |
| 店家身份 | Player.player_type = 3 | AdminUser.type = 4 |
| 分润比例 | PlayerPromoter.ratio | AdminUser.ratio |
| 上级关联 | PlayerPromoter.recommend_id | AdminUser.parent_admin_id |
| 下级玩家 | Player.recommend_id = 店家player_id | Player.store_admin_id = 店家admin_id |
| 代理关联 | 无 | Player.agent_admin_id = 代理admin_id |
| 结算时间 | PlayerPromoter.last_settlement_timestamp (datetime) | AdminUser.last_settlement_timestamp (unix timestamp) |
