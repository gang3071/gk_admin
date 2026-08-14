<?php

return [
    'title' => 'オフライン機台管理',

    'fields' => [
        'code' => '機台番号',
        'name' => '機台名',
        'label' => '機台ラベル',
        'type' => '機台タイプ',
        'channel' => 'チャネル',
        'store' => '紐付け店舗',
        'ip' => '機台IP',
        'port' => '機台ポート',
        'domain' => '機台Domain',
        'control_type' => '制御タイプ',
        'status' => 'ステータス',
        'gaming' => 'ゲームステータス',
        'sort' => 'ソート',
        'remark' => '備考',
    ],

    'status' => [
        'unassigned' => '未割当',
        'unbound' => '未紐付け',
        'gaming' => 'ゲーム中',
        'idle' => 'アイドル',
    ],

    'error' => [
        'no_media_config' => 'オフライン機台はライブストリーム設定をサポートしていません',
        'not_offline_machine' => 'この機台はオフライン機台ではないため、編集できません',
    ],
];
