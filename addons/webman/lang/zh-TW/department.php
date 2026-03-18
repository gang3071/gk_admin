<?php

use addons\webman\model\AdminDepartment;

return [
    'title' => '部門管理',
    'normal' => '正常',
    'disable' => '禁用',
    'parent_id_repeat' => '上級部門不能為本部門',
    'fields' => [
        'pid' => '上級部門',
        'name' => '部門名稱',
        'leader' => '負責人',
        'mobile' => '手機號',
        'status' => '狀態',
        'sort' => '排序',
        'create_at' => '創建時間',
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '總站管理員',
        AdminDepartment::TYPE_CHANNEL => '通路管理員',
        AdminDepartment::TYPE_AGENT => '代理管理員',
        AdminDepartment::TYPE_STORE => '店機管理員',
    ],
];
