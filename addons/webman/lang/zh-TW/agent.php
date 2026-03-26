<?php

return [
    'title' => '代理管理',
    'offline_only' => '此功能僅限線下管道使用',

    // 欄位名稱
    'fields' => [
        'id' => 'ID',
        'name' => '代理名稱',
        'username' => '登入帳號',
        'phone' => '聯絡電話',
        'department_name' => '部門名稱',
        'status' => '狀態',
        'is_super' => '超級管理員',
        'created_at' => '建立時間',
        'password' => '登入密碼',
        'password_confirmation' => '確認密碼',
        'avatar' => '上傳頭像',
    ],

    // 狀態
    'status' => [
        'normal' => '正常',
        'disabled' => '已停用',
    ],

    // 超級管理員
    'is_super' => [
        'yes' => '是',
        'no' => '否',
    ],

    // 表單
    'form' => [
        'create_title' => '建立代理',
        'create_hint' => '建立代理後，該代理可登入代理後台，管理下級店家',
        'section_account' => '帳號資訊',
        'section_avatar' => '頭像配置',
        'section_password' => '密碼配置',
    ],

    // 佔位符
    'placeholder' => [
        'status' => '狀態',
        'username' => '登入帳號',
        'name' => '代理名稱',
        'phone' => '聯絡電話',
        'created_at' => '建立時間',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
    ],

    // 幫助文字
    'help' => [
        'phone' => '選填，用於聯絡',
        'username' => '必填，用於登入代理後台',
        'name' => '代理的顯示名稱',
        'avatar' => '支援jpg、png格式，建議尺寸200x200',
        'password' => '代理後台登入密碼',
    ],

    // 驗證規則
    'validation' => [
        'password_min' => '密碼至少6位',
    ],

    // 錯誤訊息
    'error' => [
        'create_failed' => '建立代理失敗：{error}',
    ],
];
