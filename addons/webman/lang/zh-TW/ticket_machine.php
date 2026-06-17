<?php

return [
    // 页面标题
    'title' => '出票機控制',
    'control_panel' => '出票機控制面板',

    // 連接狀態
    'status' => [
        'connected' => '已連接',
        'disconnected' => '未連接',
        'connecting' => '連接中...',
        'error' => '連接錯誤',
    ],

    // 紙張狀態
    'paper' => [
        'normal' => '正常',
        'empty' => '缺紙',
        'jam' => '卡紙',
    ],

    // 操作按鈕
    'action' => [
        'connect' => '連接出票機',
        'disconnect' => '斷開連接',
        'heartbeat' => '發送心跳',
        'sync_datetime' => '同步日期時間',
        'set_uid' => '設置唯一ID',
        'set_machine_no' => '設置機台號',
        'set_store_name' => '設置店名稱',
        'set_serial_no' => '設置序列號',
        'send_lottery' => '發送彩票數據',
        'send_qr' => '發送QR碼',
        'reset' => '復位設備',
        'query_status' => '查詢狀態',
        'init_machine' => '初始化設備',
        'send_hex' => '發送HEX指令',
    ],

    // 表單標籤
    'field' => [
        'port' => '串口路徑',
        'baud_rate' => '波特率',
        'uid' => '唯一ID',
        'machine_no' => '機台號',
        'store_name' => '店名稱',
        'serial_no' => '打印序列號',
        'ticket_count' => '票數',
        'gift_count' => '贈送數',
        'code_table' => '碼表',
        'number' => '數',
        'qr_code' => 'QR碼內容',
        'hex_command' => 'HEX指令',
    ],

    // 幫助文本
    'help' => [
        'port' => 'Linux: /dev/ttyUSB0, Windows: COM3',
        'uid' => '16個字符的唯一標識',
        'machine_no' => '機台號範圍: 0-65535',
        'store_name' => '最多10個字符',
        'serial_no' => '序列號範圍: 0-9999999',
        'hex_command' => '空格分隔的HEX字符串，例如: FA EA 01 01 00 XX XX FB EB',
    ],

    // 消息
    'message' => [
        'connect_success' => '出票機連接成功',
        'connect_failed' => '出票機連接失敗: {error}',
        'disconnect_success' => '出票機已斷開連接',
        'heartbeat_success' => '心跳發送成功',
        'heartbeat_failed' => '心跳發送失敗',
        'datetime_synced' => '日期時間已同步',
        'uid_set' => '唯一ID已設置',
        'machine_no_set' => '機台號已設置',
        'store_name_set' => '店名稱已設置',
        'serial_no_set' => '序列號已設置',
        'lottery_sent' => '彩票數據已發送',
        'qr_sent' => 'QR碼已發送',
        'reset_sent' => '復位指令已發送',
        'init_success' => '設備初始化完成',
        'hex_sent' => 'HEX指令已發送',
        'not_connected' => '出票機未連接',
        'service_unavailable' => '出票機服務不可用',
    ],

    // 日誌
    'log' => [
        'title' => '通信日誌',
        'send' => '發送',
        'receive' => '接收',
        'info' => '信息',
        'error' => '錯誤',
        'clear' => '清空日誌',
    ],

    // 出票記錄
    'record' => [
        'title' => '出票記錄',
        'order_id' => '訂單號',
        'store_name' => '店名',
        'machine_no' => '台號',
        'score' => '分數/金額',
        'ticket_type' => '票據類型',
        'type_recharge' => '開分',
        'type_withdraw' => '洗分',
        'qr_code' => '二維碼信息',
        'qr_code_no' => '二維碼編號',
        'status' => '狀態',
        'status_disabled' => '禁用',
        'status_normal' => '正常',
        'status_printed' => '已列印',
        'status_used' => '已使用',
        'status_unknown' => '未知',
        'print_count' => '列印次數',
        'last_print_time' => '最後列印時間',
        'remark' => '備註',
        'created_at' => '創建時間',
    ],
];
