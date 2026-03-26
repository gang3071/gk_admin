<?php

return [
    'title' => 'Store Management',
    'offline_only' => 'This feature is only available for offline channels',
    'create_success' => 'Store {name} created successfully! Login account: {username}, {agent_label}: {agent_name}',
    'create_failed' => 'Failed to create store: {error}',
    'welcome_message' => 'Welcome to the Store Admin System!',

    // Column names
    'fields' => [
        'id' => 'ID',
        'name' => 'Store Name',
        'username' => 'Login Account',
        'phone' => 'Contact Phone',
        'department_name' => 'Department Name',
        'agent_commission' => 'Agent Commission',
        'channel_commission' => 'Channel Commission',
        'status' => 'Status',
        'created_at' => 'Created At',
        'parent_agent' => 'Parent Agent',
        'password' => 'Login Password',
        'password_confirmation' => 'Confirm Password',
        'avatar' => 'Upload Avatar',
    ],

    // Status
    'status' => [
        'normal' => 'Normal',
        'disabled' => 'Disabled',
        'not_set' => 'Not Set',
    ],

    // Form
    'form' => [
        'create_title' => 'Create Store',
        'create_hint' => 'After creating a store, the store can log in to the store admin',
        'section_account' => 'Account Information',
        'section_parent_agent' => 'Parent Agent',
        'section_avatar' => 'Avatar Configuration',
        'section_password' => 'Password Configuration',
        'select_parent_agent' => 'Select Parent Agent',
    ],

    // Placeholders
    'placeholder' => [
        'status' => 'Status',
        'username' => 'Login Account',
        'name' => 'Store Name',
        'phone' => 'Contact Phone',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
    ],

    // Help text
    'help' => [
        'phone' => 'Optional, for contact',
        'username' => 'Required, for logging into store admin',
        'name' => 'Display name of the store',
        'parent_agent' => 'Select the parent agent for this store',
        'avatar' => 'Supports jpg, png formats, recommended size 200x200',
        'password' => 'Store admin login password',
    ],

    // Validation rules
    'validation' => [
        'password_min' => 'Password must be at least 6 characters',
    ],

    // Error messages
    'error' => [
        'offline_only' => 'This feature is only available for offline channels',
        'avatar_required' => 'Please upload an avatar',
        'password_mismatch' => 'The two passwords do not match',
        'parent_agent_not_found' => 'Parent agent not found',
        'username_exists' => 'Login account {username} already exists',
    ],

    // Auto shift configuration
    'auto_shift' => [
        'morning_title' => 'Morning Shift',
        'afternoon_title' => 'Afternoon Shift',
        'night_title' => 'Night Shift',
        'morning_desc' => 'Morning shift auto handover (08:00-16:00)',
        'afternoon_desc' => 'Afternoon shift auto handover (16:00-24:00)',
        'night_desc' => 'Night shift auto handover (00:00-08:00)',
    ],
];
