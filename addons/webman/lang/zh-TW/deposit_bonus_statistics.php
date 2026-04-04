<?php

return [
    'title' => '開分滿贈統計報表',

    'fields' => [
        'id' => 'ID',
        'activity_name' => '活動名稱',
        'stat_date' => '統計日期',
        'total_participants' => '參與人數',
        'new_participants' => '新增參與人數',
        'total_orders' => '訂單總數',
        'total_deposit_amount' => '開分總金額',
        'total_bonus_amount' => '贈送總金額',
        'total_bet_amount' => '總打碼量',
        'total_withdraw_amount' => '已洗分金額',
        'completed_orders' => '完成訂單數',
        'expired_orders' => '過期訂單數',
        'cancelled_orders' => '取消訂單數',
        'updated_at' => '更新時間',
        'created_at' => '創建時間',
    ],

    'stats' => [
        'total_participants' => '參與人數',
        'total_orders' => '訂單總數',
        'total_deposit' => '開分總額',
        'total_bonus' => '贈送總額',
        'total_bet' => '總打碼量',
        'completed_orders' => '完成訂單',
    ],

    'stat_date_start' => '開始日期',
    'stat_date_end' => '結束日期',
];
