# 翻译文件修复完成 ✅

## 问题已解决

错误信息：
```json
{
    "code": 100,
    "message": "File /www/wwwroot/admin-test.5super9.com/resource/translations not found",
    "data": []
}
```

**解决方案：** 已创建基础翻译文件结构 ✅

---

## 已完成的工作

### 1. ✅ 创建翻译文件结构

```
resource/translations/
├── zh_CN/
│   └── messages.json (30条基础翻译)
├── zh_TW/
│   └── messages.json (30条基础翻译)
├── en/
│   └── messages.json (30条基础翻译)
└── jp/
    └── messages.json (30条基础翻译)
```

### 2. ✅ 创建的脚本工具

| 脚本文件 | 用途 | 用法 |
|---------|------|------|
| `create_translations.php` | 快速创建基础翻译文件 | `php create_translations.php` |
| `export_translations_from_db.php` | 从数据库导出完整翻译 | `php export_translations_from_db.php` |
| `TRANSLATION_SETUP_GUIDE.md` | 详细配置指南 | 查看文档 |

---

## 下一步操作

### 方案A：使用基础翻译文件（快速修复）

如果只是为了修复错误，当前创建的基础翻译文件已经足够。

**1. 同步到生产服务器**

```bash
# 方法1: 使用 rsync
rsync -avz resource/translations/ user@admin-test.5super9.com:/www/wwwroot/admin-test.5super9.com/resource/translations/

# 方法2: 使用 scp
scp -r resource/translations user@admin-test.5super9.com:/www/wwwroot/admin-test.5super9.com/resource/

# 方法3: 使用 FTP/SFTP
# 上传 resource/translations 整个目录
```

**2. 设置权限**

```bash
ssh user@admin-test.5super9.com
cd /www/wwwroot/admin-test.5super9.com
chmod -R 755 resource/translations
chown -R www-data:www-data resource/translations
```

**3. 清除缓存并重启**

```bash
# 清除翻译缓存
rm -rf runtime/cache/translation*

# 重启 Webman
php start.php restart
```

**4. 验证**

访问之前报错的页面，确认错误已消失。

---

### 方案B：从 yjb_s 数据库导出完整翻译（推荐）

如果需要更完整的翻译内容，从数据库导出。

**1. 运行导出脚本**

```bash
php export_translations_from_db.php
```

**2. 如果脚本找不到翻译数据**

手动查询数据库：

```bash
# 连接数据库
mysql -h 127.0.0.1 -P 3306 -usuper9 -p super9

# 查找翻译相关的表
SHOW TABLES LIKE '%translation%';
SHOW TABLES LIKE '%yjb_s%';
SHOW TABLES LIKE '%lang%';

# 查看表内容
SELECT * FROM yjb_system_config WHERE feature LIKE '%translation%';
SELECT * FROM yjb_system_config WHERE feature LIKE '%lang%';

# 导出数据
SELECT * FROM yjb_system_config 
WHERE feature = 'translations' 
INTO OUTFILE '/tmp/translations.json';
```

**3. 如果 yjb_s 是表名**

```sql
-- 查看 yjb_s 表结构
DESCRIBE yjb_s;

-- 查询翻译数据
SELECT * FROM yjb_s WHERE type = 'translation';
SELECT * FROM yjb_s WHERE key LIKE '%lang%';

-- 导出
SELECT content FROM yjb_s WHERE type = 'translation' LIMIT 1;
```

**4. 手动导入翻译**

如果获取到 JSON 格式的翻译数据：

```php
<?php
// 将数据库中的 JSON 保存到文件
$dbContent = '{"zh_CN":{"welcome":"欢迎"},...}'; // 从数据库获取
$translations = json_decode($dbContent, true);

foreach ($translations as $locale => $messages) {
    $filePath = "resource/translations/{$locale}/messages.json";
    file_put_contents(
        $filePath,
        json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}
```

---

## 翻译文件说明

### 当前包含的基础翻译

```json
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
    "confirm": "确认",
    "back": "返回",
    "list": "列表",
    "detail": "详情",
    "create": "创建",
    "update": "更新",
    "status": "状态",
    "action": "操作",
    "yes": "是",
    "no": "否",
    "close": "关闭",
    "export": "导出",
    "import": "导入",
    "download": "下载",
    "upload": "上传",
    "please_select": "请选择",
    "please_input": "请输入",
    "operation_success": "操作成功",
    "operation_failed": "操作失败",
    "loading": "加载中...",
    "no_data": "暂无数据"
}
```

### 如何添加新翻译

