<?php

return [
    'title' => '店家管理',
    'offline_only' => '此功能仅限线下渠道使用',
    'create_success' => '店家 {name} 创建成功！登录账号：{username}，{agent_label}：{agent_name}',
    'create_failed' => '创建店家失败：{error}',
    'welcome_message' => '欢迎使用店家后台系统！',

    // 列名
    'fields' => [
        'id' => 'ID',
        'name' => '店家名称',
        'username' => '登录账号',
        'phone' => '联系电话',
        'department_name' => '部门名称',
        'agent_commission' => '代理抽成',
        'channel_commission' => '渠道抽成',
        'status' => '状态',
        'created_at' => '创建时间',
        'parent_agent' => '上级代理',
        'password' => '登录密码',
        'password_confirmation' => '确认密码',
        'avatar' => '上传头像',
    ],

    // 状态
    'status' => [
        'normal' => '正常',
        'disabled' => '已禁用',
        'not_set' => '未设置',
    ],

    // 表单
    'form' => [
        'create_title' => '创建店家',
        'create_hint' => '创建店家后，该店家可登录店家后台',
        'section_account' => '账号信息',
        'section_parent_agent' => '上级代理',
        'section_avatar' => '头像配置',
        'section_password' => '密码配置',
        'select_parent_agent' => '选择上级代理',
    ],

    // 占位符
    'placeholder' => [
        'status' => '状态',
        'username' => '登录账号',
        'name' => '店家名称',
        'phone' => '联系电话',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
    ],

    // 帮助文字
    'help' => [
        'phone' => '选填，用于联系',
        'username' => '必填，用于登录店家后台',
        'name' => '店家的显示名称',
        'parent_agent' => '选择该店家的上级代理',
        'avatar' => '支持jpg、png格式，建议尺寸200x200',
        'password' => '店家后台登录密码',
    ],

    // 验证规则
    'validation' => [
        'password_min' => '密码至少6位',
    ],

    // 错误消息
    'error' => [
        'offline_only' => '此功能仅限线下渠道使用',
        'avatar_required' => '请上传头像',
        'password_mismatch' => '两次密码输入不一致',
        'parent_agent_not_found' => '上级代理不存在',
        'username_exists' => '登录账号 {username} 已存在',
    ],

    // 自动交班配置
    'auto_shift' => [
        'morning_title' => '早班',
        'afternoon_title' => '中班',
        'night_title' => '晚班',
        'morning_desc' => '早班自动交班（08:00-16:00）',
        'afternoon_desc' => '中班自动交班（16:00-24:00）',
        'night_desc' => '晚班自动交班（00:00-08:00）',
    ],
];
