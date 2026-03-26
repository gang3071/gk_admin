<?php

return [
    'title' => '線上玩家彩金',

    'validation' => [
        'parameter_error' => '參數錯誤',
        'player_not_exist' => '玩家不存在',
        'lottery_not_exist' => '彩金不存在',
        'insufficient_lottery_balance' => '彩金池餘額不足，當前餘額：{balance}',
    ],

    'notice' => [
        'lottery_payout_title' => '彩金派彩',
        'lottery_payout_content' => '恭喜您獲得{lottery_name}的彩金獎勵，金額：{amount}',
    ],

    'log' => [
        'send_socket_message_failed' => '發送彩金Socket消息失敗：{message}',
        'manual_payout_success' => '手動發放彩金成功',
        'manual_payout_failed' => '手動發放彩金失敗：{message}',
    ],

    'message' => [
        'payout_success' => '彩金發放成功',
        'payout_failed' => '彩金發放失敗：{message}',
    ],
];
