<?php

use addons\webman\model\AdminDepartment;

return [
    'title'=>'访问权限管理',
    'parent'=>'父级',
    'field_title_grant'=>'字段权限（隐藏选中的字段）',
    'field_grant'=>'字段权限',
    'data_grant'=>'数据权限',
    'auth_grant'=>'功能权限',
    'menu_grant'=>'菜单权限',
    'select_user'=>'选个人',
    'select_group'=>'选组织',
    'select_user_tip'=>'具有包含所选人员的查看数据权限',
    'select_group_tip'=>'具有包含所选组织的查看数据数据权限',
    'all'=>'全选',
    'father_son_linkage'=>'父子联动',
    'role_type_error'=>'角色类型错误',
    'fields'=>[
        'name'=>'名称',
        'desc'=>'描述',
        'status'=>'状态',
        'sort'=>'排序',
        'data_type'=>'数据范围',
        'department'=>'部门列表',
        'type'=>'角色类型',
        'is_protected'=>'系统角色',
    ],
    'options'=>[
        'data_type'=>[
            'full_data_rights' => '全部数据权限',
            'data_permissions_for_this_department' => '本部门数据权限',
            'this_department_and_the_following_data_permissions' => '本部门及以下数据权限',
            'personal_data_rights' => '本人数据权限',
            'custom_data_permissions' => '自定义数据权限',
            'channel_and_the_following_data_permissions' => '子站全部数据权限',
            'agent_and_the_following_data_permissions' => '代理全部数据权限'
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '总站角色',
        AdminDepartment::TYPE_CHANNEL => '渠道角色',
        AdminDepartment::TYPE_AGENT => '代理角色',
        AdminDepartment::TYPE_STORE => '店家角色',
    ],
    'tag' => [
        'built_in_role' => '内置角色',
        'custom_role' => '自定义',
    ],
];
