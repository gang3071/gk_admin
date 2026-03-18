<?php

use addons\webman\model\PlayerPlatformCash;

return [
    'title' => '平台錢包',
    'fields' => [
        'id' => 'ID',
        'player_id' => '玩家ID',
        'platform_id' => '平台ID',
        'platform_name' => '平台名稱',
        'money' => '點數',
        'status' => '遊戲平台狀態',
        'created_at' => '創建時間',
        'updated_at' => '更新時間',
    ],
    'platform_name' => [
        PlayerPlatformCash::PLATFORM_SELF => '錢包餘額'
    ]
];
