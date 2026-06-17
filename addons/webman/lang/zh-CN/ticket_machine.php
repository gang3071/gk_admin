<?php

return [
    // 页面标题
    'title' => '出票机控制',
    'control_panel' => '出票机控制面板',

    // 连接状态
    'status' => [
        'connected' => '已连接',
        'disconnected' => '未连接',
        'connecting' => '连接中...',
        'error' => '连接错误',
    ],

    // 纸张状态
    'paper' => [
        'normal' => '正常',
        'empty' => '缺纸',
        'jam' => '卡纸',
    ],

    // 操作按钮
    'action' => [
        'connect' => '连接出票机',
        'disconnect' => '断开连接',
        'heartbeat' => '发送心跳',
        'sync_datetime' => '同步日期时间',
        'set_uid' => '设置唯一ID',
        'set_machine_no' => '设置机台号',
        'set_store_name' => '设置店名称',
        'set_serial_no' => '设置序列号',
        'send_lottery' => '发送彩票数据',
        'send_qr' => '发送QR码',
        'reset' => '复位设备',
        'query_status' => '查询状态',
        'init_machine' => '初始化设备',
        'send_hex' => '发送HEX指令',
    ],

    // 表单标签
    'field' => [
        'port' => '串口路径',
        'baud_rate' => '波特率',
        'uid' => '唯一ID',
        'machine_no' => '机台号',
        'store_name' => '店名称',
        'serial_no' => '打印序列号',
        'ticket_count' => '票数',
        'gift_count' => '赠送数',
        'code_table' => '码表',
        'number' => '数',
        'qr_code' => 'QR码内容',
        'hex_command' => 'HEX指令',
    ],

    // 帮助文本
    'help' => [
        'port' => 'Linux: /dev/ttyUSB0, Windows: COM3',
        'uid' => '16个字符的唯一标识',
        'machine_no' => '机台号范围: 0-65535',
        'store_name' => '最多10个字符',
        'serial_no' => '序列号范围: 0-9999999',
        'hex_command' => '空格分隔的HEX字符串，例如: FA EA 01 01 00 XX XX FB EB',
    ],

    // 消息
    'message' => [
        'connect_success' => '出票机连接成功',
        'connect_failed' => '出票机连接失败: {error}',
        'disconnect_success' => '出票机已断开连接',
        'heartbeat_success' => '心跳发送成功',
        'heartbeat_failed' => '心跳发送失败',
        'datetime_synced' => '日期时间已同步',
        'uid_set' => '唯一ID已设置',
        'machine_no_set' => '机台号已设置',
        'store_name_set' => '店名称已设置',
        'serial_no_set' => '序列号已设置',
        'lottery_sent' => '彩票数据已发送',
        'qr_sent' => 'QR码已发送',
        'reset_sent' => '复位指令已发送',
        'init_success' => '设备初始化完成',
        'hex_sent' => 'HEX指令已发送',
        'not_connected' => '出票机未连接',
        'service_unavailable' => '出票机服务不可用',
    ],

    // 日志
    'log' => [
        'title' => '通信日志',
        'send' => '发送',
        'receive' => '接收',
        'info' => '信息',
        'error' => '错误',
        'clear' => '清空日志',
    ],

    // 出票记录
    'record' => [
        'title' => '出票记录',
        'order_id' => '订单号',
        'store_name' => '店名',
        'machine_no' => '台号',
        'score' => '分数/金额',
        'ticket_type' => '票据类型',
        'type_recharge' => '开分',
        'type_withdraw' => '洗分',
        'qr_code' => '二维码信息',
        'qr_code_no' => '二维码编号',
        'status' => '状态',
        'status_disabled' => '禁用',
        'status_normal' => '正常',
        'status_printed' => '已打印',
        'status_used' => '已使用',
        'status_unknown' => '未知',
        'print_count' => '打印次数',
        'last_print_time' => '最后打印时间',
        'remark' => '备注',
        'created_at' => '创建时间',
    ],
];
