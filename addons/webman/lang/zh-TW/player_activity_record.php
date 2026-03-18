<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityRecord;

return [
    'title' => '玩家活動參與記錄',
    'fields' => [
        'id' => 'ID',
        'status' => '狀態',
        'score' => '當前達成',
        'finish_at' => '結束時間',
        'created_at' => '參數時間',
    ],
    'status' => [
        PlayerActivityRecord::STATUS_FINISH => '已結束',
        PlayerActivityRecord::STATUS_BEGIN => '進行中',
    ],
    'created_at_start' => '開始時間',
    'created_at_end' => '結束時間',
    'player_activity_phase_record' => '玩家領取記錄',
];
