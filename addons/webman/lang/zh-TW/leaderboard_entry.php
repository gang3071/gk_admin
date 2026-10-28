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
        1 => '已領取',
        2 => '待領取'
    ],
    'granted_at' => [
        'start' => '發放開始時間',
        'end' => '發放結束時間'
    ],
    'buttonGrant' => '發放獎勵',
    'confirmGrant' => '確定發放排行榜獎勵？',
    'errorGrant' => '數據錯誤，請確認排行榜期別已結束、獎金大於 0 且未發放過',
    'errorGrantPermissions' => '無權操作',
    'successGrant' => '已建立待領取通知',
];
