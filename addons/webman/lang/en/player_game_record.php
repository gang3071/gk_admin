<?php

use addons\webman\model\PlayerGameRecord;

return [
    'title' => 'Machine Game Records',
    'fields' => [
        'id' => 'ID',
        'game_id' => 'Game ID',
        'machine_id' => 'Machine ID',
        'player_id' => 'Player ID',
        'machine_name' => 'Machine Name',
        'machine_code' => 'Machine Code',
        'player_name' => 'Player Name',
        'player_uuid' => 'Player UUID',
        'type' => 'Type',
        'open_point' => 'Open Point',
        'wash_point' => 'Wash Point',
        'open_amount' => 'Machine Open Amount',
        'wash_amount' => 'Machine Wash Amount',
        'after_game_amount' => 'After Game Amount',
        'give_amount' => 'Give Amount',
        'odds' => 'Odds',
        'status' => 'Status',
        'national_damage_ratio' => 'National Damage Ratio',
        'vip_level_id' => 'VIP Level',
        'cashback_ratio' => 'Cashback Ratio',
        'cashback_amount' => 'Cashback Amount',
        'chip_amount' => 'Chip Amount',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'status' => [
        PlayerGameRecord::STATUS_START => 'In Progress',
        PlayerGameRecord::STATUS_END => 'Ended',
    ],
];
