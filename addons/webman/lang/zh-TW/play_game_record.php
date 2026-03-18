<?php

use addons\webman\model\PlayGameRecord;

return [
    'title' => '玩家遊戲記錄',
    'replay' => '回放',
    'fields' => [
        'id' => 'ID',
        'game_code' => '遊戲編號',
        'bet' => '押注額',
        'win' => '贏取額',
        'reward' => '獎金（不計入贏取）',
        'order_no' => '單號（遊戲平臺）',
        'status' => '狀態',
        'platform_action_at' => '結算時間（遊戲平臺）',
        'action_at' => '結算時間',
        'create_at' => '創建時間',
    ],
    'status' => [
        PlayGameRecord::STATUS_UNSETTLED => '未分潤',
        PlayGameRecord::STATUS_SETTLED => '已分潤',
    ],
    'all_bet' => '總押注',
    'all_diff' => '總輸贏',
    'all_reward' => '總獎金',
];
