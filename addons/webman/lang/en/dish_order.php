<?php

return [
    'title' => 'Meal Orders',
    'fields' => [
        'id' => 'ID',
        'order_no' => 'Order No.',
        'player_id' => 'Player',
        'department_id' => 'Channel/Department',
        'admin_user_id' => 'Store',
        'device_id' => 'Device',
        'total_amount' => 'Total Amount',
        'status' => 'Status',
        'remark' => 'Remarks',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At'
    ],
    'reportItem' => [
        'title' => 'Meal Order Item Report',
        'quantity' => 'Total quantity',
        'subtotal' => 'Total Points'
    ],
    'status' => [
        0 => 'Pending',
        1 => 'Confirmed',
        2 => 'Preparing',
        3 => 'Completed',
        4 => 'Cancelled'
    ],
    'cancel_refund' => 'Order cancelled - Points refunded'
];

