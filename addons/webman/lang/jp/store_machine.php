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
];
