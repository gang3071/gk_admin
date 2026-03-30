<?php

return [
    'title' => 'Third-Party Game Platform Accounts',

    // Fields
    'fields' => [
        'player_uuid' => 'Player UUID',
        'platform_name' => 'Game Platform',
        'player_code' => 'Platform Account',
        'player_name' => 'Platform Username',
        'status' => 'Status',
        'has_out' => 'Has Transfer Out',
        'created_at' => 'Created At',
    ],

    // Status
    'status' => [
        'normal' => 'Normal',
        'locked' => 'Locked',
        'unknown' => 'Unknown',
    ],

    // Transfer Out Status
    'has_out' => [
        'yes' => 'Transferred Out',
        'no' => 'Not Transferred',
    ],

    // Other
    'unknown_platform' => 'Unknown Platform',
];
