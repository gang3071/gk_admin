<?php

return [
    'title' => '注文内容',
    'fields' => [
        'id' => 'ID',
        'order_no' => '注文番号',
        'player_id' => 'プレイヤー',
        'department_id' => 'チャネル/部門',
        'admin_user_id' => '店舗',
        'device_id' => '設備',
        'total_amount' => '合計金額',
        'status' => 'ステータス',
        'remark' => '備考',
        'created_at' => '作成日時',
        'updated_at' => '更新日時'
    ],
    'reportItem' => [
        'title' => '食事明細レポート',
        'quantity' => '総数',
        'subtotal' => '合計ポイント'
    ],
    'status' => [
        0 => '未確認',
        1 => '確認済み',
        2 => '調理中',
        3 => '完了',
        4 => 'キャンセル済み'
    ],
    'cancel_refund' => '注文キャンセル・ポイント返金'
];

