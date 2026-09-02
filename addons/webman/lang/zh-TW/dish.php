<?php

return [
    'title' => '餐點',
    'fields' => [
        'id' => 'ID',
        'department_id' => '渠道/部門',
        'admin_user_id' => '門店',
        'category_id' => '類別',
        'title' => '名稱',
        'content' => '內容',
        'picture' => '圖片',
        'price' => '價格',
        'daily_limit' => '每人每日限量',
        'status' => '狀態',
        'top' => '置頂',
        'sort' => '排序',
        'remark' => '備註',
        'created_at' => '創建時間',
        'updated_at' => '更新時間'
    ],
    'status' => [
        0 => '停用',
        1 => '啟用'
    ],
    'help' => [
        'daily_limit' => '不限量請輸入 0'
    ]
];
