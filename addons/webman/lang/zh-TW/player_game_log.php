<?php

use addons\webman\model\PlayerGameLog;

return [
    'title' => '遊戲日誌',
    'point_title' => '上下分記錄',
    'player_info' => '玩家資訊',
    'machine_info' => '機台資訊',
    'player_wallet_info' => '玩家錢包',
    'machine_data' => '機台數據',
    'fields' => [
        'id' => '編號',
        'game_id' => '遊戲分類',
        'player_id' => '玩家ID',
        'department_id' => '部門/通路ID',
        'machine_id' => '機台ID',
        'type' => '類型',
        'odds' => '比值',
        'open_point' => '上分',
        'wash_point' => '下分',
        'gift_point' => '外贈分數',
        'before_game_amount' => '現時錢包',
        'game_amount' => '遊戲點',
        'after_game_amount' => '剩餘錢包',
        'machine_amount' => '機台分數',
        'control_open_point' => '工控壓分',
        'pressure' => '押分',
        'score' => '得分',
        'create_at' => '創建時間',
        'turn_point' => '轉數',
        'created_at_start' => '開始時間',
        'created_at_end' => '結束時間',
        'action_type' => '操作來源',
        'action' => '操作',
        'chip_amount' => '打碼量',
    ],
    'total_data' => [
        'total_game_amount' => '總遊戲點',
        'total_open_point' => '總上分',
        'total_wash_point' => '總下分',
        'total_pressure' => '總押分',
        'total_score' => '總得分',
        'total_turn_point' => '總轉數',
        'total_chip_amount' => '總打碼量',
        'total_wash_amount' => '下分遊戲點數',
        'total_open_amount' => '上分遊戲點數',
    ],
    'type' => [
        'system' => '系統自動',
        'admin' => '管理員',
        'player' => '玩家',
    ],
    'action' => [
        PlayerGameLog::ACTION_OPEN => '開分',
        PlayerGameLog::ACTION_LEAVE => '弃臺',
        PlayerGameLog::ACTION_DOWN => '下分',
    ]
];
