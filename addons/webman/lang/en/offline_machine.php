<?php

return [
    'title' => 'Offline Machines',

    'fields' => [
        'code' => 'Machine Code',
        'name' => 'Machine Name',
        'label' => 'Machine Label',
        'type' => 'Machine Type',
        'channel' => 'Channel',
        'store' => 'Bound Store',
        'ip' => 'Machine IP',
        'port' => 'Machine Port',
        'domain' => 'Machine Domain',
        'control_type' => 'Control Type',
        'status' => 'Status',
        'gaming' => 'Gaming Status',
        'sort' => 'Sort',
        'remark' => 'Remark',
    ],

    'status' => [
        'unassigned' => 'Unassigned',
        'unbound' => 'Unbound',
        'gaming' => 'Gaming',
        'idle' => 'Idle',
    ],

    'error' => [
        'no_media_config' => 'Offline machines do not support live stream configuration',
        'not_offline_machine' => 'This machine is not an offline machine and cannot be edited',
    ],
];
