<?php

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => 'Open Score method configuration',
    'placeholder_name' => 'Please enter the name of the open score method',
    'placeholder_min' => 'Please enter the minimum open score amount',
    'placeholder_max' => 'Please enter the maximum open score amount',
    'limitation' => 'limitation',
    'no_limit' => 'no limit',
    'fields' => [
        'id' => 'ID',
        'name' => 'Open Score method',
        'department_id' => 'Department/channel id',
        'user_id' => 'Administrator id',
        'user_name' => 'Creator',
        'max' => 'Maximum open score amount',
        'min' => 'Minimum open score amount',
        'status' => 'status',
        'created_at' => 'Creation time',
        'amount_limit' => 'Is there a limit on the amount',
        'type' => 'Open Score method',
    ],
    'rul' => [
        'min_required' => 'Minimum open score amount required',
        'min_min_1' => 'The minimum amount cannot be 0',
        'min_max_100000000' => 'The maximum amount is set to 100 million',
        'max_required' => 'The minimum open score amount is required',
        'max_min_1' => 'The minimum amount cannot be 0',
        'max_max_100000000' => 'The maximum amount is set to 100 million',
        'max_gt_min' => 'The maximum open score amount must be greater than the minimum open score amount',
    ],
    'recharge_method_not_found' => 'Open Score method does not exist',
    'wechat_count' => 'WeChat ID',
    'ali_account' => 'Alipay account',
    'type' => [
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
        ChannelRechargeMethod::TYPE_ALI => 'Alipay payment',
        ChannelRechargeMethod::TYPE_WECHAT => 'WeChat Pay',
        ChannelRechargeMethod::TYPE_BANK => 'Bank Card',
        ChannelRechargeMethod::TYPE_GB => 'GouBao Wallet',
        ChannelRechargeMethod::TYPE_COIN => 'Coin merchant deduction point',
    ]
];
