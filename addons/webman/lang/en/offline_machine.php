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
];
