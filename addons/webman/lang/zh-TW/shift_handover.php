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
];
