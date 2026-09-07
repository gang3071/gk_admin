<?php

return [
    'title' => '店家分润报表',

    // 字段
    'fields' => [
        'id' => 'ID',
        'store_name' => '店家名称',
        'device_count' => '设备数量',
        'store_username' => '登录账号',
        'agent_name' => '所属代理',
        'remark' => '备注',
        'recharge_amount' => '开分',
        'open_score_amount' => '开分',
        'withdraw_amount' => '洗分',
        'machine_put_point' => '投钞',
        'incoming_ticket_amount' => '入票',
        'ticket_redeem_amount' => '出卷',
        'ticket_open_score_amount' => '开票',
        'counter_ticket_amount' => '柜台开票',
        'redeem_amount' => '核销',
        'redeem_machine_amount' => '机台核销',
        'ticket_unredeemed_amount' => '未核销',
        'experience_coupon_amount' => '体验券',
        'welfare_coupon_amount' => '福利券',
        'lottery_amount' => '彩金',
        'activity_total' => '活动奖励',
        'electronic_game_bet_amount' => '电子打码量',
        'machine_bet_amount' => '机器打码量',
        'total_income' => '总收入',
        'total_expense' => '总支出',
        'total_profit' => '利润',
        'subtotal' => '小计',
        'agent_commission' => '代理抽成比例',
        'agent_profit' => '代理分润',
        'channel_commission' => '渠道抽成比例',
        'channel_profit' => '渠道分润',
    ],

    // 筛选器
    'filter' => [
        'select_agent' => '选择代理',
        'all_agents' => '全部代理',
        'select_store' => '选择店家',
        'all_stores' => '全部店家',
        'remark_placeholder' => '请输入备注关键词',
        'time_range' => '时间范围',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'select_shift' => '选择班次',
        'all_shifts' => '全部班次',
    ],

    // 班次
    'shift' => [
        'morning' => '早班 (08:00-16:00)',
        'afternoon' => '中班 (16:00-00:00)',
        'night' => '晚班 (00:00-08:00)',
    ],

    // 统计数据
    'stats' => [
        'panel_header' => '查看统计数据',
        'loading' => '数据加载中...',
        'refresh' => '刷新数据',
        'load_error' => '数据加载失败',
        'retry' => '重试',
        'click_to_view' => '点击展开查看统计数据',
        'load_failed_msg' => '数据加载失败，请重试',
        'total_recharge' => '总开分',
        'total_withdraw' => '总洗分',
        'total_machine_put' => '总投钞',
        'total_lottery' => '总彩金',
        'total_subtotal' => '总小计',
        'total_agent_profit' => '总代理分润',
        'total_channel_profit' => '总渠道分润',
        'total_income' => '累计总收入',
        'total_expense' => '累计总支出',
        'total_profit' => '累计总利润',
        'total_activity' => '总活动奖励',
    ],

    // 消息提示
    'message' => [
        'store_not_found' => '店家不存在',
        'update_success' => '备注更新成功',
    ],
];