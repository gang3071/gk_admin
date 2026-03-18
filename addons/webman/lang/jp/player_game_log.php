<?php

use addons\webman\model\PlayerGameLog;

return [
    'title' => 'ゲームログ',
    'point_title' => '上下ポイント記録',
    'player_info' => 'プレイヤー情報',
    'machine_info' => 'マシン情報',
    'player_wallet_info' => 'プレイヤーウォレット',
    'machine_data' => 'マシンデータ',
    'fields' => [
        'id' => '番号',
        'game_id' => 'ゲーム分類',
        'player_id' => 'プレイヤーID',
        'Department_id' => '部門/チャネルID',
        'machine_id' => 'マシンID',
        'type' => 'タイプ',
        'odds' => '比率',
        'open_point' => '上の点',
        'wash_point' => '下位ポイント',
        'gift_point' => 'ギフトポイント',
        'before_game_amount' => '現在のウォレット',
        'game_amount' => 'ゲームポイント',
        'after_game_amount' => 'ウォレットの残り',
        'machine_amount' => 'マシンスコア',
        'control_open_point' => '工業用制御圧力点',
        'pressure' => '圧力',
        'score' => 'スコア',
        'create_at' => '作成時刻',
        'turn_point' => 'ターン数',
        'created_at_start' => '開始時刻',
        'created_at_end' => '終了時刻',
        'action_type' => 'アクションソース',
        'action' => 'アクション',
        'chip_amount' => '打ヤード量',
    ],
    'total_data' => [
        'total_game_amount' => '総ゲームポイント',
        'total_open_point' => '合計ポイント',
        'total_wash_point' => '合計ポイント',
        'total_pressure' => '総圧力',
        'total_score' => '合計スコア',
        'total_turn_point' => '合計ターン数',
        'total_chip_amount' => '総打符号量',
        'total_wash_amount' => '下分ゲームポイント',
        'total_open_amount' => '上分ゲームポイント',
    ],
    'type' => [
        'system' => 'システム自動',
        'admin' => '管理者',
        'player' => 'プレイヤー',
    ],
    'action' => [
        PlayerGameLog::ACTION_OPEN => '開分',
        PlayerGameLog::ACTION_LEAVE => '廃棄',
        PlayerGameLog::ACTION_DOWN => '下分',
    ]
];
