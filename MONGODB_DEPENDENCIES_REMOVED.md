# MongoDB 依赖包已移除

**移除时间**: 2026-04-02  
**项目**: gk_admin (管理后台)

---

## 📦 已移除的依赖

从 `composer.json` 中移除了以下 MongoDB 相关依赖：

```json
"mongodb/mongodb": "~1.12.0",
"jenssegers/mongodb": "3.8.*",
"ext-mongodb": "*"
```

### 依赖说明

| 包名 | 版本 | 用途 |
|------|------|------|
| mongodb/mongodb | ~1.12.0 | MongoDB PHP 官方驱动 |
| jenssegers/mongodb | 3.8.* | Laravel MongoDB ORM (Eloquent扩展) |
| ext-mongodb | * | MongoDB PHP 扩展 (C扩展) |

---

## 🔄 更新依赖

移除后需要执行：

```bash
cd D:\gk_admin

# 更新 composer.lock
composer update --lock

# 或者重新安装（推荐在测试环境先执行）
# composer install
```

⚠️ **注意**: 
- 在生产环境执行前，请先在测试环境验证
- `composer update` 可能会更新其他包，建议使用 `composer update --lock` 只更新 lock 文件

---

## 🔍 验证是否成功

```bash
# 1. 检查已安装的包
composer show | grep -i mongo
# 应该没有输出

# 2. 检查 composer.json
grep -i mongo composer.json
# 应该没有输出

# 3. 验证配置有效性
composer validate
# 应该显示 valid
```

---

## 📊 影响评估

### ✅ 不受影响的功能

- 后台管理系统核心功能
- 玩家管理
- 机台管理
- 报表统计
- 权限管理
- API调用

### ⚠️ 受影响的功能

| 功能 | 影响 | 解决方案 |
|------|------|----------|
| 查看机台操作日志 | ❌ 无法查询MongoDB历史数据 | 使用MySQL日志表替代 |
| 查看彩金池日志 | ❌ 无法查询MongoDB历史数据 | 数据重要性低，可忽略 |
| 机台通信日志 | ❌ 无法查询MongoDB历史数据 | 使用文件日志替代 |

---

## 🔧 如需恢复

### 方法1: 恢复依赖（完全回滚）

编辑 `composer.json`，在 `require` 部分添加：

```json
{
  "require": {
    ...
    "mongodb/mongodb": "~1.12.0",
    "jenssegers/mongodb": "3.8.*",
    "ext-mongodb": "*",
    ...
  }
}
```

然后执行：

```bash
composer update mongodb/mongodb jenssegers/mongodb
```

### 方法2: 仅恢复配置

如果 vendor 目录中仍有 MongoDB 包（未执行 composer install），只需：

1. 恢复 `composer.json` 中的依赖声明
2. 取消 `config/database.php` 中 mongodb 配置的注释
3. 取消 `helpers.php` 中降级逻辑的修改

---

## 📋 相关文件修改

### gk_admin 项目

- ✅ `composer.json` - 移除MongoDB依赖
- ✅ `addons/webman/helpers.php` - 添加优雅降级

### gk_work 项目

- ✅ `config/database.php` - 注释MongoDB配置
- ✅ `process/LogClear.php` - 禁用MongoDB日志清理
- ✅ `.env.example` - 注释MongoDB环境变量

---

## 🚨 重要提醒

### 不要在生产环境直接执行

```bash
# ❌ 错误做法（会删除vendor中的MongoDB包）
composer install
composer update

# ✅ 正确做法（仅在测试环境验证后再部署）
# 1. 在测试环境执行 composer install
# 2. 验证所有功能正常
# 3. 提交 composer.json 和 composer.lock
# 4. 在生产环境 git pull + composer install
```

### 备份 composer.lock

在执行任何 composer 操作前，建议备份：

```bash
cp composer.lock composer.lock.backup
```

---

## 📊 依赖树分析

### 被移除前的依赖关系

```
gk_admin
├── mongodb/mongodb (~1.12.0)
│   └── ext-mongodb (*)
└── jenssegers/mongodb (3.8.*)
    ├── mongodb/mongodb
    └── illuminate/database
```

### 潜在的间接依赖

检查是否有其他包依赖 MongoDB：

```bash
composer why mongodb/mongodb
composer why jenssegers/mongodb
```

如果有输出，说明有其他包依赖 MongoDB，需要评估影响。

---

## ✅ 完成检查清单

- [x] composer.json 已移除 MongoDB 依赖
- [ ] composer.lock 已更新（需手动执行 `composer update --lock`）
- [ ] 测试环境验证通过
- [ ] 生产环境部署完成

---

**建议**: 
- 如果确认永久不使用 MongoDB，建议删除 `app/model/mongo/` 目录
- 考虑使用 MySQL 创建替代的日志表，便于后台管理系统查询

**文档**: 详细信息请查看 `D:\gk_work\MONGODB_REMOVAL_SUMMARY.md`