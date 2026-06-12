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
        'name' => 'Campaign Name',
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
        'drawing' => 'Drawing',
        'drawn' => 'Drawn (Pending Distribution)', // ⭐ New
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
        'claimed' => 'Distributed',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'processing' => 'Processing', // ⭐ New
        'failed' => 'Failed',
        'granted' => 'Distributed', // Legacy
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
        'view_detail' => 'View Details',
        'close' => 'Close Campaign',
        'export' => 'Export Records',
        'grant' => 'Grant Prize',
        'distribute' => 'Distribute',
        'batch_distribute' => 'Batch Distribute',
        'batch_distribute_selected' => 'Distribute Selected',
    ],

    // Statistics
    'stats' => [
        'total_activities' => 'Total Campaigns',
        'ongoing_activities' => 'Active Campaigns',
        'total_draws' => 'Total Draws',
        'total_winners' => 'Total Winners',
        'total_prize_amount' => 'Total Prize Amount',
        'pending_count' => 'Pending Records',          // ⭐ New
        'pending_amount' => 'Pending Amount',          // ⭐ New
        'claimed_count' => 'Distributed Records',      // ⭐ New
        'claimed_amount' => 'Distributed Amount',      // ⭐ New
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
        'distribute_success' => 'Distributed successfully',
        'distribute_failed' => 'Distribution failed',
        'batch_complete' => 'Batch distribution complete: {success} succeeded, {fail} failed',
        'batch_distribute_selected' => 'Batch distribute selected records',
        'export_in_development' => 'Export feature under development',
        'admin_manual_update' => 'Manually updated by admin',
    ],

    // Error Messages
    'error' => [
        'record_not_found' => 'Record not found',
        // Input validation
        'invalid_record_id' => 'Invalid parameter: Record ID is invalid',
        'invalid_activity_id' => 'Invalid parameter: Activity ID is invalid',
        'invalid_record_ids' => 'Invalid parameter: Record IDs must be an array',
        'invalid_record_id_value' => 'Invalid parameter: Record ID contains illegal value',
        'note_too_long' => 'Distribution note cannot exceed 255 characters',
        'no_selection' => 'Please specify activity ID or select records',
        'no_pending_records' => 'No pending records to distribute',
        // Business logic validation
        'invalid_status' => 'Invalid record status, can only distribute pending records',
        'status_changed' => 'Status has changed',
        'empty_prize' => 'Empty prize does not need distribution',
        'invalid_amount' => 'Prize amount must be greater than 0',
        'player_not_found' => 'Player not found',
        'player_disabled' => 'Player is disabled, cannot distribute reward',
        'activity_not_found' => 'Activity not found',
        'activity_invalid_status' => 'Invalid activity status, can only distribute for drawn activities',
        'amount_exceeded' => 'Distribution amount exceeds total prize amount',
        'ticket_not_found_or_used' => 'Ticket {ticket_no} not found or already used',
        'prize_level_not_found_for_ticket' => 'Prize level not found for ticket {ticket_no}',
        'bet_progress_not_found' => 'Betting progress record not found',
        // Other
        'too_many_levels' => 'Maximum {max} prize levels allowed',
        'no_prize_levels' => 'Please configure at least one prize level',
        'no_prizes' => 'Prize quantity cannot be zero',
        'probability_exceed' => 'Total win rate cannot exceed 100%, current: {total}%',
        'level_rank_exists' => 'This rank already exists',
        'invalid_prize_type' => 'Invalid prize type',
    ],

    // Detail View Labels
    'view' => [
        'detail_title' => 'Prize Record Details',
        'basic_info' => 'Basic Information',
        'prize_info' => 'Prize Information',
        'distribution_info' => 'Distribution Information',
        'activity_name' => 'Activity Name',
        'ticket_no' => 'Ticket No.',
        'player_name' => 'Player',
        'player_phone' => 'Phone',
        'prize_name' => 'Prize Name',
        'prize_type' => 'Prize Type',
        'prize_amount' => 'Prize Amount',
        'status' => 'Status',
        'distributed_at' => 'Distributed At',
        'distributed_by' => 'Distributed By',
        'distribution_note' => 'Distribution Note',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    // Confirm Dialogs
    'confirm' => [
        'distribute' => 'Confirm distribution of this prize to player account?',
    ],

    // Modal Titles
    'modal' => [
        'record_win_title' => 'Record Winning Entry',
        'live_url_title' => 'Add Live Stream URL',
        'live_url_prompt' => 'Please enter the live stream URL:',
        'live_url_required' => 'Please enter the live stream URL',
        'batch_distribute_title' => 'Batch Distribute Prizes',
    ],

    // Form Labels
    'form' => [
        'select_activity' => 'Select Activity',
        'select_activity_help' => 'Only showing drawn activities pending distribution',
        'distribution_note' => 'Distribution Note',
        'distribution_note_placeholder' => 'Please enter distribution note (optional)',
    ],
];
