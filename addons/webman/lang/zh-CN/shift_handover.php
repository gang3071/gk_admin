<?php

return [
    'title' => '交班管理',
    'auto_shift_enabled' => '已开启自动交班',
    'auto_shift_enabled_desc' => '系统已启用自动交班功能，无法进行手动交班操作。',
    'auto_shift_close_hint' => '如需手动交班，请先到"自动交班配置"中关闭自动交班功能。',
    'goto_auto_shift_config' => '前往自动交班配置',
    'manual_shift_disabled' => '手动交班已禁用',
    'shift_time' => '交班时间',
    'shift_time_help' => '交班时间不能超过当前时间, 无法选择过去5天内的时间, 不能选择上次交班时间的范围',
    'start_time' => '开始时间',
    'end_time' => '结束时间',
    'none' => '无',
    'last_shift_time' => '上次交班时间',

    // 错误消息
    'error' => [
        'end_time_future' => '结束时间不能超过当前时间',
        'start_time_future' => '开始时间不能是未来时间',
        'start_gte_end' => '开始时间必须早于结束时间',
        'time_range_too_long' => '交班时间范围不能超过30天',
        'duplicate_record' => '该时间范围已存在交班记录，请勿重复提交',
        'config_error' => '系统配置错误，请联系管理员',
        'shift_success' => '交班成功',
        'shift_failed' => '交班失败',
    ],

    // 字段说明
    'fields' => [
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'machine_amount' => '机台金额',
        'machine_point' => '机台积分',
        'total_in' => '总上分',
        'total_out' => '总下分',
        'total_profit_amount' => '总盈利',
    ],
];
