<?php

use addons\webman\model\PlayerPresentRecord;

return [
    'title' => 'Turn point record',
    'user_info' => 'Initiator information',
    'player_info' => 'Trading object',
    'fields' => [
        'id' => 'ID',
        'user_id' => 'initiator',
        'type' => 'Trading behavior',
        'player_id' => 'Transaction object',
        'department_id' => 'channel',
        'tradeno' => 'Order number',
        'amount' => 'Transaction amount',
        'user_origin_amount' => 'Points before transfer',
        'user_after_amount' => 'Points after transfer',
        'player_origin_amount' => 'Points before transfer',
        'player_after_amount' => 'Points after transfer',
        'created_at' => 'Transaction time',
    ],
    'type' => [
        PlayerPresentRecord::TYPE_IN => 'Transfer',
        PlayerPresentRecord::TYPE_OUT => 'Transfer'
    ],
    'search' => [
        'user_placeholder' => 'Please select the initiator',
        'player_placeholder' => 'Please select the transaction object',
        'placeholder' => 'Please enter keywords',
        'user_uuid' => 'Initiator UID',
        'user_name' => 'Initiator name',
        'user_phone' => 'Mobile phone number of the initiator',
        'player_uuid' => 'Transaction object UID',
        'player_name' => 'Transaction object name',
        'player_phone' => "Transaction partner's mobile phone number",
    ],
    'total_data' => [
        'total_icon_amount' => 'The currency dealer transfers the total game points',
        'total_player_amount' => 'Player transfers total game points',
    ],
    'user_type' => 'Initiator type',
    'player_type' => 'Transaction object type',
];
