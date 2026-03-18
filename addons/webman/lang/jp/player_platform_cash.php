<?php

use addons\webman\model\PlayerPlatformCash;

return [
    'title' => 'プラットフォームウォレット',
    'fields' => [
        'id' => 'ID',
        'player_id' => 'プレイヤーID',
        'platform_id' => 'プラットフォーム ID',
        'platform_name' => 'プラットフォーム名',
        'money' => 'ポイント',
        'status' => 'ゲームプラットフォームのステータス',
        'created_at' => '作成時刻',
        'updated_at' => '更新時刻',
    ],
    'platform_name' => [
        PlayerPlatformCash::PLATFORM_SELF => 'ウォレット残高'
    ]
];
