<?php

return [
    'title' => 'Deposit Bonus Order Management',
    'generate_title' => 'Generate Deposit Bonus Order',

    'fields' => [
        'id' => 'ID',
        'order_no' => 'Order No',
        'activity_id' => 'Activity',
        'activity_name' => 'Activity Name',
        'player_id' => 'Player',
        'player_account' => 'Player Account',
        'player_info' => 'Player Info',
        'deposit_amount' => 'Deposit Amount',
        'bonus_amount' => 'Bonus Amount',
        'required_bet_amount' => 'Required Bet Amount',
        'current_bet_amount' => 'Current Bet Amount',
        'bet_progress' => 'Bet Progress',
        'status' => 'Order Status',
        'qrcode_token' => 'QR Code Token',
        'qrcode_expires_at' => 'QR Code Expires At',
        'expires_at' => 'Order Expires At',
        'verified_at' => 'Verified At',
        'completed_at' => 'Completed At',
        'created_at' => 'Created At',
        'remark' => 'Remark',
    ],

    'help' => [
        'activity_id' => 'Select the activity to participate in',
        'deposit_amount' => 'Enter deposit amount, must match activity tier',
        'player_id' => 'Search and select player account',
        'player_username' => 'Please enter player account',
    ],

    'status_pending' => 'Pending',
    'status_verified' => 'Verified',
    'status_completed' => 'Completed',
    'status_expired' => 'Expired',
    'status_cancelled' => 'Cancelled',
    'status_unknown' => 'Unknown Status',

    'cannot_edit' => 'Order cannot be edited, only added',
    'activity_invalid' => 'Activity does not exist or has expired',
    'tier_not_match' => 'Deposit amount does not match any activity tier',
    'player_not_found' => 'Player not found',
    'player_limit_exceeded' => 'Player participation limit exceeded',
    'parent_agent_not_found' => 'Parent agent not found',
    'no_available_activity' => 'Parent agent has no available deposit bonus activities',
    'generate_success' => 'Order generated successfully',
    'generate_success_with_orderno' => 'Order generated successfully! Order No: {order_no}',
    'generate_fail' => 'Order generation failed',

    'stats' => [
        'total_count' => 'Total Orders',
        'total_deposit' => 'Total Deposit',
        'total_bonus' => 'Total Bonus',
        'completed_count' => 'Completed',
    ],
];