<?php

return [
    'title' => 'Leaderboard Details',
    'fields' => [
        'id' => 'ID',
        'leaderboard_id' => 'Leaderboard',
        'player_id' => 'Player',
        'store_admin_id' => 'Store',
        'rank' => 'Rank',
        'score' => 'Score',
        'prize_amount' => 'Prize Amount',
        'grant_status' => 'Distribution Status',
        'granted_at' => 'Distributed At',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At'
    ],
    'grant_status' => [
        0 => 'Pending',
        1 => 'Distributed'
    ],
    'granted_at' => [
        'start' => 'Distribution Start Time',
        'end' => 'Distribution End Time'
    ],
];

