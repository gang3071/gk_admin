<?php

return [
    'title' => '代理/店家列表',
    'settlement_records_title' => '下线分润结算记录',

    // 字段
    'fields' => [
        'id' => 'ID',
        'agent_account' => '代理账号',
        'agent_name' => '代理/店家',
        'bound_player_uuid' => '绑定玩家UUID',
        'payment_ratio' => '上缴比例',
        'current_total_revenue' => '当期总营收',
        'agent_profit_amount' => '代理分润金额',
        'profit_adjust_amount' => '分润调整金额',
        'current_payment_amount' => '当期上缴金额',
        'current_transfer_in' => '当期转入(开分)',
        'current_cash_in' => '当期投钞(开分)',
        'current_transfer_out' => '当期转出(洗分)',
        'store_device_count' => '店家/设备数量',
        'device_count' => '设备数量',
        'current_machine_score' => '当前机台分数',
        'parent_agent' => '上级代理',
        'tradeno' => '结算单号',
        'settlement_tradeno' => '结算单号',
        'settlement_time' => '结算时间',
        'last_settlement_time' => '上次结算时间',
        'actual_profit_amount' => '实际分润金额',
        'profit_amount' => '分润金额',
        'profit_ratio' => '分润比例',
        'payment_target' => '上缴对象',
        'payment_amount' => '上缴金额',
        'payment_ratio_percent' => '上缴比例',
        'total_bet' => '总押注',
        'total_diff' => '总输赢',
        'machine_point' => '投钞点数',
        'total_income' => '总营收',
        'total_cash_in' => '总投钞(开分)',
        'total_transfer_in' => '总转入(开分)',
        'transfer_in' => '转入(开分)',
        'transfer_out' => '转出(洗分)',
        'admin' => '管理员',
        'settlement_start_time' => '结算开始时间',
        'settlement_end_time' => '结算结束时间',
        'created_at' => '创建时间',
        'start_time' => '开始时间',
    ],

    // 标签
    'label' => [
        'agent_account_with_value' => '代理账号：{value}',
        'agent_settlement_with_value' => '代理分润结算：{value}',
    ],

    // 占位符
    'placeholder' => [
        'tradeno' => '结算单号',
        'agent_promoter' => '代理/店家',
    ],

    // 帮助文字
    'help' => [
        'settlement_time' => '结算时间不能超过当前时间，不能选择上次结算时间的范围',
        'settlement_range' => '可选择结算时间范围：{start} ~ 此刻',
    ],

    // 表单标题
    'form' => [
        'last_settlement_time' => '上次结算时间',
    ],

    // 按钮
    'button' => [
        'settlement' => '结算',
        'batch_settlement' => '批量结算',
    ],

    // 确认消息
    'confirm' => [
        'batch_settlement' => '批量结算无法根据时间精确结算，将会结算所有未结算的时间，是否确定结算？',
    ],

    // 标签
    'tag' => [
        'channel' => '渠道',
        'store' => '店家',
        'agent' => '代理',
    ],

    // Detail 项目
    'detail' => [
        'agent_name' => '代理名称',
        'agent_created_at' => '代理创建时间',
        'settled_amount' => '已结算金额',
        'last_settlement_time' => '最近结算时间',
        'start_time_label' => '开始时间',
        'end_time_label' => '结束时间',
        'no_time' => '无',
    ],
];
