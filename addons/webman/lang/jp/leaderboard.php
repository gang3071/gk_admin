<?php

return [
    'title' => 'ランキング',
    'fields' => [
        'id' => 'ID',
        'code' => '識別コード',
        'name' => '名称',
        'period_type' => '周期',
        'scope_type' => '範囲',
        'period_key' => '期別',
        'start_at' => '開始時間',
        'end_at' => '終了時間',
        'settle_at' => '集計時間',
        'threshold' => 'しきい値スコア',
        'prize_config' => '報酬設定',
        'entry_count' => '今期ランクイン件数',
        'created_at' => '作成日時',
        'updated_at' => '更新日時'
    ],
    'period_type' => [
        1 => '週刊',
        2 => '月刊'
    ],
    'scope_type' => [
        1 => 'サイト全体',
        3 => '店舗'
    ],
    'settle_at' => [
        'start' => '集計開始時間',
        'end' => '集計終了時間'
    ],
];

