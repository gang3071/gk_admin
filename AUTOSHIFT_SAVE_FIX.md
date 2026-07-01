# 自动交班配置保存失败 - 解决方案

## ❌ 问题现象

在自动交班配置页面提交表单后，配置没有保存成功。

## 🔍 问题原因

**数据库表缺少三个时间字段！**

- **控制器提交的字段：** `shift_time_1`, `shift_time_2`, `shift_time_3`
- **数据库实际字段：** 表中没有这三个字段
- **结果：** 保存时数据库报错（字段不存在）

## ✅ 解决方案

### 方案 A：本地开发环境（推荐）

#### 1. 双击运行批处理文件

```
database/migrations/run_add_three_shift_times.bat
```

按提示输入数据库信息：
- 主机：127.0.0.1（默认）
- 端口：3306（默认）
- 数据库名：你的数据库名
- 用户名：root（默认）
- 密码：你的数据库密码

#### 2. 验证字段已添加

打开数据库管理工具，查看 `store_auto_shift_config` 表，确认有这三个字段：
- `shift_time_1` - 早班交班时间（默认：08:00:00）
- `shift_time_2` - 中班交班时间（默认：16:00:00）
- `shift_time_3` - 晚班交班时间（默认：00:00:00）

#### 3. 测试保存功能

刷新配置页面，重新保存配置。

---

### 方案 B：线上服务器

#### 1. 上传 SQL 文件到服务器

```bash
scp database/migrations/add_three_shift_times_simple.sql user@server:/path/to/project/database/migrations/
```

#### 2. 在服务器上执行迁移

```bash
cd /www/wwwroot/admin.supergames9.com
mysql -u用户名 -p数据库名 < database/migrations/add_three_shift_times_simple.sql
```

#### 3. 重启 Webman 服务

```bash
php start.php restart
```

---

### 方案 C：手动执行 SQL（适用于任何环境）

登录 phpMyAdmin 或 Navicat，执行以下 SQL：

```sql
-- 添加三个交班时间字段
ALTER TABLE `store_auto_shift_config`
ADD COLUMN `shift_time_1` TIME DEFAULT '08:00:00' COMMENT '早班交班时间（晚班 → 早班）' AFTER `is_enabled`,
ADD COLUMN `shift_time_2` TIME DEFAULT '16:00:00' COMMENT '中班交班时间（早班 → 中班）' AFTER `shift_time_1`,
ADD COLUMN `shift_time_3` TIME DEFAULT '00:00:00' COMMENT '晚班交班时间（中班 → 晚班）' AFTER `shift_time_2`;

-- 验证字段
SHOW COLUMNS FROM `store_auto_shift_config` LIKE 'shift_time_%';
```

---

## 🔧 代码修改说明

已更新以下文件：

### 1. `app/service/store/AutoShiftService.php`
- ✅ 添加了 `status` 字段设置
- ✅ 添加了保存前的调试日志
- ✅ 保存三个时间字段

### 2. `addons/webman/model/StoreAutoShiftConfig.php`
- ✅ 更新了模型注释，添加三个字段说明

### 3. `addons/webman/controller/ChannelAutoShiftController.php`
- ✅ 表单使用三个时间字段

---

## 📋 验证步骤

### 1. 检查数据库字段

```sql
DESC store_auto_shift_config;
```

应该看到：
```
shift_time_1    | time | YES  |      | 08:00:00 |
shift_time_2    | time | YES  |      | 16:00:00 |
shift_time_3    | time | YES  |      | 00:00:00 |
```

### 2. 查看日志

执行迁移后，查看日志文件：

**本地环境：**
```
runtime/logs/webman.log
```

**线上环境：**
```bash
tail -f /www/wwwroot/admin.supergames9.com/runtime/logs/webman.log
```

查找这些关键日志：
- `准备保存自动交班配置` - 保存前日志
- `保存自动交班配置成功` - 保存成功
- `保存自动交班配置失败` - 保存失败（如果有）

### 3. 测试保存

1. 登录店家后台
2. 进入"自动交班管理" → "交班配置"
3. 修改三个时间：
   - 早班：08:00
   - 中班：16:00
   - 晚班：00:00
4. 点击"保存"
5. 刷新页面
6. ✓ 确认时间已保存

---

## 🚨 常见错误

### 错误 1: Unknown column 'shift_time_1'

**原因：** 数据库表还没有添加字段

**解决：** 执行上述任一迁移方案

### 错误 2: Field 'status' doesn't have a default value

**原因：** status 字段为 NOT NULL 但没有默认值

**解决：** 已在代码中添加 `$config->status = 1;`

### 错误 3: 保存成功但刷新后数据消失

**原因：**
1. 数据库事务未提交
2. 缓存问题

**解决：**
```bash
# 重启 Webman
php start.php restart

# 清除浏览器缓存（Ctrl+Shift+Delete）
```

---

## 📊 数据库表结构

迁移后的完整字段：

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| id | INT | - | 主键 |
| department_id | INT | - | 部门ID |
| bind_admin_user_id | INT | - | 绑定用户ID |
| is_enabled | TINYINT | 0 | 是否启用 |
| **shift_time_1** | **TIME** | **08:00:00** | **早班时间** ⭐新增 |
| **shift_time_2** | **TIME** | **16:00:00** | **中班时间** ⭐新增 |
| **shift_time_3** | **TIME** | **00:00:00** | **晚班时间** ⭐新增 |
| auto_settlement | TINYINT | 1 | 自动结算 |
| status | TINYINT | 1 | 状态 |
| next_shift_time | DATETIME | NULL | 下次交班时间 |
| created_at | DATETIME | - | 创建时间 |
| updated_at | DATETIME | - | 更新时间 |

---

## 📞 需要帮助？

如果迁移后仍然无法保存，请提供：

1. 数据库表结构：
   ```sql
   SHOW CREATE TABLE store_auto_shift_config;
   ```

2. 错误日志：
   ```bash
   tail -100 runtime/logs/webman.log
   ```

3. 浏览器控制台错误（F12）

---

## ✅ 完成清单

- [ ] 执行数据库迁移
- [ ] 验证字段已添加
- [ ] 重启 Webman 服务（线上环境）
- [ ] 测试配置保存
- [ ] 查看日志确认成功
- [ ] 验证下次交班时间自动计算

完成所有步骤后，自动交班配置功能应该可以正常使用了！
