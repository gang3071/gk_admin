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
        'diff' => 'win/loss amount',
        'balance_before' => 'Balance Before Bet',
        'balance_after' => 'Balance After Bet',
        'reward' => 'bonus (not counted towards winning)',
        'order_no' => 'Order Number (Game Platform)',
        'status' => 'state',
        'settlement_status' => 'Settlement Status',
        'platform_action_at' => 'settlement time (game platform)',
        'action_at' => 'settlement time',
        'create_at' => 'creation time',
        'vip_level_id' => 'VIP Level',
        'cashback_ratio' => 'Cashback Ratio',
        'cashback_amount' => 'Cashback Amount',
    ],
    'status' => [
        PlayGameRecord::STATUS_UNSETTLED => 'Undistributed',
        PlayGameRecord::STATUS_SETTLED => 'already distributed',
    ],
    'settlement_status' => [
        PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED => 'Unsettled',
        PlayGameRecord::SETTLEMENT_STATUS_SETTLED => 'Settled',
        PlayGameRecord::SETTLEMENT_STATUS_CANCELLED => 'Cancelled',
        PlayGameRecord::SETTLEMENT_STATUS_CONFIRM => 'Confirm',
    ],
    'all_bet' => 'total bet',
    'all_diff' => 'total wins and losses',
    'all_reward' => 'Total Bonus',
];
