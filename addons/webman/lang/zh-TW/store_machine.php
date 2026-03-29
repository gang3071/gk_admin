<?php

return [
    'title' => '店家管理',
    'offline_only' => '此功能僅限線下管道使用',
    'create_success' => '店家 {name} 建立成功！登入帳號：{username}，{agent_label}：{agent_name}',
    'create_failed' => '建立店家失敗：{error}',
    'welcome_message' => '歡迎使用店家後台系統！',

    // 列名
    'fields' => [
        'id' => 'ID',
        'name' => '店家名稱',
        'username' => '登入帳號',
        'phone' => '聯絡電話',
        'department_name' => '部門名稱',
        'agent_commission' => '代理抽成',
        'channel_commission' => '管道抽成',
        'status' => '狀態',
        'created_at' => '建立時間',
        'parent_agent' => '上級代理',
        'password' => '登入密碼',
        'password_confirmation' => '確認密碼',
        'avatar' => '上傳頭像',
    ],

    // 狀態
    'status' => [
        'normal' => '正常',
        'disabled' => '已停用',
        'not_set' => '未設定',
    ],

    // 表單
    'form' => [
        'create_title' => '建立店家',
        'create_hint' => '建立店家後，該店家可登入店家後台',
        'section_account' => '帳號資訊',
        'section_parent_agent' => '上級代理',
        'section_avatar' => '頭像配置',
        'section_password' => '密碼配置',
        'select_parent_agent' => '選擇上級代理',
    ],

    // 佔位符
    'placeholder' => [
        'status' => '狀態',
        'username' => '登入帳號',
        'name' => '店家名稱',
        'phone' => '聯絡電話',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
    ],

    // 篩選器
    'filter' => [
        'select_store' => '選擇店家',
    ],

    // 其他
    'all' => '全部',

    // 幫助文字
    'help' => [
        'phone' => '選填，用於聯絡',
        'username' => '必填，用於登入店家後台',
        'name' => '店家的顯示名稱',
        'parent_agent' => '選擇該店家的上級代理',
        'avatar' => '支援jpg、png格式，建議尺寸200x200',
        'password' => '店家後台登入密碼',
    ],

    // 驗證規則
    'validation' => [
        'password_min' => '密碼至少6位',
    ],

    // 錯誤訊息
    'error' => [
        'offline_only' => '此功能僅限線下管道使用',
        'avatar_required' => '請上傳頭像',
        'password_mismatch' => '兩次密碼輸入不一致',
        'parent_agent_not_found' => '上級代理不存在',
        'username_exists' => '登入帳號 {username} 已存在',
    ],

    // 自動交班配置
    'auto_shift' => [
        'morning_title' => '早班',
        'afternoon_title' => '中班',
        'night_title' => '晚班',
        'morning_desc' => '早班自動交班（08:00-16:00）',
        'afternoon_desc' => '中班自動交班（16:00-24:00）',
        'night_desc' => '晚班自動交班（00:00-08:00）',
    ],
];
