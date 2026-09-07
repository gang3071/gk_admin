<?php

return [
    'title' => '店舗分潤レポート',

    // フィールド
    'fields' => [
        'id' => 'ID',
        'store_name' => '店舗名',
        'device_count' => 'デバイス数',
        'store_username' => 'ログインアカウント',
        'recharge_amount' => '開分',
        'open_score_amount' => '開分',
        'withdraw_amount' => '洗分',
        'machine_put_point' => '投鈔',
        'incoming_ticket_amount' => '入票',
        'ticket_redeem_amount' => '出卷',
        'ticket_open_score_amount' => '開票',
        'counter_ticket_amount' => 'カウンター発券',
        'redeem_amount' => '核銷',
        'redeem_machine_amount' => 'マシン消込',
        'ticket_unredeemed_amount' => '未核銷',
        'experience_coupon_amount' => '體驗券',
        'welfare_coupon_amount' => '福利券',
        'lottery_amount' => '宝くじ',
        'activity_total' => 'アクティビティ報酬',
        'electronic_game_bet_amount' => '電子ベット',
        'machine_bet_amount' => 'マシンベット',
        'total_income' => '総収入',
        'total_expense' => '総支出',
        'total_profit' => '利益',
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
        'select_shift' => 'シフト選択',
        'all_shifts' => '全シフト',
        'time_range' => '時間範囲',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
    ],

    // シフト
    'shift' => [
        'morning' => '朝番 (08:00-16:00)',
        'afternoon' => '午番 (16:00-00:00)',
        'night' => '夜番 (00:00-08:00)',
    ],

    // 統計データ
    'stats' => [
        'panel_header' => '統計データを表示',
        'loading' => 'データ読み込み中...',
        'refresh' => '更新',
        'load_error' => 'データの読み込みに失敗しました',
        'retry' => '再試行',
        'click_to_view' => 'クリックして統計データを表示',
        'load_failed_msg' => 'データの読み込みに失敗しました。再試行してください',
        'total_recharge' => '総開分',
        'total_withdraw' => '総洗分',
        'total_machine_put' => '総投鈔',
        'total_lottery' => '総宝くじ',
        'total_subtotal' => '総小計',
        'total_agent_profit' => '総代理分潤',
        'total_channel_profit' => '総チャネル分潤',
        'total_income' => '累計総収入',
        'total_expense' => '累計総支出',
        'total_profit' => '累計総利益',
        'total_activity' => '総活動ボーナス',
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
