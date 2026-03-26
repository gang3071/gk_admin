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
    'notice' => [
        'activity_pass_title' => '活動獎勵稽核通過',
        'activity_pass_content_with_machine' => '恭喜您在機台{machine_code}的活動獎勵稽核通過，獎勵遊戲點數 {bonus}',
        'activity_pass_content' => '恭喜您活動獎勵稽核通過，獎勵遊戲點數 {bonus}',
        'activity_reject_title' => '活動獎勵稽核不通過',
        'activity_reject_content' => '抱歉您的活動獎勵稽核不通過，原因是: {reason}',
    ],
];
