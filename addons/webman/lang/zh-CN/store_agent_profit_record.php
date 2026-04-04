<?php

return [
    'title' => '下线分润结算记录',

    'fields' => [
        'id' => 'ID',
        'settlement_tradeno' => '结算单号',
        'adjust_amount' => '分润调整金额',
        'actual_amount' => '实际分润金额',
        'profit_amount' => '分润金额',
        'ratio' => '分润比例',
        'sub_name' => '上缴对象',
        'sub_profit_amount' => '上缴金额',
        'sub_ratio' => '上缴比例',
        'total_bet' => '总押注',
        'total_diff' => '总输赢',
        'machine_point' => '投钞点数',
        'total_income' => '总营收',
        'total_in' => '转入（开分）',
        'total_out' => '转出（洗分）',
        'user_name' => '管理员',
        'start_time' => '结算开始时间',
        'end_time' => '结算结束时间',
        'created_at' => '创建时间',
    ],

    'detail' => [
        'agent_name' => '代理名称',
        'uuid' => 'UUID',
        'submit_ratio' => '上缴比例',
        'machine_put_point' => '总投钞（开分）',
        'present_out_amount' => '转出（洗分）',
        'present_in_amount' => '总转入（开分）',
        'total_point' => '总营收',
        'settlement_amount' => '已结算金额',
        'created_at' => '创建时间',
        'last_settlement_time' => '最近结算时间',
    ],

    'label' => [
        'agent_store' => '代理/店家',
        'agent_account' => '代理账号',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'no_time' => '无',
    ],

    'type' => [
        'store' => '店家',
        'agent' => '代理',
        'channel' => '渠道',
    ],

    'placeholder' => [
        'settlement_tradeno' => '结算单号',
        'agent_store' => '代理/店家',
    ],
];
