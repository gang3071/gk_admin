<?php

use addons\webman\model\PromoterProfitRecord;

return [
    'title' => '分潤報表',
    'fields' => [
        'id' => 'ID',
        'department_id' => '通路ID',
        'status' => '結算狀態',
        'withdraw_amount' => '提現金額',
        'recharge_amount' => '充值金額',
        'bonus_amount' => '活動獎勵金額',
        'admin_deduct_amount' => '管理員扣點',
        'admin_add_amount' => '管理員加點',
        'present_amount' => '系統贈送',
        'machine_up_amount' => '機台上點',
        'machine_down_amount' => '機台下點',
        'lottery_amount' => '彩金獎金',
        'profit_amount' => '通路當前分潤',
        'self_profit_amount' => '平臺當前分潤',
        'water_amount' => '電子遊戲返水金額',
        'settlement_tradeno' => '結算單號',
        'ratio' => '分潤比例',
        'settlement_time' => '結算時間',
        'created_at' => '創建時間',
        'date' => '數據產生日期',
        'updated_at' => '更新時間',
        'total_amount' => '金額',
        'open_point' => '上分',
        'wash_point' => '下分',
        'game_amount' => '電子遊戲金額',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => '未結算',
        PromoterProfitRecord::STATUS_COMPLETED => '已結算',
    ],
    'settlement_time_start' => '結算開始時間',
    'settlement_time_end' => '結算結束時間',
    'date_tip' => '數據淩晨3點更新前一日0點到24點數據',
    'profit_amount_tip' => '分潤結算公式（機台上分+管理員扣點）-（活動獎勵+系統贈送+管理員加點+機台下分+彩金獎勵）. ',
];
