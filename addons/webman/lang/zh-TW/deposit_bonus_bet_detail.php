<?php

return [
    'title' => '押碼量明細',

    'fields' => [
        'id' => 'ID',
        'order_no' => '訂單號',
        'player_account' => '玩家賬號',
        'player_info' => '玩家信息',
        'game_type' => '遊戲類型',
        'game_platform' => '遊戲平台',
        'game_name' => '遊戲名稱',
        'bet_amount' => '押注金額',
        'valid_bet_amount' => '有效押注',
        'win_amount' => '贏取金額',
        'balance_before' => '押注前餘額',
        'balance_after' => '押注後餘額',
        'accumulated_bet' => '累計打碼量',
        'new_accumulated_bet' => '本次後累計',
        'bet_time' => '押注時間',
        'settle_time' => '結算時間',
        'created_at' => '創建時間',
    ],

    'stats' => [
        'total_count' => '記錄總數',
        'total_bet' => '押注總額',
        'total_valid_bet' => '有效押注總額',
        'total_win' => '贏取總額',
    ],

    'game_type_slot' => '老虎機',
    'game_type_electron' => '電子遊戲',
    'game_type_baccarat' => '真人百家',
    'game_type_lottery' => '彩票',
];
