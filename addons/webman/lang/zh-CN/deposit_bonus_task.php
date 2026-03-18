<?php

return [
    'title' => '打码量任务管理',

    'fields' => [
        'id' => '任务ID',
        'player' => '玩家',
        'activity' => '活动名称',
        'bet_info' => '打码量信息',
        'progress' => '完成进度',
        'status' => '任务状态',
        'time_info' => '时间信息',
    ],

    'stats' => [
        'total_count' => '总任务数',
        'in_progress' => '进行中',
        'completed' => '已完成',
        'expired' => '已过期',
    ],

    'status_in_progress' => '进行中',
    'status_completed' => '已完成',
    'status_expired' => '已过期',

    'required' => '需要打码',
    'current' => '当前已打',
    'remaining' => '剩余需打',

    'created_at' => '创建时间',
    'expires_at' => '过期时间',
    'completed_at' => '完成时间',
    'remaining_days' => '剩余天数',

    'search_player' => '搜索玩家账号',
    'view_detail' => '查看详情',
];
