<?php

return [
    'title' => '店舗分潤レポート',

    // フィールド
    'fields' => [
        'id' => 'ID',
        'store_name' => '店舗名',
        'device_count' => 'デバイス数',
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
        'select_store' => '店舗を選択',
        'all_stores' => '全店舗',
        'time_range' => '時間範囲',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
    ],

    // 統計データ
    'stats' => [
        'total_recharge' => '総開分',
        'total_withdraw' => '総洗分',
        'total_machine_put' => '総投鈔',
        'total_lottery' => '総宝くじ',
        'total_subtotal' => '総小計',
        'total_agent_profit' => '総代理分潤',
        'total_channel_profit' => '総チャネル分潤',
    ],

    // エクスポート
    'export' => [
        'filename' => '店舗分潤月報_',
        'title' => '店舗分潤月報',
        'agent_info' => '代理：',
        'time_range' => '統計期間：',
        'start_from' => '開始時間：',
        'end_at' => '終了時間：',
        'all_time' => '全期間',
        'export_time' => 'エクスポート時間：',
        'summary_title' => '統計集計',
        'total' => '合計',
    ],
];
