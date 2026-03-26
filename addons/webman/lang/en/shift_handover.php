<?php

return [
    'title' => 'Shift Handover',
    'auto_shift_status_enabled' => 'Auto Shift: Enabled',
    'auto_shift_status_disabled' => 'Auto Shift: Disabled',
    'auto_shift_enabled' => 'Auto Shift Enabled',
    'auto_shift_enabled_desc' => 'Automatic shift handover is enabled, manual shift handover is not allowed.',
    'auto_shift_close_hint' => 'If you need to manually hand over shifts, please turn off the auto shift function in "Auto Shift Config".',
    'goto_auto_shift_config' => 'Go to Auto Shift Config',
    'manual_shift_disabled' => 'Manual Shift Disabled',
    'shift_failed' => 'Shift failed: ',
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

    // Shift Handover Records
    'record' => [
        'title' => 'Shift Handover Records',
        'id' => 'ID',
        'time_range' => 'Statistical Time Range',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'shift_type' => 'Shift Type',
        'auto_shift' => 'Auto Shift',
        'manual_shift' => 'Manual Shift',
        'machine_amount' => 'Machine Amount',
        'machine_point' => 'Machine Points',
        'total_in' => 'Total Income',
        'total_out' => 'Total Outgo',
        'total_profit' => 'Total Profit',
        'created_at' => 'Created At',
        'detail_title' => 'Shift Details',
        'auto_shift_log_id' => 'Auto Shift Log ID',
        'filter_shift_type' => 'Shift Type',
        'filter_time_range' => 'Time Range',
        'filter_start_date' => 'Start Date',
        'filter_end_date' => 'End Date',
    ],

    // Auto Shift
    'auto' => [
        'title' => 'Auto Shift Configuration',
        'enable' => 'Enable Auto Shift',
        'enable_help' => 'When enabled, the system will automatically execute shifts at specified times each day and push notifications to the store backend',
        'shift_time_1' => 'Morning Shift Time',
        'shift_time_1_help' => 'Morning shift time (Night → Morning), Recommended: 08:00',
        'shift_time_2' => 'Afternoon Shift Time',
        'shift_time_2_help' => 'Afternoon shift time (Morning → Afternoon), Recommended: 16:00',
        'shift_time_3' => 'Night Shift Time',
        'shift_time_3_help' => 'Night shift time (Afternoon → Night), Recommended: 00:00',
        'config_title' => 'Shift Configuration',
        'exec_info' => 'Execution Info',
        'next_shift_time' => 'Next Shift Time',
        'config_save_hint' => 'After saving the configuration, the system will automatically calculate the next shift time',
        'quick_actions' => 'Quick Actions',
        'view_logs' => 'View Execution Logs',
        'manual_trigger' => 'Manual Trigger',
        'manual_trigger_confirm' => 'Are you sure you want to execute an automatic shift now?\n\nThis will not affect the scheduled execution plan.',
        'save_success' => 'Save Successful',
        'save_failed' => 'Save Failed',

        // Execution Statistics
        'stats_title' => 'Last 7 Days Execution Statistics',
        'stats_total' => 'Total Executions',
        'stats_success' => 'Successful',
        'stats_failed' => 'Failed',
        'stats_success_rate' => 'Success Rate',
        'stats_times' => 'times',

        // Log List
        'logs_title' => 'Auto Shift Execution Logs',
        'log_id' => 'ID',
        'execute_time' => 'Execution Time',
        'time_range' => 'Statistics Period',
        'time_start' => 'Start',
        'time_end' => 'End',
        'status' => 'Execution Status',
        'status_success' => 'Success',
        'status_failed' => 'Failed',
        'status_partial' => 'Partial Success',
        'status_unknown' => 'Unknown',
        'machine_point' => 'Cash-in Points',
        'total_in' => 'Total Income',
        'total_out' => 'Total Expense',
        'lottery_amount' => 'Lottery Amount',
        'total_profit' => 'Total Profit',
        'execution_duration' => 'Execution Duration',
        'error_message' => 'Error Message',

        // Log Details
        'detail_title' => 'Execution Details',
        'config_id' => 'Config ID',
        'shift_record_id' => 'Shift Record ID',
        'time_range_start' => 'Statistics Start Time',
        'time_range_end' => 'Statistics End Time',
        'machine_amount' => 'Machine Cash-in Amount',
        'machine_point_detail' => 'Machine Cash-in Points',
        'total_in_detail' => 'Total Income (Deposit)',
        'total_out_detail' => 'Total Expense (Withdrawal)',
        'lottery_amount_detail' => 'Lottery Payout',
        'total_profit_detail' => 'Total Profit',
        'execute_time_detail' => 'Execution Time',
        'execution_duration_detail' => 'Execution Duration',
        'error_message_detail' => 'Error Message',
        'seconds' => 'seconds',

        // Filter
        'filter_status' => 'Execution Status',
        'filter_date_start' => 'Start Date',
        'filter_date_end' => 'End Date',
        'filter_execute_time' => 'Execution Time',

        // Manual Trigger
        'trigger_no_config' => 'Auto shift configuration not found, please complete the configuration first',
        'trigger_not_enabled' => 'Auto shift is not enabled, please enable it before manually triggering',
        'trigger_success' => 'Manual trigger successful! Shift completed, please check the execution logs.',
        'trigger_failed' => 'Manual trigger failed',

        // Others
        'log_not_found' => 'Log not found',
        'config_not_found' => 'Configuration not found',
    ],

    // Export Related
    'shift_id' => 'Shift ID',
    'shift_time' => 'Shift Time',
    'shift_type' => 'Shift Type',
    'auto_shift' => 'Auto Shift',
    'manual_shift' => 'Manual Shift',
    'machine_point' => 'Machine Points',
    'lottery_amount' => 'Lottery',
    'total_in' => 'Total In',
    'total_out' => 'Total Out',
    'profit' => 'Profit',
    'device_name' => 'Device Name',
    'device_number' => 'Device No.',
    'recharge_amount' => 'Recharge',
    'withdrawal_amount' => 'Withdrawal',
    'modified_add_amount' => 'Admin Add',
    'modified_deduct_amount' => 'Admin Deduct',
    'subtotal' => 'Subtotal',
    'no_device_data' => 'No Device Details (Use Shift Summary)',
    'grand_total' => 'Grand Total',
    'total_shifts' => 'Total',
    'shifts' => 'Shifts',
    'devices' => 'Devices',
    'all_devices_summary' => 'All Devices Summary',
    'devices_unit' => '',
    'export_note' => 'Note: Total data is aggregated from all device details; if a shift record has no device details, shift summary data is used.',
    'details_title' => '▼ Shift Record Details ▼',

    // Device Detail Export
    'export' => [
        'title' => 'Device Details Export - Shift Record ID: {id}',
        'start_time_label' => 'Start Time:',
        'end_time_label' => 'End Time:',
        'shift_type_label' => 'Shift Type:',
        'machine_point_label' => 'Machine Points:',
        'lottery_amount_label' => 'Lottery:',
        'total_in_label' => 'Total Income:',
        'total_out_label' => 'Total Expense:',
        'total_profit_label' => 'Total Profit:',
        'no_device_data' => 'No Device Details',
        'device_detail_note' => 'Note: This report is a device detail export, export time: {time}',
        'subtotal_devices' => 'Subtotal ({count} Devices)',
    ],

    // Transaction Details
    'transaction' => [
        'detail_title' => '{name} - Transaction Details ({count} Records)',
        'time' => 'Time',
        'type' => 'Type',
        'amount' => 'Amount',
        'remark' => 'Remark',

        // Transaction Types
        'type_recharge' => 'Recharge',
        'type_withdrawal' => 'Withdrawal',
        'type_lottery' => 'Lottery',
        'type_add_point' => 'Admin Add',
        'type_deduct_point' => 'Admin Deduct',
    ],

    // Labels (with colon)
    'label' => [
        'start' => 'Start:',
        'end' => 'End:',
        'shift_type' => 'Shift Type:',
        'log_id' => 'Log ID:',
        'machine_amount' => 'Machine Amount:',
        'machine_point' => 'Machine Points:',
        'total_in' => 'Total Income:',
        'total_out' => 'Total Expense:',
        'total_profit' => 'Total Profit:',
        'created_at' => 'Created At:',
    ],

    // Actions
    'action' => [
        'view_detail' => 'View Details',
        'operation' => 'Operation',
    ],

    // Filters
    'filter' => [
        'time_range' => 'Time Range',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
    ],

    // Device Details
    'device' => [
        'detail_not_found' => 'Device Details - Record Not Found',
        'detail_title' => 'Device Details',
        'device_count' => 'Device Count',
        'detail_data' => 'Device Detail Data',
        'label' => [
            'device_name' => 'Device Name:',
            'device_number' => 'Device No.:',
            'machine_point' => 'Machine Points:',
            'recharge_amount' => 'Recharge Amount:',
            'withdrawal_amount' => 'Withdrawal Amount:',
            'backend_add_amount' => 'Admin Add:',
            'backend_deduct_amount' => 'Admin Deduct:',
            'lottery_amount' => 'Lottery Payout:',
            'total_in' => 'Total Income:',
            'total_out' => 'Total Expense:',
            'device_profit' => 'Device Profit:',
        ],
    ],
];
