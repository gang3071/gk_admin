<?php

use addons\webman\model\ChannelFinancialRecord;

return [
    'title' => '財務操作記錄',
    'content' => '序號{setting_id}',
    'fields' => [
        'id' => 'ID',
        'department_id' => '部門/通路ID',
        'player_id' => '玩家ID',
        'player' => '玩家資訊',
        'target' => '資料錶',
        'target_id' => '資料錶ID',
        'action' => '操作行為',
        'tradeno' => '操作訂單',
        'user_id' => '操作訂單',
        'user_name' => '操作人',
        'created_at' => '操作時間',
    ],
    'action' => [
        ChannelFinancialRecord::ACTION_RECHARGE_PASS => '充值稽核通過',
        ChannelFinancialRecord::ACTION_RECHARGE_REJECT => '充值稽核拒絕',
        ChannelFinancialRecord::ACTION_WITHDRAW_PASS => '提現稽核通過',
        ChannelFinancialRecord::ACTION_WITHDRAW_REJECT => '提現稽核拒絕',
        ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT => '完成打款',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD => '添加充值帳戶',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP => '停用充值帳戶',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE => '啟用充值帳戶',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT => '編輯充值帳戶',
        ChannelFinancialRecord::ACTION_WITHDRAW_GB_ERROR => '購寶提現支付失敗',
        ChannelFinancialRecord::ACTION_WITHDRAW_EH_ERROR => 'EH提現支付失敗',
    ]
];
