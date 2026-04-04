<?php

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => '开分方式配置',
    'placeholder_name' => '请输入开分方式名',
    'placeholder_min' => '请输入最小开分金额',
    'placeholder_max' => '请输入最大开分金额',
    'limitation' => '限制',
    'no_limit' => '不限制',
    'fields' => [
        'id' => 'ID',
        'name' => '开分方式',
        'department_id' => '部门/渠道id',
        'user_id' => '管理员id',
        'user_name' => '创建人',
        'max' => '最大开分金额',
        'min' => '最小开分金额',
        'status' => '状态',
        'created_at' => '创建时间',
        'amount_limit' => '是否限制金额',
        'type' => '开分方式',
    ],
    'rul' => [
        'min_required' => '最小开分金额必填',
        'min_min_1' => '最少金额不能为0',
        'min_max_100000000' => '金额最大设置1亿',
        'max_required' => '最小开分金额必填',
        'max_min_1' => '最少金额不能为0',
        'max_max_100000000' => '金额最大设置1亿',
        'max_gt_min' => '最大开分金额必须大于最小开分金额',
    ],
    'recharge_method_not_found' => '开分方式不存在',
    'wechat_account' => '微信号',
    'ali_account' => '支付宝账号',
    'type' => [
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
        ChannelRechargeMethod::TYPE_ALI => '支付宝支付',
        ChannelRechargeMethod::TYPE_WECHAT => '微信支付',
        ChannelRechargeMethod::TYPE_BANK => '银行卡',
        ChannelRechargeMethod::TYPE_GB => '购宝钱包',
        ChannelRechargeMethod::TYPE_COIN => '币商扣点',
    ]
];
