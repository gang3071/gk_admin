<?php

return [
    'title' => 'オフライン利益清算記録',

    'fields' => [
        'id' => 'ID',
        'settlement_tradeno' => '清算番号',
        'adjust_amount' => '利益調整金額',
        'actual_amount' => '実際の利益金額',
        'profit_amount' => '利益金額',
        'ratio' => '利益比率',
        'sub_name' => '提出対象',
        'sub_profit_amount' => '提出金額',
        'sub_ratio' => '提出比率',
        'total_bet' => '総ベット額',
        'total_diff' => '総勝敗',
        'machine_point' => '入金額',
        'total_income' => '総収益',
        'total_in' => '振込（チャージ）',
        'total_out' => '振出（出金）',
        'user_name' => '管理者',
        'start_time' => '清算開始時間',
        'end_time' => '清算終了時間',
        'created_at' => '作成時間',
    ],

    'detail' => [
        'agent_name' => '代理店名',
        'uuid' => 'UUID',
        'submit_ratio' => '提出比率',
        'machine_put_point' => '総入金（チャージ）',
        'present_out_amount' => '振出（出金）',
        'present_in_amount' => '総振込（チャージ）',
        'total_point' => '総収益',
        'settlement_amount' => '清算済み金額',
        'created_at' => '作成時間',
        'last_settlement_time' => '最近の清算時間',
    ],

    'label' => [
        'agent_store' => '代理店/店舗',
        'agent_account' => '代理店アカウント',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
        'no_time' => 'なし',
    ],

    'type' => [
        'store' => '店舗',
        'agent' => '代理店',
        'channel' => 'チャネル',
    ],

    'placeholder' => [
        'settlement_tradeno' => '清算番号',
        'agent_store' => '代理店/店舗',
    ],
];
