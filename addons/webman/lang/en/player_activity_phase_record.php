<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityPhaseRecord;

return [
    'title' => 'Player activity participation record',
    'examine_title' => 'Reward collection review',
    'receive_title' => 'Activity receipt record',
    'fields' => [
        'id' => 'ID',
        'status' => 'status',
        'condition' => 'Conditions for receiving',
        'bonus' => 'activity reward',
        'player_score' => 'Player record',
        'user_name' => 'Reviewer',
        'reject_reason' => 'Rejection reason',
        'created_at' => 'Achievement time',
        'updated_at' => 'Update (review) time',
    ],
    'status' => [
        PlayerActivityPhaseRecord::STATUS_UNRECEIVED => 'Not received',
        PlayerActivityPhaseRecord::STATUS_RECEIVED => 'Received (pending review)',
        PlayerActivityPhaseRecord::STATUS_COMPLETE => 'Issued (approved)',
        PlayerActivityPhaseRecord::STATUS_REJECT => 'Rejected',
    ],
    'created_at_start' => 'Start time',
    'created_at_end' => 'End time',
    'player_activity_phase_record' => 'Player receives record',
    'record_unreceived' => 'The player has not received it yet',
    'record_complete' => 'Reward has been issued',
    'record_reject' => 'Rejected',
    'not_fount' => 'Receipt record not found',
    'action_error' => 'Operation failed',
    'action_success' => 'Action successful',
    'bath_error' => 'Record number: ',
    'bath_not_found' => 'Operation item not found',
    'bath_action' => 'Batch operation',
    'btn' => [
        'action' => 'action',
        'examine_pass' => 'Examination passed',
        'examine_reject' => 'Examination rejection',
        'examine_pass_confirm' => 'Please confirm and click the review button. After passing the review, the system will automatically issue game points',
        'examine_reject_confirm' => 'Examine rejection, after clicking review rejection, players will not be able to obtain event reward points',
    ],
];
