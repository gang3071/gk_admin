<?php

return [
    'title' => '反水獎勵記錄',
    'fields' => [
        'id' => 'ID',
        'admin_id' => '操作人',
        'platform_id' => '平臺id',
        'point' => '壓碼量',
        'reverse_water' => '反水額',
        'real_reverse_water' => '實際發放量',
        'all_diff' => '總輸贏',
        'platform_ratio' => '平臺比例',
        'level_ratio' => '等級加成',
        'date' => '結算日期',
        'created_at' => '發放時間',
        'receive_time' => '領取時間',
        'switch' => '開關',
    ],
    'created_at_start' => '開始時間',
    'created_at_end' => '結束時間',
    'checkout_time' => '結算時間為每日01:00',
    'bath_settlement' => '批量結算',
    'profit_settlement_confirm' => '對反水獎勵進行結算操作，該操作不可逆確定要進行操作嗎?',
    'settlement_reward_null' => '沒有需要結算的反水獎勵',
    'success' => '反水獎勵結算成功'
];
