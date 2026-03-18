<?php

use addons\webman\model\ChannelFinancialRecord;

return [
    'title' => 'Financial Operation Record',
    'content' => 'Serial number {setting_id}',
    'fields' => [
        'id' => 'ID',
        'department_id' => 'Department/channel ID',
        'player_id' => 'player ID',
        'player' => 'Player information',
        'target' => 'data table',
        'target_id' => 'Data table ID',
        'action' => 'operation behavior',
        'tradeno' => 'Operate order',
        'user_id' => 'Operate order',
        'user_name' => 'Operator',
        'created_at' => 'Operation time',
    ],
    'action' => [
        ChannelFinancialRecord::ACTION_RECHARGE_PASS => 'Recharge review passed',
        ChannelFinancialRecord::ACTION_RECHARGE_REJECT => 'Recharge review rejection',
        ChannelFinancialRecord::ACTION_WITHDRAW_PASS => 'Withdrawal review passed',
        ChannelFinancialRecord::ACTION_WITHDRAW_REJECT => 'Withdrawal review rejection',
        ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT => 'Complete payment',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD => 'Add recharge account',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP => 'Disable recharge account',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE => 'Enable recharge account',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT => 'Edit recharge account',
        ChannelFinancialRecord::ACTION_WITHDRAW_GB_ERROR => 'Purchase Treasure Withdrawal Payment Failed',
        ChannelFinancialRecord::ACTION_WITHDRAW_EH_ERROR => 'Purchase Treasure Withdrawal Payment Failed',
    ]
];
