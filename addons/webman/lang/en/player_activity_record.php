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
    'notice' => [
        'activity_pass_title' => 'Activity Reward Audit Passed',
        'activity_pass_content_with_machine' => 'Congratulations, your activity reward audit for machine {machine_code} has been approved, game points rewarded {bonus}',
        'activity_pass_content' => 'Congratulations, your activity reward audit has been approved, game points rewarded {bonus}',
        'activity_reject_title' => 'Activity Reward Audit Failed',
        'activity_reject_content' => 'Sorry, your activity reward audit failed, the reason is: {reason}',
    ],
];
