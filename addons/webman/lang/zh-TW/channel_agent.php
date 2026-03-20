<?php

return [
    // 標題
    'title' => '店家/設備管理',
    'store_list' => '店家列表',
    'device_list' => '設備列表',
    'delivery_record' => '賬變記錄',
    'shift_handover_record' => '交班報表',

    // 字段
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

    // 成功消息
    'success_open_score' => '開分成功',

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