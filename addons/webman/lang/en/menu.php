<?php

use addons\webman\model\AdminDepartment;

return [
    'add' => 'Add menu',
    'title' => 'System Menu Management',
    'fields' => [
        'top' => 'Top menu',
        'pid' => 'Previous menu',
        'name' => 'Menu name',
        'url' => 'Menu link',
        'icon' => 'menu icon',
        'sort' => 'sort',
        'status' => 'status',
        'open' => 'Menu expansion',
        'super_status' => 'Super administrator status',
        'type' => 'Menu type',
    ],
    'options' => [
        'admin_visible' => [
            [1 => 'display'],
            [0 => 'Hide']
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => 'Terminal menu',
        AdminDepartment::TYPE_CHANNEL => 'Channel Menu',
        AdminDepartment::TYPE_AGENT => 'Agent Menu',
        AdminDepartment::TYPE_STORE => 'Store Menu',
    ],
    'titles' => [
        'home' => 'Home',
        'system' => 'system',
        'system_manage' => 'System management',
        'config_manage' => 'Configuration management',
        'attachment_manage' => 'Attachment management',
        'permissions_manage' => 'Permission management',
        'admin' => 'User management',
        'role_manage' => 'Role management',
        'menu_manage' => 'Menu management',
        'plug_manage' => 'Plug-in management',
        'department_manage' => 'Department management',
        'post_manage' => 'Post management',
        /** General backend */
        'admin_manage' => 'General backend',
        'data_center' => 'data center',
        //User management
        'user_manage' => 'Player management',
        'user_manage_list' => 'Player list',
        'accounting_change_records' => 'Accounting change records',
        //game management
        'game_manager' => 'Game management',
        'game_category' => 'Game category',
        'machine_category' => 'Machine category',
        'machine_list' => 'Machine list',
        'machine_information' => 'Machine information',
        //financial data
        'financial_data' => 'Financial data',
        'transfer_point_record' => 'Transfer point record',
        'recharge_record' => 'Recharge record',
        'withdrawal_records' => 'Withdrawal records',
        //Report Center
        'report_center' => 'Report Center',
        'machine_report' => 'Machine report',
        'up_and_down_report' => 'Up and down report',
        //Client management
        'client_manager' => 'Client management',
        'rotation_chart_manager' => 'rotation chart management',
        'announcement_manager' => 'Announcement Management',
        'system_settings' => 'System settings',
        //channel management
        'channel_manager' => 'Channel Management',
        'channel_list' => 'channel list',
        'currency_manager' => 'currency management',
        /** Channel backend */
        'channel_manage' => 'channel backend',
        'channel_data_center' => 'data center',
        //Coin dealer management
        'channel_coin_merchant_manage' => 'Coin Merchant Management',
        'channel_coin_merchant_list' => 'Coin merchant list',
        'channel_coin_merchant_recharge_records' => 'Coin merchant recharge records',
        'channel_coin_merchant_transaction_records' => 'Coin merchant transaction records',
        //Player management
        'channel_player_manage' => 'Player management',
        'channel_player_list' => 'Player list',
        'channel_player_accounting_change_records' => 'Accounting change records',
        //machine management
        'channel_machine_manage' => 'Machine management',
        'channel_machine_information' => 'Machine information',
        'channel_machine_report' => 'Machine report',
        'channel_up_and_down_report' => 'Up and down report',
        'machine_strategy_list' => 'Machine Strategy',
        'machine_producer' => 'Manufacturer list',
        //Front-end configuration
        'channel_client_manager' => 'Client management',
        'channel_rotation_chart_manager' => 'Carousel chart management',
        'channel_marquee_manager' => 'System configuration',
        'channel_announcement_manager' => 'Announcement Management',
        //Financial management
        'channel_financial_manager' => 'Financial Management',
        'channel_recharge_review' => 'Recharge review',
        'channel_withdrawal_review' => 'Withdrawal review',
        'channel_withdrawal_and_payment' => 'Withdrawal',
        'channel_recharge_record' => 'Recharge Record',
        'channel_talk_recharge_records' => 'QTalk recharge records',
        'channel_withdrawal_records' => 'Withdrawal records',
        'channel_talk_withdrawal_records' => 'QTalk withdrawal records',
        'channel_recharge_channel_configuration' => 'Recharge channel configuration',
        'channel_financial_operation_records' => 'Financial operation records',
        //Permission management
        'channel_auth_manager' => 'Permission management',
        'channel_admin_user_manager' => 'User management',
        'channel_post_manager' => 'Post management',
        /** TODO start translation */
        //Activity management
        'activity' => 'activity management',
        'activity_index' => 'activity list',
        'player_activity_record' => 'Activity participation record',
        'player_activity_record_examine' => 'Reward collection review',
        'player_activity_record_receive' => 'Activity receipt record',
        //Promotion management
        'promotion_management' => 'Promotion management',
        'promoter_list' => 'Promoter list',
        'profit_record' => 'Profit report',
        'profit_settlement_record' => 'Profit settlement record',
        //Bonus management
        'lottery_management' => 'Lottery management',
        'lottery_list' => 'Lottery list',
        'lottery_audit_list' => 'Lottery audit',
        'lottery_records' => 'Lottery collection record',
        //Coin dealer management
        'coin_management' => 'Coin Business Management',
        'coin_list' => 'Coin dealer list',
        'coin_recharge_record' => 'Coin dealer recharge record',
        //Log center
        'log_center' => 'Log center',
        'machine_keeping_log' => 'Machine keeping log',
        'player_edit_log' => 'Player profile modification log',
        'machine_operation_log' => 'Machine operation log',
        'machine_edit_log' => 'Machine change log',
        'lottery_add_log' => 'Lottery pool accumulation log',
        'player_money_edit_log' => 'Wallet operation log',
        'computer_game' => 'computer game',
        'game_platforms' => 'List of gaming platforms',
        'game_transfer_record' => 'Game transfer records',
        'play_game_record' => 'Game Records',
        'game_platform_profit' => 'Game platform profit sharing',
        //Store Configuration
        'store_setting_manage' => 'Store System Configuration',
        //Agent Management
        'agent_management' => 'Agent Management',
        //Store Management
        'store_management' => 'Store Management',
        //Agent Lottery Management
        'agent_lottery_management' => 'Lottery Management',
        //Store Lottery Management
        'store_lottery_management' => 'Lottery Management',
        //Deposit Bonus Management (Admin)
        'deposit_bonus_manage' => 'Deposit Bonus Management',
        'deposit_bonus_activity' => 'Activity Management',
        'deposit_bonus_qrcode' => 'Order Management',
        'deposit_bonus_statistics' => 'Statistics Report',
        'deposit_bonus_bet_detail' => 'Betting Details',
        //Channel Deposit Bonus Management
        'channel_deposit_bonus_manage' => 'Deposit Bonus Management',
        'channel_deposit_bonus_activity' => 'Activity Management',
        'channel_deposit_bonus_order' => 'Order Management',
        'channel_deposit_bonus_statistics' => 'Statistics Report',
        'channel_deposit_bonus_bet_detail' => 'Betting Details',
        //Agent/Store Game Log
        'agent_game_log' => 'Game Log Report',
        'agent_game_log_list' => 'Report List',
        'store_game_log' => 'Game Log Report',
        'store_game_log_list' => 'Report List',
        //Agent Financial Management
        'agent_financial_management' => 'Financial Management',
        'agent_recharge_record' => 'Recharge Records',
        'agent_withdraw_record' => 'Withdraw Records',
        //Agent Game Management
        'agent_game_management' => 'Game Management',
        'agent_game_record' => 'Game Records',
        //Agent Deposit Bonus Management
        'agent_deposit_bonus_manage' => 'Deposit Bonus Management',
        'agent_deposit_bonus_activity' => 'Activity Management',
        'agent_deposit_bonus_qrcode' => 'Order Management',
        'agent_deposit_bonus_order' => 'Order Management',
        'agent_deposit_bonus_statistics' => 'Statistics Report',
        'agent_deposit_bonus_bet_detail' => 'Betting Details',
        'agent_deposit_bonus_task' => 'Wagering Tasks',
        //Store Device Management
        'store_player' => 'Device Management',
        'store_player_list' => 'Device List',
        //Store Deposit Bonus Management
        'store_deposit_bonus_manage' => 'Deposit Bonus Management',
        'store_deposit_bonus_activity' => 'Activity Management',
        'store_deposit_bonus_order' => 'Order Management',
        'store_deposit_bonus_statistics' => 'Statistics Report',
        'store_deposit_bonus_bet_detail' => 'Betting Details',
        'store_deposit_bonus_task' => 'Wagering Tasks',
        //Store Financial Management
        'store_financial_manage' => 'Credit Management Center',
        'store_recharge_record' => 'Credit Addition Records',
        'store_withdraw_record' => 'Credit Withdrawal Records',
        //Store Game Management
        'store_game_manage' => 'Game Management',
        'store_game_record' => 'Game Records',
        'store_deposit_bonus_qrcode' => 'Order Management',
        /** TODO end translation */
    ]
];
