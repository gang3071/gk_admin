<?php

use addons\webman\model\AdminDepartment;

return [
    'title' => 'Access Rights Management',
    'parent' => 'parent',
    'field_title_grant' => 'Field permission (hide selected field)',
    'field_grant' => 'Field permission',
    'data_grant' => 'data permission',
    'auth_grant' => 'Functional permission',
    'menu_grant' => 'Menu permission',
    'select_user' => 'Select a person',
    'select_group' => 'select organization',
    'select_user_tip' => 'Have permission to view data including the selected person',
    'select_group_tip' => 'Have permission to view data containing the selected organization',
    'all' => 'select all',
    'father_son_linkage' => 'Father and son linkage',
    'role_type_error' => 'Role type error',
    'fields' => [
        'name' => 'name',
        'desc' => 'description',
        'status' => 'status',
        'sort' => 'sort',
        'data_type' => 'data range',
        'department' => 'department list',
        'type' => 'role type',
    ],
    'options' => [
        'data_type' => [
            'full_data_rights' => 'Full data rights',
            'data_permissions_for_this_department' => 'Data permissions for this department',
            'this_department_and_the_following_data_permissions' => 'This department and the following data permissions',
            'personal_data_rights' => 'Personal data rights',
            'custom_data_permissions' => 'Custom data permissions',
            'channel_and_the_following_data_permissions' => 'All data permissions for substations',
            'agent_and_the_following_data_permissions' => 'All data permissions for agent'
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => 'Terminal role',
        AdminDepartment::TYPE_CHANNEL => 'Channel role',
        AdminDepartment::TYPE_AGENT => 'Agent role',
        AdminDepartment::TYPE_STORE => 'Store role',
    ],
];
