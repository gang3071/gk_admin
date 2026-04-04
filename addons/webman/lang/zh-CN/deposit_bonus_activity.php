<?php

return [
    'title' => '开分满赠活动管理',

    'fields' => [
        'id' => 'ID',
        'activity_name' => '活动名称',
        'store_id' => '所属渠道',
        'time_range' => '活动时间',
        'bet_multiple' => '打码倍数',
        'valid_days' => '有效天数',
        'tier_count' => '档位数量',
        'status' => '状态',
        'created_at' => '创建时间',
        'unlock_type' => '解锁方式',
        'limit_per_player' => '参与次数限制',
        'limit_period' => '限制周期',
        'description' => '活动说明',
        'tiers' => '赠送档位配置',
        'limit_info' => '参与限制',
    ],

    'tier' => [
        'deposit_amount' => '开分金额',
        'bonus_amount' => '赠送金额',
        'bonus_ratio' => '赠送比例',
        'sort_order' => '排序',
    ],

    'help' => [
        'activity_name' => '请输入活动名称，例如：充1000送500',
        'store_id' => '选择活动所属的渠道',
        'time_range' => '设置活动的开始和结束时间',
        'bet_multiple' => '赠送金额需要打码的倍数，例如：5倍表示赠送500需要打码2500',
        'valid_days' => '订单有效天数，超过此天数未完成打码量将失效',
        'unlock_type' => '选择解锁方式：打码量解锁或不上机解锁',
        'limit_per_player' => '每个玩家在限制周期内可参与的次数，0表示不限制',
        'limit_period' => '参与次数限制的统计周期',
        'description' => '活动说明，将显示给玩家',
        'tiers' => '配置不同开分金额对应的赠送金额，至少需要配置一个档位',
    ],

    'status_enabled' => '启用',
    'status_disabled' => '停用',

    'unlock_type_bet' => '打码量解锁',
    'unlock_type_no_machine' => '不上机解锁',

    'period_day' => '每天',
    'period_week' => '每周',
    'period_month' => '每月',

    'days' => '天',
    'times' => '次',
    'no_limit' => '不限制',

    'not_found' => '活动不存在',
    'tier_required' => '请至少配置一个赠送档位',

    'created_at_start' => '创建开始时间',
    'created_at_end' => '创建结束时间',
];
