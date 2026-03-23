<?php

return [
    'title' => '交班管理',
    'auto_shift_enabled' => '已開啟自動交班',
    'auto_shift_enabled_desc' => '系統已啟用自動交班功能，無法進行手動交班操作。',
    'auto_shift_close_hint' => '如需手動交班，請先到「自動交班配置」中關閉自動交班功能。',
    'goto_auto_shift_config' => '前往自動交班配置',
    'manual_shift_disabled' => '手動交班已停用',
    'shift_time' => '交班時間',
    'shift_time_help' => '交班時間不能超過當前時間, 無法選擇過去5天內的時間, 不能選擇上次交班時間的範圍',
    'start_time' => '開始時間',
    'end_time' => '結束時間',
    'none' => '無',
    'last_shift_time' => '上次交班時間',

    // 錯誤訊息
    'error' => [
        'end_time_future' => '結束時間不能超過當前時間',
        'start_time_future' => '開始時間不能是未來時間',
        'start_gte_end' => '開始時間必須早於結束時間',
        'time_range_too_long' => '交班時間範圍不能超過30天',
        'duplicate_record' => '該時間範圍已存在交班記錄，請勿重複提交',
        'config_error' => '系統配置錯誤，請聯繫管理員',
        'shift_success' => '交班成功',
        'shift_failed' => '交班失敗',
    ],

    // 欄位說明
    'fields' => [
        'start_time' => '開始時間',
        'end_time' => '結束時間',
        'machine_amount' => '機台金額',
        'machine_point' => '機台積分',
        'total_in' => '總上分',
        'total_out' => '總下分',
        'total_profit_amount' => '總盈利',
    ],

    // 自動交班
    'auto' => [
        'title' => '自動交班配置',
        'enable' => '啟用自動交班',
        'enable_help' => '啟用後，系統將在每天的指定時間自動執行交班，並即時推送通知到店家後台',
        'shift_time_1' => '早班交班時間',
        'shift_time_1_help' => '早班交班時間（晚班 → 早班），建議：08:00',
        'shift_time_2' => '中班交班時間',
        'shift_time_2_help' => '中班交班時間（早班 → 中班），建議：16:00',
        'shift_time_3' => '晚班交班時間',
        'shift_time_3_help' => '晚班交班時間（中班 → 晚班），建議：00:00',
        'config_title' => '交班配置',
        'exec_info' => '執行資訊',
        'next_shift_time' => '下次交班時間',
        'config_save_hint' => '配置儲存後，系統將自動計算下次交班時間',
        'quick_actions' => '快捷操作',
        'view_logs' => '查看執行日誌',
        'manual_trigger' => '手動觸發一次',
        'manual_trigger_confirm' => '確定要立即執行一次自動交班嗎？\n\n這不會影響定時執行計畫。',
        'save_success' => '儲存成功',
        'save_failed' => '儲存失敗',

        // 執行統計
        'stats_title' => '最近7天執行統計',
        'stats_total' => '總執行次數',
        'stats_success' => '成功次數',
        'stats_failed' => '失敗次數',
        'stats_success_rate' => '成功率',
        'stats_times' => '次',

        // 日誌列表
        'logs_title' => '自動交班執行日誌',
        'log_id' => 'ID',
        'execute_time' => '執行時間',
        'time_range' => '統計時間段',
        'time_start' => '開始',
        'time_end' => '結束',
        'status' => '執行狀態',
        'status_success' => '成功',
        'status_failed' => '失敗',
        'status_partial' => '部分成功',
        'status_unknown' => '未知',
        'machine_point' => '投鈔點數',
        'total_in' => '總收入',
        'total_out' => '總支出',
        'lottery_amount' => '彩金金額',
        'total_profit' => '總利潤',
        'execution_duration' => '執行耗時',
        'error_message' => '錯誤訊息',

        // 日誌詳情
        'detail_title' => '執行詳情',
        'config_id' => '配置ID',
        'shift_record_id' => '交班記錄ID',
        'time_range_start' => '統計開始時間',
        'time_range_end' => '統計結束時間',
        'machine_amount' => '機台投鈔金額',
        'machine_point_detail' => '機台投鈔點數',
        'total_in_detail' => '總收入（送分）',
        'total_out_detail' => '總支出（取分）',
        'lottery_amount_detail' => '彩金發放',
        'total_profit_detail' => '總利潤',
        'execute_time_detail' => '執行時間',
        'execution_duration_detail' => '執行耗時',
        'error_message_detail' => '錯誤訊息',
        'seconds' => '秒',

        // 篩選
        'filter_status' => '執行狀態',
        'filter_date_start' => '開始日期',
        'filter_date_end' => '結束日期',
        'filter_execute_time' => '執行時間',

        // 手動觸發
        'trigger_no_config' => '未找到自動交班配置，請先完成配置',
        'trigger_not_enabled' => '自動交班未啟用，請先啟用後再手動觸發',
        'trigger_success' => '手動觸發成功！交班已完成，請查看執行日誌。',
        'trigger_failed' => '手動觸發失敗',

        // 其他
        'log_not_found' => '日誌不存在',
        'config_not_found' => '未找到配置',
    ],
];
