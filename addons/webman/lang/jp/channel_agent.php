<?php

return [
    // タイトル
    'title' => '店舗/デバイス管理',
    'store_list' => '店舗リスト',
    'device_list' => 'デバイスリスト',
    'delivery_record' => '取引記録',
    'shift_handover_record' => '引継ぎ報告',

    // フィールド
    'store_name' => '店舗名',
    'bind_player' => '紐付けプレイヤー',
    'account' => 'アカウント',
    'recommend_id' => '所属店舗',
    'select_store' => '所属店舗',
    'last_settlement_time' => '最終決済時間',

    // 当期データ
    'current_data' => '当期データ',
    'present_in' => '入金(チャージ)',
    'present_out' => '出金(キャッシュアウト)',
    'machine_put' => '投入(リチャージ)',
    'total_revenue' => '合計(売上)',
    'store_profit_rate' => '店舗利益率',
    'store_profit_amount' => '店舗利益',
    'company_profit_rate' => '会社利益率',
    'company_profit_amount' => '会社利益',

    // 累計データ
    'total_data' => '累計データ',

    // チャージ関連
    'open_score' => 'チャージ',
    'recharge_amount' => 'リチャージ金額',
    'quick_amount' => 'クイック金額',
    'custom_amount' => 'カスタム金額',
    'preset_amount' => 'プリセット金額',
    'default_amount' => 'デフォルト金額',
    'device_balance' => 'デバイス残高',
    'exchange_rate' => '為替レート',
    'game_points' => 'ゲームポイント',
    'reference' => '参考',
    'points' => 'ポイント',

    // ヒント
    'tip_device_balance' => 'ヒント: デバイス残高 {balance} {currency} = {points} ゲームポイント',
    'tip_exchange_rate' => '1 {currency} = {ratio} ゲームポイント、通貨金額を入力してください、システムが自動的にゲームポイントに変換します',
    'tip_select_preset' => 'プリセット金額またはカスタム金額を選択 (通貨 → ゲームポイント、1{currency} = {ratio}ポイント)',
    'tip_reference' => 'デバイス残高: {currency}{balance}、為替レート: 1{currency} = {ratio}ゲームポイント。{reference}',

    // エラーメッセージ
    'error_amount_required' => '金額を入力してください',
    'error_amount_invalid' => '有効な金額を入力してください',
    'error_store_not_found' => '店舗アカウントが存在しません',
    'error_currency_not_found' => '通貨情報が見つかりません',
    'error_ratio_invalid' => '為替レートは0より大きい必要があります',
    'error_device_not_found' => 'デバイスが存在しません',
    'error_insufficient_balance' => 'デバイス残高不足',

    // 成功メッセージ
    'success_open_score' => 'チャージ成功',

    // その他
    'remark' => '備考',
    'select_placeholder' => '選択してください',
    'total' => '合計',
    'player_type_store' => '店舗',
    'player_type_device' => 'デバイス',
    'player' => 'プレイヤー',
    'admin' => '管理者',
    'type' => 'タイプ',
    'all' => '全て',
    'total_profit_amount' => '総売上',
    'start_time' => '開始時間',
    'end_time' => '終了時間',
    'created_at' => '作成日時',
];