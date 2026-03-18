<?php

use addons\webman\model\ChannelFinancialRecord;

return [
    'title' => '财务操作记录',
    'content' => '序号{setting_id}',
    'fields' => [
        'id' => 'ID',
        'department_id' => '部门/渠道ID',
        'player_id' => '玩家ID',
        'player' => '玩家信息',
        'target' => '資料表',
        'target_id' => '資料表ID',
        'action' => '操作行为',
        'tradeno' => '操作订单',
        'user_id' => '操作订单',
        'user_name' => '操作人',
        'created_at' => '操作时间',
    ],
    'action' => [
        ChannelFinancialRecord::ACTION_RECHARGE_PASS => '充值审核通过',
        ChannelFinancialRecord::ACTION_RECHARGE_REJECT => '充值审核拒绝',
        ChannelFinancialRecord::ACTION_WITHDRAW_PASS => '提现审核通过',
        ChannelFinancialRecord::ACTION_WITHDRAW_REJECT => '提现审核拒绝',
        ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT => '完成打款',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD => '添加充值账户',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP => '停用充值账户',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE => '启用充值账户',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT => '编辑充值账户',
        ChannelFinancialRecord::ACTION_WITHDRAW_GB_ERROR => '购宝提现支付失败',
        ChannelFinancialRecord::ACTION_WITHDRAW_EH_ERROR => 'EH提现支付失败',
    ]
];
