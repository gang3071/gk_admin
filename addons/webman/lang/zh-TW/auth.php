<?php

use addons\webman\model\AdminDepartment;

return [
    'title' => '訪問許可權管理',
    'parent' => '父級',
    'field_title_grant' => '欄位許可權（隱藏選中的欄位）',
    'field_grant' => '欄位許可權',
    'data_grant' => '數據許可權',
    'auth_grant' => '功能許可權',
    'menu_grant' => '選單許可權',
    'select_user' => '選個人',
    'select_group' => '選組織',
    'select_user_tip' => '具有包含所選人員的查看數據許可權',
    'select_group_tip' => '具有包含所選組織的查看數據數據許可權',
    'all' => '全選',
    'father_son_linkage' => '父子聯動',
    'role_type_error' => '角色類型錯誤',
    'fields' => [
        'name' => '名稱',
        'desc' => '描述',
        'status' => '狀態',
        'sort' => '排序',
        'data_type' => '數據範圍',
        'department' => '部門清單',
        'type' => '角色類型',
    ],
    'options' => [
        'data_type' => [
            'full_data_rights' => '全部數據許可權',
            'data_permissions_for_this_department' => '本部門數據許可權',
            'this_department_and_the_following_data_permissions' => '本部門及以下數據許可權',
            'personal_data_rights' => '本人數據許可權',
            'custom_data_permissions' => '自定義數據許可權',
            'channel_and_the_following_data_permissions' => '子站全部數據許可權',
            'agent_and_the_following_data_permissions' => '代理全部數據許可權'
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '總站角色',
        AdminDepartment::TYPE_CHANNEL => '通路角色',
        AdminDepartment::TYPE_AGENT => '代理角色',
        AdminDepartment::TYPE_STORE => '店機角色',
    ],
];
