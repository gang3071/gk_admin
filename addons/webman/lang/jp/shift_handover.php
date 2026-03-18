<?php

return [
    'title' => 'シフト引継ぎ',
    'auto_shift_enabled' => '自動交代が有効になっています',
    'auto_shift_enabled_desc' => 'システムは自動交代機能が有効になっており、手動交代操作はできません。',
    'auto_shift_close_hint' => '手動で交代する必要がある場合は、「自動交代設定」で自動交代機能をオフにしてください。',
    'goto_auto_shift_config' => '自動交代設定へ',
    'manual_shift_disabled' => '手動交代が無効',
    'shift_time' => '交代時間',
    'shift_time_help' => '交代時間は現在時刻を超えることはできません。過去5日以内の時間を選択できません。前回の交代時間の範囲を選択できません',
    'start_time' => '開始時間',
    'end_time' => '終了時間',
    'none' => 'なし',
    'last_shift_time' => '前回の交代時間',

    // エラーメッセージ
    'error' => [
        'end_time_future' => '終了時間は現在時刻を超えることはできません',
        'start_time_future' => '開始時間は未来の時間にはできません',
        'start_gte_end' => '開始時間は終了時間より前でなければなりません',
        'time_range_too_long' => '交代時間範囲は30日を超えることはできません',
        'duplicate_record' => 'この時間範囲の交代記録がすでに存在します。再送信しないでください',
        'config_error' => 'システム設定エラー、管理者にお問い合わせください',
        'shift_success' => '交代成功',
        'shift_failed' => '交代失敗',
    ],

    // フィールドの説明
    'fields' => [
        'start_time' => '開始時間',
        'end_time' => '終了時間',
        'machine_amount' => 'マシン金額',
        'machine_point' => 'マシンポイント',
        'total_in' => '合計入金',
        'total_out' => '合計出金',
        'total_profit_amount' => '合計利益',
    ],
];
