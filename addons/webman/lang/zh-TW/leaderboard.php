<?php

return [
    'title' => '排行榜',
    'fields' => [
        'id' => 'ID',
        'code' => '識別碼',
        'name' => '名稱',
        'period_type' => '週期',
        'scope_type' => '範圍',
        'period_key' => '期別',
        'start_at' => '開始時間',
        'end_at' => '結束時間',
        'settle_at' => '結算時間',
        'threshold' => '門檻分數',
        'prize_config' => '獎勵金額設定',
        'entry_count' => '本期名次筆數',
        'created_at' => '創建時間',
        'updated_at' => '更新時間'
    ],
    'period_type' => [
        1 => '週榜',
        2 => '月榜'
    ],
    'scope_type' => [
        1 => '全站',
        3 => '門店'
    ],
    'settle_at' => [
        'start' => '結算開始時間',
        'end' => '結算結束時間'
    ],
];
