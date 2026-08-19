<?php

return [
    'title' => 'Online Player Lottery',

    // Tab titles
    'tab' => [
        'game_online_players' => 'Online Game Players',
        'machine_online_players' => 'Machine Online Players',
    ],

    // Card titles
    'card' => [
        'game_title' => 'Online Game Players ({count} online)',
        'machine_title' => 'Machine Online Players ({count} online)',
    ],

    // Tag text
    'tag' => [
        'realtime_update' => 'Real-time',
        'last_update' => 'Last update: {time}',
        'playing' => 'Playing',
        'seconds_ago' => '{seconds}s ago',
    ],

    // Button text
    'button' => [
        'refresh' => 'Refresh',
        'grant_lottery' => 'Grant Lottery',
    ],

    // Empty state description
    'empty' => [
        'no_online_players' => 'No online players (no bet records in the last 1 minute)',
    ],

    // Table column titles
    'columns' => [
        'id' => 'ID',
        'player_info' => 'Player Info',
        'uuid' => 'UUID',
        'current_machine' => 'Current Machine',
        'current_platform' => 'Current Platform',
        'last_bet_time' => 'Last Bet Time',
        'total_pressure' => 'Total Bets',
        'total_bet' => 'Total Bets',
        'status' => 'Status',
        'action' => 'Action',
    ],

    // Other display text
    'display' => [
        'code_prefix' => 'Code: {code}',
    ],

    // Modal title and form
    'modal' => [
        'grant_lottery' => 'Grant Lottery',
        'player_info' => 'Player Info',
        'select_lottery' => 'Select Lottery',
        'grant_amount' => 'Grant Amount',
        'remark' => 'Remark',
    ],

    // Placeholder
    'placeholder' => [
        'select_lottery' => 'Please select lottery type',
        'input_amount' => 'Please enter grant amount',
        'input_remark' => 'Please enter reason or remark',
    ],

    // Validation messages
    'validation_msg' => [
        'select_lottery' => 'Please select lottery type',
        'input_valid_amount' => 'Please enter a valid amount',
        'grant_success' => 'Lottery granted successfully',
        'grant_failed' => 'Lottery grant failed',
    ],

    // Default values
    'default' => [
        'not_updated' => 'Not updated',
    ],

    // Lottery pool
    'lottery_pool' => 'Pool',

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
