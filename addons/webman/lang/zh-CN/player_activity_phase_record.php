<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityPhaseRecord;

return [
    'title' => '玩家活动参与记录',
    'examine_title' => '奖励领取审核',
    'receive_title' => '活动领取记录',
    'fields' => [
        'id' => 'ID',
        'status' => '状态',
        'condition' => '领取条件',
        'bonus' => '活动奖励',
        'player_score' => '玩家记录',
        'user_name' => '审核人',
        'reject_reason' => '拒绝原因',
        'created_at' => '达成时间',
        'updated_at' => '更新(审核)时间',
    ],
    'status' => [
        PlayerActivityPhaseRecord::STATUS_UNRECEIVED => '未领取',
        PlayerActivityPhaseRecord::STATUS_RECEIVED => '已领取(待审核)',
        PlayerActivityPhaseRecord::STATUS_COMPLETE => '已发放(审核通过)',
        PlayerActivityPhaseRecord::STATUS_REJECT => '已拒绝',
    ],
    'created_at_start' => '开始时间',
    'created_at_end' => '结束时间',
    'player_activity_phase_record' => '玩家领取记录',
    'record_unreceived' => '玩家还未领取',
    'record_complete' => '奖励已发放',
    'record_reject' => '已拒绝',
    'not_fount' => '领取记录未找到',
    'action_error' => '操作失败',
    'action_success' => '操作成功',
    'bath_error' => '记录编号: ',
    'bath_not_found' => '未找到操作项',
    'bath_action' => '批量操作',
    'btn' => [
        'action' => '操作',
        'examine_pass' => '审核通过',
        'examine_reject' => '审核拒绝',
        'examine_pass_confirm' => '请确认点击审核通过后, 系统将会自动发放游戏点数',
        'examine_reject_confirm' => '审核拒绝, 点击审核拒绝后, 玩家将无法获得活动奖励点数',
    ],
];
