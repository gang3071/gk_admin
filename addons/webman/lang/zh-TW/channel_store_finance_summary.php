<?php

return [
    'title' => '門店財務匯總',

    'fields' => [
        'id'                       => 'ID',
        'store_name'               => '店家名稱',
        'store_username'           => '登入帳號',
        'agent_name'               => '所屬代理',
        'electronic_game_win_loss' => '電子遊戲輸贏',
        'machine_win_loss'         => '實體機輸贏',
        'lottery_ticket_sent'      => '摸彩劵送出',
        'electronic_jackpot'       => '電子遊戲彩金',
        'machine_jackpot'          => '實體機台彩金',
        'wallet_increase'          => '錢包增加',
        'wallet_decrease'          => '錢包扣除',
        'machine_rebate'           => '實體機台返水',
        'electronic_rebate'        => '電子遊戲返水',
        'coupon_sent'              => '體驗/福利劵',
    ],

    'filter' => [
        'select_agent'  => '選擇代理',
        'all_agents'    => '全部代理',
        'select_store'  => '選擇店家',
        'all_stores'    => '全部店家',
        'time_range'    => '時間範圍',
        'start_time'    => '開始時間',
        'end_time'      => '結束時間',
    ],

    'summary' => [
        'row_label' => '匯總',
    ],
];
