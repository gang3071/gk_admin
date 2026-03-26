<?php

return [
    'title' => 'Agent Management',
    'offline_only' => 'This feature is only available for offline channels',

    // Field names
    'fields' => [
        'id' => 'ID',
        'name' => 'Agent Name',
        'username' => 'Login Account',
        'phone' => 'Contact Phone',
        'department_name' => 'Department Name',
        'status' => 'Status',
        'is_super' => 'Super Admin',
        'created_at' => 'Created At',
        'password' => 'Login Password',
        'password_confirmation' => 'Confirm Password',
        'avatar' => 'Upload Avatar',
    ],

    // Status
    'status' => [
        'normal' => 'Normal',
        'disabled' => 'Disabled',
    ],

    // Super Admin
    'is_super' => [
        'yes' => 'Yes',
        'no' => 'No',
    ],

    // Form
    'form' => [
        'create_title' => 'Create Agent',
        'create_hint' => 'After creating an agent, the agent can log in to the agent backend and manage subordinate stores',
        'section_account' => 'Account Information',
        'section_avatar' => 'Avatar Configuration',
        'section_password' => 'Password Configuration',
    ],

    // Placeholders
    'placeholder' => [
        'status' => 'Status',
        'username' => 'Login Account',
        'name' => 'Agent Name',
        'phone' => 'Contact Phone',
        'created_at' => 'Created Time',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
    ],

    // Help text
    'help' => [
        'phone' => 'Optional, for contact',
        'username' => 'Required, for logging into agent backend',
        'name' => 'Display name of the agent',
        'avatar' => 'Supports jpg, png formats, recommended size 200x200',
        'password' => 'Agent backend login password',
    ],

    // Validation rules
    'validation' => [
        'password_min' => 'Password must be at least 6 characters',
    ],

    // Error messages
    'error' => [
        'create_failed' => 'Failed to create agent: {error}',
    ],
];
