<?php

return [
    // 通用错误消息
    'data_not_found' => 'データが見つかりません',
    'player_not_exist' => 'プレイヤーが存在しません',
    'player_already_exists' => 'プレイヤーは既に存在します',
    'recommended_player_not_exist' => '推奨プレイヤーが存在しません',
    'game_not_exist' => 'ゲームが存在しません',
    'please_select_games' => '認証するゲームを選択してください',
    'games_not_found' => '選択されたゲームが見つかりません',
    'offline_channel_only' => 'この機能はオフラインチャネル専用です',
    'offline_channel_feature_only' => 'この機能はオフラインチャネルにのみ適用されます',
    'channel_no_game_platform' => 'このチャネルは電子ゲームプラットフォームを有効にしていません',
    'games_not_in_channel_scope' => '選択されたゲームはチャネルスコープ内にありません',
    'game_not_in_channel_scope' => 'このゲームはチャネルスコープ内にありません',
    'game_platform_not_in_channel_scope' => '選択されたゲームプラットフォームはチャネルスコープ内にありません',
    'invalid_operation' => '無効な操作',
    'operation_failed' => '操作が失敗しました',
    'save_failed' => '保存が失敗しました',
    'player_id_required' => 'プレイヤーIDは必須です',
    'invalid_parameter' => '無効なパラメータ',
    'load_failed' => '読み込みが失敗しました',
    'invalid_game_points' => '変換されたゲームポイントが無効です',
    'system_error' => 'システムエラー',
    'machine_in_test_mode' => 'このマシンは新バージョン工業制御テストマシンとして使用されています',
    'video_host_request_failed' => 'ビデオホストのリクエストが失敗しました',
    'get_stream_info_failed' => 'ストリーム情報の取得が失敗しました',

    // 通用成功消息
    'settlement_success' => '決済成功',
    'operation_success' => '操作成功',
    'authentication_passed' => '認証成功',
    'batch_generation_failed' => 'バッチ生成失敗：{message}',
    'create_agent_failed' => '代理店作成失敗：{message}',
    'create_store_failed' => '店舗作成失敗：{message}',

    // 通用标签
    'administrator' => '管理者',
    'player' => 'プレイヤー',
    'total' => '合計',

    // 通用确认消息
    'confirm_save' => '保存を確認しますか？',

    // 登录相关
    'please_enter_credentials' => 'アカウントとパスワードを入力してください',
    'account_not_exist' => 'アカウントが存在しません',
    'password_incorrect' => 'パスワードが間違っています',
    'login_success' => 'ログイン成功',
    'implement_login_logic' => 'CustomLoginControllerでログインロジックを実装してください',

    // 代理/店家相关
    'agent_commission_range_error' => '代理店手数料は0-100の範囲である必要があります',
    'channel_commission_range_error' => 'チャネル手数料は0-100の範囲である必要があります',
    'please_upload_avatar' => 'アバターをアップロードしてください',
    'password_mismatch' => 'パスワードが一致しません',
    'username_exists' => 'アカウント {username} は既に存在します',
    'agent_create_success' => '代理店 {name} 作成成功！アカウント：{username}',
    'parent_agent_not_exist' => '上位代理店が存在しません',
    'please_select_settlement_targets' => '決済する代理店/店舗を選択してください',
    'settlement_end_time_error' => '決済終了時刻は現在時刻を超えることはできません',
    'store_ratio_less_than_agent' => '店舗比率は代理店 ({name}) 比率 {ratio}% より小さくできません',
    'agent_ratio_greater_than_store' => '代理店比率は店舗 ({name}) 比率 {ratio}% を超えることはできません',

    // 游戏权限相关
    'game_permission_set_success' => '{count} 個のゲーム権限を設定しました',
    'electronic_game_set_success' => '{count} 個の電子ゲームを設定しました',

    // 交班相关
    'shift_handover_failed_no_department' => 'シフト失敗：管理者が部門にリンクされていません',
    'shift_handover_failed_no_currency' => 'シフト失敗：通貨設定がありません',

    // 彩池相关
    'pool_ratio_must_greater_than_zero' => 'プール比率は0より大きくする必要があります',
    'pool_ratio_cannot_exceed_100' => 'プール比率は100%を超えることはできません',
    'win_probability_must_greater_than_zero' => '当選確率は0より大きくする必要があります',
    'win_probability_cannot_exceed_1' => '当選確率は1 (100%) を超えることはできません',
    'max_pool_amount_must_greater_than_zero' => '最大プール金額は0より大きくする必要があります',
    'minimum_amount_must_greater_than_zero' => '有効にする場合、最小金額は0より大きくする必要があります',
    'minimum_amount_cannot_exceed_max' => '最小金額は最大プール金額を超えることはできません',
    'distribution_ratio_range_error' => '配布比率は0-100の範囲である必要があります',

    // 机台相关
    'please_select_reset_hosts' => 'リセットするビデオホストを選択してください',
    'please_fill_zhcn_name' => '簡体字中国語名を入力してください',
    'please_upload_zhcn_image' => '簡体字中国語画像をアップロードしてください',

    // 角色相关
    'builtin_role_cannot_modify_name' => '組み込みロールは名前を変更できません',
    'builtin_role_cannot_modify_type' => '組み込みロールはタイプを変更できません',
    'role_not_exist' => 'ロールが存在しません',
    'builtin_role_cannot_delete' => '組み込みロールは削除できません',

    // 批量生成相关
    'batch_generate_success' => '{count} 個のプレイヤーアカウントを生成しました',
    'batch_generate_partial_success' => '{success} 個生成、{failed} 個失敗：{accounts}',
    'account_exists' => '既に存在します',

    // 帮助文本
    'help' => [
        'account_format' => 'アカウント形式：接頭辞+番号、例：P0001',
        'number_auto_padding' => '番号は4桁に自動補完されます、例：1 → 0001',
        'nickname_format' => 'ニックネーム形式：接頭辞+番号、例：プレイヤー0001',
        'number_auto_padding_simple' => '番号は4桁に自動補完されます',
        'all_players_use_this_avatar' => '生成されたすべてのプレイヤーはこのアバターを使用します',
        'avatar_format_recommendation' => 'jpg、png対応、推奨サイズ200x200、すべてのプレイヤーがこのアバターを使用',
        'all_accounts_use_this_password' => '生成されたすべてのアカウントはこのパスワードを使用します',
        'avatar_format' => 'jpg、png対応、推奨サイズ200x200',
        'agent_login_password' => '代理店バックエンドパスワード、最低6文字',
        'store_login_password' => '店舗バックエンドパスワード、最低6文字',
        'agent_commission_help' => '代理店が店舗収益から抽出する割合、範囲 0-100',
        'channel_commission_help' => 'チャネルが店舗収益から抽出する割合、範囲 0-100',
    ],

    // 提示文本
    'tips' => [
        'offline_channel_only_notice' => '><font size=3 color="#ff4d4f">この機能はオフラインチャネル専用です</font>',
        'batch_generate_bind_notice' => '><font size=2 color="#1890ff">一括生成されたアカウントは指定された店舗に自動的にバインドされます</font>',
    ],

    // 其他
    'divider' => [
        'commission_settings' => '手数料設定',
    ],

    // 默认文本
    'default' => [
        'admin' => '管理者',
        'no_agent' => '代理店なし',
        'not_filled' => '未入力',
        'welcome_agent_system' => '代理店バックエンドシステムへようこそ！',
        'welcome_store_system' => '店舗バックエンドシステムへようこそ！',
    ],

    // 日期筛选
    'date_filter' => [
        'all' => 'すべて',
        'today' => '今日',
        'yesterday' => '昨日',
        'this_week' => '今週',
        'last_week' => '先週',
        'this_month' => '今月',
        'last_month' => '先月',
    ],

    // 自动交班
    'auto_shift' => [
        'enabled' => '自動シフトが有効です',
        'manual_shift_success' => '店舗手動シフト成功',
        'manual_shift_failed' => '手動シフト失敗',
    ],

    // 班次
    'shift' => [
        'morning' => '朝シフト',
        'morning_desc' => '朝シフト自動（08:00-16:00）',
        'afternoon' => '昼シフト',
        'afternoon_desc' => '昼シフト自動（16:00-24:00）',
        'night' => '夜シフト',
        'night_desc' => '夜シフト自動（00:00-08:00）',
    ],

    // 通用UI
    'start_time' => '開始時間',
    'end_time' => '終了時間',
    'no_permission' => '権限がありません',
    'refresh' => '更新',
    'save' => '保存',
    'cancel' => 'キャンセル',
    'loading' => '読み込み中...',
    'submit' => '送信',
    'confirm' => '確認',
    'delete' => '削除',
    'edit' => '編集',
    'view' => '表示',
    'create' => '作成',
    'update' => '更新',
    'search' => '検索',
    'reset' => 'リセット',
    'export' => 'エクスポート',
    'import' => 'インポート',
    'close' => '閉じる',
    'back' => '戻る',

    // 通用错误（分组）
    'error' => [
        'busy_retry' => '操作がビジーです、後でもう一度お試しください',
        'operation_failed' => '操作が失敗しました',
    ],
];
