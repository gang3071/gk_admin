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

    'command_test' => [
        'danger_command_title' => '危險指令警告',
        'danger_command_content' => '您即將執行危險指令：{name}',
        'danger_command_desc' => '指令說明：{desc}',
        'danger_command_confirm' => '確定要執行此指令嗎？',
        'cancel' => '取消',
        'confirm' => '確定執行',
        'success' => '指令執行成功：{name}',
        'failed' => '指令執行失敗：{msg}',
        'request_failed' => '請求失敗：{error}',
        'machine_status' => '機台狀態',
        'player_status' => '玩家狀態',
        'current_data' => '當前數據',
        'idle' => '空閒中',
        'gaming' => '遊戲中',
        'player_name' => '玩家',
        'refresh' => '刷新數據',
        'refreshing' => '刷新中...',
        'refresh_success' => '數據已刷新',
        'no_player' => '無玩家使用',
    ],
];
