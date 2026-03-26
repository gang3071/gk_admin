<?php

use addons\webman\model\PromoterProfitRecord;

return [
    'title' => '通路分潤',
    'fields' => [
        'id' => 'ID',
        'department_id' => '渠道ID',
        'status' => '结算状态',
        'withdraw_amount' => '提现金额',
        'recharge_amount' => '充值金额',
        'bonus_amount' => '活动奖励金额',
        'admin_deduct_amount' => '管理员扣点',
        'admin_add_amount' => '管理员加点',
        'present_amount' => '系统赠送',
        'machine_up_amount' => '机台上点',
        'machine_down_amount' => '机台下点',
        'lottery_amount' => '彩金奖金',
        'profit_amount' => '渠道当前分润',
        'self_profit_amount' => '平台当前分润',
        'water_amount' => '电子游戏返水金额',
        'settlement_tradeno' => '结算单号',
        'ratio' => '分润比例',
        'settlement_time' => '结算时间',
        'created_at' => '创建时间',
        'date' => '数据产生日期',
        'updated_at' => '更新时间',
        'total_amount' => '金额',
        'open_point' => '上分',
        'wash_point' => '下分',
        'game_amount' => '电子游戏金额',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => '未结算',
        PromoterProfitRecord::STATUS_COMPLETED => '已结算',
    ],
    'settlement_time_start' => '结算开始时间',
    'settlement_time_end' => '结算结束时间',
    'date_tip' => '数据凌晨3点更新前一日0点到24点数据',
    'profit_amount_tip' => '分润结算公式 (机台上分 + 管理员扣点) - (活动奖励 + 系统赠送 + 管理员加点 + 机台下分 + 彩金奖励). ',
    'channel_settlement' => '渠道结算',
];
