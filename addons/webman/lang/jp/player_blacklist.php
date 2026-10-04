<?php

return [
    'title' => 'IDカードブラックリスト',

    'fields' => [
        'id_number'  => 'ID番号',
        'player_name' => 'プレイヤー名',
        'admin_name' => '操作者',
        'remark'     => '備考',
        'created_at' => '追加時間',
    ],

    'action' => [
        'add' => 'ブラックリストに追加',
    ],

    'placeholder' => [
        'id_number' => 'ID番号を入力',
        'remark'    => '備考を入力',
    ],

    'message' => [
        'add_success' => 'ブラックリストへの追加が完了しました',
    ],

    'error' => [
        'id_number_required' => 'ID番号は必須です',
    ],

    'filter' => [
        'id_number' => 'ID番号',
    ],
];
