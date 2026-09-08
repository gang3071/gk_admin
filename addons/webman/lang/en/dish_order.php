<?php

return [
    'title' => 'Meal Orders',
    'fields' => [
        'id' => 'ID',
        'order_no' => 'Order No.',
        'player_id' => 'Player',
        'department_id' => 'Channel/Department',
        'admin_user_id' => 'Store',
        'total_amount' => 'Total Amount',
        'status' => 'Status',
        'remark' => 'Remarks',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At'
    ],
    'status' => [
        0 => 'Pending',
        1 => 'Confirmed',
        2 => 'Preparing',
        3 => 'Completed',
        4 => 'Cancelled'
    ]
];

