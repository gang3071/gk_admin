<?php

use addons\webman\model\PlayerLotteryRecord;

return [
    'title' => 'Bonus collection',
    'audit_title' => 'Bonus audit',
    'fields' => [
        'id' => 'number',
        'uuid' => 'Player uuid',
        'player_phone' => 'player phone number',
        'department_id' => 'channel',
        'machine_name' => 'machine name',
        'machine_code' => 'Machine number',
        'odds' => 'Machine ratio',
        'lottery_name' => 'Lottery name',
        'amount' => 'payout amount',
        'lottery_pool_amount' => 'Lottery pool amount',
        'lottery_rate' => 'amount ratio',
        'cate_rate' => 'Payout coefficient',
        'reject_reason' => 'Rejection reason',
        'user_name' => 'Reviewer',
        'status' => 'status',
        'created_at' => 'Creation time',
        'audit_at' => 'Audit time',
        'source' => 'Source',
    ],
    'double' => 'double',
    'btn' => [
        'action' => 'action',
        'examine_pass' => 'Examination passed',
        'examine_reject' => 'Examination rejection',
        'examine_pass_confirm' => 'Please confirm and click the approval button. After passing the review, the system will automatically send a claim message',
        'examine_reject_confirm' => 'Examination rejection, after clicking review rejection, players will not be able to obtain bonus points',
    ],
    'lottery_record_error' => 'Lottery collection record type error',
    'lottery_record_has_pass' => 'Lottery collection record has passed review',
    'lottery_record_has_reject' => 'Lottery record has been rejected',
    'lottery_record_has_complete' => 'Player has received',
    'action_error' => 'Operation failed',
    'action_success' => 'Action successful',
    'not_fount' => 'The bonus collection record does not exist',
    'max_amount' => 'Highest',
    'bath_error' => 'Record number: ',
    'bath_not_found' => 'Operation item not found',
    'bath_action' => 'Batch operation',
    'source' => [
        PlayerLotteryRecord::SOURCE_MACHINE => 'Physical Machine',
        PlayerLotteryRecord::SOURCE_GAME => 'Electronic Game',
        PlayerLotteryRecord::SOURCE_MANUAL => 'Manual Distribution',
    ],

    // Notice messages
    'notice' => [
        'title' => 'Lottery Payout',
        'content_machine' => 'Congratulations! You won {lottery_name} lottery reward on {machine_type} machine {machine_code}',
    ],

    // Machine types
    'machine_type' => [
        'slot' => 'Slot',
        'steel_ball' => 'Steel Ball',
    ],
    'status' => [
        PlayerLotteryRecord::STATUS_UNREVIEWED => 'Unreviewed',
        PlayerLotteryRecord::STATUS_REJECT => 'Rejected',
        PlayerLotteryRecord::STATUS_PASS => 'Passed',
        PlayerLotteryRecord::STATUS_COMPLETE => 'Received',
    ],
    'total_data' => [
        'total_unreviewed_amount' => 'Unreviewed',
        'total_reject_amount' => 'Rejected',
        'total_pass_amount' => 'Passed',
        'total_complete_amount' => 'Received',
        'total_count' => 'Total Records',
    ],
    'notice' => [
        'lottery_payout_title' => 'Jackpot Payout',
        'lottery_payout_content' => 'Congratulations, you won the {lottery_name} jackpot reward on {machine_type} machine {machine_code}',
    ],
    'machine_type' => [
        'slot' => 'Slot',
        'steel_ball' => 'Pachinko',
    ],
];
