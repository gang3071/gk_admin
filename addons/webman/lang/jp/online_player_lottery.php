<?php

return [
    'title' => 'オンラインプレイヤー宝くじ',

    'validation' => [
        'parameter_error' => 'パラメータエラー',
        'player_not_exist' => 'プレイヤーが存在しません',
        'lottery_not_exist' => '宝くじが存在しません',
        'insufficient_lottery_balance' => '宝くじプール残高不足、現在の残高：{balance}',
    ],

    'notice' => [
        'lottery_payout_title' => '宝くじ配当',
        'lottery_payout_content' => 'おめでとうございます！{lottery_name}の宝くじ報酬を獲得しました、金額：{amount}',
    ],

    'log' => [
        'send_socket_message_failed' => '宝くじSocketメッセージの送信に失敗しました：{message}',
        'manual_payout_success' => '手動宝くじ配当成功',
        'manual_payout_failed' => '手動宝くじ配当失敗：{message}',
    ],

    'message' => [
        'payout_success' => '宝くじ配当成功',
        'payout_failed' => '宝くじ配当失敗：{message}',
    ],
];
