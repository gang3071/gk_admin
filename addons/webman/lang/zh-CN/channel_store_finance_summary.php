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
        'select_agent'  => '选择代理',
        'all_agents'    => '全部代理',
        'select_store'  => '选择店家',
        'all_stores'    => '全部店家',
        'time_range'    => '时间范围',
        'start_time'    => '开始时间',
        'end_time'      => '结束时间',
    ],

    'summary' => [
        'row_label' => '汇总',
    ],
];