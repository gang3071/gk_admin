<?php

return [
    // Common error messages
    'data_not_found' => 'Data not found',
    'player_not_exist' => 'Player does not exist',
    'game_not_exist' => 'Game does not exist',
    'please_select_games' => 'Please select games to authorize',
    'games_not_found' => 'Selected games not found',
    'offline_channel_only' => 'This feature is only available for offline channels',
    'offline_channel_feature_only' => 'This feature is only applicable to offline channels',
    'channel_no_game_platform' => 'This channel has not enabled any electronic game platforms',
    'games_not_in_channel_scope' => 'Selected games are not within the channel scope',
    'game_not_in_channel_scope' => 'This game is not within the channel scope',
    'game_platform_not_in_channel_scope' => 'Selected game platform is not within the channel scope',
    'invalid_operation' => 'Invalid operation',
    'operation_failed' => 'Operation failed',
    'save_failed' => 'Save failed',
    'player_id_required' => 'Player ID is required',
    'invalid_parameter' => 'Invalid parameter',
    'load_failed' => 'Load failed',
    'invalid_game_points' => 'Converted game points are invalid',
    'system_error' => 'System error',
    'player_already_exists' => 'Player already exists',
    'recommended_player_not_exist' => 'Recommended player does not exist',
    'machine_in_test_mode' => 'This machine is being used as a new version industrial control test machine',
    'video_host_request_failed' => 'Failed to request video host',
    'get_stream_info_failed' => 'Failed to get stream information',

    // Common success messages
    'settlement_success' => 'Settlement successful',
    'operation_success' => 'Operation successful',
    'authentication_passed' => 'Authentication passed',
    'batch_generation_failed' => 'Batch generation failed: {message}',
    'create_agent_failed' => 'Failed to create agent: {message}',
    'create_store_failed' => 'Failed to create store: {message}',

    // Common labels
    'administrator' => 'Administrator',
    'player' => 'Player',
    'total' => 'Total',

    // Common confirmation messages
    'confirm_save' => 'Confirm to save?',

    // Login related
    'please_enter_credentials' => 'Please enter account and password',
    'account_not_exist' => 'Account does not exist',
    'password_incorrect' => 'Incorrect password',
    'login_success' => 'Login successful',
    'implement_login_logic' => 'Please implement actual login logic in CustomLoginController',

    // Agent/Store related
    'agent_commission_range_error' => 'Agent commission ratio must be between 0-100',
    'channel_commission_range_error' => 'Channel commission ratio must be between 0-100',
    'please_upload_avatar' => 'Please upload avatar',
    'password_mismatch' => 'Passwords do not match',
    'username_exists' => 'Login account {username} already exists',
    'agent_create_success' => 'Agent {name} created successfully! Login account: {username}',
    'parent_agent_not_exist' => 'Parent agent does not exist',
    'please_select_settlement_targets' => 'Please select agents/stores to settle',
    'settlement_end_time_error' => 'Settlement end time cannot exceed current time',
    'store_ratio_less_than_agent' => 'Store turnover ratio cannot be less than agent ({name}) turnover ratio, {ratio}%',
    'agent_ratio_greater_than_store' => 'Agent turnover ratio cannot be greater than store ({name}) turnover ratio, {ratio}%',

    // Game permissions related
    'game_permission_set_success' => 'Successfully set {count} game permissions',
    'electronic_game_set_success' => 'Successfully set {count} electronic games',

    // Shift handover related
    'shift_handover_failed_no_department' => 'Shift handover failed: Administrator not associated with department',
    'shift_handover_failed_no_currency' => 'Shift handover failed: System currency configuration missing',

    // Lottery pool related
    'pool_ratio_must_greater_than_zero' => 'Pool ratio must be greater than 0',
    'pool_ratio_cannot_exceed_100' => 'Pool ratio cannot exceed 100%',
    'win_probability_must_greater_than_zero' => 'Win probability must be greater than 0',
    'win_probability_cannot_exceed_1' => 'Win probability cannot exceed 1 (100%)',
    'max_pool_amount_must_greater_than_zero' => 'Maximum pool amount must be greater than 0',
    'minimum_amount_must_greater_than_zero' => 'When minimum amount is enabled, minimum amount must be greater than 0',
    'minimum_amount_cannot_exceed_max' => 'Minimum amount cannot exceed maximum pool amount',
    'distribution_ratio_range_error' => 'Distribution ratio must be between 0-100',

    // Machine related
    'please_select_reset_hosts' => 'Please select video hosts to reset',
    'please_fill_zhcn_name' => 'Please fill in Simplified Chinese name',
    'please_upload_zhcn_image' => 'Please upload Simplified Chinese image',

    // Role related
    'builtin_role_cannot_modify_name' => 'Built-in role name cannot be modified',
    'builtin_role_cannot_modify_type' => 'Built-in role type cannot be modified',
    'role_not_exist' => 'Role does not exist',
    'builtin_role_cannot_delete' => 'This is a built-in role and cannot be deleted',

    // Batch generation related
    'batch_generate_success' => 'Successfully generated {count} player accounts',
    'batch_generate_partial_success' => 'Successfully generated {success} player accounts, failed {failed}: {accounts}',
    'account_exists' => 'already exists',

    // Help text
    'help' => [
        'account_format' => 'Account format: prefix + number, e.g.: P0001',
        'number_auto_padding' => 'Number will be automatically padded to 4 digits, e.g.: 1 → 0001',
        'nickname_format' => 'Nickname format: prefix + number, e.g.: Player0001',
        'number_auto_padding_simple' => 'Number will be automatically padded to 4 digits',
        'all_players_use_this_avatar' => 'All generated players will use this avatar',
        'avatar_format_recommendation' => 'Supports jpg, png format, recommended size 200x200, all generated players will use this avatar',
        'all_accounts_use_this_password' => 'All generated accounts will use this password',
        'avatar_format' => 'Supports jpg, png format, recommended size 200x200',
        'agent_login_password' => 'Agent backend login password, at least 6 characters',
        'store_login_password' => 'Store backend login password, at least 6 characters',
        'agent_commission_help' => 'The ratio that the agent extracts from store revenue, range 0-100',
        'channel_commission_help' => 'The ratio that the channel extracts from store revenue, range 0-100',
    ],

    // Tips
    'tips' => [
        'offline_channel_only_notice' => '><font size=3 color="#ff4d4f">This feature is only available for offline channels</font>',
        'batch_generate_bind_notice' => '><font size=2 color="#1890ff">Batch generated accounts will be automatically bound to the specified store</font>',
    ],

    // Others
    'divider' => [
        'commission_settings' => 'Commission Settings',
    ],

    // Default text
    'default' => [
        'admin' => 'Administrator',
        'no_agent' => 'No Agent',
        'not_filled' => 'Not filled',
        'welcome_agent_system' => 'Welcome to Agent Backend System!',
        'welcome_store_system' => 'Welcome to Store Backend System!',
    ],

    // Date Filter
    'date_filter' => [
        'all' => 'All',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This Week',
        'last_week' => 'Last Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
    ],

    // Auto Shift
    'auto_shift' => [
        'enabled' => 'Auto shift enabled',
        'manual_shift_success' => 'Manual shift handover success',
        'manual_shift_failed' => 'Manual shift handover failed',
    ],

    // Shift
    'shift' => [
        'morning' => 'Morning Shift',
        'morning_desc' => 'Morning shift auto handover (08:00-16:00)',
        'afternoon' => 'Afternoon Shift',
        'afternoon_desc' => 'Afternoon shift auto handover (16:00-24:00)',
        'night' => 'Night Shift',
        'night_desc' => 'Night shift auto handover (00:00-08:00)',
    ],
];
