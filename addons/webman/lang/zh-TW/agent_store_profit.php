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
        'select_store' => '選擇店家',
        'all_stores' => '全部店家',
        'time_range' => '時間範圍',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
    ],

    // 統計數據
    'stats' => [
        'total_recharge' => '總開分',
        'total_withdraw' => '總洗分',
        'total_machine_put' => '總投鈔',
        'total_lottery' => '總彩金',
        'total_subtotal' => '總小計',
        'total_agent_profit' => '總代理分潤',
        'total_channel_profit' => '總管道分潤',
    ],

    // 導出
    'export' => [
        'filename' => '店家分潤月報_',
        'title' => '店家分潤月報',
        'agent_info' => '代理：',
        'time_range' => '統計時間：',
        'start_from' => '起始時間：',
        'end_at' => '截止時間：',
        'all_time' => '全部時間',
        'export_time' => '導出時間：',
        'summary_title' => '統計匯總',
        'total' => '合計',
    ],
];
