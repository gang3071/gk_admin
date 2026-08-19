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
        'wash_point_config' => '洗分配置',
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

    // 操作菜單
    'actions' => [
        'limit_group' => '限紅組配置',
        'auto_shift_config' => '自動交班配置',
        'system_setting' => '系統配置',
        'open_score_setting' => '開分配置',
        'wash_point_setting' => '洗分配置',
        'activity_config' => '活動配置',
    ],

    // 活動配置
    'activity_config' => [
        'title' => '活動配置',
        'list_title' => '活動配置列表',
        'create_title' => '新增活動配置',
        'edit_title' => '編輯活動配置',
        'no_config' => '暫無活動配置',

        // 欄位
        'fields' => [
            'id' => 'ID',
            'start_time' => '活動開始時間',
            'end_time' => '活動結束時間',
            'status' => '狀態',
            'created_at' => '建立時間',
            'updated_at' => '更新時間',
        ],

        // 狀態
        'status' => [
            '0' => '停用',
            '1' => '啟用',
        ],

        // 區段標題
        'section' => [
            'basic' => '基本資訊',
            'experience' => '體驗券配置',
            'welfare' => '福利券配置',
            'order_prefix' => '訂單前綴配置',
        ],

        // 欄位標籤
        'label' => [
            'start_time' => '開始時間',
            'end_time' => '結束時間',
            'activity_end_time' => '發放截止時間',
            'experience_enabled' => '啟用體驗券',
            'experience_register_after' => '新用戶閾值',
            'experience_daily_limit' => '每日次數',
            'experience_total_limit' => '總次數',
            'experience_score' => '領取分數',
            'experience_expire_hours' => '有效時長(時)',
            'welfare_enabled' => '啟用福利券',
            'welfare_daily_limit' => '每日次數',
            'welfare_rules' => '福利券檔位規則',
            'welfare_expire_hours' => '有效時長(時)',
            'order_prefix_experience' => '體驗券前綴',
            'order_prefix_welfare' => '福利券前綴',
            'order_prefix_recharge' => '開分前綴',
            'order_prefix_withdraw' => '洗分前綴',
        ],

        // 幫助文字
        'help' => [
            'start_time' => '活動開始時間，留空表示立即開始',
            'end_time' => '活動結束時間，留空表示不限制',
            'activity_end_time' => '到達此時間後，福利券和體驗券暫停發放',
            'experience_register_after' => '大於等於此時間註冊的用戶視為新用戶',
            'experience_daily_limit' => '每個用戶每天可領取的次數',
            'experience_total_limit' => '每個用戶總共可領取的次數',
            'experience_score' => '每次領取獲得的分數',
            'experience_expire_hours' => '超過此時間後券自動失效',
            'welfare_daily_limit' => '0 表示不限制',
            'welfare_rules' => '配置福利券的打碼量門檻，達到門檻即可領取',
            'welfare_expire_hours' => '超過此時間後券自動失效',
            'order_prefix_experience' => '體驗券訂單號前綴',
            'order_prefix_welfare' => '福利券訂單號前綴',
            'order_prefix_recharge' => '開分訂單號前綴',
            'order_prefix_withdraw' => '洗分訂單號前綴',
        ],

        // 規則相關
        'rules' => [
            'bet_amount' => '打碼量門檻',
            'score' => '領取分數',
            'add_rule' => '新增檔位',
            'remove_rule' => '移除',
            'day_type' => '計算類型',
            'yesterday' => '昨日打碼量',
            'today' => '今日打碼量',
        ],

        // 驗證
        'validation' => [
            'start_time_required' => '請填寫活動開始時間',
            'end_time_after_start' => '結束時間必須晚於開始時間',
        ],

        // 錯誤訊息
        'error' => [
            'already_exists' => '該店鋪已存在啟用中的活動配置，請先停用後再新增',
        ],

        // 訊息
        'message' => [
            'create_success' => '活動配置建立成功',
            'update_success' => '活動配置更新成功',
            'delete_success' => '活動配置刪除成功',
            'delete_confirm' => '確定要刪除此活動配置嗎？',
        ],
    ],
];
