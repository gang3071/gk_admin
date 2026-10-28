<?php

return [
    'title' => '榜单详情',
    'fields' => [
        'id' => 'ID',
        'leaderboard_id' => '排行榜',
        'player_id' => '玩家',
        'store_admin_id' => '门店',
        'rank' => '名次',
        'score' => '分数',
        'prize_amount' => '奖金',
        'grant_status' => '发放状态',
        'granted_at' => '发放时间',
        'created_at' => '创建时间',
        'updated_at' => '更新时间'
    ],
    'grant_status' => [
        0 => '未发放',
        1 => '已发放'
    ],
    'granted_at' => [
        'start' => '发放开始时间',
        'end' => '发放结束时间'
    ],
    'buttonGrant' => '发放奖励',
    'confirmGrant' => '确定发放排行榜奖励？',
    'errorGrant' => '数据错误，请确认排行榜期别已结束、奖金大于 0 且未发放过',
    'errorGrantPermissions' => '无权操作',
    'successGrant' => '发放成功',
];

