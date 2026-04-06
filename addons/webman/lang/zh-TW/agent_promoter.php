<?php

return [
    'title' => '代理/設備列表',
    'settlement_records_title' => '下線分潤結算記錄',

    // 欄位
    'fields' => [
        'id' => 'ID',
        'agent_account' => '代理帳號',
        'agent_name' => '代理/店家',
        'bound_player_uuid' => '綁定玩家UUID',
        'payment_ratio' => '上繳比例',
        'current_total_revenue' => '當期總營收',
        'agent_profit_amount' => '代理分潤金額',
        'profit_adjust_amount' => '分潤調整金額',
        'current_payment_amount' => '當期上繳金額',
        'current_transfer_in' => '當期轉入(開分)',
        'current_cash_in' => '當期投鈔(開分)',
        'current_transfer_out' => '當期轉出(洗分)',
        'store_device_count' => '店家/設備數量',
        'device_count' => '設備數量',
        'current_machine_score' => '當前機台分數',
        'parent_agent' => '上級代理',
        'tradeno' => '結算單號',
        'settlement_tradeno' => '結算單號',
        'settlement_time' => '結算時間',
        'last_settlement_time' => '上次結算時間',
        'actual_profit_amount' => '實際分潤金額',
        'profit_amount' => '分潤金額',
        'profit_ratio' => '分潤比例',
        'payment_target' => '上繳對象',
        'payment_amount' => '上繳金額',
        'payment_ratio_percent' => '上繳比例',
        'total_bet' => '總押注',
        'total_diff' => '總輸贏',
        'machine_point' => '投鈔點數',
        'total_income' => '總營收',
        'total_cash_in' => '總投鈔(開分)',
        'total_transfer_in' => '總轉入(開分)',
        'transfer_in' => '轉入(開分)',
        'transfer_out' => '轉出(洗分)',
        'admin' => '管理員',
        'settlement_start_time' => '結算開始時間',
        'settlement_end_time' => '結算結束時間',
        'created_at' => '創建時間',
        'start_time' => '開始時間',
    ],

    // 標籤
    'label' => [
        'agent_account_with_value' => '代理帳號：{value}',
        'agent_settlement_with_value' => '代理分潤結算：{value}',
    ],

    // 佔位符
    'placeholder' => [
        'tradeno' => '結算單號',
        'agent_promoter' => '代理/店家',
    ],

    // 幫助文字
    'help' => [
        'settlement_time' => '結算時間不能超過當前時間，不能選擇上次結算時間的範圍',
        'settlement_range' => '可選擇結算時間範圍：{start} ~ 此刻',
    ],

    // 表單標題
    'form' => [
        'last_settlement_time' => '上次結算時間',
    ],

    // 按鈕
    'button' => [
        'settlement' => '結算',
        'batch_settlement' => '批量結算',
    ],

    // 確認消息
    'confirm' => [
        'batch_settlement' => '批量結算無法根據時間精確結算，將會結算所有未結算的時間，是否確定結算？',
    ],

    // 標籤
    'tag' => [
        'channel' => '渠道',
        'store' => '店家',
        'agent' => '代理',
    ],

    // Detail 項目
    'detail' => [
        'agent_name' => '代理名稱',
        'agent_created_at' => '代理創建時間',
        'settled_amount' => '已結算金額',
        'last_settlement_time' => '最近結算時間',
        'start_time_label' => '開始時間',
        'end_time_label' => '結束時間',
        'no_time' => '無',
    ],
];
