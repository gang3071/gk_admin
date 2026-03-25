<?php

return [
    'title' => 'Deposit Bonus Activity Management',

    'fields' => [
        'id' => 'ID',
        'activity_name' => 'Activity Name',
        'store_id' => 'Channel',
        'time_range' => 'Activity Time',
        'bet_multiple' => 'Bet Multiple',
        'valid_days' => 'Valid Days',
        'tier_count' => 'Tier Count',
        'status' => 'Status',
        'created_at' => 'Created At',
        'unlock_type' => 'Unlock Type',
        'limit_per_player' => 'Participation Limit',
        'limit_period' => 'Limit Period',
        'description' => 'Activity Description',
        'tiers' => 'Bonus Tier Config',
        'limit_info' => 'Participation Limit',
    ],

    'tier' => [
        'deposit_amount' => 'Deposit Amount',
        'bonus_amount' => 'Bonus Amount',
        'bonus_ratio' => 'Bonus Ratio',
        'sort_order' => 'Sort Order',
    ],

    'help' => [
        'activity_name' => 'Enter activity name, e.g.: Deposit 1000 Get 500',
        'store_id' => 'Select the channel for this activity',
        'time_range' => 'Set the start and end time of the activity',
        'bet_multiple' => 'The multiple of bonus amount to bet, e.g.: 5x means 500 bonus requires 2500 bet',
        'valid_days' => 'Order valid days, will expire if bet amount not completed',
        'unlock_type' => 'Select unlock type: Bet amount unlock or No machine unlock',
        'limit_per_player' => 'Number of times each player can participate in the limit period, 0 means no limit',
        'limit_period' => 'Statistical period for participation limit',
        'description' => 'Activity description, will be displayed to players',
        'tiers' => 'Configure bonus amounts for different deposit amounts, at least one tier required',
    ],

    'status_enabled' => 'Enabled',
    'status_disabled' => 'Disabled',

    'unlock_type_bet' => 'Bet Amount Unlock',
    'unlock_type_no_machine' => 'No Machine Unlock',

    'period_day' => 'Daily',
    'period_week' => 'Weekly',
    'period_month' => 'Monthly',

    'days' => 'Days',
    'times' => 'Times',
    'no_limit' => 'No Limit',

    'not_found' => 'Activity not found',
    'tier_required' => 'Please configure at least one bonus tier',

    'created_at_start' => 'Created Start Time',
    'created_at_end' => 'Created End Time',
];