<?php
/**
 * 实体机台配置（gk_admin 共享配置，与 gk_work/config/machine.php 保持同步）
 */
return [
    // ========== 小松線下機台開分選項 ==========
    // 換算：player_amount = machine_score × odds_x / odds_y
    // min_player_amount：玩家單次上分最低金額（分）
    'song_offline' => [
        // key: 比值字串（玩家:機台）；value: 對應的機台積分、比值、玩家最低單次上分金額
        'open_options' => [
             '3:1'   => ['machine_score' =>  100, 'odds_x' =>  3, 'odds_y' =>  1, 'min_player_amount' =>  300],
             '5:2'   => ['machine_score' =>   40, 'odds_x' =>  5, 'odds_y' =>  2, 'min_player_amount' =>  100],
            '10:1'   => ['machine_score' =>   10, 'odds_x' => 10, 'odds_y' =>  1, 'min_player_amount' =>  100],
             '5:1'   => ['machine_score' =>   20, 'odds_x' =>  5, 'odds_y' =>  1, 'min_player_amount' =>  100],
             '4:1'   => ['machine_score' =>   25, 'odds_x' =>  4, 'odds_y' =>  1, 'min_player_amount' =>  100],
             '2:1'   => ['machine_score' =>   50, 'odds_x' =>  2, 'odds_y' =>  1, 'min_player_amount' =>  100],
             '1:1'   => ['machine_score' =>  100, 'odds_x' =>  1, 'odds_y' =>  1, 'min_player_amount' =>  100],
             '1:2'   => ['machine_score' =>  200, 'odds_x' =>  1, 'odds_y' =>  2, 'min_player_amount' =>  100],
             '1:3'   => ['machine_score' =>  300, 'odds_x' =>  1, 'odds_y' =>  3, 'min_player_amount' =>  100],
             '1:4'   => ['machine_score' =>  400, 'odds_x' =>  1, 'odds_y' =>  4, 'min_player_amount' =>  100],
             '1:5'   => ['machine_score' =>  500, 'odds_x' =>  1, 'odds_y' =>  5, 'min_player_amount' =>  100],
             '1:8'   => ['machine_score' =>  800, 'odds_x' =>  1, 'odds_y' =>  8, 'min_player_amount' =>  100],
            '1:10'   => ['machine_score' => 1000, 'odds_x' =>  1, 'odds_y' => 10, 'min_player_amount' =>  100],
        ],
    ],
];
