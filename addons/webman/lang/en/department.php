<?php

use addons\webman\model\AdminDepartment;

return [
    'title' => 'Department Management',
    'normal' => 'normal',
    'disable' => 'disable',
    'parent_id_repeat' => 'The superior department cannot be this department',
    'fields' => [
        'pid' => 'Superior department',
        'name' => 'Department name',
        'leader' => 'person in charge',
        'mobile' => 'Mobile phone number',
        'status' => 'status',
        'sort' => 'sort',
        'create_at' => 'Creation time',
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => 'Headmaster',
        AdminDepartment::TYPE_CHANNEL => 'Channel Administrator',
        AdminDepartment::TYPE_AGENT => 'Agent Administrator',
        AdminDepartment::TYPE_STORE => 'Store Administrator',
    ],
];
