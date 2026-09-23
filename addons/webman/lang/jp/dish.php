<?php

return [
    'title' => 'メニュー',
    'fields' => [
        'id' => 'ID',
        'department_id' => 'チャネル/部門',
        'admin_user_id' => '店舗',
        'category_id' => 'カテゴリー',
        'title' => '名称',
        'content' => '内容',
        'picture' => '画像',
        'price' => '価格',
        'daily_limit' => 'お一人様1日あたりの数量限定',
        'status' => 'ステータス',
        'top' => 'トップ固定',
        'sort' => '並び順',
        'remark' => '備考',
        'created_at' => '作成日時',
        'updated_at' => '更新日時'
    ],
    'status' => [
        0 => '無効',
        1 => '有効'
    ],
    'help' => [
        'daily_limit' => '無制限の場合は0を入力してください。',
        'remark' => '種類は半角スラッシュ（/）で、オプションは半角セミコロン（;）で区切ってください。（例：少なめ;微量;なし/全糖;半糖;無糖）'
    ]
];

