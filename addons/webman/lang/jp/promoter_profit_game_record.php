<?php

return [
    'title' => 'ゲームプラットフォーム利益',
    'fields' => [
        'id' => 'ID',
        'player_id' => 'プレーヤーID',
        'department_id' => 'チャネルID',
        'promoter_player_id' => 'プロモータープレーヤーID',
        'total_bet' => '総ベット',
        'total_win' => '総勝利',
        'total_diff' => 'プレーヤー総勝敗',
        'total_reward' => '総報酬',
        'game_amount' => '利益金額',
        'game_platform_ratio' => 'ゲームプラットフォーム利益率',
        'date' => '日付',
    ],
    'play_game_record' => 'ゲーム記録',
    'total_diff_tip' => '総勝敗はプレーヤーがこのプラットフォームで1つの決済日における総勝敗金額です',
    'game_amount_tip' => '利益金額の計算方法：総勝敗からゲームプラットフォーム収益を差し引いた残額',
];
