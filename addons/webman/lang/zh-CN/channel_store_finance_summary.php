<?php

return [
    'title' => '门店财务汇总',

    'fields' => [
        'id'                       => 'ID',
        'store_name'               => '店家名称',
        'store_username'           => '登录账号',
        'agent_name'               => '所属代理',
        'electronic_game_win_loss' => '电子游戏输赢',
        'machine_win_loss'         => '实体机输赢',
        'lottery_ticket_sent'      => '摸彩劵送出',
        'electronic_jackpot'       => '电子游戏彩金',
        'machine_jackpot'          => '实体机台彩金',
        'wallet_increase'          => '钱包增加',
        'wallet_decrease'          => '钱包扣除',
        'machine_rebate'           => '实体机台返水',
        'electronic_rebate'        => '电子游戏返水',
        'coupon_sent'              => '体验/福利劵',
    ],

    'filter' => [
        'select_agent'         => '选择代理',
        'all_agents'           => '全部代理',
        'select_store'         => '选择店家',
        'all_stores'           => '全部店家',
        'placeholder_stores'   => '选择门店（可多选）',
        'placeholder_agent'    => '代理（全部）',
        'placeholder_date'     => '时间类型',
        'time_range'           => '时间范围',
        'start_time'           => '开始时间',
        'end_time'             => '结束时间',
        'query'                => '查询',
        'reset'                => '重置',
    ],

    'date_types' => [
        'today'      => '今天',
        'yesterday'  => '昨天',
        'this_week'  => '本周',
        'last_week'  => '上周',
        'this_month' => '本月',
        'last_month' => '上月',
    ],

    'panel' => [
        'machine_win_loss'    => '实体机台输赢',
        'electronic_win_loss' => '电子游戏输赢',
        'net_revenue'         => '净营收',
        'operating_cost'      => '运营成本',
        'cost_detail'         => '运营成本明细（点击类型查看说明）',
        'wallet_detail'       => '钱包流水参考（不计入营收）',
        'total'               => '合计',
        'profit'              => '盈利',
        'loss'                => '亏损',
        'even'                => '持平',
    ],

    'states' => [
        'hint'          => '选择门店和时间范围后点击查询',
        'search_failed' => '查询失败',
        'retry'         => '重试',
        'no_data'       => '该条件下暂无数据',
    ],

    'delivery_types' => [
        1  => '管理员加点',
        4  => '机台上分',
        5  => '机台下分',
        6  => '充值(开分)',
        7  => '提现(洗分)',
        8  => '管理员扣点',
        12 => '推广员分润',
        13 => '彩金中奖',
        16 => '全民邀请奖励',
        22 => '特殊类型',
        23 => '投钞',
        31 => '预扣',
        32 => '退款',
        33 => '摸奖券奖励',
        34 => 'VIP生日礼金',
        35 => 'VIP升级礼金',
        36 => '反水池反水',
        37 => 'VIP每日登录',
        38 => '活动外增',
        39 => '排行榜奖励',
    ],

    'desc_panel' => [
        'title'      => '账变类型说明',
        'click_hint' => '点击左侧账变类型\n查看详细说明',
    ],

    'summary' => [
        'row_label' => '汇总',
    ],
];
