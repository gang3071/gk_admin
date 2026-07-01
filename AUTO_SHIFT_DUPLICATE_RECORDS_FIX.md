# 自动交班多条记录问题完整修复方案

## 📋 问题摘要

**现象：** 同一个店家账号存在多条自动交班配置记录，导致即使关闭了自动交班，手动交班仍然无法启动。

**根本原因：** 数据库表 `yjb_store_auto_shift_config` 的唯一索引设计错误。

---

## 🔍 技术分析

### 原始设计的错误索引

```php
// database/phinx_migrations/20260322100000_create_auto_shift_config_table.php (第117-120行)
->addIndex(['bind_admin_user_id', 'deleted_at'], [
    'unique' => true,
    'name' => 'uk_bind_admin',
])
```

### ❌ 设计缺陷

#### 1. **缺少 `department_id` 字段**

**问题：**
- 业务逻辑是：`(渠道ID + 管理员ID)` 应该唯一
- 但索引只包含 `bind_admin_user_id`
- 导致不同渠道下的相同管理员ID会冲突

**示例场景：**
```sql
-- 渠道A的店家（ID=10）
INSERT INTO yjb_store_auto_shift_config (department_id, bind_admin_user_id)
VALUES (100, 10);  -- ✅ 成功

-- 渠道B的店家（ID也是10，但属于不同渠道）
INSERT INTO yjb_store_auto_shift_config (department_id, bind_admin_user_id)
VALUES (200, 10);  -- ❌ 失败（唯一键冲突：bind_admin_user_id=10 已存在）
```

#### 2. **错误地包含了 `deleted_at` 字段**

**问题：**
- 软删除场景下，`deleted_at` 的值会变化
- 导致唯一键的组合值不同，允许插入多条记录

**示例场景：**
```sql
-- 第1次：创建配置
INSERT INTO yjb_store_auto_shift_config (department_id, bind_admin_user_id, deleted_at)
VALUES (100, 5, NULL);
-- 唯一键: (bind_admin_user_id=5, deleted_at=NULL) ✅ 成功

-- 第2次：软删除配置
UPDATE yjb_store_auto_shift_config
SET deleted_at = '2026-03-01 10:00:00'
WHERE id = 1;
-- 唯一键变为: (bind_admin_user_id=5, deleted_at='2026-03-01 10:00:00')

-- 第3次：再次创建配置
INSERT INTO yjb_store_auto_shift_config (department_id, bind_admin_user_id, deleted_at)
VALUES (100, 5, NULL);
-- 唯一键: (bind_admin_user_id=5, deleted_at=NULL) ✅ 成功（因为之前的记录deleted_at不是NULL了）

-- 结果：数据库中有2条记录！
SELECT * FROM yjb_store_auto_shift_config WHERE bind_admin_user_id = 5;
-- id  department_id  bind_admin_user_id  deleted_at
-- 1   100            5                   2026-03-01 10:00:00
-- 2   100            5                   NULL
```

**查询逻辑的矛盾：**
```php
// AutoShiftService.php 第27-31行
$config = StoreAutoShiftConfig::query()
    ->where('department_id', $departmentId)
    ->where('bind_admin_user_id', $bindAdminUserId)
    ->where('is_enabled', 1)
    ->first();  // 只取第一条，但如果有多条deleted_at=NULL的记录呢？
```

如果有多条 `deleted_at = NULL` 的记录，`first()` 只会返回其中一条（通常是 ID 最小的），但实际上可能有多条有效记录。

---

## 🛠️ 解决方案

### ✅ 正确的唯一索引设计

```php
->addIndex(['department_id', 'bind_admin_user_id'], [
    'unique' => true,
    'name' => 'uk_dept_admin',
])
```

**优点：**
1. **保证业务逻辑正确性**：同一个渠道下的同一个管理员只能有一条配置
2. **允许跨渠道**：不同渠道下的管理员可以各自配置
3. **不受软删除影响**：无论 `deleted_at` 是什么值，都不会产生重复记录

---

## 📝 修复步骤

### 方案 A：使用 Phinx 迁移（推荐）

**1. 运行迁移命令：**

```bash
vendor/bin/phinx migrate
```

这会自动执行以下操作：
- ✅ 检查重复记录
- ✅ 清理重复记录（保留最新的一条）
- ✅ 删除错误的唯一索引 `uk_bind_admin`
- ✅ 创建正确的唯一索引 `uk_dept_admin`
- ✅ 验证修复结果

**2. 查看迁移输出：**

