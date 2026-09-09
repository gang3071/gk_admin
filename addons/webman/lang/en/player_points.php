<?php

use addons\webman\model\PlayerPointsRecord;

return [
    'title' => 'Player Points',
    'records_title' => 'Points Records',

    'fields' => [
        'id' => 'ID',
        'player_id' => 'Player ID',
        'player_name' => 'Player Account',
        'total_points' => 'Total Points',
        'available_points' => 'Available Points',
        'frozen_points' => 'Frozen Points',
        'used_points' => 'Used Points',
        'expired_points' => 'Expired Points',
        'type' => 'Type',
        'source' => 'Source',
        'points' => 'Points Change',
        'points_before' => 'Before',
        'points_after' => 'After',
        'remark' => 'Remark',
        'admin_name' => 'Operator',
        'admin_ip' => 'Operator IP',
        'created_at' => 'Created At',
    ],

    'type' => [
        PlayerPointsRecord::TYPE_BETTING_SUMMARY => 'Betting Summary',
        PlayerPointsRecord::TYPE_EXCHANGE => 'Exchange',
        PlayerPointsRecord::TYPE_EXPIRE => 'Expired',
        PlayerPointsRecord::TYPE_ADMIN_ADJUST => 'Admin Adjust',
        PlayerPointsRecord::TYPE_ACTIVITY => 'Activity Reward',
        PlayerPointsRecord::TYPE_REFUND => 'Refund',
    ],

    'action' => [
        'add_points' => 'Add Points',
        'deduct_points' => 'Deduct Points',
        'freeze_points' => 'Freeze Points',
        'unfreeze_points' => 'Unfreeze Points',
        'view_records' => 'Points Records',
    ],

    'form' => [
        'add_points_title' => 'Add Points',
        'deduct_points_title' => 'Deduct Points',
        'freeze_points_title' => 'Freeze Points',
        'unfreeze_points_title' => 'Unfreeze Points',
        'current_points' => 'Current Available Points',
        'current_frozen_points' => 'Current Frozen Points',
        'points_amount' => 'Points Amount',
        'remark_placeholder' => 'Please enter the reason',
    ],

    'message' => [
        'add_success' => 'Points added successfully',
        'add_failed' => 'Failed to add points',
        'deduct_success' => 'Points deducted successfully',
        'deduct_failed' => 'Failed to deduct points',
        'freeze_success' => 'Points frozen successfully',
        'freeze_failed' => 'Failed to freeze points',
        'unfreeze_success' => 'Points unfrozen successfully',
        'unfreeze_failed' => 'Failed to unfreeze points',
        'insufficient_points' => 'Insufficient available points',
        'insufficient_frozen_points' => 'Insufficient frozen points',
        'player_not_found' => 'Player not found',
        'points_not_found' => 'Points record not found',
        'invalid_points_amount' => 'Points amount must be greater than 0',
        'permission_denied' => 'Permission denied to access this player\'s points data',
    ],

    'statistics' => [
        'available_points' => 'Available Points',
        'frozen_points' => 'Frozen Points',
        'total_points' => 'Total Points',
        'player_account' => 'Player Account',
    ],

    'filter' => [
        'all_types' => 'All Types',
        'select_type' => 'Select Type',
    ],
];
