<?php

return [
    // ページタイトル
    'title' => '出票機制御',
    'control_panel' => '出票機制御パネル',

    // 接続状態
    'status' => [
        'connected' => '接続済み',
        'disconnected' => '未接続',
        'connecting' => '接続中...',
        'error' => '接続エラー',
    ],

    // 用紙状態
    'paper' => [
        'normal' => '正常',
        'empty' => '用紙なし',
        'jam' => '紙詰まり',
    ],

    // 操作ボタン
    'action' => [
        'connect' => '接続',
        'disconnect' => '切断',
        'heartbeat' => 'ハートビート送信',
        'sync_datetime' => '日時同期',
        'set_uid' => 'UID設定',
        'set_machine_no' => '機台番号設定',
        'set_store_name' => '店名設定',
        'set_serial_no' => 'シリアル番号設定',
        'send_lottery' => '宝くじデータ送信',
        'send_qr' => 'QRコード送信',
        'reset' => 'リセット',
        'query_status' => '状態確認',
        'init_machine' => 'デバイス初期化',
        'send_hex' => 'HEXコマンド送信',
    ],

    // フォームラベル
    'field' => [
        'port' => 'シリアルポート',
        'baud_rate' => 'ボーレート',
        'uid' => 'ユニークID',
        'machine_no' => '機台番号',
        'store_name' => '店名',
        'serial_no' => 'シリアル番号',
        'ticket_count' => 'チケット数',
        'gift_count' => 'プレゼント数',
        'code_table' => 'コードテーブル',
        'number' => '数',
        'qr_code' => 'QRコード内容',
        'hex_command' => 'HEXコマンド',
    ],

    // ヘルプテキスト
    'help' => [
        'port' => 'Linux: /dev/ttyUSB0, Windows: COM3',
        'uid' => '16文字の一意識別子',
        'machine_no' => '範囲: 0-65535',
        'store_name' => '最大10文字',
        'serial_no' => '範囲: 0-9999999',
        'hex_command' => 'スペース区切りのHEX文字列、例: FA EA 01 01 00 XX XX FB EB',
    ],

    // メッセージ
    'message' => [
        'connect_success' => '出票機に接続しました',
        'connect_failed' => '接続失敗: {error}',
        'disconnect_success' => '切断しました',
        'heartbeat_success' => 'ハートビート送信成功',
        'heartbeat_failed' => 'ハートビート送信失敗',
        'datetime_synced' => '日時を同期しました',
        'uid_set' => 'UIDを設定しました',
        'machine_no_set' => '機台番号を設定しました',
        'store_name_set' => '店名を設定しました',
        'serial_no_set' => 'シリアル番号を設定しました',
        'lottery_sent' => '宝くじデータを送信しました',
        'qr_sent' => 'QRコードを送信しました',
        'reset_sent' => 'リセットコマンドを送信しました',
        'init_success' => 'デバイスを初期化しました',
        'hex_sent' => 'HEXコマンドを送信しました',
        'not_connected' => '未接続です',
        'service_unavailable' => 'サービスが利用できません',
    ],

    // ログ
    'log' => [
        'title' => '通信ログ',
        'send' => '送信',
        'receive' => '受信',
        'info' => '情報',
        'error' => 'エラー',
        'clear' => 'ログクリア',
    ],

    // チケット記録
    'redeem' => [
        'title' => 'チケット記録',
        'order_id' => '注文番号',
        'store_name' => '店名',
        'machine_no' => '機台番号',
        'score' => 'スコア/金額',
        'ticket_type' => 'チケットタイプ',
        'type_redeem' => 'チケット',
        'player_id' => 'プレイヤー情報',
        'qr_code' => 'QRコード情報',
        'qr_code_no' => 'QRコード番号',
        'status' => 'ステータス',
        'status_disabled' => '無効',
        'status_normal' => '正常',
        'status_printed' => '印刷済み',
        'status_used' => '使用済み',
        'status_unknown' => '不明',
        'print_count' => '印刷回数',
        'last_print_time' => '最終印刷時間',
        'remark' => '備考',
        'created_at' => '作成日時',
        'disable' => '無効にする',
        'restore' => '復元する',
        'redeem' => 'チケット',
        'redeem_btn' => 'チケット',
        'redeem_confirm' => 'この記録をチケットしますか？',
        'redeem_success' => 'チケット成功',
        'input_qr_code' => 'QRコード',
        'input_qr_code_placeholder' => 'QRコードを入力またはスキャンしてください',
        'scan_qr_code_placeholder' => 'QRコードをスキャンしてください',
        'qr_code_required' => 'QRコードを入力してください',
        'record_not_found' => '記録が見つからないか、チケットできません',
        'qr_code_mismatch' => 'QRコードが一致しません',
        'delete_confirm' => 'この記録を無効にしますか？',
        'delete_success' => '記録が無効になりました',
        'restore_confirm' => 'この記録を復元しますか？',
        'restore_success' => '記録が復元されました',
        'total_score' => '合計チケット金額：',
        'total_count' => '合計チケット回数：',
        'used_count' => '使用済み：',
        'used_score' => '使用済み金額：',
    ],
];
