<?php

return [
    'title' => 'Online Player Lottery',

    'validation' => [
        'parameter_error' => 'Parameter error',
        'player_not_exist' => 'Player does not exist',
        'lottery_not_exist' => 'Lottery does not exist',
        'insufficient_lottery_balance' => 'Insufficient lottery pool balance, current balance: {balance}',
    ],

    'notice' => [
        'lottery_payout_title' => 'Lottery Payout',
        'lottery_payout_content' => 'Congratulations! You received {lottery_name} lottery reward, amount: {amount}',
    ],

    'log' => [
        'send_socket_message_failed' => 'Failed to send lottery socket message: {message}',
        'manual_payout_success' => 'Manual lottery payout successful',
        'manual_payout_failed' => 'Manual lottery payout failed: {message}',
    ],

    'message' => [
        'payout_success' => 'Lottery payout successful',
        'payout_failed' => 'Lottery payout failed: {message}',
    ],
];
