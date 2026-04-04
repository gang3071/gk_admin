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
        ChannelFinancialRecord::ACTION_RECHARGE_PASS => 'Open Score review passed',
        ChannelFinancialRecord::ACTION_RECHARGE_REJECT => 'Open Score review rejection',
        ChannelFinancialRecord::ACTION_WITHDRAW_PASS => 'Wash Score review passed',
        ChannelFinancialRecord::ACTION_WITHDRAW_REJECT => 'Wash Score review rejection',
        ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT => 'Complete payment',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD => 'Add open score account',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP => 'Disable open score account',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE => 'Enable open score account',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT => 'Edit open score account',
        ChannelFinancialRecord::ACTION_WITHDRAW_GB_ERROR => 'Purchase Treasure Wash Score Payment Failed',
        ChannelFinancialRecord::ACTION_WITHDRAW_EH_ERROR => 'Purchase Treasure Wash Score Payment Failed',
    ]
];
