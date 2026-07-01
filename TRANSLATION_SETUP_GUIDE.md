# 翻译文件配置指南

## 错误信息
```json
{
    "code": 100,
    "message": "File /www/wwwroot/admin-test.5super9.com/resource/translations not found",
    "data": []
}
```

## 问题分析

根据 `config/translation.php` 配置：
- 翻译文件路径：`resource/translations`
- 默认语言：`zh_CN`
- 备用语言：`['zh_CN', 'en']`

当前 `resource/translations/` 目录为空，需要从 `yjb_s` 数据库或表中获取翻译数据。

---

## 解决方案

### 方案1：从数据库 yjb_s 获取翻译（推荐）

#### 步骤1：连接数据库查询翻译数据

```bash
# 连接到数据库
mysql -h [HOST] -u [USER] -p [DATABASE]

# 查看系统配置表（可能的表名）
SHOW TABLES LIKE '%system%';
SHOW TABLES LIKE '%translation%';
SHOW TABLES LIKE '%lang%';
SHOW TABLES LIKE '%yjb_s%';

# 查询翻译数据
SELECT * FROM yjb_system_config WHERE feature = 'translations';
# 或
SELECT * FROM yjb_s WHERE type = 'translation';
```

#### 步骤2：导出翻译数据

如果翻译存储在数据库中，使用以下 PHP 脚本导出：

**创建文件：`scripts/export_translations.php`**

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use support\Db;

// 从数据库获取翻译
$translations = Db::table('yjb_system_config')
    ->where('feature', 'translations')
    ->first();

if (!$translations) {
    echo "未找到翻译数据\n";
    exit(1);
}

// 解析翻译内容（假设存储为JSON）
$content = json_decode($translations->content, true);

// 创建翻译文件目录
$basePath = __DIR__ . '/../resource/translations';
if (!is_dir($basePath)) {
    mkdir($basePath, 0755, true);
}

// 支持的语言
$locales = ['zh_CN', 'zh_TW', 'en', 'jp'];

