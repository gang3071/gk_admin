<?php

return [
    'title' => '店舗利益レポート',

    // Fields
    'fields' => [
        'id' => 'ID',
        'store_name' => '店舗名',
        'device_count' => 'デバイス数',
        'store_username' => 'ログインID',
        'agent_name' => '所属代理店',
        'remark' => '備考',
        'recharge_amount' => '開分',
        'withdraw_amount' => '洗分',
        'machine_put_point' => 'コイン投入',
        'incoming_ticket_amount' => '入票',
        'ticket_redeem_amount' => '出卷',
        'ticket_open_score_amount' => '開票',
        'redeem_amount' => '核銷',
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
        'remark_placeholder' => '備考キーワードを入力',
        'time_range' => '期間',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
        'select_shift' => 'シフト選択',
        'all_shifts' => '全シフト',
    ],

    // Shifts
    'shift' => [
        'morning' => '朝番 (08:00-16:00)',
        'afternoon' => '午番 (16:00-00:00)',
        'night' => '夜番 (00:00-08:00)',
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
        'total_income' => '累計総収入',
        'total_expense' => '累計総支出',
        'total_profit' => '累計総利益',
        'total_activity' => '総活動ボーナス',
    ],

    // Messages
    'message' => [
        'store_not_found' => '店舗が見つかりません',
        'update_success' => '備考が正常に更新されました',
    ],
];