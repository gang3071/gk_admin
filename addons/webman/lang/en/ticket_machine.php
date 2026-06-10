<?php

return [
    // Page title
    'title' => 'Ticket Machine Control',
    'control_panel' => 'Ticket Machine Control Panel',

    // Connection status
    'status' => [
        'connected' => 'Connected',
        'disconnected' => 'Disconnected',
        'connecting' => 'Connecting...',
        'error' => 'Connection Error',
    ],

    // Paper status
    'paper' => [
        'normal' => 'Normal',
        'empty' => 'No Paper',
        'jam' => 'Paper Jam',
    ],

    // Action buttons
    'action' => [
        'connect' => 'Connect',
        'disconnect' => 'Disconnect',
        'heartbeat' => 'Send Heartbeat',
        'sync_datetime' => 'Sync Date/Time',
        'set_uid' => 'Set UID',
        'set_machine_no' => 'Set Machine No.',
        'set_store_name' => 'Set Store Name',
        'set_serial_no' => 'Set Serial No.',
        'send_lottery' => 'Send Lottery Data',
        'send_qr' => 'Send QR Code',
        'reset' => 'Reset Device',
        'query_status' => 'Query Status',
        'init_machine' => 'Initialize Device',
        'send_hex' => 'Send HEX Command',
    ],

    // Form labels
    'field' => [
        'port' => 'Serial Port',
        'baud_rate' => 'Baud Rate',
        'uid' => 'Unique ID',
        'machine_no' => 'Machine Number',
        'store_name' => 'Store Name',
        'serial_no' => 'Serial Number',
        'ticket_count' => 'Ticket Count',
        'gift_count' => 'Gift Count',
        'code_table' => 'Code Table',
        'number' => 'Number',
        'qr_code' => 'QR Code Content',
        'hex_command' => 'HEX Command',
    ],

    // Help text
    'help' => [
        'port' => 'Linux: /dev/ttyUSB0, Windows: COM3',
        'uid' => '16-character unique identifier',
        'machine_no' => 'Range: 0-65535',
        'store_name' => 'Max 10 characters',
        'serial_no' => 'Range: 0-9999999',
        'hex_command' => 'Space-separated HEX string, e.g.: FA EA 01 01 00 XX XX FB EB',
    ],

    // Messages
    'message' => [
        'connect_success' => 'Ticket machine connected successfully',
        'connect_failed' => 'Connection failed: {error}',
        'disconnect_success' => 'Disconnected',
        'heartbeat_success' => 'Heartbeat sent',
        'heartbeat_failed' => 'Heartbeat failed',
        'datetime_synced' => 'Date/Time synced',
        'uid_set' => 'UID set',
        'machine_no_set' => 'Machine number set',
        'store_name_set' => 'Store name set',
        'serial_no_set' => 'Serial number set',
        'lottery_sent' => 'Lottery data sent',
        'qr_sent' => 'QR code sent',
        'reset_sent' => 'Reset command sent',
        'init_success' => 'Device initialized',
        'hex_sent' => 'HEX command sent',
        'not_connected' => 'Not connected',
        'service_unavailable' => 'Service unavailable',
    ],

    // Log
    'log' => [
        'title' => 'Communication Log',
        'send' => 'Send',
        'receive' => 'Receive',
        'info' => 'Info',
        'error' => 'Error',
        'clear' => 'Clear Log',
    ],
];
