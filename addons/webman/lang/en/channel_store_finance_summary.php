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
        'select_agent'         => 'Select Agent',
        'all_agents'           => 'All Agents',
        'select_store'         => 'Select Store',
        'all_stores'           => 'All Stores',
        'placeholder_stores'   => 'Select Stores (multi-select)',
        'placeholder_agent'    => 'Agent (All)',
        'placeholder_date'     => 'Date Type',
        'time_range'           => 'Time Range',
        'start_time'           => 'Start Time',
        'end_time'             => 'End Time',
        'query'                => 'Search',
        'reset'                => 'Reset',
    ],

    'date_types' => [
        'today'      => 'Today',
        'yesterday'  => 'Yesterday',
        'this_week'  => 'This Week',
        'last_week'  => 'Last Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
    ],

    'panel' => [
        'machine_win_loss'    => 'Machine Win/Loss',
        'electronic_win_loss' => 'Electronic Game Win/Loss',
        'net_revenue'         => 'Net Revenue',
        'operating_cost'      => 'Operating Cost',
        'cost_detail'         => 'Operating Cost Details (click type for description)',
        'wallet_detail'       => 'Wallet Flow Reference (excluded from revenue)',
        'total'               => 'Total',
        'profit'              => 'Profit',
        'loss'                => 'Loss',
        'even'                => 'Break Even',
    ],

    'states' => [
        'hint'          => 'Select stores and date range then click Search',
        'search_failed' => 'Search Failed',
        'retry'         => 'Retry',
        'no_data'       => 'No data for the selected criteria',
    ],

    'delivery_types' => [
        1  => 'Admin Credit',
        4  => 'Machine Score Up',
        5  => 'Machine Score Down',
        6  => 'Recharge (Open Score)',
        7  => 'Withdrawal (Wash Score)',
        8  => 'Admin Deduct',
        12 => 'Promoter Commission',
        13 => 'Jackpot Win',
        16 => 'Referral Reward',
        22 => 'Special Type',
        23 => 'Cash Insert',
        31 => 'Pre-deduction',
        32 => 'Refund',
        33 => 'Lottery Ticket Reward',
        34 => 'VIP Birthday Bonus',
        35 => 'VIP Upgrade Bonus',
        36 => 'Rebate Pool Rebate',
        37 => 'VIP Daily Login',
        38 => 'External Activity Bonus',
        39 => 'Leaderboard Reward',
    ],

    'desc_panel' => [
        'title'      => 'Account Change Types',
        'click_hint' => "Click a type on the left\nto view details",
    ],

    'summary' => [
        'row_label' => 'Total',
    ],
];
