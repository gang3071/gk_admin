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
        'recharge_amount' => '累計開分',
        'withdraw_amount' => '累計洗分',
        'machine_put_point' => '投钞',
        'lottery_amount' => '彩金',
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
    ],

    // 消息提示
    'message' => [
        'store_not_found' => '店家不存在',
        'update_success' => '備註更新成功',
    ],
];