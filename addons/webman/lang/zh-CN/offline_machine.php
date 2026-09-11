<?php

return [
    'title' => '线下机台管理',

    'fields' => [
        'code' => '机台编号',
        'name' => '机台名称',
        'label' => '机台标签',
        'type' => '机台类型',
        'channel' => '所属渠道',
        'store' => '绑定店家',
        'ip' => '机台IP',
        'port' => '机台端口',
        'domain' => '机台Domain',
        'control_type' => '工控类型',
        'status' => '状态',
        'gaming' => '游戏状态',
        'sort' => '排序',
        'remark' => '备注',
    ],

    'status' => [
        'unassigned' => '未分配',
        'unbound' => '未绑定',
        'gaming' => '游戏中',
        'idle' => '空闲',
    ],

    'error' => [
        'no_media_config' => '线下机台不支持直播流配置',
        'not_offline_machine' => '该机台不是线下机台，无法编辑',
    ],

    'command_test' => [
        'danger_command_title' => '危险指令警告',
        'danger_command_content' => '您即将执行危险指令：{name}',
        'danger_command_desc' => '指令说明：{desc}',
        'danger_command_confirm' => '确定要执行此指令吗？',
        'cancel' => '取消',
        'confirm' => '确定执行',
        'success' => '指令执行成功：{name}',
        'failed' => '指令执行失败：{msg}',
        'request_failed' => '请求失败：{error}',
        'machine_status' => '机台状态',
        'player_status' => '玩家状态',
        'current_data' => '当前数据',
        'idle' => '空闲中',
        'gaming' => '游戏中',
        'player_name' => '玩家',
        'refresh' => '刷新数据',
        'refreshing' => '刷新中...',
        'refresh_success' => '数据已刷新',
        'no_player' => '无玩家使用',
    ],
];
