<?php

return [
    'title' => 'ベット量詳細',

    'fields' => [
        'id' => 'ID',
        'order_no' => '注文番号',
        'player_account' => 'プレーヤーアカウント',
        'player_info' => 'プレーヤー情報',
        'game_type' => 'ゲームタイプ',
        'game_platform' => 'ゲームプラットフォーム',
        'game_name' => 'ゲーム名',
        'bet_amount' => 'ベット金額',
        'valid_bet_amount' => '有効ベット',
        'win_amount' => '勝利金額',
        'balance_before' => 'ベット前残高',
        'balance_after' => 'ベット後残高',
        'accumulated_bet' => '累計ベット量',
        'new_accumulated_bet' => '今回後の累計',
        'bet_time' => 'ベット時間',
        'settle_time' => '決済時間',
        'created_at' => '作成日時',
    ],

    'stats' => [
        'total_count' => '総記録数',
        'total_bet' => '総ベット',
        'total_valid_bet' => '総有効ベット',
        'total_win' => '総勝利',
    ],

    'game_type_slot' => 'スロットマシン',
    'game_type_electron' => '電子ゲーム',
    'game_type_baccarat' => 'ライブバカラ',
    'game_type_lottery' => '宝くじ',
];
