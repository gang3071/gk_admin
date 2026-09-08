<?php

return [
    'title' => 'Vending Machine Score Purchase Configuration',
    'score_settings' => 'Score Purchase Options Configuration',
    'player_exists' => 'This store already has a purchase score configuration',
    'at_least_one_score' => 'At least one purchase score option is required',
    'reset_to_default' => 'Reset to Default Configuration',
    'reset_confirm_message' => 'Are you sure you want to reset the purchase score configuration to default values?',
    'reset_success' => 'Successfully reset to default configuration',
    'reset_failed' => 'Failed to reset default configuration',
    'not_set' => 'Not Set',
    'fields' => [
        'id' => 'ID',
        'store_admin_id' => 'Store',
        'store_admin_name' => 'Store Name',
        'scores' => 'Current Score Options',
        'default_scores' => 'Default Score',
        'system_default' => 'System Default Score',
        'score_1' => 'Score Option 1',
        'score_2' => 'Score Option 2',
        'score_3' => 'Score Option 3',
        'score_4' => 'Score Option 4',
        'score_5' => 'Score Option 5',
        'score_6' => 'Score Option 6',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'help' => [
        'store_admin_id' => 'Select the store to configure purchase scores',
        'score' => 'Enter the score amount, 0 means this option is disabled',
        'default_scores' => 'Set the default score value, used when resetting to default configuration',
    ],
    'error' => [
        'must_be_positive_integer' => ':field must be a positive integer',
    ],
];
