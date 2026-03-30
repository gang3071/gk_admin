<?php

return [
    'title' => 'サードパーティゲームプラットフォームアカウント',

    // フィールド
    'fields' => [
        'player_uuid' => 'プレイヤーUUID',
        'platform_name' => 'ゲームプラットフォーム',
        'player_code' => 'プラットフォームアカウント',
        'player_name' => 'プラットフォームユーザー名',
        'status' => 'ステータス',
        'has_out' => '転出済み',
        'created_at' => '作成日時',
    ],

    // ステータス
    'status' => [
        'normal' => '正常',
        'locked' => 'ロック',
        'unknown' => '不明',
    ],

    // 転出状態
    'has_out' => [
        'yes' => '転出済み',
        'no' => '未転出',
    ],

    // その他
    'unknown_platform' => '不明なプラットフォーム',
];
