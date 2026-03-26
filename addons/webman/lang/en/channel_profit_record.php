<?php

use addons\webman\model\PromoterProfitRecord;

return [
    'title' => 'Channel profit sharing',
    'fields' => [
        'id' => 'ID',
        'department_id' => 'channel ID',
        'status' => 'Settlement status',
        'withdraw_amount' => 'Withdraw amount',
        'recharge_amount' => 'Recharge amount',
        'bonus_amount' => 'Activity reward amount',
        'admin_deduct_amount' => 'Administrator deduct points',
        'admin_add_amount' => 'Administrator adds points',
        'present_amount' => 'System gift',
        'machine_up_amount' => 'Machine point',
        'machine_down_amount' => 'Machine down point',
        'lottery_amount' => 'Lottery bonus',
        'profit_amount' => 'Current channel profit sharing',
        'self_profit_amount' => 'Current profit sharing on the platform',
        'water_amount' => 'Electronic game rebate amount',
        'settlement_tradeno' => 'Settlement order number',
        'ratio' => 'Profit sharing ratio',
        'settlement_time' => 'settlement time',
        'created_at' => 'Creation time',
        'date' => 'Data generation date',
        'updated_at' => 'Update time',
        'total_amount' => 'amount',
        'open_point' => 'upper point',
        'wash_point' => 'Lower points',
        'game_amount' => 'Electronic game amount',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => 'Unsettled',
        PromoterProfitRecord::STATUS_COMPLETED => 'Settled',
    ],
    'settlement_time_start' => 'Settlement start time',
    'settlement_time_end' => 'Settlement end time',
    'date_tip' => "The data is updated from 0:00 to 24:00 on the previous day at 3 a.m.",
    'profit_amount_tip' => "Profit settlement formula (machine points + administrator deduction points) - (activity rewards + system gifts + administrator points + machine points + bonus bonus). ",
    'channel_settlement' => 'Channel Settlement',
];
