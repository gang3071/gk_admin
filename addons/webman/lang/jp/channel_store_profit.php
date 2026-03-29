<?php

return [
    'title' => '店舗利益レポート',

    // Fields
    'fields' => [
        'id' => 'ID',
        'store_name' => '店舗名',
        'store_username' => 'ログインID',
        'agent_name' => '所属代理店',
        'recharge_amount' => '累計開分',
        'withdraw_amount' => '累計洗分',
        'machine_put_point' => 'コイン投入',
        'lottery_amount' => '宝くじ',
        'subtotal' => '小計',
        'agent_commission' => '代理店手数料率',
        'agent_profit' => '代理店利益',
        'channel_commission' => 'チャンネル手数料率',
        'channel_profit' => 'チャンネル利益',
    ],

    // Filters
    'filter' => [
        'select_agent' => '代理店選択',
        'all_agents' => '全代理店',
        'select_store' => '店舗選択',
        'all_stores' => '全店舗',
        'time_range' => '期間',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
    ],

    // Statistics
    'stats' => [
        'total_recharge' => '総開分',
        'total_withdraw' => '総洗分',
        'total_machine_put' => '総コイン投入',
        'total_lottery' => '総宝くじ',
        'total_subtotal' => '総小計',
        'total_agent_profit' => '総代理店利益',
        'total_channel_profit' => '総チャンネル利益',
    ],
];