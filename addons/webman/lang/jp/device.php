<?php

return [
    // ページタイトル
    'title' => 'プレイヤーリスト',
    'create' => 'プレイヤー追加',
    'add_device' => 'プレイヤー追加',
    'edit_device' => 'プレイヤー編集',
    'edit' => 'プレイヤー編集',
    'ip_list' => 'IPバインドリスト',
    'ip_management' => 'IP管理',
    'add_ip' => 'IP追加',
    'manage_ip' => 'IP管理',
    'access_log_title' => 'デバイスアクセスログ',

    // フィールド
    'fields' => [
        'device_name' => 'デバイス名',
        'device_no' => 'デバイス番号',
        'device_no_help' => 'Androidデバイスの一意識別子（例：Android ID、IMEIなど）',
        'device_model' => 'デバイスモデル',
        'voice_url' => '音声アナウンス',
        'channel_name' => '所属チャネル',
        'department_name' => '所属部門',
        'agent_name' => '所属エージェント',
        'agent_help' => 'デバイスの所属エージェントを選択（オフラインチャネルのみ）',
        'store_name' => '所属ストア',
        'store_help' => 'デバイスの所属ストアを選択（オフラインチャネルのみ）',
        'status' => 'ステータス',
        'remark' => '備考',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
        'ip_count' => 'IP数',
        'ip_address' => 'IPアドレス',
        'ip_address_help' => 'IPv4またはIPv6形式をサポート',
        'ip_type' => 'IPタイプ',
        'last_used_at' => '最終使用日時',
        'is_allowed' => 'アクセス結果',
        'reject_reason' => '拒否理由',
        'request_url' => 'リクエストURL',
        'user_agent' => 'User Agent',
    ],

    // ステータス
    'status' => [
        'disabled' => '無効',
        'enabled' => '有効',
    ],

    // アクセスログ
    'access_log' => [
        'allowed' => '許可',
        'rejected' => '拒否',
    ],

    // オプション
    'no_agent' => 'エージェントなし',
    'no_store' => 'ストアなし',

    // 機能
    'batch_disable' => '一括無効化',
    'batch_disable_confirm' => '選択したデバイスを一括無効化してもよろしいですか？',
    'batch_disable_success' => '{count}個のデバイスを無効化しました',
    'batch_disable_failed' => 'デバイスの一括無効化に失敗しました',
    'no_device_selected' => '無効化するデバイスを選択してください',

    // 音声アナウンス
    'voice' => [
        'not_generated' => '未生成',
        'generated' => '音声生成済み',
        'generate_failed' => '音声生成失敗',
        'generate_error' => '音声生成エラー',
        'regenerate' => '音声を再生成',
        'regenerate_confirm' => '音声アナウンスファイルを再生成してもよろしいですか？',
        'regenerate_success' => '音声の再生成に成功しました',
    ],

    // メッセージ
    'device_no_exists' => 'デバイス番号が既に存在します',
    'device_no_help' => '一意のデバイス番号、重複不可',
    'device_not_found' => 'デバイスが見つかりません',
    'invalid_ip_address' => 'IPアドレスの形式が正しくありません',
    'ip_already_exists' => 'IPアドレスが既に存在します',
    'delete_confirm' => 'このデバイスを削除してもよろしいですか？削除すると、バインドされたすべてのIPアドレスも削除されます。',
    'save_success' => '保存に成功しました',
    'select_channel_first' => '最初にチャネルを選択してください',
];
