<?php

return [
    'title' => 'Store Management',
    'offline_only' => 'This feature is only available for offline channels',
    'create_success' => 'Store {name} created successfully! Login account: {username}, {agent_label}: {agent_name}',
    'create_failed' => 'Failed to create store: {error}',
    'welcome_message' => 'Welcome to the Store Admin System!',

    // Column names
    'fields' => [
        'id' => 'ID',
        'name' => 'Store Name',
        'username' => 'Login Account',
        'phone' => 'Contact Phone',
        'department_name' => 'Department Name',
        'agent_commission' => 'Agent Commission',
        'channel_commission' => 'Channel Commission',
        'wash_point_config' => 'Wash Point Config',
        'status' => 'Status',
        'created_at' => 'Created At',
        'parent_agent' => 'Parent Agent',
        'password' => 'Login Password',
        'password_confirmation' => 'Confirm Password',
        'avatar' => 'Upload Avatar',
    ],

    // Status
    'status' => [
        'normal' => 'Normal',
        'disabled' => 'Disabled',
        'not_set' => 'Not Set',
    ],

    // Form
    'form' => [
        'create_title' => 'Create Store',
        'create_hint' => 'After creating a store, the store can log in to the store admin',
        'section_account' => 'Account Information',
        'section_parent_agent' => 'Parent Agent',
        'section_avatar' => 'Avatar Configuration',
        'section_password' => 'Password Configuration',
        'select_parent_agent' => 'Select Parent Agent',
    ],

    // Placeholders
    'placeholder' => [
        'status' => 'Status',
        'username' => 'Login Account',
        'name' => 'Store Name',
        'phone' => 'Contact Phone',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
    ],

    // Filters
    'filter' => [
        'select_store' => 'Select Store',
    ],

    // Others
    'all' => 'All',

    // Help text
    'help' => [
        'phone' => 'Optional, for contact',
        'username' => 'Required, for logging into store admin',
        'name' => 'Display name of the store',
        'parent_agent' => 'Select the parent agent for this store',
        'avatar' => 'Supports jpg, png formats, recommended size 200x200',
        'password' => 'Store admin login password',
    ],

    // Validation rules
    'validation' => [
        'password_min' => 'Password must be at least 6 characters',
    ],

    // Error messages
    'error' => [
        'offline_only' => 'This feature is only available for offline channels',
        'avatar_required' => 'Please upload an avatar',
        'password_mismatch' => 'The two passwords do not match',
        'parent_agent_not_found' => 'Parent agent not found',
        'username_exists' => 'Login account {username} already exists',
    ],

    // Auto shift configuration
    'auto_shift' => [
        'morning_title' => 'Morning Shift',
        'afternoon_title' => 'Afternoon Shift',
        'night_title' => 'Night Shift',
        'morning_desc' => 'Morning shift auto handover (08:00-16:00)',
        'afternoon_desc' => 'Afternoon shift auto handover (16:00-24:00)',
        'night_desc' => 'Night shift auto handover (00:00-08:00)',
    ],

    // Action Menu
    'actions' => [
        'limit_group' => 'Limit Group Config',
        'auto_shift_config' => 'Auto Shift Config',
        'system_setting' => 'System Settings',
        'open_score_setting' => 'Open Score Config',
        'wash_point_setting' => 'Wash Point Config',
        'activity_config' => 'Activity Config',
    ],

    // Activity Configuration
    'activity_config' => [
        'title' => 'Activity Configuration',
        'list_title' => 'Activity Config List',
        'create_title' => 'Create Activity Config',
        'edit_title' => 'Edit Activity Config',
        'no_config' => 'No activity configuration',

        // Fields
        'fields' => [
            'id' => 'ID',
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],

        // Status
        'status' => [
            '0' => 'Disabled',
            '1' => 'Enabled',
        ],

        // Section titles
        'section' => [
            'basic' => 'Basic Information',
            'experience' => 'Experience Voucher Settings',
            'welfare' => 'Welfare Voucher Settings',
            'order_prefix' => 'Order Prefix Settings',
        ],

        // Field labels
        'label' => [
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'activity_end_time' => 'Distribution Deadline',
            'experience_enabled' => 'Enable Experience',
            'experience_register_after' => 'New User Threshold',
            'experience_daily_limit' => 'Daily Limit',
            'experience_total_limit' => 'Total Limit',
            'experience_score' => 'Claim Score',
            'experience_expire_hours' => 'Expiration(Hrs)',
            'welfare_enabled' => 'Enable Welfare',
            'welfare_daily_limit' => 'Daily Limit',
            'welfare_rules' => 'Welfare Tier Rules',
            'welfare_expire_hours' => 'Expiration(Hrs)',
            'order_prefix_experience' => 'Experience Prefix',
            'order_prefix_welfare' => 'Welfare Prefix',
            'order_prefix_recharge' => 'Recharge Prefix',
            'order_prefix_withdraw' => 'Withdraw Prefix',
        ],

        // Help text
        'help' => [
            'start_time' => 'Activity start time, leave empty for immediate start',
            'end_time' => 'Activity end time, leave empty for no limit',
            'activity_end_time' => 'Voucher distribution pauses after this time',
            'experience_register_after' => 'Users registered after this time are considered new',
            'experience_daily_limit' => 'Number of times a user can claim per day',
            'experience_total_limit' => 'Total number of times a user can claim',
            'experience_score' => 'Points earned per claim',
            'experience_expire_hours' => 'Voucher expires after this many hours',
            'welfare_daily_limit' => '0 means no limit',
            'welfare_rules' => 'Configure bet amount thresholds for welfare vouchers, claim when threshold is met',
            'welfare_expire_hours' => 'Voucher expires after this many hours',
            'order_prefix_experience' => 'Experience voucher order number prefix',
            'order_prefix_welfare' => 'Welfare voucher order number prefix',
            'order_prefix_recharge' => 'Recharge order number prefix',
            'order_prefix_withdraw' => 'Withdraw order number prefix',
        ],

        // Rules related
        'rules' => [
            'bet_amount' => 'Bet Amount Threshold',
            'score' => 'Claim Score',
            'add_rule' => 'Add Tier',
            'remove_rule' => 'Remove',
            'day_type' => 'Calculation Type',
            'yesterday' => 'Yesterday Bet Amount',
            'today' => 'Today Bet Amount',
        ],

        // Validation
        'validation' => [
            'start_time_required' => 'Please enter activity start time',
            'end_time_after_start' => 'End time must be after start time',
        ],

        // Error messages
        'error' => [
            'already_exists' => 'This store already has an active activity configuration, please disable it first',
        ],

        // Messages
        'message' => [
            'create_success' => 'Activity configuration created successfully',
            'update_success' => 'Activity configuration updated successfully',
            'delete_success' => 'Activity configuration deleted successfully',
            'delete_confirm' => 'Are you sure you want to delete this activity configuration?',
        ],
    ],
];
