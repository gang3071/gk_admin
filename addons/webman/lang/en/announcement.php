<?php

use addons\webman\model\Announcement;

return [
    'title' => 'Announcement Management',
    'fields' => [
        'id' => 'ID',
        'title' => 'title',
        'content' => 'content',
        'valid_time' => 'valid time',
        'push_time' => 'Publish time',
        'status' => 'status',
        'department_id' => 'channel',
        'sort' => 'sort',
        'priority' => 'Priority',
        'admin_id' => 'Administrator ID',
        'admin_name' => 'Administrator name',
        'created_at' => 'Creation time',
    ],
    'priority' => [
        Announcement::PRIORITY_ORDINARY => 'Normal',
        Announcement::PRIORITY_SENIOR => 'Advanced',
        Announcement::PRIORITY_EMERGENT => 'Emergency',
    ],
    'help' => [
        'valid_time' => 'If left blank, it will be permanently valid',
        'push_time' => 'This announcement will not be displayed to customers until after the release time',
        'title' => 'The announcement title can be up to 200 words',
    ]
];
