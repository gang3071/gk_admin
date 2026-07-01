<?php
/**
 * 从数据库 yjb_s 导出翻译文件
 * 用法: php export_translations_from_db.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use support\Db;

echo "开始从数据库导出翻译...\n\n";

try {
    // 方案1: 尝试从 yjb_system_config 表获取
    echo "尝试从 yjb_system_config 表获取...\n";

    $systemConfig = Db::table('yjb_system_config')
        ->where('feature', 'translations')
        ->orWhere('feature', 'like', '%lang%')
        ->orWhere('feature', 'like', '%translation%')
        ->get();

    if ($systemConfig->count() > 0) {
        echo "✓ 找到 {$systemConfig->count()} 条系统配置记录\n\n";

        foreach ($systemConfig as $config) {
            echo "ID: {$config->id}\n";
            echo "Feature: {$config->feature}\n";
            echo "Content: " . substr($config->content, 0, 100) . "...\n";
            echo "---\n";
        }

        // 如果 content 是 JSON 格式，尝试解析
        $translationConfig = $systemConfig->first();
        if ($translationConfig && !empty($translationConfig->content)) {
            $content = json_decode($translationConfig->content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($content)) {
                echo "\n✓ 成功解析翻译数据\n";
                saveTranslations($content);
            } else {
                echo "\n⚠ 内容不是有效的 JSON 格式\n";
            }
        }
    } else {
        echo "⚠ 未在 yjb_system_config 表找到翻译数据\n";
    }

    // 方案2: 尝试查找其他可能的表
    echo "\n尝试查找其他可能包含翻译的表...\n";

    $tables = Db::select("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND (TABLE_NAME LIKE '%translation%'
            OR TABLE_NAME LIKE '%lang%'
            OR TABLE_NAME LIKE '%locale%'
            OR TABLE_NAME LIKE '%yjb_s%')
    ");

    if (!empty($tables)) {
        echo "找到以下可能的表:\n";
        foreach ($tables as $table) {
            echo "  - {$table->TABLE_NAME}\n";
        }
        echo "\n";

        // 查询第一个表的内容
        if (isset($tables[0])) {
            $tableName = $tables[0]->TABLE_NAME;
            echo "查询 {$tableName} 表的内容:\n";

            $records = Db::table($tableName)->limit(5)->get();
            foreach ($records as $record) {
                echo json_encode((array)$record, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    } else {
        echo "⚠ 未找到相关的翻译表\n";
    }

    // 方案3: 显示所有表，让用户手动选择
    echo "\n所有可用的表:\n";
    $allTables = Db::select("SHOW TABLES");
    $count = 0;
    foreach ($allTables as $table) {
        $tableName = array_values((array)$table)[0];
        if (stripos($tableName, 'yjb_') === 0) {
            echo "  - {$tableName}\n";
            $count++;
        }
    }
    echo "共 {$count} 个 yjb_ 开头的表\n";

} catch (\Exception $e) {
    echo "\n✗ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
}

echo "\n";
echo "================================================\n";
echo "提示:\n";
echo "================================================\n";
echo "1. 如果找不到翻译数据，请手动查询数据库:\n";
echo "   mysql -u USERNAME -p DATABASE\n";
echo "   SHOW TABLES LIKE '%yjb_s%';\n";
echo "   SELECT * FROM table_name;\n";
echo "\n";
echo "2. 如果已有基础翻译文件，可以使用:\n";
echo "   resource/translations/zh_CN/messages.json\n";
echo "\n";
echo "3. 查看详细文档:\n";
echo "   cat TRANSLATION_SETUP_GUIDE.md\n";
echo "\n";

/**
 * 保存翻译到文件
 */
function saveTranslations(array $translations)
{
    $basePath = __DIR__ . '/resource/translations';

    foreach ($translations as $locale => $messages) {
        $localePath = $basePath . '/' . $locale;

        // 创建目录
        if (!is_dir($localePath)) {
            mkdir($localePath, 0755, true);
        }

        // 保存翻译文件
        $filePath = $localePath . '/messages.json';
        file_put_contents(
            $filePath,
            json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        echo "✓ 保存翻译: {$filePath} (" . count($messages) . " 条)\n";
    }
}
