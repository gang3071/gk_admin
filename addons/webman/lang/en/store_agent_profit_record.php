<?php

return [
    'title' => 'Offline Profit Settlement Records',

    'fields' => [
        'id' => 'ID',
        'settlement_tradeno' => 'Settlement No.',
        'adjust_amount' => 'Profit Adjustment Amount',
        'actual_amount' => 'Actual Profit Amount',
        'profit_amount' => 'Profit Amount',
        'ratio' => 'Profit Ratio',
        'sub_name' => 'Submit Target',
        'sub_profit_amount' => 'Submit Amount',
        'sub_ratio' => 'Submit Ratio',
        'total_bet' => 'Total Bet',
        'total_diff' => 'Total Win/Loss',
        'machine_point' => 'Cash Deposit',
        'total_income' => 'Total Revenue',
        'total_in' => 'Transfer In (Top-up)',
        'total_out' => 'Transfer Out (Cash Out)',
        'user_name' => 'Administrator',
        'start_time' => 'Settlement Start Time',
        'end_time' => 'Settlement End Time',
        'created_at' => 'Created Time',
    ],

    'detail' => [
        'agent_name' => 'Agent Name',
        'uuid' => 'UUID',
        'submit_ratio' => 'Submit Ratio',
        'machine_put_point' => 'Total Cash Deposit (Recharge)',
        'present_out_amount' => 'Transfer Out (Cash Out)',
        'present_in_amount' => 'Total Transfer In (Top-up)',
        'total_point' => 'Total Revenue',
        'settlement_amount' => 'Settled Amount',
        'created_at' => 'Created Time',
        'last_settlement_time' => 'Last Settlement Time',
    ],

    'label' => [
        'agent_store' => 'Agent/Store',
        'agent_account' => 'Agent Account',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'no_time' => 'None',
    ],

    'type' => [
        'store' => 'Store',
        'agent' => 'Agent',
        'channel' => 'Channel',
    ],

    'placeholder' => [
        'settlement_tradeno' => 'Settlement No.',
        'agent_store' => 'Agent/Store',
    ],
];
