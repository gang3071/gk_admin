<?php

return [
    'title' => '游戏平台分润',
    'fields' => [
        'id' => 'ID',
        'player_id' => '玩家ID',
        'department_id' => '渠道ID',
        'promoter_player_id' => '推广员玩家id',
        'total_bet' => '总押注',
        'total_win' => '总输赢',
        'total_diff' => '玩家总輸贏額度',
        'total_reward' => '总奖金',
        'game_amount' => '分润金额',
        'game_platform_ratio' => '游戏平台分润比值',
        'date' => '日期',
    ],
    'play_game_record' => '游戏记录',
    'total_diff_tip' => '总输赢为该玩家在该平台一个结算日内总的输赢金额',
    'game_amount_tip' => '分润金额计算方式, 总的输赢扣除游戏平台收益剩余的金额',
];
