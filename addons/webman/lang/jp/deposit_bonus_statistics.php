<?php

return [
    'title' => '入金ボーナス統計レポート',

    'fields' => [
        'id' => 'ID',
        'activity_name' => 'アクティビティ名',
        'stat_date' => '統計日',
        'total_participants' => '総参加者数',
        'new_participants' => '新規参加者数',
        'total_orders' => '総注文数',
        'total_deposit_amount' => '総入金金額',
        'total_bonus_amount' => '総ボーナス金額',
        'total_bet_amount' => '総ベット量',
        'total_withdraw_amount' => '総出金金額',
        'completed_orders' => '完了注文',
        'expired_orders' => '期限切れ注文',
        'cancelled_orders' => 'キャンセル注文',
        'updated_at' => '更新日時',
        'created_at' => '作成日時',
    ],

    'stats' => [
        'total_participants' => '総参加者',
        'total_orders' => '総注文',
        'total_deposit' => '総入金',
        'total_bonus' => '総ボーナス',
        'total_bet' => '総ベット',
        'completed_orders' => '完了注文',
    ],

    'stat_date_start' => '開始日',
    'stat_date_end' => '終了日',
];
