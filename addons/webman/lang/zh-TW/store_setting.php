<?php

return [
    'title' => '店機系統配置',
    'all_stores' => '所有店機',
    'fields' => [
        'feature' => '功能',
        'setting' => '配置',
        'status' => '狀態',
        'player' => '綁定店機',

        // 配置項
        'home_notice' => '首頁提醒消息',
        'store_marquee' => '店機跑馬燈',
        'order_expiration' => '訂單過期時間',
        'business_hours' => '營業時間段',
        'enable_physical_machine' => '是否開啟實體機台',
        'enable_live_baccarat' => '是否開啟真人百家',
    ],
    'home_notice_max_len' => '首頁提醒消息最多500個字符',
    'store_marquee_max_len' => '店機跑馬燈最多200個字符',
    'minutes' => '分鐘',
    'time_range' => '時間範圍',
    'edit_business_hours' => '編輯營業時間',
    'enable' => '啟用',
    'disable' => '禁用',

    // 驗證消息
    'validation' => [
        'integer' => '必須是整數',
        'max' => '不能超過 {max}',
        'min' => '不能少於 {min}',
    ],
];