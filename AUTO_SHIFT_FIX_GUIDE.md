# 自动交班无法关闭问题完整修复指南

## 问题描述
在 `ChannelIndexController/storeIndex` 中关闭了自动交班，但手动交班仍然无法启动，显示"自动交班已启用"的提示。

## 根本原因
手动交班的入口 `shiftHandover()` 方法会检查自动交班状态：
- 如果 `yjb_store_auto_shift_config` 表中存在 `is_enabled = 1` 的记录
- 就会阻止手动交班操作

## 排查步骤

### 第 1 步：检查配置表状态

```sql
-- 查看所有自动交班配置
SELECT
    id,
    department_id,
    bind_admin_user_id,
    is_enabled,
    last_shift_time,
    next_shift_time,
    updated_at
FROM yjb_store_auto_shift_config
ORDER BY updated_at DESC;
```

**预期结果：**
- `is_enabled` 应该全部为 `0`（如果你已经关闭了自动交班）
- 如果有任何一条记录的 `is_enabled = 1`，就会导致手动交班被阻止

---

### 第 2 步：检查是否有重复记录

```sql
-- 检查同一个店家是否有多条配置
SELECT
    department_id,
    bind_admin_user_id,
    COUNT(*) as 配置数量,
    GROUP_CONCAT(id ORDER BY id) as 配置ID,
    GROUP_CONCAT(is_enabled ORDER BY id) as 启用状态
FROM yjb_store_auto_shift_config
GROUP BY department_id, bind_admin_user_id
HAVING COUNT(*) > 1;
```

**预期结果：**
- 理想情况下应该返回空结果集
- 如果返回结果，说明同一个店家有多条配置，可能导致冲突

---

### 第 3 步：检查最近的更新操作

```sql
-- 查看最近的配置更新时间
SELECT
    id,
    department_id,
    bind_admin_user_id,
    is_enabled,
    updated_at,
    TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as 更新距今分钟数
FROM yjb_store_auto_shift_config
ORDER BY updated_at DESC
LIMIT 10;
```

**问题判断：**
- 如果 `updated_at` 很早（比如几天前），说明关闭操作可能没有成功执行
- 如果 `updated_at` 很新但 `is_enabled` 仍然是 1，说明前端调用的接口有问题

---

## 修复方案

### 🚀 快速修复（推荐）

直接运行 SQL 强制关闭：

```sql
-- 方法 1：关闭所有自动交班
UPDATE yjb_store_auto_shift_config
SET is_enabled = 0,
    next_shift_time = NULL,
    updated_at = NOW();

-- 验证修复结果
SELECT COUNT(*) as 已启用数量 FROM yjb_store_auto_shift_config WHERE is_enabled = 1;
-- 应该返回 0
```

### 🎯 精确修复（针对特定店家）

如果你知道具体的店家信息：

```sql
-- 先查询店家的 department_id 和 admin_user_id
SELECT
    au.id as admin_user_id,
    au.username as 店家账号,
    au.department_id,
    d.name as 渠道名称,
    sac.is_enabled as 自动交班状态
FROM yjb_admin_user au
LEFT JOIN yjb_department d ON au.department_id = d.id
LEFT JOIN yjb_store_auto_shift_config sac
    ON sac.department_id = au.department_id
    AND sac.bind_admin_user_id = au.id
WHERE au.type = 4  -- 4 = 店家类型
ORDER BY au.id DESC
LIMIT 20;

-- 然后针对特定店家关闭
UPDATE yjb_store_auto_shift_config
SET is_enabled = 0,
    next_shift_time = NULL,
    updated_at = NOW()
WHERE department_id = ?      -- 替换为实际值
  AND bind_admin_user_id = ?; -- 替换为实际值
```

### 🧹 清理重复记录（如果有）

