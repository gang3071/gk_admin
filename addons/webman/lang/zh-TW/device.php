<?php

return [
    // 頁面標題
    'title' => '設備列表',
    'create' => '新增設備',
    'add_device' => '添加設備',
    'edit_device' => '編輯設備',
    'edit' => '編輯設備',
    'ip_list' => 'IP綁定列表',
    'ip_management' => 'IP管理',
    'add_ip' => '添加IP',
    'manage_ip' => '管理IP',
    'access_log_title' => '設備訪問日誌',

    // 字段
    'fields' => [
        'device_name' => '設備名稱',
        'device_no' => '設備號',
        'device_no_help' => '安卓設備的唯一標識符（如：Android ID、IMEI等）',
        'device_model' => '設備型號',
        'device_type' => '設備類型',
        'voice_url' => '語音播報',
        'channel_name' => '所屬渠道',
        'department_name' => '所屬部門',
        'agent_name' => '所屬代理',
        'agent_help' => '選擇設備所屬的代理（僅線下渠道）',
        'store_name' => '所屬店家',
        'store_help' => '選擇設備所屬的店家（僅線下渠道）',
        'status' => '狀態',
        'remark' => '備註',
        'created_at' => '創建時間',
        'updated_at' => '更新時間',
        'ip_count' => 'IP數量',
        'ip_address' => 'IP地址',
        'ip_address_help' => '支持 IPv4 或 IPv6 格式',
        'ip_type' => 'IP類型',
        'last_used_at' => '最後使用時間',
        'is_allowed' => '訪問結果',
        'reject_reason' => '拒絕原因',
        'request_url' => '請求URL',
        'user_agent' => 'User Agent',
    ],

    // 狀態
    'status' => [
        'disabled' => '禁用',
        'enabled' => '啟用',
    ],

    // 設備類型
    'type' => [
        'game_machine' => '遊戲機',
        'vending_machine' => '儲值機',
    ],

    // 訪問日誌
    'access_log' => [
        'allowed' => '允許',
        'rejected' => '拒絕',
    ],

    // 選項
    'no_agent' => '無代理',
    'no_store' => '無店家',

    // 功能
    'batch_disable' => '批量關閉',
    'batch_disable_confirm' => '確定要批量關閉選中的設備嗎？',
    'batch_disable_success' => '成功關閉 {count} 個設備',
    'batch_disable_failed' => '批量關閉設備失敗',
    'no_device_selected' => '請先選擇要關閉的設備',

    // 語音播報
    'voice' => [
        'not_generated' => '未生成',
        'generated' => '語音已生成',
        'generate_failed' => '語音生成失敗',
        'generate_error' => '語音生成異常',
        'regenerate' => '重新生成語音',
        'regenerate_confirm' => '確定要重新生成語音播報文件嗎？',
        'regenerate_success' => '語音重新生成成功',
    ],

    // 消息提示
    'device_no_exists' => '設備號已存在',
    'device_no_help' => '設備的唯一編號，不可重複',
    'device_not_found' => '設備不存在',
    'invalid_ip_address' => 'IP地址格式不正確',
    'ip_already_exists' => 'IP地址已存在',
    'delete_confirm' => '確定要刪除該設備嗎？刪除後將同時刪除所有綁定的IP地址。',
    'save_success' => '保存成功',
    'select_channel_first' => '請先選擇渠道',
];