```
========== 开始修复自动交班配置表唯一索引 ==========

[步骤 1/4] 检查重复记录...
⚠️  发现 2 组重复记录
   - 渠道 100, 管理员 5: 3 条记录 (IDs: 1,2,3)
   - 渠道 200, 管理员 8: 2 条记录 (IDs: 4,5)

[步骤 2/4] 清理重复记录（保留ID最大的记录）...
   ✓ 删除旧记录: IDs 1,2, 保留 ID 3
   ✓ 删除旧记录: IDs 4, 保留 ID 5
✓ 共删除 3 条重复记录

[步骤 3/4] 删除错误的唯一索引 uk_bind_admin...
✓ 已删除旧索引 uk_bind_admin

[步骤 4/4] 创建正确的唯一索引 uk_dept_admin...
✓ 已创建新索引 uk_dept_admin (department_id, bind_admin_user_id)

========== 验证修复结果 ==========
✓ 总配置数: 10, 有效配置数: 8
✓ 确认无重复记录

========== 修复完成 ==========
```

**3. 如果需要回滚：**

```bash
vendor/bin/phinx rollback
```

---

### 方案 B：手动执行 SQL（适用于无法运行 Phinx 的环境）

**1. 运行修复脚本：**

```bash
mysql -u username -p database_name < database/fixes/fix_auto_shift_unique_index.sql
```

或者在 phpMyAdmin / Navicat 中打开 `database/fixes/fix_auto_shift_unique_index.sql` 并执行。

**2. 该脚本会自动：**
- 检查并显示重复记录
- 备份所有数据到 `yjb_store_auto_shift_config_backup_20260401`
- 清理重复记录
- 删除旧索引，创建新索引
- 验证修复结果

---

## ✅ 验证修复

### 1. 数据库层面验证

```sql
-- 检查索引是否正确
SHOW INDEX FROM yjb_store_auto_shift_config WHERE Key_name = 'uk_dept_admin';
-- 应该返回2行（department_id 和 bind_admin_user_id）

-- 确认无重复记录
SELECT
    department_id,
    bind_admin_user_id,
    COUNT(*) as count
FROM yjb_store_auto_shift_config
WHERE deleted_at IS NULL
GROUP BY department_id, bind_admin_user_id
HAVING COUNT(*) > 1;
-- 应该返回空结果集

-- 查看当前配置状态
SELECT
    id,
    department_id,
    bind_admin_user_id,
    is_enabled,
    deleted_at
FROM yjb_store_auto_shift_config
ORDER BY department_id, bind_admin_user_id;
```

### 2. 功能测试

**测试 1：防止重复创建**

1. 登录店家账号A
2. 进入自动交班配置页面
3. 保存配置（启用）
4. 再次点击保存
5. **预期结果：** 数据库中只有1条记录（更新而不是新增）

```sql
-- 查询验证
SELECT COUNT(*) FROM yjb_store_auto_shift_config
WHERE department_id = ? AND bind_admin_user_id = ?;
-- 应该只返回 1
```

**测试 2：软删除后重新创建**

1. 登录店家账号A
2. 删除自动交班配置（如果系统支持）
3. 重新创建配置
4. **预期结果：** 数据库中只有1条 `deleted_at = NULL` 的记录

```sql
-- 查询验证
SELECT deleted_at FROM yjb_store_auto_shift_config
WHERE department_id = ? AND bind_admin_user_id = ?;
-- 只应该有1条 deleted_at IS NULL 的记录
```

**测试 3：关闭自动交班后手动交班**

1. 登录店家账号
2. 进入自动交班配置，关闭自动交班（`is_enabled = 0`）
3. 进入店家中心
4. 点击"手动交班"按钮
5. **预期结果：** 能够正常打开手动交班表单

---

## 🚨 常见问题 FAQ

### Q1: 为什么我的环境有多条记录？

**A:** 主要有3种可能：

1. **历史遗留**：修复前系统已经产生了重复数据
2. **软删除后重新创建**：删除配置后再次创建（最常见）
3. **并发请求**：快速连续点击保存按钮（较少见）

运行修复脚本后，这些问题都会被解决。

---

### Q2: 修复后会不会丢失数据？

**A:** 不会。修复过程中：

1. **自动备份**：所有数据会备份到 `yjb_store_auto_shift_config_backup_20260401`
2. **保留最新**：清理重复记录时，保留ID最大（最新创建）的记录
3. **可回滚**：Phinx 迁移支持回滚操作

---

### Q3: 如果修复失败怎么办？

**A:** 如果 Phinx 迁移失败：

1. **查看错误日志**：
   ```bash
   vendor/bin/phinx migrate -vvv
   ```

2. **手动执行 SQL**：使用方案B（手动SQL）

3. **恢复备份**：
   ```sql
   -- 删除当前表
   DROP TABLE yjb_store_auto_shift_config;
   
   -- 恢复备份
   CREATE TABLE yjb_store_auto_shift_config LIKE yjb_store_auto_shift_config_backup_20260401;
   INSERT INTO yjb_store_auto_shift_config SELECT * FROM yjb_store_auto_shift_config_backup_20260401;
   ```

