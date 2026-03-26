<?php

return [
    'title' => '店家分润报表',

    // 字段
    'fields' => [
        'id' => 'ID',
        'store_name' => '店家名称',
        'store_username' => '登录账号',
        'recharge_amount' => '累计开分',
        'withdraw_amount' => '累计洗分',
        'machine_put_point' => '投钞',
        'lottery_amount' => '彩金',
        'subtotal' => '小计',
        'agent_commission' => '代理抽成比例',
        'agent_profit' => '代理分润',
        'channel_commission' => '渠道抽成比例',
        'channel_profit' => '渠道分润',
    ],

    // 筛选器
    'filter' => [
        'time_range' => '时间范围',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
    ],
];
