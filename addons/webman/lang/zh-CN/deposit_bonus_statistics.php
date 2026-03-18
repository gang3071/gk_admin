<?php

return [
    'title' => '充值满赠统计报表',

    'fields' => [
        'id' => 'ID',
        'activity_name' => '活动名称',
        'stat_date' => '统计日期',
        'total_participants' => '参与人数',
        'new_participants' => '新增参与人数',
        'total_orders' => '订单总数',
        'total_deposit_amount' => '充值总金额',
        'total_bonus_amount' => '赠送总金额',
        'total_bet_amount' => '总打码量',
        'total_withdraw_amount' => '已提现金额',
        'completed_orders' => '完成订单数',
        'expired_orders' => '过期订单数',
        'cancelled_orders' => '取消订单数',
        'updated_at' => '更新时间',
        'created_at' => '创建时间',
    ],

    'stats' => [
        'total_participants' => '参与人数',
        'total_orders' => '订单总数',
        'total_deposit' => '充值总额',
        'total_bonus' => '赠送总额',
        'total_bet' => '总打码量',
        'completed_orders' => '完成订单',
    ],

    'stat_date_start' => '开始日期',
    'stat_date_end' => '结束日期',
];
