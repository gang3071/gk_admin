<?php

use addons\webman\model\PlayerPlatformCash;

return [
    'title' => 'Platform Wallet',
    'fields' => [
        'id' => 'ID',
        'player_id' => 'player ID',
        'platform_id' => 'Platform ID',
        'platform_name' => 'Platform name',
        'money' => 'points',
        'status' => 'Game platform status',
        'created_at' => 'Creation time',
        'updated_at' => 'Update time',
    ],
    'platform_name' => [
        PlayerPlatformCash::PLATFORM_SELF => 'Wallet Balance'
    ]
];
