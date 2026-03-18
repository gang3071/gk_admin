<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityRecord;

return [
    'title' => '玩家活动参与记录',
    'fields' => [
        'id' => 'ID',
        'status' => '状态',
        'score' => '当前达成',
        'finish_at' => '结束时间',
        'created_at' => '参数时间',
    ],
    'status' => [
        PlayerActivityRecord::STATUS_FINISH => '已结束',
        PlayerActivityRecord::STATUS_BEGIN => '进行中',
    ],
    'created_at_start' => '开始时间',
    'created_at_end' => '结束时间',
    'player_activity_phase_record' => '玩家领取记录',
];
