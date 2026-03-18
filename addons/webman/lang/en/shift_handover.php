<?php

return [
    'title' => 'Shift Handover',
    'auto_shift_enabled' => 'Auto Shift Enabled',
    'auto_shift_enabled_desc' => 'Automatic shift handover is enabled, manual shift handover is not allowed.',
    'auto_shift_close_hint' => 'If you need to manually hand over shifts, please turn off the auto shift function in "Auto Shift Config".',
    'goto_auto_shift_config' => 'Go to Auto Shift Config',
    'manual_shift_disabled' => 'Manual Shift Disabled',
    'shift_time' => 'Shift Time',
    'shift_time_help' => 'Shift time cannot exceed current time, cannot select time within the past 5 days, cannot select the time range of the last shift',
    'start_time' => 'Start Time',
    'end_time' => 'End Time',
    'none' => 'None',
    'last_shift_time' => 'Last Shift Time',

    // Error messages
    'error' => [
        'end_time_future' => 'End time cannot exceed current time',
        'start_time_future' => 'Start time cannot be in the future',
        'start_gte_end' => 'Start time must be earlier than end time',
        'time_range_too_long' => 'Shift time range cannot exceed 30 days',
        'duplicate_record' => 'A shift record already exists for this time range, please do not resubmit',
        'config_error' => 'System configuration error, please contact administrator',
        'shift_success' => 'Shift handover successful',
        'shift_failed' => 'Shift handover failed',
    ],

    // Field descriptions
    'fields' => [
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'machine_amount' => 'Machine Amount',
        'machine_point' => 'Machine Points',
        'total_in' => 'Total In',
        'total_out' => 'Total Out',
        'total_profit_amount' => 'Total Profit',
    ],
];
