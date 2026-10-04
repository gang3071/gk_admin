<?php

return [
    'title' => 'ID Card Blacklist',

    'fields' => [
        'id_number'  => 'ID Number',
        'player_name' => 'Player Name',
        'admin_name' => 'Operator',
        'remark'     => 'Remark',
        'created_at' => 'Added At',
        'status'     => 'Blacklist Status',
    ],

    'action' => [
        'add' => 'Add to Blacklist',
    ],

    'placeholder' => [
        'id_number' => 'Enter ID number',
        'remark'    => 'Enter remark',
    ],

    'message' => [
        'add_success'      => 'Successfully added to blacklist',
        'not_blacklisted'  => '✓ Not in blacklist',
    ],

    'error' => [
        'id_number_required'     => 'ID number is required',
        'id_number_in_blacklist' => 'This ID number is blacklisted and cannot be used to create a player',
    ],

    'filter' => [
        'id_number' => 'ID Number',
    ],
];
