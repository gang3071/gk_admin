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
        'recharge_amount' => '累计开分',
        'withdraw_amount' => '累计洗分',
        'machine_put_point' => '投钞',
        'lottery_amount' => '彩金',
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
    ],

    // 统计数据
    'stats' => [
        'total_recharge' => '总开分',
        'total_withdraw' => '总洗分',
        'total_machine_put' => '总投钞',
        'total_lottery' => '总彩金',
        'total_subtotal' => '总小计',
        'total_agent_profit' => '总代理分润',
        'total_channel_profit' => '总渠道分润',
    ],

    // 消息提示
    'message' => [
        'store_not_found' => '店家不存在',
        'update_success' => '备注更新成功',
    ],
];