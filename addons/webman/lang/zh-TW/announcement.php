<?php

use addons\webman\model\Announcement;

return [
    'title' => '公告管理',
    'fields' => [
        'id' => 'ID',
        'title' => '標題',
        'content' => '內容',
        'valid_time' => '有效時間',
        'push_time' => '發佈時間',
        'status' => '狀態',
        'department_id' => '通路',
        'sort' => '排序',
        'priority' => '優先順序',
        'admin_id' => '管理員ID',
        'admin_name' => '管理員名稱',
        'created_at' => '創建時間',
    ],
    'priority' => [
        Announcement::PRIORITY_ORDINARY => '普通',
        Announcement::PRIORITY_SENIOR => '高級',
        Announcement::PRIORITY_EMERGENT => '緊急',
    ],
    'help' => [
        'valid_time' => '不填時為永久有效',
        'push_time' => '過發佈時間該公告才對客戶展示',
        'title' => '公告標題最多200個字',
    ],
];
