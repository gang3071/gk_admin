<?php

return [
    'title' => '店家分潤報表',

    // 字段
    'fields' => [
        'id' => 'ID',
        'store_name' => '店家名稱',
        'device_count' => '設備數量',
        'store_username' => '登錄賬號',
        'agent_name' => '所屬代理',
        'remark' => '備註',
        'recharge_amount' => '開分',
        'withdraw_amount' => '洗分',
        'machine_put_point' => '投钞',
        'incoming_ticket_amount' => '入票',
        'ticket_redeem_amount' => '出卷',
        'ticket_open_score_amount' => '開票',
        'redeem_amount' => '核銷',
        'ticket_unredeemed_amount' => '未核銷',
        'experience_coupon_amount' => '體驗券',
        'welfare_coupon_amount' => '福利券',
        'lottery_amount' => '彩金',
        'activity_total' => '活動獎勵',
        'electronic_game_bet_amount' => '電子打碼量',
        'machine_bet_amount' => '機器打碼量',
        'total_income' => '總收入',
        'total_expense' => '總支出',
        'total_profit' => '利潤',
        'subtotal' => '小計',
        'agent_commission' => '代理抽成比例',
        'agent_profit' => '代理分潤',
        'channel_commission' => '渠道抽成比例',
        'channel_profit' => '渠道分潤',
    ],

    // 筛选器
    'filter' => [
        'select_agent' => '選擇代理',
        'all_agents' => '全部代理',
        'select_store' => '選擇店家',
        'all_stores' => '全部店家',
        'remark_placeholder' => '請輸入備註關鍵詞',
        'time_range' => '時間範圍',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
        'select_shift' => '選擇班次',
        'all_shifts' => '全部班次',
    ],

    // 班次
    'shift' => [
        'morning' => '早班 (08:00-16:00)',
        'afternoon' => '中班 (16:00-00:00)',
        'night' => '晚班 (00:00-08:00)',
    ],

    // 統計數據
    'stats' => [
        'total_recharge' => '總開分',
        'total_withdraw' => '總洗分',
        'total_machine_put' => '總投钞',
        'total_lottery' => '總彩金',
        'total_subtotal' => '總小計',
        'total_agent_profit' => '總代理分潤',
        'total_channel_profit' => '總渠道分潤',
        'total_income' => '累計總收入',
        'total_expense' => '累計總支出',
        'total_profit' => '累計總利潤',
        'total_activity' => '總活動獎勵',
    ],

    // 消息提示
    'message' => [
        'store_not_found' => '店家不存在',
        'update_success' => '備註更新成功',
    ],
];