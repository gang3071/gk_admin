<?php

return [
    'title' => '打碼量任務管理',

    'fields' => [
        'id' => '任務ID',
        'player' => '玩家',
        'activity' => '活動名稱',
        'bet_info' => '打碼量信息',
        'progress' => '完成進度',
        'status' => '任務狀態',
        'time_info' => '時間信息',
    ],

    'stats' => [
        'total_count' => '總任務數',
        'in_progress' => '進行中',
        'completed' => '已完成',
        'expired' => '已過期',
    ],

    'status_in_progress' => '進行中',
    'status_completed' => '已完成',
    'status_expired' => '已過期',

    'required' => '需要打碼',
    'current' => '當前已打',
    'remaining' => '剩餘需打',

    'created_at' => '創建時間',
    'expires_at' => '過期時間',
    'completed_at' => '完成時間',
    'remaining_days' => '剩餘天數',

    'search_player' => '搜索玩家賬號',
    'view_detail' => '查看詳情',
];
