<?php

return [
    'title' => '代理管理',
    'offline_only' => '此功能仅限线下渠道使用',

    // 字段名称
    'fields' => [
        'id' => 'ID',
        'name' => '代理名称',
        'username' => '登录账号',
        'phone' => '联系电话',
        'department_name' => '部门名称',
        'status' => '状态',
        'is_super' => '超级管理员',
        'created_at' => '创建时间',
        'password' => '登录密码',
        'password_confirmation' => '确认密码',
        'avatar' => '上传头像',
    ],

    // 状态
    'status' => [
        'normal' => '正常',
        'disabled' => '已禁用',
    ],

    // 超级管理员
    'is_super' => [
        'yes' => '是',
        'no' => '否',
    ],

    // 表单
    'form' => [
        'create_title' => '创建代理',
        'create_hint' => '创建代理后，该代理可登录代理后台，管理下级店家',
        'section_account' => '账号信息',
        'section_avatar' => '头像配置',
        'section_password' => '密码配置',
    ],

    // 占位符
    'placeholder' => [
        'status' => '状态',
        'username' => '登录账号',
        'name' => '代理名称',
        'phone' => '联系电话',
        'created_at' => '创建时间',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
    ],

    // 帮助文字
    'help' => [
        'phone' => '选填，用于联系',
        'username' => '必填，用于登录代理后台',
        'name' => '代理的显示名称',
        'avatar' => '支持jpg、png格式，建议尺寸200x200',
        'password' => '代理后台登录密码',
    ],

    // 验证规则
    'validation' => [
        'password_min' => '密码至少6位',
    ],

    // 错误消息
    'error' => [
        'create_failed' => '创建代理失败：{error}',
    ],
];