```sql
-- 保留最新的记录，删除旧的重复记录
DELETE t1
FROM yjb_store_auto_shift_config t1
INNER JOIN (
    SELECT
        department_id,
        bind_admin_user_id,
        MAX(id) as max_id
    FROM yjb_store_auto_shift_config
    GROUP BY department_id, bind_admin_user_id
    HAVING COUNT(*) > 1
) t2
ON t1.department_id = t2.department_id
   AND t1.bind_admin_user_id = t2.bind_admin_user_id
   AND t1.id < t2.max_id;

-- 然后关闭所有配置
UPDATE yjb_store_auto_shift_config SET is_enabled = 0;
```

---

## 验证修复

### 1. 数据库验证

```sql
-- 确认所有配置都已关闭
SELECT
    COUNT(*) as 总配置数,
    SUM(CASE WHEN is_enabled = 1 THEN 1 ELSE 0 END) as 已启用数,
    SUM(CASE WHEN is_enabled = 0 THEN 1 ELSE 0 END) as 已禁用数
FROM yjb_store_auto_shift_config;

-- 预期结果：已启用数 = 0
```

### 2. 功能验证

1. 刷新浏览器页面（清除前端缓存）
2. 登录店家账号
3. 进入"店家中心"页面
4. 检查自动交班状态显示是否为"已禁用"
5. 尝试点击"手动交班"按钮
6. 应该能够正常打开交班表单

---

## 预防措施

### 后端代码优化建议

如果问题反复出现，建议修改 `ChannelAutoShiftController` 中的保存逻辑，确保：

1. **唯一性约束**：同一个店家只能有一条配置记录
2. **事务完整性**：更新操作必须在事务中完成
3. **日志记录**：记录配置变更日志，便于追踪

### 数据库优化

添加唯一索引防止重复记录：

```sql
-- 创建唯一索引（如果不存在）
ALTER TABLE yjb_store_auto_shift_config
ADD UNIQUE INDEX uk_dept_admin (department_id, bind_admin_user_id);
```

---

## 常见问题 FAQ

### Q1: 为什么关闭了还是显示"已启用"？
**A:** 可能原因：
1. 数据库中 `is_enabled` 字段没有更新为 0
2. 存在多条配置记录，其中某条仍然是启用状态
3. 浏览器缓存（刷新页面解决）

### Q2: 执行 SQL 后还是不行怎么办？
**A:** 尝试以下步骤：
1. 清除浏览器缓存（Ctrl+F5 强制刷新）
2. 重启 Webman 服务：`php start.php restart`
3. 检查是否有 Redis 缓存（虽然代码中没看到）

### Q3: 如何彻底删除自动交班配置？
**A:** 不推荐删除配置记录，建议只关闭：
```sql
UPDATE yjb_store_auto_shift_config SET is_enabled = 0;
```
如果确实要删除：
```sql
DELETE FROM yjb_store_auto_shift_config WHERE is_enabled = 0;
```

### Q4: 关闭后能否重新启用？
**A:** 可以，在店家中心重新配置交班时间即可自动启用。

---

## 技术细节

### 代码逻辑流程

```
用户点击"手动交班"
    ↓
ChannelIndexController::shiftHandover()
    ↓
调用 AutoShiftService::isAutoShiftEnabled()
    ↓
查询: SELECT * FROM yjb_store_auto_shift_config
      WHERE department_id = ? AND bind_admin_user_id = ?
      AND is_enabled = 1
    ↓
如果查询结果不为空 → 阻止手动交班，显示提示
如果查询结果为空   → 允许手动交班
```

### 相关文件

- 控制器: `addons/webman/controller/ChannelIndexController.php`
- 服务类: `app/service/store/AutoShiftService.php`
- 模型: `addons/webman/model/StoreAutoShiftConfig.php`
- 数据表: `yjb_store_auto_shift_config`

---

## 联系支持

如果上述方案都无法解决问题，请提供以下信息：

1. 运行 `AUTO_SHIFT_DEBUG.sql` 的查询结果
2. 店家账号的 `department_id` 和 `bind_admin_user_id`
3. 最近一次尝试关闭自动交班的时间
4. 浏览器控制台的错误信息（F12 → Console）

---

**最后更新时间:** 2026-04-01
