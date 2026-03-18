<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityRecord;

return [
    'title' => 'プレイヤーアクティビティ参加記録',
    'fields' => [
        'id' => 'ID',
        'status' => 'ステータス',
        'score' => '現在の成績',
        'finish_at' => '終了時刻',
        'created_at' => 'パラメータ時間',
    ],
    'status' => [
        PlayerActivityRecord::STATUS_FINISH => '終了',
        PlayerActivityRecord::STATUS_BEGIN => '進行中',
    ],
    'created_at_start' => '開始時刻',
    'created_at_end' => '終了時刻',
    'player_activity_phase_record' => 'プレーヤーはレコードを受信します',
];
