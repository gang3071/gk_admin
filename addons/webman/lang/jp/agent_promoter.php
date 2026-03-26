<?php

return [
    'title' => '代理/店舗リスト',
    'settlement_records_title' => '下位分潤精算記録',

    // フィールド
    'fields' => [
        'id' => 'ID',
        'agent_account' => '代理アカウント',
        'agent_name' => '代理/店舗',
        'bound_player_uuid' => 'バインドプレイヤーUUID',
        'payment_ratio' => '上納比率',
        'current_total_revenue' => '当期総収益',
        'agent_profit_amount' => '代理分潤金額',
        'profit_adjust_amount' => '分潤調整金額',
        'current_payment_amount' => '当期上納金額',
        'current_transfer_in' => '当期転入(投入)',
        'current_cash_in' => '当期投銭(リチャージ)',
        'current_transfer_out' => '当期転出(払出)',
        'store_device_count' => '店舗/デバイス数',
        'device_count' => 'デバイス数',
        'current_machine_score' => '現在の機械スコア',
        'parent_agent' => '上位代理',
        'tradeno' => '精算番号',
        'settlement_tradeno' => '精算番号',
        'settlement_time' => '精算時間',
        'last_settlement_time' => '前回の精算時間',
        'actual_profit_amount' => '実際の分潤金額',
        'profit_amount' => '分潤金額',
        'profit_ratio' => '分潤比率',
        'payment_target' => '上納対象',
        'payment_amount' => '上納金額',
        'payment_ratio_percent' => '上納比率',
        'total_bet' => '総ベット',
        'total_diff' => '総勝敗',
        'machine_point' => '投銭ポイント',
        'total_income' => '総収益',
        'total_cash_in' => '総投銭(リチャージ)',
        'total_transfer_in' => '総転入(投入)',
        'transfer_in' => '転入(投入)',
        'transfer_out' => '転出(払出)',
        'admin' => '管理者',
        'settlement_start_time' => '精算開始時間',
        'settlement_end_time' => '精算終了時間',
        'created_at' => '作成日時',
        'start_time' => '開始時間',
    ],

    // ラベル
    'label' => [
        'agent_account_with_value' => '代理アカウント：{value}',
        'agent_settlement_with_value' => '代理分潤精算：{value}',
    ],

    // プレースホルダー
    'placeholder' => [
        'tradeno' => '精算番号',
        'agent_promoter' => '代理/店舗',
    ],

    // ヘルプテキスト
    'help' => [
        'settlement_time' => '精算時間は現在時刻を超えることはできません。前回の精算時間の範囲を選択できません',
        'settlement_range' => '選択可能な精算時間範囲：{start} ～ 現在',
    ],

    // フォームタイトル
    'form' => [
        'last_settlement_time' => '前回の精算時間',
    ],

    // ボタン
    'button' => [
        'settlement' => '精算',
        'batch_settlement' => '一括精算',
    ],

    // 確認メッセージ
    'confirm' => [
        'batch_settlement' => '一括精算は時間によって正確に精算できません。未精算のすべての時間が精算されます。精算しますか？',
    ],

    // タグ
    'tag' => [
        'channel' => 'チャネル',
        'store' => '店舗',
        'agent' => '代理',
    ],

    // 詳細項目
    'detail' => [
        'agent_name' => '代理名',
        'agent_created_at' => '代理作成日時',
        'settled_amount' => '精算済み金額',
        'last_settlement_time' => '前回の精算時間',
        'start_time_label' => '開始時間',
        'end_time_label' => '終了時間',
        'no_time' => 'なし',
    ],
];
