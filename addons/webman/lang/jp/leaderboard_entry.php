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
        1 => '受取済',
        2 => '受取待ち'
    ],
    'granted_at' => [
        'start' => '付与開始時間',
        'end' => '付与終了時間'
    ],
    'buttonGrant' => '報酬を配布',
    'confirmGrant' => 'ランキング報酬を配布しますか？',
    'errorGrant' => 'データエラー。ランキング期間が終了していること、賞金が0より大きいこと、および未配布であることを確認してください。',
    'errorGrantPermissions' => '操作権限がありません。',
    'successGrant' => '受取待ち報酬を作成しました。',
];

