<?php

return [
    'title' => '身份证黑名单',

    'fields' => [
        'id_number'  => '身份证号码',
        'player_name' => '玩家名称',
        'admin_name' => '操作人员',
        'remark'     => '备注',
        'created_at' => '加入时间',
        'status'     => '黑名单状态',
    ],

    'action' => [
        'add' => '加入黑名单',
    ],

    'placeholder' => [
        'id_number' => '请输入身份证号码',
        'remark'    => '请输入备注',
    ],

    'message' => [
        'add_success'      => '已成功加入黑名单',
        'not_blacklisted'  => '✓ 未加入黑名单',
    ],

    'error' => [
        'id_number_required'      => '身份证号码不能为空',
        'id_number_in_blacklist'  => '此身份证号码已在黑名单中，无法新增玩家',
    ],

    'filter' => [
        'id_number' => '身份证号码',
    ],
];
