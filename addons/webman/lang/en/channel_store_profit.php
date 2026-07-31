<?php

return [
    'title' => 'Store Profit Report',

    // Fields
    'fields' => [
        'id' => 'ID',
        'store_name' => 'Store Name',
        'device_count' => 'Device Count',
        'store_username' => 'Username',
        'agent_name' => 'Agent',
        'remark' => 'Remark',
        'recharge_amount' => 'Total Open Score',
        'withdraw_amount' => 'Total Wash Score',
        'machine_put_point' => 'Coin Deposit',
        'lottery_amount' => 'Lottery',
        'activity_total' => 'Activity Rewards',
        'electronic_game_bet_amount' => 'E-Game Bet Amount',
        'machine_bet_amount' => 'Machine Bet Amount',
        'subtotal' => 'Subtotal',
        'agent_commission' => 'Agent Commission %',
        'agent_profit' => 'Agent Profit',
        'channel_commission' => 'Channel Commission %',
        'channel_profit' => 'Channel Profit',
    ],

    // Filters
    'filter' => [
        'select_agent' => 'Select Agent',
        'all_agents' => 'All Agents',
        'select_store' => 'Select Store',
        'all_stores' => 'All Stores',
        'remark_placeholder' => 'Enter remark keyword',
        'time_range' => 'Time Range',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'select_shift' => 'Select Shift',
        'all_shifts' => 'All Shifts',
    ],

    // Shifts
    'shift' => [
        'morning' => 'Morning (08:00-16:00)',
        'afternoon' => 'Afternoon (16:00-00:00)',
        'night' => 'Night (00:00-08:00)',
    ],

    // Statistics
    'stats' => [
        'total_recharge' => 'Total Open Score',
        'total_withdraw' => 'Total Wash Score',
        'total_machine_put' => 'Total Coin Deposit',
        'total_lottery' => 'Total Lottery',
        'total_subtotal' => 'Total Subtotal',
        'total_agent_profit' => 'Total Agent Profit',
        'total_channel_profit' => 'Total Channel Profit',
    ],

    // Messages
    'message' => [
        'store_not_found' => 'Store not found',
        'update_success' => 'Remark updated successfully',
    ],
];
