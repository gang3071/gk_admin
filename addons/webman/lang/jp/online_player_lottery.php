<?php

return [
    'title' => 'オンラインプレイヤー宝くじ',

    // タブタイトル
    'tab' => [
        'game_online_players' => 'オンラインゲームプレイヤー',
        'machine_online_players' => 'マシンオンラインプレイヤー',
    ],

    // カードタイトル
    'card' => [
        'game_title' => 'オンラインゲームプレイヤー ({count}人オンライン)',
        'machine_title' => 'マシンオンラインプレイヤー ({count}人オンライン)',
    ],

    // タグテキスト
    'tag' => [
        'realtime_update' => 'リアルタイム更新',
        'last_update' => '最終更新: {time}',
        'playing' => 'プレイ中',
        'seconds_ago' => '{seconds}秒前',
    ],

    // ボタンテキスト
    'button' => [
        'refresh' => '更新',
        'grant_lottery' => '宝くじ付与',
    ],

    // 空の状態の説明
    'empty' => [
        'no_online_players' => 'オンラインプレイヤーがいません（直近1分以内にベット記録なし）',
    ],

    // テーブル列タイトル
    'columns' => [
        'id' => 'ID',
        'player_info' => 'プレイヤー情報',
        'uuid' => 'UUID',
        'current_machine' => '現在のマシン',
        'current_platform' => '現在のプラットフォーム',
        'last_bet_time' => '最終ベット時間',
        'total_pressure' => '累計ベット',
        'total_bet' => '累計ベット',
        'status' => 'ステータス',
        'action' => 'アクション',
    ],

    // その他の表示テキスト
    'display' => [
        'code_prefix' => 'コード: {code}',
    ],

    // モーダルタイトルとフォーム
    'modal' => [
        'grant_lottery' => '宝くじ付与',
        'player_info' => 'プレイヤー情報',
        'select_lottery' => '宝くじ選択',
        'grant_amount' => '付与金額',
        'remark' => '備考',
    ],

    // プレースホルダー
    'placeholder' => [
        'select_lottery' => '宝くじタイプを選択してください',
        'input_amount' => '付与金額を入力してください',
        'input_remark' => '理由または備考を入力してください',
    ],

    // バリデーションメッセージ
    'validation_msg' => [
        'select_lottery' => '宝くじタイプを選択してください',
        'input_valid_amount' => '有効な金額を入力してください',
        'grant_success' => '宝くじ付与成功',
        'grant_failed' => '宝くじ付与失敗',
    ],

    // デフォルト値
    'default' => [
        'not_updated' => '未更新',
    ],

    // 宝くじプール
    'lottery_pool' => 'プール',

    'validation' => [
        'parameter_error' => 'パラメータエラー',
        'player_not_exist' => 'プレイヤーが存在しません',
        'lottery_not_exist' => '宝くじが存在しません',
        'insufficient_lottery_balance' => '宝くじプール残高不足、現在の残高：{balance}',
    ],

    'notice' => [
        'lottery_payout_title' => '宝くじ配当',
        'lottery_payout_content' => 'おめでとうございます！{lottery_name}の宝くじ報酬を獲得しました、金額：{amount}',
    ],

    'log' => [
        'send_socket_message_failed' => '宝くじSocketメッセージの送信に失敗しました：{message}',
        'manual_payout_success' => '手動宝くじ配当成功',
        'manual_payout_failed' => '手動宝くじ配当失敗：{message}',
    ],

    'message' => [
        'payout_success' => '宝くじ配当成功',
        'payout_failed' => '宝くじ配当失敗：{message}',
    ],
];
