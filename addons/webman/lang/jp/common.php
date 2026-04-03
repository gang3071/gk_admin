<?php

return [
    // 共通エラーメッセージ
    'data_not_found' => 'データが見つかりません',
    'player_not_exist' => 'プレーヤーが存在しません',
    'game_not_exist' => 'ゲームが存在しません',
    'please_select_games' => '承認するゲームを選択してください',
    'games_not_found' => '選択したゲームが見つかりません',
    'offline_channel_only' => 'この機能はオフラインチャネル専用です',
    'offline_channel_feature_only' => 'この機能はオフラインチャネルにのみ適用されます',
    'channel_no_game_platform' => 'このチャネルは電子ゲームプラットフォームを有効にしていません',
    'games_not_in_channel_scope' => '選択したゲームはチャネルの範囲内にありません',
    'game_not_in_channel_scope' => 'このゲームはチャネルの範囲内にありません',
    'game_platform_not_in_channel_scope' => '選択したゲームプラットフォームはチャネルの範囲内にありません',
    'invalid_operation' => '無効な操作',
    'operation_failed' => '操作に失敗しました',
    'save_failed' => '保存に失敗しました',
    'player_id_required' => 'プレーヤーIDは必須です',
    'invalid_parameter' => 'パラメータが無効です',
    'load_failed' => '読み込みに失敗しました',
    'invalid_game_points' => '変換後のゲームポイントが無効です',
    'system_error' => 'システムエラー',
    'player_already_exists' => 'プレーヤーは既に存在します',
    'recommended_player_not_exist' => '推薦プレーヤーが存在しません',
    'machine_in_test_mode' => 'このマシンは新バージョンの産業用制御テストマシンとして使用されています',
    'video_host_request_failed' => 'ビデオホストのリクエストに失敗しました',
    'get_stream_info_failed' => 'ストリーム情報の取得に失敗しました',

    // 共通成功メッセージ
    'settlement_success' => '決済が成功しました',
    'operation_success' => '操作が成功しました',
    'authentication_passed' => '認証が成功しました',
    'batch_generation_failed' => '一括生成に失敗しました：{message}',
    'create_agent_failed' => 'エージェントの作成に失敗しました：{message}',
    'create_store_failed' => 'ストアの作成に失敗しました：{message}',

    // 共通ラベル
    'administrator' => '管理者',
    'player' => 'プレイヤー',
    'total' => '合計',

    // 共通確認メッセージ
    'confirm_save' => '保存してもよろしいですか？',

    // ログイン関連
    'please_enter_credentials' => 'アカウントとパスワードを入力してください',
    'account_not_exist' => 'アカウントが存在しません',
    'password_incorrect' => 'パスワードが正しくありません',
    'login_success' => 'ログインに成功しました',
    'implement_login_logic' => 'CustomLoginControllerに実際のログインロジックを実装してください',

    // エージェント/ストア関連
    'agent_commission_range_error' => 'エージェント手数料率は0〜100の間でなければなりません',
    'channel_commission_range_error' => 'チャネル手数料率は0〜100の間でなければなりません',
    'please_upload_avatar' => 'アバターをアップロードしてください',
    'password_mismatch' => 'パスワードが一致しません',
    'username_exists' => 'ログインアカウント {username} は既に存在します',
    'agent_create_success' => 'エージェント {name} が正常に作成されました！ログインアカウント：{username}',
    'parent_agent_not_exist' => '上位エージェントが存在しません',
    'please_select_settlement_targets' => '決済するエージェント/ストアを選択してください',
    'settlement_end_time_error' => '決済終了時間は現在時刻を超えることはできません',
    'store_ratio_less_than_agent' => 'ストア上納比率はエージェント ({name}) の上納比率 {ratio}% 未満に設定できません',
    'agent_ratio_greater_than_store' => 'エージェント上納比率はストア ({name}) の上納比率 {ratio}% を超えることはできません',

    // ゲーム権限関連
    'game_permission_set_success' => '{count} 個のゲーム権限を正常に設定しました',
    'electronic_game_set_success' => '{count} 個の電子ゲームを正常に設定しました',

    // シフト引継ぎ関連
    'shift_handover_failed_no_department' => 'シフト引継ぎ失敗：管理者が部門に関連付けられていません',
    'shift_handover_failed_no_currency' => 'シフト引継ぎ失敗：システム通貨設定がありません',

    // 宝くじプール関連
    'pool_ratio_must_greater_than_zero' => 'プール率は0より大きくなければなりません',
    'pool_ratio_cannot_exceed_100' => 'プール率は100%を超えることはできません',
    'win_probability_must_greater_than_zero' => '当選確率は0より大きくなければなりません',
    'win_probability_cannot_exceed_1' => '当選確率は1（100%）を超えることはできません',
    'max_pool_amount_must_greater_than_zero' => '最大プール金額は0より大きくなければなりません',
    'minimum_amount_must_greater_than_zero' => '最低金額を有効にした場合、最低金額は0より大きくなければなりません',
    'minimum_amount_cannot_exceed_max' => '最低金額は最大プール金額を超えることはできません',
    'distribution_ratio_range_error' => '配分比率は0〜100の間でなければなりません',

    // マシン関連
    'please_select_reset_hosts' => 'リセットするビデオホストを選択してください',
    'please_fill_zhcn_name' => '簡体字中国語名を入力してください',
    'please_upload_zhcn_image' => '簡体字中国語画像をアップロードしてください',

    // ロール関連
    'builtin_role_cannot_modify_name' => '組み込みロール名は変更できません',
    'builtin_role_cannot_modify_type' => '組み込みロールタイプは変更できません',
    'role_not_exist' => 'ロールが存在しません',
    'builtin_role_cannot_delete' => 'これは組み込みロールで、削除できません',

    // 一括生成関連
    'batch_generate_success' => '{count} 個のプレーヤーアカウントを正常に生成しました',
    'batch_generate_partial_success' => '{success} 個のプレーヤーアカウントを正常に生成しました、失敗 {failed} 個：{accounts}',
    'account_exists' => '既に存在します',

    // ヘルプテキスト
    'help' => [
        'account_format' => 'アカウント形式：プレフィックス+番号、例：P0001',
        'number_auto_padding' => '番号は自動的に4桁にパディングされます、例：1 → 0001',
        'nickname_format' => 'ニックネーム形式：プレフィックス+番号、例：プレーヤー0001',
        'number_auto_padding_simple' => '番号は自動的に4桁にパディングされます',
        'all_players_use_this_avatar' => '生成されたすべてのプレーヤーはこのアバターを使用します',
        'avatar_format_recommendation' => 'jpg、png形式をサポート、推奨サイズ200x200、生成されたすべてのプレーヤーはこのアバターを使用します',
        'all_accounts_use_this_password' => '生成されたすべてのアカウントはこのパスワードを使用します',
        'avatar_format' => 'jpg、png形式をサポート、推奨サイズ200x200',
        'agent_login_password' => 'エージェントバックエンドログインパスワード、最低6文字',
        'store_login_password' => 'ストアバックエンドログインパスワード、最低6文字',
        'agent_commission_help' => 'エージェントがストア収益から抽出する比率、範囲0〜100',
        'channel_commission_help' => 'チャネルがストア収益から抽出する比率、範囲0〜100',
    ],

    // ヒント
    'tips' => [
        'offline_channel_only_notice' => '><font size=3 color="#ff4d4f">この機能はオフラインチャネル専用です</font>',
        'batch_generate_bind_notice' => '><font size=2 color="#1890ff">一括生成されたアカウントは指定されたストアに自動的にバインドされます</font>',
    ],

    // その他
    'divider' => [
        'commission_settings' => '手数料設定',
    ],

    // デフォルトテキスト
    'default' => [
        'admin' => '管理者',
        'no_agent' => 'エージェントなし',
        'not_filled' => '未入力',
        'welcome_agent_system' => 'エージェントバックエンドシステムへようこそ！',
        'welcome_store_system' => 'ストアバックエンドシステムへようこそ！',
    ],

    // 日付フィルター
    'date_filter' => [
        'all' => '全部',
        'today' => '今日',
        'yesterday' => '昨日',
        'this_week' => '今週',
        'last_week' => '先週',
        'this_month' => '今月',
        'last_month' => '先月',
    ],

    // 自動シフト
    'auto_shift' => [
        'enabled' => '自動シフトが有効になっています',
        'manual_shift_success' => '手動シフト引継ぎ成功',
        'manual_shift_failed' => '手動シフト引継ぎ失敗',
    ],

    // シフト
    'shift' => [
        'morning' => '早番',
        'morning_desc' => '早番自動引継ぎ（08:00-16:00）',
        'afternoon' => '中番',
        'afternoon_desc' => '中番自動引継ぎ（16:00-24:00）',
        'night' => '夜番',
        'night_desc' => '夜番自動引継ぎ（00:00-08:00）',
    ],
];
