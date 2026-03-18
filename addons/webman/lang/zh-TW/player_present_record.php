<?php

use addons\webman\model\PlayerPresentRecord;

return [
    'title' => '轉點記錄',
    'user_info' => '發起者資訊',
    'player_info' => '交易對象',
    'fields' => [
        'id' => 'ID',
        'user_id' => '發起者',
        'type' => '交易行為',
        'player_id' => '交易對象',
        'department_id' => '通路',
        'tradeno' => '單號',
        'amount' => '交易金額',
        'user_origin_amount' => '轉出前點數',
        'user_after_amount' => '轉出後點數',
        'player_origin_amount' => '轉入前點數',
        'player_after_amount' => '轉入後點數',
        'created_at' => '交易時間',
    ],
    'type' => [
        PlayerPresentRecord::TYPE_IN => '轉入',
        PlayerPresentRecord::TYPE_OUT => '轉出'
    ],
    'search' => [
        'user_placeholder' => '請選擇發起人',
        'player_placeholder' => '請選擇交易對象',
        'placeholder' => '請輸入關鍵字',
        'user_uuid' => '發起人UID',
        'user_name' => '發起人名稱',
        'user_phone' => '發起人手機號',
        'player_uuid' => '交易對象UID',
        'player_name' => '交易對象名稱',
        'player_phone' => '交易對象手機號',
    ],
    'total_data' => [
        'total_icon_amount' => '幣商轉出總遊戲點',
        'total_player_amount' => '玩家轉出總遊戲點',
    ],
    'user_type' => '發起者類型',
    'player_type' => '交易對象類型',
];
