<?php

return [
    'title' => '線下機台列表',
    'slot_list' => '斯洛機台列表',
    'steel_ball_list' => '鋼珠機台列表',
    'slot_info_list' => '斯洛機台資訊',
    'steel_ball_info_list' => '鋼珠機台資訊',

    'gaming_device' => '遊戲中設備',
    'device_info' => '設備資訊',
    'device_balance' => '設備餘額',

    // 操作
    'actions' => [
        'view_qrcode'  => '查看二維碼',
        'batch_qrcode' => '批量生成二維碼',
        'kick_player'  => '踢出玩家',
    ],

    // 确认消息
    'confirm' => [
        'batch_qrcode' => '確定要為選中的機台生成二維碼嗎？',
        'kick_player'  => '確定要踢出玩家並返還餘額嗎？',
    ],

    // 一般消息
    'message' => [
        'kick_success' => '已成功踢出玩家並返還餘額',
    ],

    // 二维码
    'qrcode_title' => '機台二維碼',
    'batch_qrcode_title' => '批量機台二維碼',

    // 错误消息
    'error' => [
        'machine_not_found'     => '機台不存在或無權訪問',
        'no_machines_selected'  => '請至少選擇一個機台',
        'too_many_machines'     => '一次最多生成30個二維碼',
        'no_player_in_machine'  => '該機台目前沒有玩家在遊戲中',
    ],

    'menu' => [
        'machine_list' => '線下機台',
        'machine_info' => '機台資訊',
    ],
];
