<?php

return [
    'title' => '线下机台列表',
    'slot_list' => '斯洛机台列表',
    'steel_ball_list' => '钢珠机台列表',
    'slot_info_list' => '斯洛机台资讯',
    'steel_ball_info_list' => '钢珠机台资讯',

    'gaming_device' => '游戏中设备',
    'device_info' => '设备资讯',
    'device_balance' => '设备余额',

    // 操作
    'actions' => [
        'view_qrcode'  => '查看二维码',
        'batch_qrcode' => '批量生成二维码',
        'kick_player'  => '踢出玩家',
    ],

    // 确认消息
    'confirm' => [
        'batch_qrcode' => '确定要为选中的机台生成二维码吗？',
        'kick_player'  => '确定要踢出玩家并返还余额吗？',
    ],

    // 一般消息
    'message' => [
        'kick_success' => '已成功踢出玩家并返还余额',
    ],

    // 二维码
    'qrcode_title' => '机台二维码',
    'batch_qrcode_title' => '批量机台二维码',

    // 错误消息
    'error' => [
        'machine_not_found'    => '机台不存在或无权访问',
        'no_machines_selected' => '请至少选择一个机台',
        'too_many_machines'    => '一次最多生成30个二维码',
        'no_player_in_machine' => '该机台当前没有玩家在游戏中',
    ],

    'menu' => [
        'machine_list' => '线下机台',
        'machine_info' => '机台资讯',
    ],
];
