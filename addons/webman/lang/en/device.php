<?php

return [
    // Page Title
    'title' => 'Player List',
    'create' => 'Add Player',
    'add_device' => 'Add Player',
    'edit_device' => 'Edit Player',
    'edit' => 'Edit Player',
    'ip_list' => 'IP Binding List',
    'ip_management' => 'IP Management',
    'add_ip' => 'Add IP',
    'manage_ip' => 'Manage IP',
    'access_log_title' => 'Device Access Log',

    // Fields
    'fields' => [
        'device_name' => 'Device Name',
        'device_no' => 'Device Number',
        'device_no_help' => 'Unique identifier for Android device (e.g., Android ID, IMEI, etc.)',
        'device_model' => 'Device Model',
        'voice_url' => 'Voice Broadcast',
        'channel_name' => 'Channel',
        'department_name' => 'Department',
        'agent_name' => 'Agent',
        'agent_help' => 'Select the agent for this device (offline channel only)',
        'store_name' => 'Store',
        'store_help' => 'Select the store for this device (offline channel only)',
        'status' => 'Status',
        'remark' => 'Remark',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'ip_count' => 'IP Count',
        'ip_address' => 'IP Address',
        'ip_address_help' => 'Supports IPv4 or IPv6 format',
        'ip_type' => 'IP Type',
        'last_used_at' => 'Last Used At',
        'is_allowed' => 'Access Result',
        'reject_reason' => 'Reject Reason',
        'request_url' => 'Request URL',
        'user_agent' => 'User Agent',
    ],

    // Status
    'status' => [
        'disabled' => 'Disabled',
        'enabled' => 'Enabled',
    ],

    // Access Log
    'access_log' => [
        'allowed' => 'Allowed',
        'rejected' => 'Rejected',
    ],

    // Options
    'no_agent' => 'No Agent',
    'no_store' => 'No Store',

    // Functions
    'batch_disable' => 'Batch Disable',
    'batch_disable_confirm' => 'Are you sure you want to disable the selected devices?',
    'batch_disable_success' => 'Successfully disabled {count} device(s)',
    'batch_disable_failed' => 'Failed to batch disable devices',
    'no_device_selected' => 'Please select devices to disable',

    // Voice Broadcast
    'voice' => [
        'not_generated' => 'Not Generated',
        'generated' => 'Voice Generated',
        'generate_failed' => 'Voice Generation Failed',
        'generate_error' => 'Voice Generation Error',
        'regenerate' => 'Regenerate Voice',
        'regenerate_confirm' => 'Are you sure you want to regenerate the voice broadcast file?',
        'regenerate_success' => 'Voice regenerated successfully',
    ],

    // Messages
    'device_no_exists' => 'Device number already exists',
    'device_no_help' => 'Unique device number, cannot be duplicated',
    'device_not_found' => 'Device not found',
    'invalid_ip_address' => 'Invalid IP address format',
    'ip_already_exists' => 'IP address already exists',
    'delete_confirm' => 'Are you sure you want to delete this device? All bound IP addresses will also be deleted.',
    'save_success' => 'Save successfully',
    'select_channel_first' => 'Please select channel first',
];
