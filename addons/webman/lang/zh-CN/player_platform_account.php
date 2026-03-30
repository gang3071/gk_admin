<?php

return [
    'title' => '第三方游戏平台账号',

    // 字段
    'fields' => [
        'player_uuid' => '玩家UUID',
        'platform_name' => '游戏平台',
        'player_code' => '平台账号',
        'player_name' => '平台用户名',
        'status' => '状态',
        'has_out' => '是否转出',
        'created_at' => '创建时间',
    ],

    // 状态
    'status' => [
        'normal' => '正常',
        'locked' => '锁定',
        'unknown' => '未知',
    ],

    // 是否转出
    'has_out' => [
        'yes' => '已转出',
        'no' => '未转出',
    ],

    // 其他
    'unknown_platform' => '未知平台',
];
