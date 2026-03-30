<?php

return [
    'title' => '第三方遊戲平台賬號',

    // 字段
    'fields' => [
        'player_uuid' => '玩家UUID',
        'platform_name' => '遊戲平台',
        'player_code' => '平台賬號',
        'player_name' => '平台用戶名',
        'status' => '狀態',
        'created_at' => '創建時間',
    ],

    // 状态
    'status' => [
        'normal' => '正常',
        'locked' => '鎖定',
        'unknown' => '未知',
    ],

    // 其他
    'unknown_platform' => '未知平台',
];
