<?php

return [
    'title' => '下線分潤結算記錄',

    'fields' => [
        'id' => 'ID',
        'settlement_tradeno' => '結算單號',
        'adjust_amount' => '分潤調整金額',
        'actual_amount' => '實際分潤金額',
        'profit_amount' => '分潤金額',
        'ratio' => '分潤比例',
        'sub_name' => '上繳對象',
        'sub_profit_amount' => '上繳金額',
        'sub_ratio' => '上繳比例',
        'total_bet' => '總押注',
        'total_diff' => '總輸贏',
        'machine_point' => '投鈔點數',
        'total_income' => '總營收',
        'total_in' => '轉入（開分）',
        'total_out' => '轉出（洗分）',
        'user_name' => '管理員',
        'start_time' => '結算開始時間',
        'end_time' => '結算結束時間',
        'created_at' => '創建時間',
    ],

    'detail' => [
        'agent_name' => '代理名稱',
        'uuid' => 'UUID',
        'submit_ratio' => '上繳比例',
        'machine_put_point' => '總投鈔（開分）',
        'present_out_amount' => '轉出（洗分）',
        'present_in_amount' => '總轉入（開分）',
        'total_point' => '總營收',
        'settlement_amount' => '已結算金額',
        'created_at' => '創建時間',
        'last_settlement_time' => '最近結算時間',
    ],

    'label' => [
        'agent_store' => '代理/店家',
        'agent_account' => '代理帳號',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
        'no_time' => '無',
    ],

    'type' => [
        'store' => '店家',
        'agent' => '代理',
        'channel' => '渠道',
    ],

    'placeholder' => [
        'settlement_tradeno' => '結算單號',
        'agent_store' => '代理/店家',
    ],
];
