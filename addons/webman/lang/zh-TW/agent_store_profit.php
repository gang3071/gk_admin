<?php

return [
    'title' => '店家分潤報表',

    // 欄位
    'fields' => [
        'id' => 'ID',
        'store_name' => '店家名稱',
        'store_username' => '登入帳號',
        'recharge_amount' => '累計開分',
        'withdraw_amount' => '累計洗分',
        'machine_put_point' => '投鈔',
        'lottery_amount' => '彩金',
        'subtotal' => '小計',
        'agent_commission' => '代理抽成比例',
        'agent_profit' => '代理分潤',
        'channel_commission' => '管道抽成比例',
        'channel_profit' => '管道分潤',
    ],

    // 篩選器
    'filter' => [
        'time_range' => '時間範圍',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
    ],
];
