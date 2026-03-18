<?php

/** TODO 翻译 */

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => "Player's bank card",
    'fields' => [
        'id' => 'ID',
        'status' => 'status',
        'bank_name' => 'Bank name',
        'account' => 'Bank account number',
        'account_name' => 'Account name',
        'created_at' => 'Creation time',
        'wallet_address' => 'Wallet Address',
        'qr_code' => 'wallet code/payment code',
        'type' => 'Account type',
    ],
    'type' => [
        ChannelRechargeMethod::TYPE_BANK => 'Bank Account',
        ChannelRechargeMethod::TYPE_ALI => 'Alipay payment',
        ChannelRechargeMethod::TYPE_WECHAT => 'WeChat Pay',
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
    ],
    'created_at_start' => 'Start time',
    'created_at_end' => 'End time',
];
