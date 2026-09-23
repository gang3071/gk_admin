<?php

return [
    'title' => '餐點訂單',
    'fields' => [
        'id' => 'ID',
        'order_no' => '訂單編號',
        'player_id' => '玩家',
        'department_id' => '渠道/部門',
        'admin_user_id' => '門店',
        'device_id' => '設備',
        'total_amount' => '總額',
        'status' => '狀態',
        'remark' => '備註',
        'created_at' => '創建時間',
        'updated_at' => '更新時間'
    ],
    'reportItem' => [
        'title' => '餐點明細報表',
        'quantity' => '總數量',
        'subtotal' => '總積分'
    ],
    'status' => [
        0 => '待確認',
        1 => '已確認',
        2 => '製作中',
        3 => '已完成',
        4 => '已取消'
    ],
    'cancel_refund' => '訂單取消退積分'
];
