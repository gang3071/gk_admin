<?php

return [
    'title' => 'ID Card Blacklist',

    'fields' => [
        'id_number'  => 'ID Number',
        'player_name' => 'Player Name',
        'admin_name' => 'Operator',
        'remark'     => 'Remark',
        'created_at' => 'Added At',
    ],

    'action' => [
        'add' => 'Add to Blacklist',
    ],

    'placeholder' => [
        'id_number' => 'Enter ID number',
        'remark'    => 'Enter remark',
    ],

    'message' => [
        'add_success' => 'Successfully added to blacklist',
    ],

    'error' => [
        'id_number_required' => 'ID number is required',
    ],

    'filter' => [
        'id_number' => 'ID Number',
    ],
];
