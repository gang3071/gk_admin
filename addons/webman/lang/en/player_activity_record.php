<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityRecord;

return [
    'title' => 'Player activity participation record',
    'fields' => [
        'id' => 'ID',
        'status' => 'status',
        'score' => 'Current achievement',
        'finish_at' => 'End time',
        'created_at' => 'Parameter time',
    ],
    'status' => [
        PlayerActivityRecord::STATUS_FINISH => 'Ended',
        PlayerActivityRecord::STATUS_BEGIN => 'In progress',
    ],
    'created_at_start' => 'Start time',
    'created_at_end' => 'End time',
    'player_activity_phase_record' => 'Player receives record',
];
