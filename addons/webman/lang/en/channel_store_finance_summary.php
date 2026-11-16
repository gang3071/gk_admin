<?php

return [
    'title' => 'Store Finance Summary',

    'fields' => [
        'id'                       => 'ID',
        'store_name'               => 'Store Name',
        'store_username'           => 'Login Account',
        'agent_name'               => 'Agent',
        'electronic_game_win_loss' => 'Electronic Game Win/Loss',
        'machine_win_loss'         => 'Machine Win/Loss',
        'lottery_ticket_sent'      => 'Lottery Tickets Issued',
        'electronic_jackpot'       => 'Electronic Jackpot',
        'machine_jackpot'          => 'Machine Jackpot',
        'wallet_increase'          => 'Wallet Increase',
        'wallet_decrease'          => 'Wallet Decrease',
        'machine_rebate'           => 'Machine Rebate',
        'electronic_rebate'        => 'Electronic Rebate',
        'coupon_sent'              => 'Trial/Welfare Coupon',
    ],

    'filter' => [
        'select_agent'  => 'Select Agent',
        'all_agents'    => 'All Agents',
        'select_store'  => 'Select Store',
        'all_stores'    => 'All Stores',
        'time_range'    => 'Time Range',
        'start_time'    => 'Start Time',
        'end_time'      => 'End Time',
    ],

    'summary' => [
        'row_label' => 'Total',
    ],
];
