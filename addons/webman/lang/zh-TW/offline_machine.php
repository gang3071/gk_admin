<?php

return [
    'title' => '線下機台管理',

    'fields' => [
        'code' => '機台編號',
        'name' => '機台名稱',
        'label' => '機台標籤',
        'type' => '機台類型',
        'channel' => '所屬渠道',
        'store' => '綁定店家',
        'ip' => '機台IP',
        'port' => '機台端口',
        'domain' => '機台Domain',
        'control_type' => '工控類型',
        'status' => '狀態',
        'gaming' => '遊戲狀態',
        'sort' => '排序',
        'remark' => '備註',
    ],

    'status' => [
        'unassigned' => '未分配',
        'unbound' => '未綁定',
        'gaming' => '遊戲中',
        'idle' => '空閒',
    ],

    'error' => [
        'no_media_config' => '線下機台不支持直播流配置',
        'not_offline_machine' => '該機台不是線下機台，無法編輯',
    ],
];
