<?php

use addons\webman\model\PlayerActivityPhaseRecord;

return [
    'title' => '玩家活動參與記錄',
    'examine_title' => '獎勵領取稽核',
    'receive_title' => '活動領取記錄',
    'fields' => [
        'id' => 'ID',
        'status' => '狀態',
        'condition' => '領取條件',
        'bonus' => '活動獎勵',
        'player_score' => '玩家記錄',
        'user_name' => '稽核人',
        'reject_reason' => '拒絕原因',
        'created_at' => '達成時間',
        'updated_at' => '更新（稽核）時間',
    ],
    'status' => [
        PlayerActivityPhaseRecord::STATUS_UNRECEIVED => '未領取',
        PlayerActivityPhaseRecord::STATUS_RECEIVED => '已領取（待稽核）',
        PlayerActivityPhaseRecord::STATUS_COMPLETE => '已發放（稽核通過）',
        PlayerActivityPhaseRecord::STATUS_REJECT => '已拒絕',
    ],
    'created_at_start' => '開始時間',
    'created_at_end' => '結束時間',
    'player_activity_phase_record' => '玩家領取記錄',
    'record_unreceived' => '玩家還未領取',
    'record_complete' => '獎勵已發放',
    'record_reject' => '已拒絕',
    'not_fount' => '領取記錄未找到',
    'action_error' => '操作失敗',
    'action_success' => '操作成功',
    'bath_error' => '記錄編號：',
    'bath_not_found' => '未找到操作項',
    'bath_action' => '批量操作',
    'btn' => [
        'action' => '操作',
        'examine_pass' => '稽核通過',
        'examine_reject' => '稽核拒絕',
        'examine_pass_confirm' => '請確認點擊稽核通過後,系統將會自動發放遊戲點數',
        'examine_reject_confirm' => '稽核拒絕,點擊稽核拒絕後,玩家將無法獲得活動獎勵點數',
    ],
];
