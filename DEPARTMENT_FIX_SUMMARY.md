# 线下渠道 department_id 修复说明

## 问题描述

之前创建代理和店家时，系统会为每个代理和店家创建独立的 `AdminDepartment` 记录，导致渠道、代理、店家的 `department_id` 各不相同。这使得数据查询和权限控制变得复杂。

## 已完成的代码修改

已修改以下三个控制器，新创建的代理和店家将直接使用渠道的 `department_id`：

1. ✅ `AgentController.php` - 创建代理时不再创建新部门
2. ✅ `StoreMachineController.php` - 创建店家时不再创建新部门
3. ✅ `ChannelPlayerController.php` - 创建店家时不再创建新部门

## 需要执行的数据迁移

为了修复**已存在**的代理和店家数据，需要执行数据迁移。

### 快速执行（3步）

#### 第1步：备份数据库（重要！）

```bash
# 备份相关表
mysqldump -u root -p database_name admin_users admin_department store_setting store_auto_shift_config store_agent_shift_handover_record store_auto_shift_log > backup_before_fix.sql
```

#### 第2步：执行迁移

**Windows:**
```bash
database\migrations\run_fix_department_migration.bat
```

**Linux/Mac:**
```bash
bash database/migrations/run_fix_department_migration.sh
```

#### 第3步：验证结果

```bash
# 在数据库中执行验证SQL
mysql -u root -p database_name < database/migrations/verify_department_fix.sql
```

## 迁移影响范围

此迁移会自动修复以下数据：

| 表名 | 修复内容 | 影响范围 |
|------|---------|---------|
| `admin_users` | 代理和店家的 `department_id` | 仅线下渠道（`is_offline=1`）|
| `store_setting` | 配置记录的 `department_id` | 代理和店家的配置 |
| `store_auto_shift_config` | 自动交班配置的 `department_id` | 代理和店家的交班配置 |
| `store_agent_shift_handover_record` | 交班记录的 `department_id` | 历史交班记录 |
| `store_auto_shift_log` | 交班日志的 `department_id` | 自动交班日志 |
| `admin_department` | **删除**多余的部门记录 | 为代理和店家创建的孤立部门 |

## 详细文档

- 📖 [完整说明文档](database/migrations/FIX_DEPARTMENT_MIGRATION_README.md)
- 🔍 [验证SQL脚本](database/migrations/verify_department_fix.sql)
- 💻 [Phinx迁移文件](database/phinx_migrations/20260324000000_fix_offline_agent_store_department.php)

## 注意事项

⚠️ **重要提醒：**
1. 执行前务必备份数据库
2. 建议在非业务高峰期执行
3. 此迁移不可回滚（原 department_id 会丢失）
4. 仅影响线下渠道，不影响线上渠道

## 执行时间估算

- 小型系统（< 100个代理/店家）：约 5-10 秒
- 中型系统（100-1000个）：约 10-30 秒
- 大型系统（> 1000个）：约 30-60 秒

## 验证修复成功

执行完成后，所有线下渠道的代理和店家应该满足：

```
代理.department_id = 渠道.department_id
店家.department_id = 渠道.department_id
```

运行验证SQL后，应该看到 `✓ 正确` 的标记。
