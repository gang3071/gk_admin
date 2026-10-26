<?php

return [
    'endpoint' => env('CLICKHOUSE_ENDPOINT', 'http://127.0.0.1:8123'),
    'user'     => env('CLICKHOUSE_USER', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', ''),
    'database' => env('CLICKHOUSE_DATABASE', 'machine_logs'),
];
