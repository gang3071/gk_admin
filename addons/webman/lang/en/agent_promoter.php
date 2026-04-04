<?php

return [
    'title' => 'Agent/Store List',
    'settlement_records_title' => 'Subordinate Profit Settlement Records',

    // Fields
    'fields' => [
        'id' => 'ID',
        'agent_account' => 'Agent Account',
        'agent_name' => 'Agent/Store',
        'bound_player_uuid' => 'Bound Player UUID',
        'payment_ratio' => 'Payment Ratio',
        'current_total_revenue' => 'Current Total Revenue',
        'agent_profit_amount' => 'Agent Profit Amount',
        'profit_adjust_amount' => 'Profit Adjustment',
        'current_payment_amount' => 'Current Payment Amount',
        'current_transfer_in' => 'Current Transfer In (Credits)',
        'current_cash_in' => 'Current Cash In (Open Score)',
        'current_transfer_out' => 'Current Transfer Out (Cash Out)',
        'store_device_count' => 'Store/Device Count',
        'device_count' => 'Device Count',
        'current_machine_score' => 'Current Machine Score',
        'parent_agent' => 'Parent Agent',
        'tradeno' => 'Settlement No',
        'settlement_tradeno' => 'Settlement No',
        'settlement_time' => 'Settlement Time',
        'last_settlement_time' => 'Last Settlement Time',
        'actual_profit_amount' => 'Actual Profit Amount',
        'profit_amount' => 'Profit Amount',
        'profit_ratio' => 'Profit Ratio',
        'payment_target' => 'Payment Target',
        'payment_amount' => 'Payment Amount',
        'payment_ratio_percent' => 'Payment Ratio',
        'total_bet' => 'Total Bet',
        'total_diff' => 'Total Win/Loss',
        'machine_point' => 'Cash In Points',
        'total_income' => 'Total Revenue',
        'total_cash_in' => 'Total Cash In (Open Score)',
        'total_transfer_in' => 'Total Transfer In (Credits)',
        'transfer_in' => 'Transfer In (Credits)',
        'transfer_out' => 'Transfer Out (Cash Out)',
        'admin' => 'Admin',
        'settlement_start_time' => 'Settlement Start Time',
        'settlement_end_time' => 'Settlement End Time',
        'created_at' => 'Created At',
        'start_time' => 'Start Time',
    ],

    // Labels
    'label' => [
        'agent_account_with_value' => 'Agent Account: {value}',
        'agent_settlement_with_value' => 'Agent Profit Settlement: {value}',
    ],

    // Placeholders
    'placeholder' => [
        'tradeno' => 'Settlement No',
        'agent_promoter' => 'Agent/Store',
    ],

    // Help text
    'help' => [
        'settlement_time' => 'Settlement time cannot exceed current time, cannot select the time range of last settlement',
        'settlement_range' => 'Selectable settlement time range: {start} ~ now',
    ],

    // Form titles
    'form' => [
        'last_settlement_time' => 'Last Settlement Time',
    ],

    // Buttons
    'button' => [
        'settlement' => 'Settlement',
        'batch_settlement' => 'Batch Settlement',
    ],

    // Confirm messages
    'confirm' => [
        'batch_settlement' => 'Batch settlement cannot be settled accurately by time. All unsettled time will be settled. Are you sure to settle?',
    ],

    // Tags
    'tag' => [
        'channel' => 'Channel',
        'store' => 'Store',
        'agent' => 'Agent',
    ],

    // Detail items
    'detail' => [
        'agent_name' => 'Agent Name',
        'agent_created_at' => 'Agent Created At',
        'settled_amount' => 'Settled Amount',
        'last_settlement_time' => 'Last Settlement Time',
        'start_time_label' => 'Start Time',
        'end_time_label' => 'End Time',
        'no_time' => 'None',
    ],
];
