<?php

return [
    // 通用錯誤消息
    'player_not_exist' => '玩家不存在',
    'player_already_exists' => '玩家已存在',
    'recommended_player_not_exist' => '推薦玩家不存在',
    'game_not_exist' => '遊戲不存在',
    'please_select_games' => '請選擇要授權的遊戲',
    'games_not_found' => '未找到選擇的遊戲',
    'offline_channel_only' => '此功能僅限線下渠道使用',
    'offline_channel_feature_only' => '該功能僅適用於線下渠道',
    'channel_no_game_platform' => '該渠道未開啟任何電子遊戲平台',
    'games_not_in_channel_scope' => '選擇的遊戲不在渠道允許的範圍內',
    'game_not_in_channel_scope' => '該遊戲不在渠道允許的範圍內',
    'game_platform_not_in_channel_scope' => '選擇的遊戲平台不在渠道允許的範圍內',
    'invalid_operation' => '無效的操作',
    'operation_failed' => '操作失敗',
    'save_failed' => '保存失敗',
    'player_id_required' => '玩家ID不能為空',
    'invalid_parameter' => '參數無效',
    'load_failed' => '加載失敗',
    'invalid_game_points' => '轉換後的遊戲點數無效',
    'system_error' => '系統錯誤',
    'machine_in_test_mode' => '該機台作為新版工控測試機台使用中',
    'video_host_request_failed' => '請求視訊主機失敗',
    'get_stream_info_failed' => '獲取流信息失敗',

    // 通用成功消息
    'settlement_success' => '結算成功',
    'operation_success' => '操作成功',
    'authentication_passed' => '認證通過',
    'batch_generation_failed' => '批量生成失敗：{message}',
    'create_agent_failed' => '創建代理失敗：{message}',
    'create_store_failed' => '創建店家失敗：{message}',

    // 通用標籤
    'administrator' => '管理員',
    'player' => '玩家',
    'total' => '合計',

    // 通用確認消息
    'confirm_save' => '確認保存？',

    // 登錄相關
    'please_enter_credentials' => '請輸入賬號和密碼',
    'account_not_exist' => '賬號不存在',
    'password_incorrect' => '密碼錯誤',
    'login_success' => '登錄成功',
    'implement_login_logic' => '請在 CustomLoginController 中實現實際的登錄邏輯',

    // 代理/店家相關
    'agent_commission_range_error' => '代理抽成比例必須在 0-100 之間',
    'channel_commission_range_error' => '渠道抽成比例必須在 0-100 之間',
    'please_upload_avatar' => '請上傳頭像',
    'password_mismatch' => '兩次密碼輸入不一致',
    'username_exists' => '登錄賬號 {username} 已存在',
    'agent_create_success' => '代理 {name} 創建成功！登錄賬號：{username}',
    'parent_agent_not_exist' => '上級代理不存在',
    'please_select_settlement_targets' => '請選擇需要結算的代理/店家',
    'settlement_end_time_error' => '結算結束時間不能超過當前時間',
    'store_ratio_less_than_agent' => '店家上繳比例設置不能小於代理 ({name}) 的上繳比例,{ratio}%',
    'agent_ratio_greater_than_store' => '代理上繳比例設置不能大於店家 ({name}) 的上繳比例,{ratio}%',

    // 遊戲權限相關
    'game_permission_set_success' => '成功設置了 {count} 個遊戲權限',
    'electronic_game_set_success' => '成功設置了 {count} 個電子遊戲',

    // 交班相關
    'shift_handover_failed_no_department' => '交班失敗：管理員未關聯部門',
    'shift_handover_failed_no_currency' => '交班失敗：系統貨幣配置缺失',

    // 彩池相關
    'pool_ratio_must_greater_than_zero' => '入池比值必須大於0',
    'pool_ratio_cannot_exceed_100' => '入池比值不能超過100%',
    'win_probability_must_greater_than_zero' => '中獎概率必須大於0',
    'win_probability_cannot_exceed_1' => '中獎概率不能超過1（100%）',
    'max_pool_amount_must_greater_than_zero' => '最大彩池金額必須大於0',
    'minimum_amount_must_greater_than_zero' => '啟用保底金額後，保底金額必須大於0',
    'minimum_amount_cannot_exceed_max' => '保底金額不能大於最大彩池金額',
    'distribution_ratio_range_error' => '派發比例必須在0-100之間',

    // 機台相關
    'please_select_reset_hosts' => '請選擇重設的視訊主機',
    'please_fill_zhcn_name' => '請填寫中文簡體名稱',
    'please_upload_zhcn_image' => '請上傳中文簡體圖',

    // 角色相關
    'builtin_role_cannot_modify_name' => '系統內置角色不允許修改名稱',
    'builtin_role_cannot_modify_type' => '系統內置角色不允許修改類型',
    'role_not_exist' => '角色不存在',
    'builtin_role_cannot_delete' => '該角色為系統內置角色，不允許刪除',

    // 批量生成相關
    'batch_generate_success' => '成功生成 {count} 個玩家賬號',
    'batch_generate_partial_success' => '成功生成 {success} 個玩家賬號，失敗 {failed} 個：{accounts}',
    'account_exists' => '已存在',

    // 幫助文本
    'help' => [
        'account_format' => '賬號格式：前綴+編號，例如：P0001',
        'number_auto_padding' => '編號將自動補齊為4位數字，例如：1 → 0001',
        'nickname_format' => '暱稱格式：前綴+編號，例如：玩家0001',
        'number_auto_padding_simple' => '編號將自動補齊為4位數字',
        'all_players_use_this_avatar' => '所有生成的玩家將使用此頭像',
        'avatar_format_recommendation' => '支持jpg、png格式，建議尺寸200x200，所有生成的玩家將使用此頭像',
        'all_accounts_use_this_password' => '所有生成的賬號將使用此密碼',
        'avatar_format' => '支持jpg、png格式，建議尺寸200x200',
        'agent_login_password' => '代理後台登錄密碼，至少6位',
        'store_login_password' => '店家後台登錄密碼，至少6位',
        'agent_commission_help' => '代理從店家收益中抽取的比例，範圍 0-100',
        'channel_commission_help' => '渠道從店家收益中抽取的比例，範圍 0-100',
    ],

    // 提示文本
    'tips' => [
        'offline_channel_only_notice' => '><font size=3 color="#ff4d4f">此功能僅限線下渠道使用</font>',
        'batch_generate_bind_notice' => '><font size=2 color="#1890ff">批量生成的賬號將自動綁定到指定的店家</font>',
    ],

    // 其他
    'divider' => [
        'commission_settings' => '抽成設置',
    ],

    // 默認文本
    'default' => [
        'admin' => '管理員',
        'no_agent' => '無代理',
        'not_filled' => '未填寫',
        'welcome_agent_system' => '歡迎使用代理後台系統！',
        'welcome_store_system' => '歡迎使用店家後台系統！',
    ],

    // 日期篩選
    'date_filter' => [
        'all' => '全部',
        'today' => '今日',
        'yesterday' => '昨天',
        'this_week' => '本周',
        'last_week' => '上周',
        'this_month' => '本月',
        'last_month' => '上月',
    ],

    // 自動交班
    'auto_shift' => [
        'enabled' => '已開啟自動交班',
        'manual_shift_success' => '店家手動交班成功',
        'manual_shift_failed' => '手動交班失敗',
    ],

    // 班次
    'shift' => [
        'morning' => '早班',
        'morning_desc' => '早班自動交班（08:00-16:00）',
        'afternoon' => '中班',
        'afternoon_desc' => '中班自動交班（16:00-24:00）',
        'night' => '晚班',
        'night_desc' => '晚班自動交班（00:00-08:00）',
    ],

    // 通用UI
    'total' => '合計',
    'detail' => '詳情',
    'chart' => '圖表',
];
