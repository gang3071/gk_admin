<?php

return [
    'title' => '餐點訂單',
    'fields' => [
        'id' => 'ID',
        'order_no' => '訂單編號',
        'player_id' => '玩家',
        'department_id' => '渠道/部門',
        'admin_user_id' => '門店',
        'total_amount' => '總額',
        'status' => '狀態',
        'remark' => '備註',
        'created_at' => '創建時間',
        'updated_at' => '更新時間'
    ],
    'status' => [
        0 => '待確認',
        1 => '已確認',
        2 => '製作中',
        3 => '已完成',
        4 => '已取消'
    ]
];
