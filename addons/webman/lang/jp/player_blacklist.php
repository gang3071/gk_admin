<?php

return [
    'title' => 'IDカードブラックリスト',

    'fields' => [
        'id_number'  => 'ID番号',
        'player_name' => 'プレイヤー名',
        'admin_name' => '操作者',
        'remark'     => '備考',
        'created_at' => '追加時間',
        'status'     => 'ブラックリスト状態',
    ],

    'action' => [
        'add' => 'ブラックリストに追加',
    ],

    'placeholder' => [
        'id_number' => 'ID番号を入力',
        'remark'    => '備考を入力',
    ],

    'message' => [
        'add_success'      => 'ブラックリストへの追加が完了しました',
        'not_blacklisted'  => '✓ ブラックリスト未登録',
    ],

    'error' => [
        'id_number_required'     => 'ID番号は必須です',
        'id_number_in_blacklist' => 'このID番号はブラックリストに登録されており、プレイヤーを作成できません',
    ],

    'filter' => [
        'id_number' => 'ID番号',
    ],
];
