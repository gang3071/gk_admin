<?php

return [
    'title' => 'オフライン機台リスト',
    'slot_list' => 'スロット機台リスト',
    'steel_ball_list' => '鋼球機台リスト',
    'slot_info_list' => 'スロット機台情報',
    'steel_ball_info_list' => '鋼球機台情報',

    'gaming_device' => 'ゲーム中デバイス',
    'device_info' => 'デバイス情報',
    'device_balance' => 'デバイス残高',

    // 操作
    'actions' => [
        'view_qrcode' => 'QRコードを表示',
        'batch_qrcode' => '一括QRコード生成',
    ],

    // 確認メッセージ
    'confirm' => [
        'batch_qrcode' => '選択した機台のQRコードを生成してもよろしいですか？',
    ],

    // QRコード
    'qrcode_title' => '機台QRコード',
    'batch_qrcode_title' => '一括機台QRコード',

    // エラーメッセージ
    'error' => [
        'machine_not_found' => '機台が存在しないか、アクセス権限がありません',
        'no_machines_selected' => '少なくとも1つの機台を選択してください',
        'too_many_machines' => '一度に最大30個のQRコードを生成できます',
    ],

    'menu' => [
        'machine_list' => 'オフライン機台',
        'machine_info' => '機台情報',
    ],
];