foreach ($locales as $locale) {
    $localePath = $basePath . '/' . $locale;
    if (!is_dir($localePath)) {
        mkdir($localePath, 0755, true);
    }
    
    // 创建翻译文件（JSON格式）
    $localeData = $content[$locale] ?? [];
    file_put_contents(
        $localePath . '/messages.json',
        json_encode($localeData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    
    echo "Created: {$localePath}/messages.json\n";
}

echo "翻译文件导出完成！\n";
```

**运行脚本：**
```bash
cd D:\gk_admin
php scripts/export_translations.php
```

---

### 方案2：手动创建翻译文件

如果无法从数据库获取，手动创建基本翻译文件：

#### 创建目录结构

```bash
mkdir -p resource/translations/zh_CN
mkdir -p resource/translations/zh_TW
mkdir -p resource/translations/en
mkdir -p resource/translations/jp
```

#### 创建翻译文件

**简体中文 (zh_CN):**
```json
// resource/translations/zh_CN/messages.json
{
    "welcome": "欢迎",
    "save": "保存",
    "cancel": "取消",
    "delete": "删除",
    "edit": "编辑",
    "search": "搜索",
    "submit": "提交",
    "success": "成功",
    "error": "错误",
    "confirm": "确认"
}
```

**繁体中文 (zh_TW):**
```json
// resource/translations/zh_TW/messages.json
{
    "welcome": "歡迎",
    "save": "儲存",
    "cancel": "取消",
    "delete": "刪除",
    "edit": "編輯",
    "search": "搜尋",
    "submit": "送出",
    "success": "成功",
    "error": "錯誤",
    "confirm": "確認"
}
```

**英文 (en):**
```json
// resource/translations/en/messages.json
{
    "welcome": "Welcome",
    "save": "Save",
    "cancel": "Cancel",
    "delete": "Delete",
    "edit": "Edit",
    "search": "Search",
    "submit": "Submit",
    "success": "Success",
    "error": "Error",
    "confirm": "Confirm"
}
```

**日文 (jp):**
```json
// resource/translations/jp/messages.json
{
    "welcome": "ようこそ",
    "save": "保存",
    "cancel": "キャンセル",
    "delete": "削除",
    "edit": "編集",
    "search": "検索",
    "submit": "送信",
    "success": "成功",
    "error": "エラー",
    "confirm": "確認"
}
```

---

### 方案3：使用一键创建脚本

**创建文件：`scripts/create_translations.php`**

```php
<?php

$basePath = __DIR__ . '/../resource/translations';
$locales = [
    'zh_CN' => [
        'welcome' => '欢迎',
        'save' => '保存',
        'cancel' => '取消',
        'delete' => '删除',
        'edit' => '编辑',
        'search' => '搜索',
        'submit' => '提交',
        'success' => '成功',
        'error' => '错误',
        'confirm' => '确认',
        'back' => '返回',
        'list' => '列表',
        'detail' => '详情',
        'create' => '创建',
        'update' => '更新',
        'status' => '状态',
        'action' => '操作',
    ],
    'zh_TW' => [
        'welcome' => '歡迎',
        'save' => '儲存',
        'cancel' => '取消',
        'delete' => '刪除',
        'edit' => '編輯',
        'search' => '搜尋',
        'submit' => '送出',
        'success' => '成功',
        'error' => '錯誤',
        'confirm' => '確認',
        'back' => '返回',
        'list' => '列表',
        'detail' => '詳情',
        'create' => '建立',
        'update' => '更新',
        'status' => '狀態',
        'action' => '操作',
    ],
    'en' => [
        'welcome' => 'Welcome',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'search' => 'Search',
        'submit' => 'Submit',
        'success' => 'Success',
        'error' => 'Error',
        'confirm' => 'Confirm',
        'back' => 'Back',
        'list' => 'List',
        'detail' => 'Detail',
        'create' => 'Create',
        'update' => 'Update',
        'status' => 'Status',
        'action' => 'Action',
    ],
    'jp' => [
        'welcome' => 'ようこそ',
        'save' => '保存',
        'cancel' => 'キャンセル',
        'delete' => '削除',
        'edit' => '編集',
        'search' => '検索',
        'submit' => '送信',
        'success' => '成功',
        'error' => 'エラー',
        'confirm' => '確認',
        'back' => '戻る',
        'list' => 'リスト',
        'detail' => '詳細',
        'create' => '作成',
        'update' => '更新',
        'status' => 'ステータス',
        'action' => '操作',
    ],
];

// 创建目录和文件
foreach ($locales as $locale => $translations) {
    $localePath = $basePath . '/' . $locale;
    
    // 创建目录
    if (!is_dir($localePath)) {
        mkdir($localePath, 0755, true);
        echo "Created directory: {$localePath}\n";
    }
    
    // 创建 messages.json
    $filePath = $localePath . '/messages.json';
    file_put_contents(
        $filePath,
        json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    echo "Created file: {$filePath}\n";
}

echo "\n✅ 翻译文件创建完成！\n";
echo "共创建 " . count($locales) . " 个语言包\n";
```

**运行脚本：**
```bash
cd D:\gk_admin
php scripts/create_translations.php
```

---

## 从 yjb_s 数据库获取的 SQL 查询示例

### 查询1：查找系统配置表

```sql
-- 查找可能包含翻译的表
SELECT TABLE_NAME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'super9' 
  AND (TABLE_NAME LIKE '%system%' 
    OR TABLE_NAME LIKE '%translation%' 
    OR TABLE_NAME LIKE '%lang%'
    OR TABLE_NAME LIKE '%config%')
ORDER BY TABLE_NAME;
```

### 查询2：查看表结构

```sql
-- 查看 yjb_s 或相关表的结构
DESCRIBE yjb_system_config;
-- 或
SHOW CREATE TABLE yjb_system_config;
```

### 查询3：导出翻译数据

```sql
-- 导出翻译配置
SELECT * 
FROM yjb_system_config 
WHERE feature IN ('translations', 'lang', 'locale')
INTO OUTFILE '/tmp/translations.json';
```

---

## 验证翻译文件

创建后验证文件结构：

```bash
# 检查目录结构
tree resource/translations/

# 应该输出：
# resource/translations/
# ├── zh_CN/
# │   └── messages.json
# ├── zh_TW/
# │   └── messages.json
# ├── en/
# │   └── messages.json
# └── jp/
#     └── messages.json

# 查看文件内容
cat resource/translations/zh_CN/messages.json
```

---

## 部署到生产环境

将创建的翻译文件同步到生产服务器：

```bash
# 使用 rsync 同步
rsync -avz resource/translations/ user@server:/www/wwwroot/admin-test.5super9.com/resource/translations/

# 或使用 scp
scp -r resource/translations user@server:/www/wwwroot/admin-test.5super9.com/resource/

# 设置权限
ssh user@server "chmod -R 755 /www/wwwroot/admin-test.5super9.com/resource/translations"
```

---

## 使用翻译

在代码中使用翻译：

```php
// 使用 trans() 函数
echo trans('welcome'); // 输出：欢迎（如果当前语言是 zh_CN）

// 指定语言
echo trans('welcome', [], 'en'); // 输出：Welcome

// 带参数的翻译
echo trans('hello_user', ['name' => '张三']); // 需要在 messages.json 中定义
```

---

## 注意事项

1. **文件权限**：确保 `resource/translations/` 目录有读取权限（755）
2. **JSON 格式**：翻译文件必须是有效的 JSON 格式
3. **编码**：使用 UTF-8 编码，保存时选择 `JSON_UNESCAPED_UNICODE`
4. **缓存**：修改翻译后，清除缓存：`php webman clear`
5. **备份**：从数据库导出后，备份翻译文件

---

## 常见问题

### Q1: 翻译文件不生效？
**A:** 清除缓存：`rm -rf runtime/cache/translation*` 然后重启服务

### Q2: 找不到 yjb_s 表？
**A:** 可能是别名或视图，尝试：
```sql
SHOW FULL TABLES WHERE Table_type = 'VIEW';
```

### Q3: 翻译文件格式错误？
**A:** 使用在线 JSON 验证工具检查格式：https://jsonlint.com/

---

**创建日期：** 2026-05-19
**适用版本：** Webman 1.5.x + ExAdmin
