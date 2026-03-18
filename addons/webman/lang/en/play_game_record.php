<?php

use addons\webman\model\PlayGameRecord;

return [
    'title' => 'Player Game Records',
    'replay' => 'replay',
    'fields' => [
        'id' => 'ID',
        'game_code' => 'Game ID',
        'bet' => 'bet amount',
        'win' => 'win amount',
        'reward' => 'bonus (not counted towards winning)',
        'order_no' => 'Order Number (Game Platform)',
        'status' => 'state',
        'platform_action_at' => 'settlement time (game platform)',
        'action_at' => 'settlement time',
        'create_at' => 'creation time',
    ],
    'status' => [
        PlayGameRecord::STATUS_UNSETTLED => 'Undistributed',
        PlayGameRecord::STATUS_SETTLED => 'already distributed',
    ],
    'all_bet' => 'total bet',
    'all_diff' => 'total wins and losses',
    'all_reward' => 'Total Bonus',
];
