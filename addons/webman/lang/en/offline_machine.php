<?php

return [
    'title' => 'Offline Machines',

    'fields' => [
        'code' => 'Machine Code',
        'name' => 'Machine Name',
        'label' => 'Machine Label',
        'type' => 'Machine Type',
        'channel' => 'Channel',
        'store' => 'Bound Store',
        'ip' => 'Machine IP',
        'port' => 'Machine Port',
        'domain' => 'Machine Domain',
        'control_type' => 'Control Type',
        'status' => 'Status',
        'gaming' => 'Gaming Status',
        'sort' => 'Sort',
        'remark' => 'Remark',
    ],

    'status' => [
        'unassigned' => 'Unassigned',
        'unbound' => 'Unbound',
        'gaming' => 'Gaming',
        'idle' => 'Idle',
    ],

    'error' => [
        'no_media_config' => 'Offline machines do not support live stream configuration',
        'not_offline_machine' => 'This machine is not an offline machine and cannot be edited',
    ],

    'command_test' => [
        'danger_command_title' => 'Dangerous Command Warning',
        'danger_command_content' => 'You are about to execute a dangerous command: {name}',
        'danger_command_desc' => 'Command description: {desc}',
        'danger_command_confirm' => 'Are you sure you want to execute this command?',
        'cancel' => 'Cancel',
        'confirm' => 'Execute',
        'success' => 'Command executed successfully: {name}',
        'failed' => 'Command execution failed: {msg}',
        'request_failed' => 'Request failed: {error}',
        'machine_status' => 'Machine Status',
        'player_status' => 'Player Status',
        'current_data' => 'Current Data',
        'idle' => 'Idle',
        'gaming' => 'Gaming',
        'player_name' => 'Player',
        'refresh' => 'Refresh Data',
        'refreshing' => 'Refreshing...',
        'refresh_success' => 'Data refreshed',
        'no_player' => 'No Player',
    ],

    'one_way_verify' => [
        // 46ccb4 - Fault Troubleshooting
        'fault_normal' => 'Machine status is normal, no fault',
        'fault_cleared' => 'Fault cleared (DA normal status)',
        'fault_not_cleared' => 'Fault not cleared, machine still in fault status, please check hardware',
        'fault_abnormal' => 'Status abnormal: was normal but became faulty',

        // 46ccb3 - Clear External Button Counter
        'external_already_zero' => 'External button counter is already 0, no need to clear',
        'external_cleared' => 'External button counter cleared to 0 (B5={open}, B7={wash})',
        'external_both_not_cleared' => 'Both B5 and B7 counters not cleared (B5={open}, B7={wash})',
        'external_open_not_cleared' => 'B5 open counter not cleared (current={count})',
        'external_wash_not_cleared' => 'B7 wash counter not cleared (current={count})',

        // 46ccba - Clear Bet Value
        'score_already_zero' => 'Bet value is already 0, no need to clear',
        'score_cleared' => 'Bet value cleared to 0',
        'score_not_cleared' => 'Bet value not cleared (current score={score})',
    ],
];
