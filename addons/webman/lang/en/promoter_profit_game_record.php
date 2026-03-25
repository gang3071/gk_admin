<?php

return [
    'title' => 'Game Platform Profit',
    'fields' => [
        'id' => 'ID',
        'player_id' => 'Player ID',
        'department_id' => 'Channel ID',
        'promoter_player_id' => 'Promoter Player ID',
        'total_bet' => 'Total Bet',
        'total_win' => 'Total Win',
        'total_diff' => 'Player Total Win/Loss',
        'total_reward' => 'Total Reward',
        'game_amount' => 'Profit Amount',
        'game_platform_ratio' => 'Game Platform Profit Ratio',
        'date' => 'Date',
    ],
    'play_game_record' => 'Game Records',
    'total_diff_tip' => 'Total win/loss is the total win/loss amount of the player on this platform in one settlement day',
    'game_amount_tip' => 'Profit amount calculation method: the remaining amount after deducting game platform revenue from total win/loss',
];