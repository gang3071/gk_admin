<?php

return [
    'title' => 'Store Profit Report',

    // Fields
    'fields' => [
        'id' => 'ID',
        'store_name' => 'Store Name',
        'store_username' => 'Login Account',
        'recharge_amount' => 'Total Open Score',
        'withdraw_amount' => 'Total Wash Score',
        'machine_put_point' => 'Cash In',
        'lottery_amount' => 'Lottery',
        'subtotal' => 'Subtotal',
        'agent_commission' => 'Agent Commission %',
        'agent_profit' => 'Agent Profit',
        'channel_commission' => 'Channel Commission %',
        'channel_profit' => 'Channel Profit',
    ],

    // Filters
    'filter' => [
        'select_store' => 'Select Store',
        'all_stores' => 'All Stores',
        'time_range' => 'Time Range',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
    ],

    // Statistics
    'stats' => [
        'total_recharge' => 'Total Open Score',
        'total_withdraw' => 'Total Wash Score',
        'total_machine_put' => 'Total Cash In',
        'total_lottery' => 'Total Lottery',
        'total_subtotal' => 'Total Subtotal',
        'total_agent_profit' => 'Total Agent Profit',
        'total_channel_profit' => 'Total Channel Profit',
    ],

    // Export
    'export' => [
        'filename' => 'store_profit_report_',
        'title' => 'Store Profit Monthly Report',
        'agent_info' => 'Agent: ',
        'time_range' => 'Time Range: ',
        'start_from' => 'Start From: ',
        'end_at' => 'End At: ',
        'all_time' => 'All Time',
        'export_time' => 'Export Time: ',
        'summary_title' => 'Summary',
        'total' => 'Total',
    ],
];
