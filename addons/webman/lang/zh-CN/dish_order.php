<?php

return [
    'title' => '餐点订单',
    'fields' => [
        'id' => 'ID',
        'order_no' => '订单编号',
        'player_id' => '玩家',
        'department_id' => '渠道/部门',
        'admin_user_id' => '门店',
        'total_amount' => '总额',
        'status' => '状态',
        'remark' => '备注',
        'created_at' => '创建时间',
        'updated_at' => '更新时间'
    ],
    'status' => [
        0 => '待确认',
        1 => '已确认',
        2 => '制作中',
        3 => '已完成',
        4 => '已取消'
    ]
];

