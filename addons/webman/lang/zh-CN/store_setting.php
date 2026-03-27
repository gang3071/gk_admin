<?php

return [
    'title' => '店家系统配置',
    'all_stores' => '所有店家',
    'fields' => [
        'feature' => '功能',
        'setting' => '配置',
        'status' => '状态',
        'player' => '绑定店家',

        // 配置项
        'home_notice' => '首页提醒消息',
        'store_marquee' => '店家跑马灯',
        'order_expiration' => '订单过期时间',
        'business_hours' => '营业时间段',
        'enable_physical_machine' => '是否开启实体机台',
        'enable_live_baccarat' => '是否开启真人百家',
        'machine_crash_amount' => '爆机金额',
    ],
    'home_notice_max_len' => '首页提醒消息最多500个字符',
    'store_marquee_max_len' => '店家跑马灯最多200个字符',
    'minutes' => '分钟',
    'time_range' => '时间范围',
    'edit_business_hours' => '编辑营业时间',
    'enable' => '启用',
    'disable' => '禁用',

    // 验证消息
    'validation' => [
        'integer' => '必须是整数',
        'numeric' => '必须是数字',
        'max' => '不能超过 {max}',
        'min' => '不能少于 {min}',
    ],
];