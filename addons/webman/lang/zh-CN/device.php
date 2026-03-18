<?php

return [
    // 页面标题
    'title' => '设备管理',
    'create' => '新增设备',
    'edit' => '编辑设备',
    'ip_list' => 'IP绑定列表',
    'ip_management' => 'IP管理',
    'add_ip' => '添加IP',
    'manage_ip' => '管理IP',
    'access_log_title' => '设备访问日志',

    // 字段
    'fields' => [
        'device_name' => '设备名称',
        'device_no' => '设备号',
        'device_no_help' => '安卓设备的唯一标识符（如：Android ID、IMEI等）',
        'device_model' => '设备型号',
        'channel_name' => '所属渠道',
        'department_name' => '所属部门',
        'agent_name' => '所属代理',
        'agent_help' => '选择设备所属的代理（仅线下渠道）',
        'store_name' => '所属店家',
        'store_help' => '选择设备所属的店家（仅线下渠道）',
        'status' => '状态',
        'remark' => '备注',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
        'ip_count' => 'IP数量',
        'ip_address' => 'IP地址',
        'ip_address_help' => '支持 IPv4 或 IPv6 格式',
        'ip_type' => 'IP类型',
        'last_used_at' => '最后使用时间',
        'is_allowed' => '访问结果',
        'reject_reason' => '拒绝原因',
        'request_url' => '请求URL',
        'user_agent' => 'User Agent',
    ],

    // 状态
    'status' => [
        'disabled' => '禁用',
        'enabled' => '启用',
    ],

    // 访问日志
    'access_log' => [
        'allowed' => '允许',
        'rejected' => '拒绝',
    ],

    // 选项
    'no_agent' => '无代理',
    'no_store' => '无店家',

    // 功能
    'batch_disable' => '批量关闭',
    'batch_disable_confirm' => '确定要批量关闭选中的设备吗？',
    'batch_disable_success' => '成功关闭 {count} 个设备',
    'batch_disable_failed' => '批量关闭设备失败',
    'no_device_selected' => '请先选择要关闭的设备',

    // 消息提示
    'device_no_exists' => '设备号已存在',
    'invalid_ip_address' => 'IP地址格式不正确',
    'ip_already_exists' => 'IP地址已存在',
    'delete_confirm' => '确定要删除该设备吗？删除后将同时删除所有绑定的IP地址。',
];
