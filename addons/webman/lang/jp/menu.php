<?php

use addons\webman\model\AdminDepartment;

return [
    'add' => 'メニューを追加',
    'title' => 'システムメニュー管理',
    'fields' => [
        'top' => 'トップメニュー',
        'pid' => '前のメニュー',
        'name' => 'メニュー名',
        'url' => 'メニューリンク',
        'icon' => 'メニューアイコン',
        'sort' => '並べ替え',
        'status' => 'ステータス',
        'open' => 'メニュー展開',
        'super_status' => 'スーパー管理者のステータス',
        'type' => 'メニュータイプ',
    ],
    'options' => [
        'admin_visible' => [
            [1 => '表示'],
            [0 => '非表示']
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => 'ターミナルメニュー',
        AdminDepartment::TYPE_CHANNEL => 'チャンネルメニュー',
        AdminDepartment::TYPE_AGENT => 'エージェントメニュー',
        AdminDepartment::TYPE_STORE => 'ストアメニュー',
    ],
    'titles' => [
        'home' => 'ホーム',
        'system' => 'システム',
        'system_manage' => 'システム管理',
        'config_manage' => '構成管理',
        'attachment_manage' => '添付ファイル管理',
        'permissions_manage' => '権限管理',
        'admin' => 'ユーザー管理',
        'role_manage' => 'ロール管理',
        'menu_manage' => 'メニュー管理',
        'plug_manage' => 'プラグイン管理',
        'Department_manage' => '部門管理',
        'post_manage' => '投稿管理',
        /** 一般的なバックエンド */
        'admin_manage' => '一般的なバックエンド',
        'data_center' => 'データセンター',
        //ユーザー管理
        'user_manage' => 'プレイヤー管理',
        'user_manage_list' => 'プレイヤーリスト',
        'accounting_change_records' => '会計変更レコード',
        //ゲーム管理
        'game_manager' => 'ゲーム管理',
        'game_category' => 'ゲームカテゴリ',
        'machine_category' => 'マシンカテゴリ',
        'machine_list' => 'マシンリスト',
        'machine_information' => 'マシン情報',
        //財務データ
        'financial_data' => '財務データ',
        'transfer_point_record' => '転送ポイントレコード',
        'recharge_record' => 'リチャージレコード',
        'withdrawal_records' => '出金記録',
        //レポートセンター
        'report_center' => 'レポート センター',
        'machine_report' => 'マシンレポート',
        'up_and_down_report' => 'アップアンドダウンレポート',
        //クライアント管理
        'client_manager' => 'クライアント管理',
        'rotation_chart_manager' => 'ローテーションチャート管理',
        'payment_manager' => 'アナウンス管理',
        'system_settings' => 'システム設定',
        //渠道管理
        'channel_manager' => 'チャンネル管理',
        'channel_list' => 'チャンネルリスト',
        'currency_manager' => '通貨管理',
        /** チャネル バックエンド */
        'channel_manage' => 'チャネル バックエンド',
        'channel_data_center' => 'データセンター',
        //コインディーラー管理
        'channel_coin_merchant_manage' => 'コイン販売者管理',
        'channel_coin_merchant_list' => 'コイン販売者リスト',
        'channel_coin_merchant_recharge_records' => 'コイン販売者のリチャージ記録',
        'channel_coin_merchant_transaction_records' => 'コイン販売者の取引記録',
        //プレイヤー管理
        'channel_player_manage' => 'プレイヤー管理',
        'channel_player_list' => 'プレイヤーリスト',
        'channel_player_accounting_change_records' => '会計変更レコード',
        //マシン管理
        'channel_machine_manage' => 'マシン管理',
        'channel_machine_information' => 'マシン情報',
        'channel_machine_report' => 'マシンレポート',
        'channel_up_and_down_report' => 'アップアンドダウンレポート',
        'machine_strategy_list' => 'マシン戦略',
        'machine_Producer' => 'メーカーリスト',
        // フロントエンドの設定
        'channel_client_manager' => 'クライアント管理',
        'channel_rotation_chart_manager' => 'カルーセル チャート管理',
        'channel_marquee_manager' => 'システム構成',
        'channel_payment_manager' => 'アナウンス管理',
        //財務管理
        'channel_financial_manager' => '財務管理',
        'channel_recharge_review' => 'リチャージレビュー',
        'channel_withdrawal_review' => '出金レビュー',
        'channel_withdrawal_and_payment' => '引き出しと支払い',
        'channel_recharge_record' => 'リチャージレコード',
        'channel_talk_recharge_records' => 'QTalk リチャージレコード',
        'channel_withdrawal_records' => '出金記録',
        'channel_talk_withdrawal_records' => 'QTalk 出金記録',
        'channel_recharge_channel_configuration' => 'リチャージチャネル構成',
        'channel_financial_operation_records' => '金融業務記録',
        //権限管理
        'channel_auth_manager' => '権限管理',
        'channel_admin_user_manager' => 'ユーザー管理',
        'channel_post_manager' => '投稿管理',
        //アクティビティ管理
        'activity' => 'アクティビティ管理',
        'activity_index' => 'アクティビティリスト',
        'player_activity_record' => 'アクティビティ参加記録',
        'player_activity_record_examine' => '報酬コレクションのレビュー',
        'player_activity_record_receive' => 'アクティビティ受信記録',
        //プロモーション管理
        'promotion_management' => 'プロモーション管理',
        'promoter_list' => 'プロモーターリスト',
        'profit_record' => '利益レポート',
        'profit_settlement_record' => '利益決済レコード',
        
        //ボーナス管理
        'lottery_management' => '宝くじ管理',
        'lottery_list' => '宝くじリスト',
        'lottery_audit_list' => '宝くじ監査',
        'lottery_records' => '宝くじ回収記録',
        //コインディーラー管理
        'coin_management' => 'コインビジネス管理',
        'coin_list' => 'コインディーラーリスト',
        'coin_recharge_record' => 'コインディーラーのリチャージ記録',
        //ログセンター
        'log_center' => 'ログセンター',
        'machine_keeper_log' => 'マシン維持ログ',
        'player_edit_log' => 'プレイヤープロフィール変更ログ',
        'machine_operation_log' => 'マシン操作ログ',
        'machine_edit_log' => 'マシン変更ログ',
        'lottery_add_log' => '宝くじプール蓄積ログ',
        'player_money_edit_log' => 'ウォレット操作ログ',
        /** TODO 翻訳終了 */
        'computer_game' => '電子ゲーム',
        'game_platforms' => 'ゲームプラットフォームリスト',
        'game_transfer_record' => '振替記録',
        'play_game_record' => 'プレイ記録',
        'game_platform_profit' => 'ゲームプラットフォームの分潤',
        //店舗設定
        'store_setting_manage' => '店舗システム設定',
        //代理店管理
        'agent_management' => '代理店管理',
        //店舗管理
        'store_management' => '店舗管理',
        //代理彩金管理
        'agent_lottery_management' => '彩金管理',
        //店舗彩金管理
        'store_lottery_management' => '彩金管理',
        //入金ボーナス管理（管理後台）
        'deposit_bonus_manage' => '入金ボーナス管理',
        'deposit_bonus_activity' => '活動管理',
        'deposit_bonus_qrcode' => '注文管理',
        'deposit_bonus_statistics' => '統計レポート',
        'deposit_bonus_bet_detail' => 'ベット詳細',
        //チャネル後台入金ボーナス管理
        'channel_deposit_bonus_manage' => '入金ボーナス管理',
        'channel_deposit_bonus_activity' => '活動管理',
        'channel_deposit_bonus_order' => '注文管理',
        'channel_deposit_bonus_statistics' => '統計レポート',
        'channel_deposit_bonus_bet_detail' => 'ベット詳細',
        //代理店/店舗ゲームログ
        'agent_game_log' => 'ゲームログレポート',
        'agent_game_log_list' => 'レポートリスト',
        'store_game_log' => 'ゲームログレポート',
        'store_game_log_list' => 'レポートリスト',
        //代理店財務管理
        'agent_financial_management' => '財務管理',
        'agent_recharge_record' => 'リチャージ記録',
        'agent_withdraw_record' => '出金記録',
        //代理店ゲーム管理
        'agent_game_management' => 'ゲーム管理',
        'agent_game_record' => 'ゲーム記録',
        //代理店入金ボーナス管理
        'agent_deposit_bonus_manage' => '入金ボーナス管理',
        'agent_deposit_bonus_activity' => '活動管理',
        'agent_deposit_bonus_order' => '注文管理',
        'agent_deposit_bonus_statistics' => '統計レポート',
        'agent_deposit_bonus_bet_detail' => 'ベット詳細',
        'agent_deposit_bonus_task' => 'ベット量タスク',
        //店舗デバイス管理
        'store_player' => 'デバイス管理',
        'store_player_list' => 'デバイスリスト',
        //店舗入金ボーナス管理
        'store_deposit_bonus_manage' => '入金ボーナス管理',
        'store_deposit_bonus_activity' => '活動管理',
        'store_deposit_bonus_order' => '注文管理',
        'store_deposit_bonus_statistics' => '統計レポート',
        'store_deposit_bonus_bet_detail' => 'ベット詳細',
        'store_deposit_bonus_task' => 'ベット量タスク',
        //店舗財務管理
        'store_financial_manage' => '入出金センター',
        'store_recharge_record' => '入金記録',
        'store_withdraw_record' => '出金記録',
        //店舗ゲーム管理
        'store_game_manage' => 'ゲーム管理',
        'store_game_record' => 'ゲーム記録',
        'store_deposit_bonus_qrcode' => '注文管理',
    ]
];
