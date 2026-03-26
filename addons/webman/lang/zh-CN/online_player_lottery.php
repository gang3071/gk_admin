<?php

return [
    'title' => '线上玩家彩金',

    'validation' => [
        'parameter_error' => '参数错误',
        'player_not_exist' => '玩家不存在',
        'lottery_not_exist' => '彩金不存在',
        'insufficient_lottery_balance' => '彩金池余额不足，当前余额：{balance}',
    ],

    'notice' => [
        'lottery_payout_title' => '彩金派彩',
        'lottery_payout_content' => '恭喜您获得{lottery_name}的彩金奖励，金额：{amount}',
    ],

    'log' => [
        'send_socket_message_failed' => '发送彩金Socket消息失败：{message}',
        'manual_payout_success' => '手动发放彩金成功',
        'manual_payout_failed' => '手动发放彩金失败：{message}',
    ],

    'message' => [
        'payout_success' => '彩金发放成功',
        'payout_failed' => '彩金发放失败：{message}',
    ],
];