---

### Q4: 修复后还是无法手动交班？

**A:** 检查以下几点：

1. **确认数据库已更新**：
   ```sql
   SELECT COUNT(*) FROM yjb_store_auto_shift_config WHERE is_enabled = 1;
   -- 应该返回 0
   ```

2. **清除浏览器缓存**：按 `Ctrl + F5` 强制刷新

3. **重启 Webman**：
   ```bash
   php start.php restart
   ```

4. **检查是否还有其他渠道的配置**：
   ```sql
   SELECT * FROM yjb_store_auto_shift_config WHERE is_enabled = 1;
   -- 确认没有其他渠道的配置影响
   ```

---

## 📊 修复前后对比

### 修复前（错误索引）

| 场景 | 是否允许插入 | 结果 |
|------|------------|------|
| 同一个店家首次创建 | ✅ 允许 | 1条记录 |
| 同一个店家软删除后重新创建 | ✅ 允许 | 2条记录（1条deleted，1条有效）❌ |
| 同一个店家并发保存 | ✅ 允许 | 2条有效记录 ❌ |
| 不同渠道相同管理员ID | ❌ 拒绝 | 冲突 ❌ |

### 修复后（正确索引）

| 场景 | 是否允许插入 | 结果 |
|------|------------|------|
| 同一个店家首次创建 | ✅ 允许 | 1条记录 ✅ |
| 同一个店家软删除后重新创建 | ❌ 拒绝 | 强制更新现有记录 ✅ |
| 同一个店家并发保存 | ❌ 拒绝 | 第二个请求报唯一键冲突，强制更新 ✅ |
| 不同渠道相同管理员ID | ✅ 允许 | 各自独立配置 ✅ |

---

## 🎯 预防措施

### 1. 代码层面优化

虽然修复了数据库索引，但代码层面也可以进一步优化：

```php
// AutoShiftService.php saveConfig() 方法建议优化
public function saveConfig(array $data): array
{
    try {
        DB::beginTransaction();

        // ✅ 使用 updateOrCreate 方法（更安全）
        $config = StoreAutoShiftConfig::updateOrCreate(
            [
                'department_id' => $data['department_id'],
                'bind_admin_user_id' => $data['bind_admin_user_id'],
            ],
            [
                'is_enabled' => $data['is_enabled'] ?? 0,
                'shift_time_1' => $data['shift_time_1'] ?? '08:00:00',
                'shift_time_2' => $data['shift_time_2'] ?? '16:00:00',
                'shift_time_3' => $data['shift_time_3'] ?? '00:00:00',
                'auto_settlement' => $data['auto_settlement'] ?? 1,
            ]
        );

        // ... 后续逻辑

        DB::commit();
        return ['code' => 0, 'msg' => '保存成功', 'data' => $config];
    } catch (\Exception $e) {
        DB::rollBack();
        return ['code' => 1, 'msg' => '保存失败: ' . $e->getMessage()];
    }
}
```

### 2. 监控告警

添加定期检查脚本，监控是否产生重复记录：

```sql
-- 定期执行（如每日）
SELECT
    department_id,
    bind_admin_user_id,
    COUNT(*) as count
FROM yjb_store_auto_shift_config
WHERE deleted_at IS NULL
GROUP BY department_id, bind_admin_user_id
HAVING COUNT(*) > 1;

-- 如果返回结果，发送告警
```

---

## 📦 相关文件

修复过程中创建的文件：

1. **SQL 修复脚本**：`database/fixes/fix_auto_shift_unique_index.sql`
2. **Phinx 迁移文件**：`database/phinx_migrations/20260401000000_fix_auto_shift_unique_index.php`
3. **问题排查脚本**：`AUTO_SHIFT_DEBUG.sql`
4. **修复指南**：`AUTO_SHIFT_FIX_GUIDE.md`
5. **本文档**：`AUTO_SHIFT_DUPLICATE_RECORDS_FIX.md`

---

## 🙏 总结

**问题本质：** 数据库唯一索引设计错误，导致软删除场景下可以产生重复记录。

**修复核心：** 将唯一索引从 `(bind_admin_user_id, deleted_at)` 改为 `(department_id, bind_admin_user_id)`。

**修复效果：**
- ✅ 阻止同一个店家创建多条配置
- ✅ 允许不同渠道下的同名管理员各自配置
- ✅ 软删除不影响唯一性约束
- ✅ 彻底解决"关闭自动交班后仍无法手动交班"的问题

---

**修复完成后，请运行功能测试确保一切正常！**

如有问题，请查看日志或联系技术支持。

---

**最后更新：** 2026-04-01
**修复版本：** v1.0
