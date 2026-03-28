<?php

return [
    // タイトル
    'title' => '店舗/デバイス管理',
    'store_list' => '店舗リスト',
    'store_management' => '店舗管理',
    'device_list' => 'デバイスリスト',
    'delivery_record' => '取引記録',
    'shift_handover_record' => '引継ぎ報告',

    // フィールド
    'fields' => [
        'store_name' => '店舗名',
        'login_account' => 'ログインアカウント',
        'contact_phone' => '連絡先電話',
        'department_name' => '部門名',
        'agent_commission' => '代理手数料',
        'channel_commission' => 'チャネル手数料',
        'status' => 'ステータス',
        'created_at' => '作成日時',
        'recharge_amount' => '累計チャージ',
        'withdraw_amount' => '累計出金',
        'machine_put_point' => '投入',
        'lottery_amount' => '宝くじ',
        'subtotal' => '小計',
        'game_platform' => 'ゲームプラットフォーム',
        'game_name' => 'ゲーム名',
        'game_category' => 'ゲームカテゴリー',
        'is_hot' => '人気',
        'is_new' => '新しいゲーム',
        'sort' => 'ソート',
    ],

    // 古いフィールドキー（互換性のため保持）
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
    'error_player_not_found' => 'プレイヤーが見つかりません',
    'error_offline_channel_only' => 'この機能はオフラインチャネルのみで利用可能です',
    'error_no_game_platform' => 'このチャネルではゲームプラットフォームが有効になっていません',

    // ステータスオプション
    'status_options' => [
        'normal' => '正常',
        'disabled' => '無効',
    ],

    // タグ
    'tag' => [
        'not_set' => '未設定',
        'disabled' => '無効',
        'normal' => '正常',
        'hot' => '人気',
        'new' => '新',
        'unknown_platform' => '不明なプラットフォーム',
    ],

    // プレースホルダー
    'placeholder' => [
        'status' => 'ステータス',
        'login_account' => 'ログインアカウント',
        'store_name' => '店舗名',
        'contact_phone' => '連絡先電話',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
        'game_platform' => 'ゲームプラットフォーム',
        'is_hot' => '人気かどうか',
        'is_new' => '新しいゲームかどうか',
    ],

    // フィルター
    'filter' => [
        'select_store' => '店舗を選択',
        'select_agent' => 'エージェントを選択',
    ],

    // ゲームオプション
    'game_options' => [
        'hot_games' => '人気ゲーム',
        'normal_games' => '通常ゲーム',
        'new_games' => '新しいゲーム',
        'old_games' => '古いゲーム',
    ],

    // ボタン
    'button' => [
        'save_selected_games' => '選択したゲームを保存',
    ],

    // 確認
    'confirm' => [
        'save_games' => '保存しますか？',
    ],

    // 成功メッセージ
    'success_open_score' => 'チャージ成功',
    'success_save' => '保存成功',

    // エラーメッセージ - 追加
    'error_save_failed' => '保存失敗',
    'error_operation_failed' => '操作失敗',

    // チャージ備考
    'remark_store_open_score' => '店舗バックエンドチャージ',

    // JavaScript テキスト
    'js' => [
        'conversion_preview' => '変換プレビュー：',
        'please_enter_amount' => '金額を入力してください',
        'points_unit' => 'ポイント',
        'exchange_rate_label' => '為替レート：',
    ],

    // ゲーム関連
    'game' => [
        'game_id' => 'ゲーム ID',
        'select_all_platform' => '【このプラットフォームのすべてのゲームを選択】',
        'game_list_title' => '{platform} - ゲームリスト',
        'tip_select_games' => 'ヒント: このプレイヤーが使用できる電子ゲームを選択してください。選択されていないゲームはクライアントに表示されません。',
    ],

    // ゲーム権限管理
    'game_permission' => [
        'title' => 'プレイヤーゲーム権限管理 - {name}',
    ],

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