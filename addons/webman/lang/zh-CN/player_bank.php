<?php

/** TODO 翻译 */

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => '玩家银行卡',
    'fields' => [
        'id' => 'ID',
        'status' => '状态',
        'bank_name' => '银行名称',
        'account' => '账户',
        'account_name' => '户名',
        'created_at' => '创建时间',
        'wallet_address' => '钱包地址',
        'qr_code' => '钱包码/收款码',
        'type' => '账户类型',
    ],
    'type' => [
        ChannelRechargeMethod::TYPE_BANK => '银行账户',
        ChannelRechargeMethod::TYPE_ALI => '支付宝支付',
        ChannelRechargeMethod::TYPE_WECHAT => '微信支付',
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
    ],
    'created_at_start' => '开始时间',
    'created_at_end' => '结束时间',
];
