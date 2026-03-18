<?php

use addons\webman\model\AdminDepartment;

return [
    'title' => '部门管理',
    'normal'=>'正常',
    'disable'=>'禁用',
    'parent_id_repeat'=>'上级部门不能为本部门',
    'fields' => [
        'pid' => '上级部门',
        'name' => '部门名称',
        'leader' => '负责人',
        'mobile' => '手机号',
        'status' => '状态',
        'sort' => '排序',
        'create_at' => '创建时间',
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '总站管理员',
        AdminDepartment::TYPE_CHANNEL => '渠道管理员',
        AdminDepartment::TYPE_AGENT => '代理管理员',
        AdminDepartment::TYPE_STORE => '店家管理员',
    ],
];
