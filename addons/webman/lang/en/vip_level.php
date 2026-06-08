<?php

return [
    'title' => 'VIP Level Management',
    'cashback' => 'Cashback Ratio',
    'cashback_title' => ':name - Cashback Ratio Settings',
    'import_template' => 'Import Template',
    'import_confirm' => 'Are you sure you want to import the default VIP template? This will create 10 default VIP levels (VIP1-VIP10)',
    'import_error_exists' => 'This channel already has {count} VIP level(s), no need to import again',
    'fields' => [
        'id' => 'ID',
        'name' => 'Level Name',
        'upgrade_limit_days' => 'Upgrade Limit (Days)',
        'retain_level_days' => 'Level Retention (Days)',
        'retain_level_bet_amount' => 'Retention Bet Amount',
        'upgrade_bet_amount' => 'Upgrade Bet Amount',
        'min_claim_amount' => 'Minimum Claim Amount',
        'birthday_bonus' => 'Birthday Bonus',
        'sort' => 'Sort',
        'status' => 'Status',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'help' => [
        'upgrade_limit_days' => 'Upgrade time limit in days',
        'retain_level_days' => 'Level retention period in days',
        'retain_level_bet_amount' => 'Bet amount required to retain level',
        'upgrade_bet_amount' => 'Bet amount required to upgrade',
        'min_claim_amount' => 'Minimum claim amount',
        'birthday_bonus' => 'Birthday bonus amount',
        'sort' => 'Lower values appear first',
        'cashback_ratio' => 'Cashback ratio, 100=100%, 0.1=0.1%',
    ],
    'status' => [
        0 => 'Disabled',
        1 => 'Enabled',
    ],
    'messages' => [
        'cashback_saved' => 'Cashback ratio saved successfully',
    ],
];
