<?php

return [
    'title' => 'カルーセル画像',
    'fields' => [
        'id' => 'ID',
        'url' => 'リンクアドレス',
        'department_id' => 'チャンネル',
        'content' => 'コンテンツ',
        'picture_url' => '画像',
        'status' => 'ステータス',
        'sort' => '並べ替え',
        'ad_position' => '広告位',
        'created_at' => '作成時刻',
    ],
    'ad_position' => [
        0 => '未選択',
        1 => '電子ゲームホール',
        2 => '実体ホール',
        3 => 'スタンバイページ',
    ],
    'url_max_length' => 'リンク アドレスは 200 文字までです',
    'help' => [
        'picture_url_size' => '推奨画像サイズ 1080 * 458',
    ]
];