**方法1：直接编辑 JSON 文件**

```bash
# 编辑简体中文翻译
vim resource/translations/zh_CN/messages.json

# 添加新的翻译条目
{
    "welcome": "欢迎",
    "new_key": "新翻译",  // 添加这行
    ...
}
```

**方法2：使用脚本批量添加**

```php
<?php
// add_translations.php
$newTranslations = [
    'zh_CN' => ['new_key' => '新翻译'],
    'zh_TW' => ['new_key' => '新翻譯'],
    'en' => ['new_key' => 'New Translation'],
    'jp' => ['new_key' => '新しい翻訳'],
];

foreach ($newTranslations as $locale => $newItems) {
    $filePath = "resource/translations/{$locale}/messages.json";
    $existing = json_decode(file_get_contents($filePath), true);
    $merged = array_merge($existing, $newItems);
    file_put_contents(
        $filePath,
        json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}
```

---

## 在代码中使用翻译

### 基本用法

```php
// 使用默认语言
echo trans('welcome'); // 输出：欢迎（如果默认是 zh_CN）

// 指定语言
echo trans('welcome', [], 'en'); // 输出：Welcome

// 带参数的翻译（需要先在翻译文件中定义）
// messages.json: "hello_user": "你好，{name}"
echo trans('hello_user', ['name' => '张三']); // 输出：你好，张三
```

### 在控制器中使用

```php
// addons/webman/controller/SomeController.php
public function index()
{
    return message_success(trans('operation_success'));
}
```

### 在视图中使用

```php
// 视图文件
<h1><?= trans('welcome') ?></h1>
<button><?= trans('save') ?></button>
```

---

## 常见问题

### Q1: 翻译文件修改后不生效？

**A:** 清除缓存并重启

```bash
# 清除翻译缓存
rm -rf runtime/cache/translation*

# 重启服务
php start.php restart
```

### Q2: 找不到 yjb_s 表？

**A:** 可能是以下情况：
1. 表名不是 `yjb_s`，而是 `yjb_system_config` 或其他
2. `yjb_s` 是视图而非表
3. 翻译存储在配置表的某个字段中

尝试：
```sql
-- 查看所有表
SHOW TABLES;

-- 查看视图
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- 搜索包含翻译的字段
SELECT TABLE_NAME, COLUMN_NAME 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'super9' 
  AND (COLUMN_NAME LIKE '%translation%' OR COLUMN_NAME LIKE '%lang%');
```

### Q3: 翻译文件格式错误？

**A:** 使用在线 JSON 验证器检查：
- https://jsonlint.com/
- 或使用 PHP 验证：`php -r "json_decode(file_get_contents('file.json'));echo json_last_error_msg();"`

### Q4: 需要更多语言支持？

**A:** 复制现有语言文件，翻译后保存：

```bash
# 添加韩语支持
cp -r resource/translations/en resource/translations/ko
vim resource/translations/ko/messages.json
# 翻译为韩语...
```

在 `config/translation.php` 中添加：
```php
'fallback_locale' => ['zh_CN', 'en', 'ko'],
```

---

## 验证清单

部署到生产后，检查以下项目：

- [ ] 翻译文件已上传到 `/www/wwwroot/admin-test.5super9.com/resource/translations/`
- [ ] 目录权限设置为 755
- [ ] 文件权限设置为 644
- [ ] 已清除缓存 `rm -rf runtime/cache/translation*`
- [ ] 已重启服务 `php start.php restart`
- [ ] 之前报错的页面现在正常显示
- [ ] 多语言切换功能正常
- [ ] JSON 格式验证通过

---

## 联系支持

如果仍有问题：

1. **检查日志**
   ```bash
   tail -f runtime/logs/webman.log
   grep "translation" runtime/logs/webman.log
   ```

2. **测试翻译加载**
   ```php
   <?php
   // test_translation.php
   require_once __DIR__ . '/vendor/autoload.php';
   
   echo "默认语言: " . config('translation.locale') . "\n";
   echo "翻译路径: " . config('translation.path') . "\n";
   echo "welcome 翻译: " . trans('welcome') . "\n";
   echo "welcome (en): " . trans('welcome', [], 'en') . "\n";
   ```

3. **提供以下信息**
   - 错误日志
   - 翻译文件路径截图
   - 数据库表结构
   - `config/translation.php` 内容

---

**修复日期：** 2026-05-19  
**当前状态：** ✅ 基础翻译文件已创建  
**下一步：** 从 yjb_s 数据库导出完整翻译（可选）
