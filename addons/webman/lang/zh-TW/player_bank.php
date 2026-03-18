<?php

/** TODO 翻译 */

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => '玩家銀行卡',
    'fields' => [
        'id' => 'ID',
        'status' => '狀態',
        'bank_name' => '銀行名稱',
        'account' => '銀行帳號',
        'account_name' => '戶名',
        'created_at' => '創建時間',
        'wallet_address' => '錢包地址',
        'qr_code' => '錢包碼/收款碼',
        'type' => '帳戶類型',
    ],
    'type' => [
        ChannelRechargeMethod::TYPE_BANK => '銀行帳戶',
        ChannelRechargeMethod::TYPE_ALI => '支付寶支付',
        ChannelRechargeMethod::TYPE_WECHAT => '微信支付',
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
    ],
    'created_at_start' => '開始時間',
    'created_at_end' => '結束時間',
];
