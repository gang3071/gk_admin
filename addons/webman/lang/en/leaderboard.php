<?php

return [
    'title' => 'Leaderboard',
    'fields' => [
        'id' => 'ID',
        'code' => 'Code',
        'name' => 'Name',
        'period_type' => 'Period Type',
        'scope_type' => 'Scope Type',
        'period_key' => 'Period Key',
        'start_at' => 'Start Time',
        'end_at' => 'End Time',
        'settle_at' => 'Settlement Time',
        'threshold' => 'Threshold Score',
        'prize_config' => 'Prize Configuration',
        'entry_count' => 'Rankings Count',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At'
    ],
    'period_type' => [
        1 => 'Weekly',
        2 => 'Monthly'
    ],
    'scope_type' => [
        1 => 'Global',
        3 => 'Store'
    ],
    'settle_at' => [
        'start' => 'Settlement Start Time',
        'end' => 'Settlement End Time'
    ],
];

