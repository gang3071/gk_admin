<?php

return [
    'title' => '身份證黑名單',

    'fields' => [
        'id_number'  => '身份證號碼',
        'player_name' => '玩家名稱',
        'admin_name' => '操作人員',
        'remark'     => '備注',
        'created_at' => '加入時間',
        'status'     => '黑名單狀態',
    ],

    'action' => [
        'add'            => '加入黑名單',
        'remove'         => '移除',
        'remove_confirm' => '確定要將此身份證號碼從黑名單中移除嗎？',
    ],

    'placeholder' => [
        'id_number' => '請輸入身份證號碼',
        'remark'    => '請輸入備注',
    ],

    'message' => [
        'add_success'      => '已成功加入黑名單',
        'remove_success'   => '已成功從黑名單中移除',
        'not_blacklisted'  => '✓ 未加入黑名單',
    ],

    'error' => [
        'id_number_required'      => '身份證號碼不能為空',
        'id_number_in_blacklist'  => '此身份證號碼已在黑名單中，無法新增玩家',
    ],

    'filter' => [
        'id_number' => '身份證號碼',
    ],
];
