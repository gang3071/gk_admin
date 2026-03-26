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
    'notice' => [
        'activity_pass_title' => '活动奖励审核通过',
        'activity_pass_content_with_machine' => '恭喜您在机台{machine_code}的活动奖励审核通过，奖励游戏点数 {bonus}',
        'activity_pass_content' => '恭喜您活动奖励审核通过，奖励游戏点数 {bonus}',
        'activity_reject_title' => '活动奖励审核不通过',
        'activity_reject_content' => '抱歉您的活动奖励审核不通过，原因是: {reason}',
    ],
];
