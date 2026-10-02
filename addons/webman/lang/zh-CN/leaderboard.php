<?php

return [
    'title' => '排行榜',
    'fields' => [
        'id' => 'ID',
        'code' => '识别码',
        'name' => '名称',
        'period_type' => '周期',
        'scope_type' => '范围',
        'period_key' => '期别',
        'start_at' => '开始时间',
        'end_at' => '结束时间',
        'settle_at' => '结算时间',
        'threshold' => '门槛分数',
        'prize_config' => '奖励金额设定',
        'entry_count' => '本期名次笔数',
        'created_at' => '创建时间',
        'updated_at' => '更新时间'
    ],
    'period_type' => [
        1 => '周榜',
        2 => '月榜'
    ],
    'scope_type' => [
        1 => '全站',
        3 => '门店'
    ],
    'settle_at' => [
        'start' => '结算开始时间',
        'end' => '结算结束时间'
    ],
];

