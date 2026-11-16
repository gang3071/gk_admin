<?php

return [
    'title' => '店舗財務サマリー',

    'fields' => [
        'id'                       => 'ID',
        'store_name'               => '店舗名',
        'store_username'           => 'ログインID',
        'agent_name'               => '代理店',
        'electronic_game_win_loss' => 'ゲーム損益',
        'machine_win_loss'         => '実機損益',
        'lottery_ticket_sent'      => '宝くじ発行',
        'electronic_jackpot'       => 'ゲームジャックポット',
        'machine_jackpot'          => '実機ジャックポット',
        'wallet_increase'          => 'ウォレット増加',
        'wallet_decrease'          => 'ウォレット減少',
        'machine_rebate'           => '実機リベート',
        'electronic_rebate'        => 'ゲームリベート',
        'coupon_sent'              => '体験/福利クーポン',
    ],

    'filter' => [
        'select_agent'  => '代理店を選択',
        'all_agents'    => 'すべての代理店',
        'select_store'  => '店舗を選択',
        'all_stores'    => 'すべての店舗',
        'time_range'    => '期間',
        'start_time'    => '開始時間',
        'end_time'      => '終了時間',
    ],

    'summary' => [
        'row_label' => '合計',
    ],
];
