<?php

return [
    'title' => '榜單詳情',
    'fields' => [
        'id' => 'ID',
        'leaderboard_id' => '排行榜',
        'player_id' => '玩家',
        'store_admin_id' => '門店',
        'rank' => '名次',
        'score' => '分數',
        'prize_amount' => '獎金',
        'grant_status' => '發放狀態',
        'granted_at' => '發放時間',
        'created_at' => '創建時間',
        'updated_at' => '更新時間'
    ],
    'grant_status' => [
        0 => '未發放',
        1 => '已發放'
    ],
    'granted_at' => [
        'start' => '發放開始時間',
        'end' => '發放結束時間'
    ],
];
