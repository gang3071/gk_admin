<?php

return [
    // Titles
    'title' => 'Store/Device Management',
    'store_list' => 'Store List',
    'store_management' => 'Store Management',
    'device_list' => 'Device List',
    'delivery_record' => 'Transaction Record',
    'shift_handover_record' => 'Shift Handover Report',

    // Fields
    'fields' => [
        'store_name' => 'Store Name',
        'login_account' => 'Login Account',
        'contact_phone' => 'Contact Phone',
        'department_name' => 'Department Name',
        'agent_commission' => 'Agent Commission',
        'channel_commission' => 'Channel Commission',
        'status' => 'Status',
        'created_at' => 'Created At',
        'recharge_amount' => 'Total Credited',
        'withdraw_amount' => 'Total Cashed Out',
        'machine_put_point' => 'Cash In',
        'lottery_amount' => 'Lottery',
        'subtotal' => 'Subtotal',
        'game_platform' => 'Game Platform',
        'game_name' => 'Game Name',
        'game_category' => 'Game Category',
        'is_hot' => 'Hot',
        'is_new' => 'New Game',
        'sort' => 'Sort',
    ],

    // Old field keys (for compatibility)
    'store_name' => 'Store Name',
    'bind_player' => 'Bound Player',
    'account' => 'Account',
    'recommend_id' => 'Parent Store',
    'select_store' => 'Parent Store',
    'last_settlement_time' => 'Last Settlement Time',

    // Current Data
    'current_data' => 'Current Data',
    'present_in' => 'Transfer In (Credit)',
    'present_out' => 'Transfer Out (Cash Out)',
    'machine_put' => 'Cash In (Recharge)',
    'total_revenue' => 'Total (Revenue)',
    'store_profit_rate' => 'Store Profit Rate',
    'store_profit_amount' => 'Store Profit',
    'company_profit_rate' => 'Company Profit Rate',
    'company_profit_amount' => 'Company Profit',

    // Total Data
    'total_data' => 'Total Data',

    // Credit Related
    'open_score' => 'Credit',
    'recharge_amount' => 'Recharge Amount',
    'quick_amount' => 'Quick Amount',
    'custom_amount' => 'Custom Amount',
    'preset_amount' => 'Preset Amount',
    'default_amount' => 'Default Amount',
    'device_balance' => 'Device Balance',
    'exchange_rate' => 'Exchange Rate',
    'game_points' => 'Game Points',
    'reference' => 'Reference',
    'points' => ' pts',

    // Tips
    'tip_device_balance' => 'Tip: Device Balance {balance} {currency} = {points} Game Points',
    'tip_exchange_rate' => '1 {currency} = {ratio} Game Points, please enter currency amount, the system will automatically convert to game points',
    'tip_select_preset' => 'Select preset amount or custom amount (Currency → Game Points, 1{currency} = {ratio}pts)',
    'tip_reference' => 'Device Balance: {currency}{balance}, Exchange Rate: 1{currency} = {ratio} Game Points. {reference}',

    // Error Messages
    'error_amount_required' => 'Please enter amount',
    'error_amount_invalid' => 'Please enter a valid amount',
    'error_store_not_found' => 'Store account does not exist',
    'error_currency_not_found' => 'Currency information not found',
    'error_ratio_invalid' => 'Exchange rate must be greater than 0',
    'error_device_not_found' => 'Device does not exist',
    'error_insufficient_balance' => 'Insufficient device balance',
    'error_player_not_found' => 'Player not found',
    'error_offline_channel_only' => 'This feature is only available for offline channels',
    'error_no_game_platform' => 'This channel has not enabled any gaming platforms',

    // Status Options
    'status_options' => [
        'normal' => 'Normal',
        'disabled' => 'Disabled',
    ],

    // Tags
    'tag' => [
        'not_set' => 'Not Set',
        'disabled' => 'Disabled',
        'normal' => 'Normal',
        'hot' => 'Hot',
        'new' => 'New',
        'unknown_platform' => 'Unknown Platform',
    ],

    // Placeholders
    'placeholder' => [
        'status' => 'Status',
        'login_account' => 'Login Account',
        'store_name' => 'Store Name',
        'contact_phone' => 'Contact Phone',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'game_platform' => 'Game Platform',
        'is_hot' => 'Is Hot',
        'is_new' => 'Is New',
    ],

    // Game Options
    'game_options' => [
        'hot_games' => 'Hot Games',
        'normal_games' => 'Normal Games',
        'new_games' => 'New Games',
        'old_games' => 'Old Games',
    ],

    // Buttons
    'button' => [
        'save_selected_games' => 'Save Selected Games',
    ],

    // Confirms
    'confirm' => [
        'save_games' => 'Confirm save?',
    ],

    // Success Messages
    'success_open_score' => 'Credit successful',
    'success_save' => 'Save successful',

    // Error Messages - Additional
    'error_save_failed' => 'Save failed',
    'error_operation_failed' => 'Operation failed',

    // Credit Remark
    'remark_store_open_score' => 'Store Backend Credit',

    // JavaScript Text
    'js' => [
        'conversion_preview' => 'Conversion Preview:',
        'please_enter_amount' => 'Please enter amount',
        'points_unit' => ' pts',
        'exchange_rate_label' => 'Exchange Rate:',
    ],

    // Game Related
    'game' => [
        'game_id' => 'Game ID',
        'select_all_platform' => '【Select All Games in This Platform】',
        'game_list_title' => '{platform} - Game List',
        'tip_select_games' => 'Tip: Select the electronic games that this player can use. Unselected games will not be displayed in the client.',
    ],

    // Game Permission Management
    'game_permission' => [
        'title' => 'Player Game Permission Management - {name}',
    ],

    // Others
    'remark' => 'Remark',
    'select_placeholder' => 'Please select',
    'total' => 'Total',
    'player_type_store' => 'Store',
    'player_type_device' => 'Device',
    'player' => 'Player',
    'admin' => 'Admin',
    'type' => 'Type',
    'all' => 'All',
    'total_profit_amount' => 'Total Revenue',
    'start_time' => 'Start Time',
    'end_time' => 'End Time',
    'created_at' => 'Created At',
];