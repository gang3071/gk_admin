<?php

return [
    'title' => '注文内容',
    'fields' => [
        'id' => 'ID',
        'order_no' => '注文番号',
        'player_id' => 'プレイヤー',
        'department_id' => 'チャネル/部門',
        'admin_user_id' => '店舗',
        'total_amount' => '合計金額',
        'status' => 'ステータス',
        'remark' => '備考',
        'created_at' => '作成日時',
        'updated_at' => '更新日時'
    ],
    'status' => [
        0 => '未確認',
        1 => '確認済み',
        2 => '調理中',
        3 => '完了',
        4 => 'キャンセル済み'
    ]
];

