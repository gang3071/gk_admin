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
];
