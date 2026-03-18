<?php

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => '充值管道配寘',
    'placeholder_name' => '請輸入充值管道名',
    'placeholder_min' => '請輸入最小充值金額',
    'placeholder_max' => '請輸入最大充值金額',
    'limitation' => '限制',
    'no_limit' => '不限制',
    'fields' => [
        'id' => 'ID',
        'name' => '充值管道',
        'department_id' => '部門/通路id',
        'user_id' => '管理員id',
        'user_name' => '創建人',
        'max' => '最大充值金額',
        'min' => '最小充值金額',
        'status' => '狀態',
        'created_at' => '創建時間',
        'amount_limit' => '是否限制金額',
        'type' => '充值管道',
    ],
    'rul' => [
        'min_required' => '最小充值金額必填',
        'min_min_1' => '最少金額不能為0',
        'min_max_100000000' => '金額最大設定1億',
        'max_required' => '最小充值金額必填',
        'max_min_1' => '最少金額不能為0',
        'max_max_100000000' => '金額最大設定1億',
        'max_gt_min' => '最大充值金額必須大於最小充值金額',
    ],
    'recharge_method_not_found' => '充值管道不存在',
    'wechat_account' => '微訊號',
    'ali_account' => '支付寶帳號',
    'type' => [
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
        ChannelRechargeMethod::TYPE_ALI => '支付寶支付',
        ChannelRechargeMethod::TYPE_WECHAT => '微信支付',
        ChannelRechargeMethod::TYPE_BANK => '銀行卡',
        ChannelRechargeMethod::TYPE_GB => '購寶錢包',
        ChannelRechargeMethod::TYPE_COIN => '幣商扣點',
    ]
];
