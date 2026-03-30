<?php

return [
    'title' => '游戏账号',

    // 字段
    'fields' => [
        'player_uuid' => '玩家UUID',
        'platform_name' => '游戏平台',
        'player_code' => '平台账号',
        'player_name' => '设备名称',
        'status' => '状态',
        'created_at' => '创建时间',
    ],

    // 状态
    'status' => [
        'normal' => '正常',
        'locked' => '锁定',
        'unknown' => '未知',
    ],

    // 其他
    'unknown_platform' => '未知平台',
];
