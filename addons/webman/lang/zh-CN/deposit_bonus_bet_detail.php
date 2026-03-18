<?php

return [
    'title' => '押码量明细',

    'fields' => [
        'id' => 'ID',
        'order_no' => '订单号',
        'player_account' => '玩家账号',
        'player_info' => '玩家信息',
        'game_type' => '游戏类型',
        'game_platform' => '游戏平台',
        'game_name' => '游戏名称',
        'bet_amount' => '押注金额',
        'valid_bet_amount' => '有效押注',
        'win_amount' => '赢取金额',
        'balance_before' => '押注前余额',
        'balance_after' => '押注后余额',
        'accumulated_bet' => '累计打码量',
        'new_accumulated_bet' => '本次后累计',
        'bet_time' => '押注时间',
        'settle_time' => '结算时间',
        'created_at' => '创建时间',
    ],

    'stats' => [
        'total_count' => '记录总数',
        'total_bet' => '押注总额',
        'total_valid_bet' => '有效押注总额',
        'total_win' => '赢取总额',
    ],

    'game_type_slot' => '老虎机',
    'game_type_electron' => '电子游戏',
    'game_type_baccarat' => '真人百家',
    'game_type_lottery' => '彩票',
];
