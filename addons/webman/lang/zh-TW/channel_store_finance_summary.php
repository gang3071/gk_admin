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
        'select_agent'         => '選擇代理',
        'all_agents'           => '全部代理',
        'select_store'         => '選擇店家',
        'all_stores'           => '全部店家',
        'placeholder_stores'   => '選擇門店（可多選）',
        'placeholder_agent'    => '代理（全部）',
        'placeholder_date'     => '時間類型',
        'time_range'           => '時間範圍',
        'start_time'           => '開始時間',
        'end_time'             => '結束時間',
        'query'                => '查詢',
        'reset'                => '重置',
    ],

    'date_types' => [
        'today'      => '今天',
        'yesterday'  => '昨天',
        'this_week'  => '本週',
        'last_week'  => '上週',
        'this_month' => '本月',
        'last_month' => '上月',
    ],

    'panel' => [
        'machine_win_loss'    => '實體機台輸贏',
        'electronic_win_loss' => '電子遊戲輸贏',
        'delivery_detail'     => '帳變明細（點擊類型查看說明）',
        'income'              => '收入',
        'expense'             => '支出',
        'profit'              => '盈利',
        'loss'                => '虧損',
        'even'                => '持平',
    ],

    'states' => [
        'hint'          => '選擇門店和時間範圍後點擊查詢',
        'search_failed' => '查詢失敗',
        'retry'         => '重試',
        'no_data'       => '該條件下暫無數據',
    ],

    'delivery_types' => [
        1  => '管理員加點',
        4  => '機台上分',
        5  => '機台下分',
        6  => '充值(開分)',
        7  => '提現(洗分)',
        8  => '管理員扣點',
        12 => '推廣員分潤',
        13 => '彩金中獎',
        16 => '全民邀請獎勵',
        22 => '特殊類型',
        23 => '投鈔',
        31 => '預扣',
        32 => '退款',
        33 => '摸獎券獎勵',
        34 => 'VIP生日禮金',
        35 => 'VIP升級禮金',
        36 => '反水池反水',
        37 => 'VIP每日登錄',
        38 => '活動外增',
        39 => '排行榜獎勵',
    ],

    'desc_panel' => [
        'title'      => '帳變類型說明',
        'click_hint' => '點擊左側帳變類型\n查看詳細說明',
    ],

    'summary' => [
        'row_label' => '匯總',
    ],
];
