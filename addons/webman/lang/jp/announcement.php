<?php

use addons\webman\model\Announcement;

return [
    'title' => 'お知らせ管理',
    'fields' => [
        'id' => 'ID',
        'title' => 'タイトル',
        'content' => 'コンテンツ',
        'valid_time' => '効果時間',
        'push_time' => 'リリースタイム',
        'status' => '状態',
        'department_id' => 'チャネル',
        'sort' => '分類する',
        'priority' => '優先順位',
        'admin_id' => '管理者ID',
        'admin_name' => '管理者名',
        'created_at' => '作成時間',
    ],
    'priority' => [
        Announcement::PRIORITY_ORDINARY => '普通',
        Announcement::PRIORITY_SENIOR => '高度',
        Announcement::PRIORITY_EMERGENT => '急',
    ],
    'help' => [
        'valid_time' => '空白のままにすると永久に有効になります',
        'push_time' => 'リリース時間後にお知らせがお客様に表示されます',
        'title' => 'お知らせタイトル 200文字以内',
    ]
];
