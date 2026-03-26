<?php

return [
    'title' => '代理管理',
    'offline_only' => 'この機能はオフラインチャネルのみで利用可能です',

    // フィールド名
    'fields' => [
        'id' => 'ID',
        'name' => '代理名',
        'username' => 'ログインアカウント',
        'phone' => '連絡先電話',
        'department_name' => '部門名',
        'status' => 'ステータス',
        'is_super' => 'スーパー管理者',
        'created_at' => '作成日時',
        'password' => 'ログインパスワード',
        'password_confirmation' => 'パスワード確認',
        'avatar' => 'アバターアップロード',
    ],

    // ステータス
    'status' => [
        'normal' => '正常',
        'disabled' => '無効',
    ],

    // スーパー管理者
    'is_super' => [
        'yes' => 'はい',
        'no' => 'いいえ',
    ],

    // フォーム
    'form' => [
        'create_title' => '代理作成',
        'create_hint' => '代理を作成後、代理は代理管理画面にログインし、下位店舗を管理できます',
        'section_account' => 'アカウント情報',
        'section_avatar' => 'アバター設定',
        'section_password' => 'パスワード設定',
    ],

    // プレースホルダー
    'placeholder' => [
        'status' => 'ステータス',
        'username' => 'ログインアカウント',
        'name' => '代理名',
        'phone' => '連絡先電話',
        'created_at' => '作成日時',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
    ],

    // ヘルプテキスト
    'help' => [
        'phone' => '任意、連絡用',
        'username' => '必須、代理管理画面へのログイン用',
        'name' => '代理の表示名',
        'avatar' => 'jpg、png形式対応、推奨サイズ200x200',
        'password' => '代理管理画面ログインパスワード',
    ],

    // 検証ルール
    'validation' => [
        'password_min' => 'パスワードは6文字以上',
    ],

    // エラーメッセージ
    'error' => [
        'create_failed' => '代理の作成に失敗しました：{error}',
    ],
];
