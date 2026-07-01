# 🚀 快速修复指南

## 问题症状

✅ 如果你遇到以下情况，说明需要执行此修复：

- ✅ 关闭了自动交班，但手动交班仍然显示"自动交班已启用"
- ✅ 数据库中同一个店家有多条 `yjb_store_auto_shift_config` 记录
- ✅ 自动交班配置无法正常保存或更新

---

## ⚡ 一键修复（最快）

### Windows 用户

双击运行：
```
fix_auto_shift.bat
```

### Linux/Mac 用户

终端运行：
```bash
./fix_auto_shift.sh
```

---

## 📋 手动修复（3 步）

### 第 1 步：运行迁移

```bash
vendor/bin/phinx migrate
```

### 第 2 步：强制关闭自动交班（如果需要）

```sql
UPDATE yjb_store_auto_shift_config SET is_enabled = 0;
```

### 第 3 步：验证

```sql
-- 检查是否还有重复记录（应该返回空）
SELECT department_id, bind_admin_user_id, COUNT(*)
FROM yjb_store_auto_shift_config
WHERE deleted_at IS NULL
GROUP BY department_id, bind_admin_user_id
HAVING COUNT(*) > 1;
```

---

## 🔍 验证修复成功

### 数据库验证

```sql
-- 1. 检查唯一索引是否正确
SHOW INDEX FROM yjb_store_auto_shift_config WHERE Key_name = 'uk_dept_admin';
-- 应该返回2行：department_id 和 bind_admin_user_id

-- 2. 检查是否还有启用的配置
SELECT COUNT(*) FROM yjb_store_auto_shift_config WHERE is_enabled = 1;
-- 应该是 0（除非确实有店家启用了自动交班）

-- 3. 检查是否还有重复记录
SELECT department_id, bind_admin_user_id, COUNT(*) FROM yjb_store_auto_shift_config
WHERE deleted_at IS NULL GROUP BY department_id, bind_admin_user_id HAVING COUNT(*) > 1;
-- 应该返回空结果集
```

### 功能验证

1. **刷新浏览器**（Ctrl+F5）
2. 登录店家账号
3. 进入"店家中心"
4. 点击"手动交班"
5. **应该能正常打开交班表单** ✅

---

## 🆘 常见问题

### Q: 执行后还是不行？

**A:** 尝试以下步骤：
1. 强制关闭所有自动交班：`UPDATE yjb_store_auto_shift_config SET is_enabled = 0;`
2. 清除浏览器缓存：Ctrl+F5
3. 重启 Webman：`php start.php restart`

### Q: 担心数据丢失？

**A:** 放心！修复过程：
- ✅ 自动备份到 `yjb_store_auto_shift_config_backup_20260401`
- ✅ 保留最新的配置记录
- ✅ 支持 Phinx 回滚：`vendor/bin/phinx rollback`

### Q: 修复需要多久？

**A:** 通常 < 1 分钟：
- Phinx 迁移：5-30 秒
- SQL 脚本：10-60 秒

---

## 📁 相关文档

- **完整技术文档**：`AUTO_SHIFT_DUPLICATE_RECORDS_FIX.md`
- **排查 SQL 脚本**：`AUTO_SHIFT_DEBUG.sql`
- **修复 SQL 脚本**：`database/fixes/fix_auto_shift_unique_index.sql`
- **Phinx 迁移文件**：`database/phinx_migrations/20260401000000_fix_auto_shift_unique_index.php`

---

## ✨ 修复完成后

系统将：
- ✅ 阻止同一个店家创建多条配置
- ✅ 软删除后不会产生重复记录
- ✅ 关闭自动交班后可以正常手动交班
- ✅ 不同渠道的店家可以各自独立配置

---

**遇到问题？查看完整文档：`AUTO_SHIFT_DUPLICATE_RECORDS_FIX.md`**
