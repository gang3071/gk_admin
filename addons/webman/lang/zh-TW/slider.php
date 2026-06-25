<?php

return [
    'title' => '輪播圖',
    'fields' => [
        'id' => 'ID',
        'url' => '鏈接地址',
        'department_id' => '渠道',
        'content' => '內容',
        'picture_url' => '圖片',
        'status' => '狀態',
        'sort' => '排序',
        'ad_position' => '廣告位',
        'created_at' => '創建時間',
    ],
    'ad_position' => [
        1 => '電子遊戲大廳',
        2 => '實體大廳',
        3 => '待機頁面',
        4 => '橫版背景',
    ],
    'url_max_length'=>'鏈接地址最多200個字符',
    'help' => [
        'picture_size_1' => '建議圖片尺寸 1080 * 350 或 1080 * 533',
        'picture_size_2' => '建議圖片尺寸 1080 * 545',
        'picture_size_3' => '建議圖片尺寸 1080 * 1920',
    ]
];
