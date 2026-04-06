<?php

return [
    // 標題
    'title' => '店家/設備管理',
    'store_list' => '設備列表',
    'store_management' => '店家管理',
    'device_list' => '設備列表',
    'delivery_record' => '賬變記錄',
    'shift_handover_record' => '交班報表',

    // 字段
    'fields' => [
        'store_name' => '店家名稱',
        'login_account' => '登入帳號',
        'contact_phone' => '聯繫電話',
        'department_name' => '部門名稱',
        'agent_commission' => '代理抽成',
        'channel_commission' => '渠道抽成',
        'status' => '狀態',
        'created_at' => '創建時間',
        'recharge_amount' => '累計開分',
        'withdraw_amount' => '累計洗分',
        'machine_put_point' => '投鈔',
        'lottery_amount' => '彩金',
        'subtotal' => '小計',
        'game_platform' => '遊戲平台',
        'game_name' => '遊戲名稱',
        'game_category' => '遊戲分類',
        'is_hot' => '熱門',
        'is_new' => '新遊戲',
        'sort' => '排序',
    ],

    // 舊的字段鍵（保留兼容性）
    'store_name' => '店家名稱',
    'bind_player' => '綁定玩家',
    'account' => '賬號',
    'recommend_id' => '所屬店家',
    'select_store' => '所屬店家',
    'last_settlement_time' => '上次結算時間',

    // 當期數據
    'current_data' => '當期數據',
    'present_in' => '轉入(開分)',
    'present_out' => '轉出(洗分)',
    'machine_put' => '投鈔(開分)',
    'total_revenue' => '總計(營業額)',
    'store_profit_rate' => '店家分潤',
    'store_profit_amount' => '店家拆賬',
    'company_profit_rate' => '公司分潤',
    'company_profit_amount' => '公司拆賬',

    // 累計數據
    'total_data' => '累計數據',

    // 開分相關
    'open_score' => '開分',
    'recharge_amount' => '開分金額',
    'quick_amount' => '快捷金額',
    'custom_amount' => '自定義金額',
    'preset_amount' => '預設金額',
    'default_amount' => '默認金額',
    'device_balance' => '設備餘額',
    'exchange_rate' => '匯率',
    'game_points' => '遊戲點數',
    'reference' => '參考',
    'points' => '點',

    // 提示信息
    'tip_device_balance' => '提示: 設備餘額 {balance} {currency} = {points} 遊戲點數',
    'tip_exchange_rate' => '1 {currency} = {ratio} 遊戲點數，請輸入貨幣金額，系統會自動轉換成遊戲點數',
    'tip_select_preset' => '選擇預設金額或自定義金額 (貨幣 → 遊戲點數，1{currency} = {ratio}點)',
    'tip_reference' => '設備餘額: {currency}{balance}，匯率：1{currency} = {ratio}遊戲點數。{reference}',

    // 錯誤消息
    'error_amount_required' => '請輸入金額',
    'error_amount_invalid' => '請輸入有效的金額',
    'error_store_not_found' => '店機賬號不存在',
    'error_currency_not_found' => '未找到貨幣信息',
    'error_ratio_invalid' => '匯率必須大於0',
    'error_device_not_found' => '設備不存在',
    'error_insufficient_balance' => '設備餘額不足',
    'error_player_not_found' => '玩家不存在',
    'error_offline_channel_only' => '該功能僅適用於線下管道',
    'error_no_game_platform' => '該管道未開啟任何電子遊戲平台',

    // 狀態選項
    'status_options' => [
        'normal' => '正常',
        'disabled' => '已禁用',
    ],

    // 標籤
    'tag' => [
        'not_set' => '未設置',
        'disabled' => '已禁用',
        'normal' => '正常',
        'hot' => '熱門',
        'new' => '新',
        'unknown_platform' => '未知平台',
    ],

    // 占位符
    'placeholder' => [
        'status' => '狀態',
        'login_account' => '登入帳號',
        'store_name' => '店家名稱',
        'contact_phone' => '聯繫電話',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
        'game_platform' => '遊戲平台',
        'is_hot' => '是否熱門',
        'is_new' => '是否新遊戲',
    ],

    // 篩選器
    'filter' => [
        'select_store' => '選擇店家',
        'select_agent' => '選擇代理',
    ],

    // 遊戲選項
    'game_options' => [
        'hot_games' => '熱門遊戲',
        'normal_games' => '普通遊戲',
        'new_games' => '新遊戲',
        'old_games' => '舊遊戲',
    ],

    // 按鈕
    'button' => [
        'save_selected_games' => '保存選擇的遊戲',
    ],

    // 確認
    'confirm' => [
        'save_games' => '確認保存？',
    ],

    // 成功消息
    'success_open_score' => '開分成功',
    'success_save' => '保存成功',

    // 錯誤消息 - 追加
    'error_save_failed' => '保存失敗',
    'error_operation_failed' => '操作失敗',

    // 開分備註
    'remark_store_open_score' => '店家後台開分',

    // JavaScript 文本
    'js' => [
        'conversion_preview' => '轉換預覽：',
        'please_enter_amount' => '請輸入金額',
        'points_unit' => '點',
        'exchange_rate_label' => '匯率：',
    ],

    // 遊戲相關
    'game' => [
        'game_id' => '遊戲 ID',
        'select_all_platform' => '【全選該平台所有遊戲】',
        'game_list_title' => '{platform} - 遊戲列表',
        'tip_select_games' => '提示: 選擇該玩家可以使用的電子遊戲。未選擇的遊戲將不會在客戶端展示。',
    ],

    // 遊戲權限管理
    'game_permission' => [
        'title' => '玩家遊戲權限管理 - {name}',
    ],

    // 其他
    'remark' => '備註',
    'select_placeholder' => '請選擇',
    'total' => '合計',
    'player_type_store' => '店家',
    'player_type_device' => '設備',
    'player' => '玩家',
    'admin' => '管理員',
    'type' => '類型',
    'all' => '全部',
    'total_profit_amount' => '總營收',
    'start_time' => '開始時間',
    'end_time' => '結束時間',
    'created_at' => '創建時間',
];