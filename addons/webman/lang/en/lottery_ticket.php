<?php

return [
    'title' => 'Lottery Ticket Management',

    // Menu
    'menu' => [
        'main' => 'Lottery Ticket Management',
        'dashboard' => 'Active Campaigns',
        'history' => 'Campaign History',
        'records' => 'Winning Records',
    ],

    // Fields
    'fields' => [
        'id' => 'ID',
        'activity_name' => 'Campaign Name',
        'description' => 'Description',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'status' => 'Status',
        'total_tickets' => 'Total Tickets',
        'used_tickets' => 'Used Tickets',
        'usage_rate' => 'Usage Rate',
        'prize_config' => 'Prize Configuration',
        'created_at' => 'Created At',

        // Winning Records
        'player_name' => 'Player Name',
        'player_phone' => 'Player Phone',
        'ticket_no' => 'Ticket Number',
        'prize_type' => 'Prize Type',
        'prize_name' => 'Prize Name',
        'prize_amount' => 'Prize Amount',
        'record_status' => 'Grant Status',
        'remark' => 'Remark',
        'draw_time' => 'Draw Time',
    ],

    // Activity Status
    'status' => [
        'not_started' => 'Not Started',
        'ongoing' => 'Ongoing',
        'ended' => 'Ended',
        'closed' => 'Closed',
        'unknown' => 'Unknown',
    ],

    // Ticket Status
    'ticket_status' => [
        'unused' => 'Unused',
        'used' => 'Used',
        'expired' => 'Expired',
        'unknown' => 'Unknown',
    ],

    // Source
    'source' => [
        'recharge' => 'Recharge Bonus',
        'activity' => 'Campaign Bonus',
        'manual' => 'Manual Grant',
        'unknown' => 'Unknown',
    ],

    // Grant Status
    'record_status' => [
        'pending' => 'Pending',
        'granted' => 'Granted',
        'failed' => 'Failed',
        'unknown' => 'Unknown',
    ],

    // Prize Type
    'prize_type' => [
        'cash' => 'Cash',
        'bonus' => 'Bonus',
        'item' => 'Physical Item',
        'points' => 'Points',
        'empty' => 'No Prize',
        'unknown' => 'Unknown',
    ],

    // Prize Level Names
    'level_name' => [
        'special' => 'Grand Prize',
        'first' => '1st Prize',
        'second' => '2nd Prize',
        'third' => '3rd Prize',
        'fourth' => '4th Prize',
        'fifth' => '5th Prize',
        'sixth' => '6th Prize',
        'seventh' => '7th Prize',
        'eighth' => '8th Prize',
        'ninth' => '9th Prize',
    ],

    // Prize Level Fields
    'prize_level_fields' => [
        'level_rank' => 'Rank',
        'level_name' => 'Level Name',
        'prize_type' => 'Prize Type',
        'prize_amount' => 'Amount',
        'prize_item_name' => 'Item Name',
        'prize_item_image' => 'Item Image',
        'prize_count' => 'Quantity',
        'win_probability' => 'Win Rate (%)',
        'description' => 'Description',
    ],

    // Actions
    'action' => [
        'create' => 'Create Campaign',
        'edit' => 'Edit Campaign',
        'view' => 'View Details',
        'close' => 'Close Campaign',
        'export' => 'Export Records',
        'grant' => 'Grant Prize',
    ],

    // Statistics
    'stats' => [
        'total_activities' => 'Total Campaigns',
        'ongoing_activities' => 'Active Campaigns',
        'total_draws' => 'Total Draws',
        'total_winners' => 'Total Winners',
        'total_prize_amount' => 'Total Prize Amount',
    ],

    // Messages
    'message' => [
        'create_success' => 'Campaign created successfully',
        'update_success' => 'Campaign updated successfully',
        'close_success' => 'Campaign closed successfully',
        'close_confirm' => 'Are you sure you want to close this campaign?',
        'activity_not_found' => 'Campaign not found',
        'activity_closed' => 'Campaign has been closed',
        'time_conflict' => 'Time conflict',
        'prize_level_saved' => 'Prize level saved successfully',
        'prize_level_deleted' => 'Prize level deleted successfully',
    ],

    // Error Messages
    'error' => [
        'too_many_levels' => 'Maximum {max} prize levels allowed',
        'no_prize_levels' => 'Please configure at least one prize level',
        'no_prizes' => 'Prize quantity cannot be zero',
        'probability_exceed' => 'Total win rate cannot exceed 100%, current: {total}%',
        'level_rank_exists' => 'This rank already exists',
        'invalid_prize_type' => 'Invalid prize type',
    ],
];
