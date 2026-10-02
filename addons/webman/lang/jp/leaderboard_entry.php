<?php

return [
    'title' => 'ランキング詳細',
    'fields' => [
        'id' => 'ID',
        'leaderboard_id' => 'ランキング',
        'player_id' => 'プレイヤー',
        'store_admin_id' => '店舗',
        'rank' => '順位',
        'score' => 'スコア',
        'prize_amount' => '賞金',
        'grant_status' => '付与状態',
        'granted_at' => '付与時間',
        'created_at' => '作成日時',
        'updated_at' => '更新日時'
    ],
    'grant_status' => [
        0 => '未付与',
        1 => '付与済'
    ],
    'granted_at' => [
        'start' => '付与開始時間',
        'end' => '付与終了時間'
    ],
];

