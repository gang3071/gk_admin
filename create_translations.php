<?php
/**
 * 创建翻译文件
 * 用法: php create_translations.php
 */

$basePath = __DIR__ . '/resource/translations';

// 定义基本翻译内容
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
        'yes' => '是',
        'no' => '否',
        'close' => '关闭',
        'export' => '导出',
        'import' => '导入',
        'download' => '下载',
        'upload' => '上传',
        'please_select' => '请选择',
        'please_input' => '请输入',
        'operation_success' => '操作成功',
        'operation_failed' => '操作失败',
        'loading' => '加载中...',
        'no_data' => '暂无数据',
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
        'yes' => '是',
        'no' => '否',
        'close' => '關閉',
        'export' => '匯出',
        'import' => '匯入',
        'download' => '下載',
        'upload' => '上傳',
        'please_select' => '請選擇',
        'please_input' => '請輸入',
        'operation_success' => '操作成功',
        'operation_failed' => '操作失敗',
        'loading' => '載入中...',
        'no_data' => '暫無資料',
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
        'yes' => 'Yes',
        'no' => 'No',
        'close' => 'Close',
        'export' => 'Export',
        'import' => 'Import',
        'download' => 'Download',
        'upload' => 'Upload',
        'please_select' => 'Please Select',
        'please_input' => 'Please Input',
        'operation_success' => 'Operation Success',
        'operation_failed' => 'Operation Failed',
        'loading' => 'Loading...',
        'no_data' => 'No Data',
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
        'yes' => 'はい',
        'no' => 'いいえ',
        'close' => '閉じる',
        'export' => 'エクスポート',
        'import' => 'インポート',
        'download' => 'ダウンロード',
        'upload' => 'アップロード',
        'please_select' => '選択してください',
        'please_input' => '入力してください',
        'operation_success' => '操作成功',
        'operation_failed' => '操作失敗',
        'loading' => '読み込み中...',
        'no_data' => 'データなし',
    ],
];

echo "开始创建翻译文件...\n\n";

// 创建基础目录
if (!is_dir($basePath)) {
    mkdir($basePath, 0755, true);
    echo "✓ 创建目录: {$basePath}\n";
}

// 创建每个语言的翻译文件
$totalFiles = 0;
foreach ($locales as $locale => $translations) {
    $localePath = $basePath . '/' . $locale;

    // 创建语言目录
    if (!is_dir($localePath)) {
        mkdir($localePath, 0755, true);
        echo "✓ 创建目录: {$localePath}\n";
    }

    // 创建 messages.json
    $filePath = $localePath . '/messages.json';
    $jsonContent = json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($filePath, $jsonContent);
    echo "✓ 创建文件: {$filePath} (" . count($translations) . " 条翻译)\n";
    $totalFiles++;
}

echo "\n";
echo "================================================\n";
echo "✅ 翻译文件创建完成！\n";
echo "================================================\n";
echo "总计: {$totalFiles} 个语言包\n";
echo "语言: " . implode(', ', array_keys($locales)) . "\n";
echo "每个语言包: " . count(reset($locales)) . " 条翻译\n";
echo "\n";
echo "文件位置:\n";
foreach (array_keys($locales) as $locale) {
    echo "  - resource/translations/{$locale}/messages.json\n";
}
echo "\n";
echo "下一步:\n";
echo "1. 将 resource/translations/ 目录同步到生产服务器\n";
echo "2. 重启 Webman 服务: php start.php restart\n";
echo "3. 如果需要更多翻译，请从 yjb_s 数据库导出\n";
echo "\n";
