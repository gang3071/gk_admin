<?php

return [
    'title' => '遊戲平台分潤',
    'fields' => [
        'id' => 'ID',
        'player_id' => '玩家ID',
        'department_id' => '渠道ID',
        'promoter_player_id' => '推廣員玩家id',
        'total_bet' => '總押注',
        'total_win' => '總輸贏',
        'total_diff' => '玩家總輸贏額度',
        'total_reward' => '總獎金',
        'game_amount' => '分潤金額',
        'game_platform_ratio' => '遊戲平台分潤比值',
        'date' => '日期',
    ],
    'play_game_record' => '遊戲記錄',
    'total_diff_tip' => '總輸贏為該玩家在該平台一個結算日內總的輸贏金額',
    'game_amount_tip' => '分潤金額計算方式, 總的輸贏扣除遊戲平台收益剩餘的金額',
];
