<?php

return [
    'title' => 'National Promoter Setting',
    'report_title' => 'National Agent Report',
    'national_profit_record' => 'Distribution Details',
    'fields' => [
        'id' => 'ID',
        'uid' => 'Player ID',
        'chip_amount' => 'Current Chip Amount',
        'level_sort' => 'User Level',
        'level' => 'User Level',
        'invite_num' => 'Number of Invited Players',
        'pending_amount' => 'Pending points',
        'settlement_amount' => 'Settlement points',
        'created_at' => 'Creation Time',
        'updated_at' => 'Update Time',
        'last_national_profit_money' => 'previous settlement points',
        'last_national_profit_created_at' => 'previous settlement time',
        'recommend_promoter_uuid' => 'Recommended person UUID',
        'type' => 'Type of commission rebate',
    ],
    'level_list' => [
        'title' => 'Upgrade Conditions',
        'sort' => 'Rank Sorting',
        'name' => 'Level Name',
        'level' => 'Level',
        'must_chip_amount' => 'Must Chip Amount',
        'damage_rebate_ratio' => 'Damage Rebate Ratio',
        'recharge_ratio' => 'Open Score Rebate points',
        'reverse_water' => 'Reverse water ratio',
    ],
    'must_chip_amount_error' => 'The Chip Amount must be higher than the subordinate and lower than the superior',
    'invite' =>[
        'min' => 'Minimum number of people',
        'max' => 'Maximum number of people',
        'number' => 'Number requirement',
        'interval' => 'Reward interval',
        'money' => 'Reward points',
        'status' => 'status',
    ],
    'invite_num_error' => 'Overlapping invitation number range',
    'machine_amount' => 'Machine lubrication',
    'game_amount' => 'Electronic game revenue sharing',
    'type' => [
        'First recharge commission rebate',
        'Customer loss rebate'
    ],
    'settlement' => 'Profit sharing settlement',
    'settlement_confirm' => 'You are currently performing settlement operations on the national agent {uuid}, and can settle the profit sharing {amount}, Please confirm if settlement has been made?',
    'level_prefix' => 'Level',
    'level_suffix' => '',
    'player' => 'Player',
    'admin' => 'Admin',

    'log' => [
        'settlement_failed' => 'Settlement Failed',
    ],
];
