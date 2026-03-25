<?php

return [
    'title' => '充值滿贈活動管理',

    'fields' => [
        'id' => 'ID',
        'activity_name' => '活動名稱',
        'store_id' => '所屬渠道',
        'time_range' => '活動時間',
        'bet_multiple' => '打碼倍數',
        'valid_days' => '有效天數',
        'tier_count' => '檔位數量',
        'status' => '狀態',
        'created_at' => '創建時間',
        'unlock_type' => '解鎖方式',
        'limit_per_player' => '參與次數限制',
        'limit_period' => '限制週期',
        'description' => '活動說明',
        'tiers' => '贈送檔位配置',
        'limit_info' => '參與限制',
    ],

    'tier' => [
        'deposit_amount' => '充值金額',
        'bonus_amount' => '贈送金額',
        'bonus_ratio' => '贈送比例',
        'sort_order' => '排序',
    ],

    'help' => [
        'activity_name' => '請輸入活動名稱，例如：充1000送500',
        'store_id' => '選擇活動所屬的渠道',
        'time_range' => '設置活動的開始和結束時間',
        'bet_multiple' => '贈送金額需要打碼的倍數，例如：5倍表示贈送500需要打碼2500',
        'valid_days' => '訂單有效天數，超過此天數未完成打碼量將失效',
        'unlock_type' => '選擇解鎖方式：打碼量解鎖或不上機解鎖',
        'limit_per_player' => '每個玩家在限制週期內可參與的次數，0表示不限制',
        'limit_period' => '參與次數限制的統計週期',
        'description' => '活動說明，將顯示給玩家',
        'tiers' => '配置不同充值金額對應的贈送金額，至少需要配置一個檔位',
    ],

    'status_enabled' => '啟用',
    'status_disabled' => '停用',

    'unlock_type_bet' => '打碼量解鎖',
    'unlock_type_no_machine' => '不上機解鎖',

    'period_day' => '每天',
    'period_week' => '每週',
    'period_month' => '每月',

    'days' => '天',
    'times' => '次',
    'no_limit' => '不限制',

    'not_found' => '活動不存在',
    'tier_required' => '請至少配置一個贈送檔位',

    'created_at_start' => '創建開始時間',
    'created_at_end' => '創建結束時間',
];
