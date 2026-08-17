<?php

return [
    'title' => '店舗管理',
    'offline_only' => 'この機能はオフラインチャネルのみで利用可能です',
    'create_success' => '店舗 {name} の作成に成功しました！ログインアカウント：{username}、{agent_label}：{agent_name}',
    'create_failed' => '店舗の作成に失敗しました：{error}',
    'welcome_message' => '店舗管理システムへようこそ！',

    // カラム名
    'fields' => [
        'id' => 'ID',
        'name' => '店舗名',
        'username' => 'ログインアカウント',
        'phone' => '連絡先電話',
        'department_name' => '部門名',
        'agent_commission' => '代理手数料',
        'channel_commission' => 'チャネル手数料',
        'wash_point_config' => '洗分設定',
        'status' => 'ステータス',
        'created_at' => '作成日時',
        'parent_agent' => '上位代理',
        'password' => 'ログインパスワード',
        'password_confirmation' => 'パスワード確認',
        'avatar' => 'アバターアップロード',
    ],

    // ステータス
    'status' => [
        'normal' => '正常',
        'disabled' => '無効',
        'not_set' => '未設定',
    ],

    // フォーム
    'form' => [
        'create_title' => '店舗作成',
        'create_hint' => '店舗を作成後、店舗管理画面にログインできます',
        'section_account' => 'アカウント情報',
        'section_parent_agent' => '上位代理',
        'section_avatar' => 'アバター設定',
        'section_password' => 'パスワード設定',
        'select_parent_agent' => '上位代理を選択',
    ],

    // プレースホルダー
    'placeholder' => [
        'status' => 'ステータス',
        'username' => 'ログインアカウント',
        'name' => '店舗名',
        'phone' => '連絡先電話',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
    ],

    // フィルター
    'filter' => [
        'select_store' => '店舗を選択',
    ],

    // その他
    'all' => '全て',

    // ヘルプテキスト
    'help' => [
        'phone' => '任意、連絡用',
        'username' => '必須、店舗管理画面へのログイン用',
        'name' => '店舗の表示名',
        'parent_agent' => 'この店舗の上位代理を選択',
        'avatar' => 'jpg、png形式対応、推奨サイズ200x200',
        'password' => '店舗管理画面ログインパスワード',
    ],

    // 検証ルール
    'validation' => [
        'password_min' => 'パスワードは6文字以上',
    ],

    // エラーメッセージ
    'error' => [
        'offline_only' => 'この機能はオフラインチャネルのみで利用可能です',
        'avatar_required' => 'アバターをアップロードしてください',
        'password_mismatch' => 'パスワードが一致しません',
        'parent_agent_not_found' => '上位代理が見つかりません',
        'username_exists' => 'ログインアカウント {username} は既に存在します',
    ],

    // 自動交代設定
    'auto_shift' => [
        'morning_title' => '早番',
        'afternoon_title' => '昼番',
        'night_title' => '夜勤',
        'morning_desc' => '早番自動交代（08:00-16:00）',
        'afternoon_desc' => '昼番自動交代（16:00-24:00）',
        'night_desc' => '夜勤自動交代（00:00-08:00）',
    ],

    // 操作メニュー
    'actions' => [
        'limit_group' => 'リミットグループ設定',
        'auto_shift_config' => '自動交代設定',
        'system_setting' => 'システム設定',
        'open_score_setting' => 'オープンスコア設定',
        'wash_point_setting' => 'ウォッシュポイント設定',
        'activity_config' => 'アクティビティ設定',
    ],

    // アクティビティ設定
    'activity_config' => [
        'title' => 'アクティビティ設定',
        'list_title' => 'アクティビティ設定一覧',
        'create_title' => 'アクティビティ設定作成',
        'edit_title' => 'アクティビティ設定編集',
        'no_config' => 'アクティビティ設定なし',

        // フィールド
        'fields' => [
            'id' => 'ID',
            'start_time' => '開始時間',
            'end_time' => '終了時間',
            'status' => 'ステータス',
            'created_at' => '作成日時',
            'updated_at' => '更新日時',
        ],

        // ステータス
        'status' => [
            '0' => '無効',
            '1' => '有効',
        ],

        // セクションタイトル
        'section' => [
            'basic' => '基本情報',
            'experience' => '体験バウチャー設定',
            'welfare' => '福利バウチャー設定',
            'order_prefix' => '注文プレフィックス設定',
        ],

        // フィールドラベル
        'label' => [
            'start_time' => '開始時間',
            'end_time' => '終了時間',
            'activity_end_time' => '配布締切',
            'experience_enabled' => '体験バウチャー有効',
            'experience_register_after' => '新規ユーザー閾値',
            'experience_daily_limit' => '1日回数',
            'experience_total_limit' => '合計回数',
            'experience_score' => '受取スコア',
            'experience_expire_hours' => '有効期間(時)',
            'welfare_enabled' => '福利バウチャー有効',
            'welfare_daily_limit' => '1日回数',
            'welfare_rules' => '福利バウチャーティアルール',
            'welfare_expire_hours' => '有効期間(時)',
            'order_prefix_experience' => '体験プレフィックス',
            'order_prefix_welfare' => '福利プレフィックス',
            'order_prefix_recharge' => 'リチャージプレフィックス',
            'order_prefix_withdraw' => 'ウィズドロウプレフィックス',
        ],

        // ヘルプテキスト
        'help' => [
            'start_time' => 'アクティビティ開始時間、空の場合は即時開始',
            'end_time' => 'アクティビティ終了時間、空の場合は制限なし',
            'activity_end_time' => 'この時間を過ぎるとバウチャー配布が一時停止',
            'experience_register_after' => 'この時間以降に登録したユーザーを新規とみなす',
            'experience_daily_limit' => 'ユーザーが1日に受取可能な回数',
            'experience_total_limit' => 'ユーザーが合計で受取可能な回数',
            'experience_score' => '1回の受取で獲得できるスコア',
            'experience_expire_hours' => 'この時間を過ぎるとバウチャーが無効',
            'welfare_daily_limit' => '0は制限なしを意味する',
            'welfare_rules' => '福利バウチャーのベット額しきい値を設定、しきい値に達すると受取可能',
            'welfare_expire_hours' => 'この時間を過ぎるとバウチャーが無効',
            'order_prefix_experience' => '体験バウチャー注文番号プレフィックス',
            'order_prefix_welfare' => '福利バウチャー注文番号プレフィックス',
            'order_prefix_recharge' => 'リチャージ注文番号プレフィックス',
            'order_prefix_withdraw' => 'ウィズドロウ注文番号プレフィックス',
        ],

        // ルール関連
        'rules' => [
            'bet_amount' => 'ベット額しきい値',
            'score' => '受取スコア',
            'add_rule' => 'ティア追加',
            'remove_rule' => '削除',
            'day_type' => '計算タイプ',
            'yesterday' => '昨日のベット額',
            'today' => '今日のベット額',
        ],

        // 検証
        'validation' => [
            'start_time_required' => 'アクティビティ開始時間を入力してください',
            'end_time_after_start' => '終了時間は開始時間より後である必要があります',
        ],

        // エラーメッセージ
        'error' => [
            'already_exists' => 'この店舗には既にアクティビティ設定があります。直接編集してください',
        ],

        // メッセージ
        'message' => [
            'create_success' => 'アクティビティ設定が正常に作成されました',
            'update_success' => 'アクティビティ設定が正常に更新されました',
            'delete_success' => 'アクティビティ設定が正常に削除されました',
            'delete_confirm' => 'このアクティビティ設定を削除してもよろしいですか？',
        ],
    ],
];
