<?php

return [
    'title' => '店舗分潤レポート',

    // フィールド
    'fields' => [
        'id' => 'ID',
        'store_name' => '店舗名',
        'store_username' => 'ログインアカウント',
        'recharge_amount' => '累計開分',
        'withdraw_amount' => '累計洗分',
        'machine_put_point' => '投鈔',
        'lottery_amount' => '宝くじ',
        'subtotal' => '小計',
        'agent_commission' => '代理手数料率',
        'agent_profit' => '代理分潤',
        'channel_commission' => 'チャネル手数料率',
        'channel_profit' => 'チャネル分潤',
    ],

    // フィルター
    'filter' => [
        'time_range' => '時間範囲',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
    ],
];
