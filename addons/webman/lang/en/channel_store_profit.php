<?php

return [
    'title' => 'Store Profit Report',

    // Fields
    'fields' => [
        'id' => 'ID',
        'store_name' => 'Store Name',
        'store_username' => 'Username',
        'agent_name' => 'Agent',
        'recharge_amount' => 'Total Recharge',
        'withdraw_amount' => 'Total Withdrawal',
        'machine_put_point' => 'Coin Deposit',
        'lottery_amount' => 'Lottery',
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
        'time_range' => 'Time Range',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
    ],

    // Statistics
    'stats' => [
        'total_recharge' => 'Total Recharge',
        'total_withdraw' => 'Total Withdrawal',
        'total_machine_put' => 'Total Coin Deposit',
        'total_lottery' => 'Total Lottery',
        'total_subtotal' => 'Total Subtotal',
        'total_agent_profit' => 'Total Agent Profit',
        'total_channel_profit' => 'Total Channel Profit',
    ],
];
