<?php

use addons\webman\model\PlayerPlatformCash;

return [
    'title' => '平台钱包',
    'fields' => [
        'id' => 'ID',
        'player_id' => '玩家ID',
        'platform_id' => '平台ID',
        'platform_name' => '平台名称',
        'money' => '点数',
        'status' => '游戏平台状态',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],
    'platform_name' => [
        PlayerPlatformCash::PLATFORM_SELF => '钱包余额'
    ]
];
