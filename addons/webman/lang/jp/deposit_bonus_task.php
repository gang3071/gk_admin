<?php

return [
    'title' => 'ベット量タスク管理',

    'fields' => [
        'id' => 'タスクID',
        'player' => 'プレーヤー',
        'activity' => 'アクティビティ名',
        'bet_info' => 'ベット量情報',
        'progress' => '完了進捗',
        'status' => 'タスクステータス',
        'time_info' => '時間情報',
    ],

    'stats' => [
        'total_count' => '総タスク数',
        'in_progress' => '進行中',
        'completed' => '完了',
        'expired' => '期限切れ',
    ],

    'status_in_progress' => '進行中',
    'status_completed' => '完了',
    'status_expired' => '期限切れ',

    'required' => '必要ベット',
    'current' => '現在のベット',
    'remaining' => '残りベット',

    'created_at' => '作成日時',
    'expires_at' => '有効期限',
    'completed_at' => '完了日時',
    'remaining_days' => '残り日数',

    'search_player' => 'プレーヤーアカウント検索',
    'view_detail' => '詳細を表示',
];
