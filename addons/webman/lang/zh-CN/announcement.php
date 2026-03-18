<?php

use addons\webman\model\Announcement;

return [
    'title' => '公告管理',
    'fields' => [
        'id' => 'ID',
        'title' => '标题',
        'content' => '内容',
        'valid_time' => '有效时间',
        'push_time' => '发布时间',
        'status' => '状态',
        'department_id' => '渠道',
        'sort' => '排序',
        'priority' => '优先级',
        'admin_id' => '管理员ID',
        'admin_name' => '管理员名称',
        'created_at' => '创建时间',
    ],
    'priority' => [
        Announcement::PRIORITY_ORDINARY => '普通',
        Announcement::PRIORITY_SENIOR => '高级',
        Announcement::PRIORITY_EMERGENT => '紧急',
    ],
    'help' => [
        'valid_time' => '不填时为永久有效',
        'push_time' => '过发布时间该公告才对客户展示',
        'title' => '公告标题最多200个字',
    ]
];
