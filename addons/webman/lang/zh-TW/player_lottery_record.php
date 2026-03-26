<?php

use addons\webman\model\PlayerLotteryRecord;

return [
    'title' => '彩金領取',
    'audit_title' => '彩金稽核',
    'fields' => [
        'id' => '編號',
        'uuid' => '玩家uuid',
        'player_phone' => '玩家手機號',
        'department_id' => '通路',
        'machine_name' => '機台名',
        'machine_code' => '機台編號',
        'odds' => '機台比值',
        'lottery_name' => '彩金名',
        'amount' => '派彩金額',
        'lottery_pool_amount' => '彩池金額',
        'lottery_rate' => '金額比例',
        'cate_rate' => '派彩係數',
        'reject_reason' => '拒絕原因',
        'user_name' => '稽核人',
        'status' => '狀態',
        'created_at' => '創建時間',
        'audit_at' => '稽核時間',
        'source' => '來源',
    ],
    'double' => '雙倍',
    'btn' => [
        'action' => '操作',
        'examine_pass' => '稽核通過',
        'examine_reject' => '稽核拒絕',
        'examine_pass_confirm' => '請確認點擊稽核通過後,系統將會自動發送領取消息',
        'examine_reject_confirm' => '稽核拒絕,點擊稽核拒絕後,玩家將無法獲得彩金獎勵點數',
    ],
    'lottery_record_error' => '彩金領取記錄類型出錯誤',
    'lottery_record_has_pass' => '彩金領取記錄已通過審核',
    'lottery_record_has_reject' => '彩金領取記錄已拒絕',
    'lottery_record_has_complete' => '玩家已領取',
    'action_error' => '操作失敗',
    'action_success' => '操作成功',
    'not_fount' => '彩金領取記錄不存在',
    'max_amount' => '最高',
    'bath_error' => '記錄編號：',
    'bath_not_found' => '未找到操作項',
    'bath_action' => '批量操作',
    'source' => [
        PlayerLotteryRecord::SOURCE_MACHINE => '實體機器',
        PlayerLotteryRecord::SOURCE_GAME => '電子遊戲',
        PlayerLotteryRecord::SOURCE_MANUAL => '手動發放',
    ],

    // 站內信消息
    'notice' => [
        'title' => '彩金派彩',
        'content_machine' => '恭喜您在{machine_type}{machine_code}機台獲得了{lottery_name}的彩金獎勵彩金金額',
    ],

    // 機台類型
    'machine_type' => [
        'slot' => '斯洛',
        'steel_ball' => '鋼珠',
    ],
    'status' => [
        PlayerLotteryRecord::STATUS_UNREVIEWED => '未稽核',
        PlayerLotteryRecord::STATUS_REJECT => '已拒絕',
        PlayerLotteryRecord::STATUS_PASS => '已通過',
        PlayerLotteryRecord::STATUS_COMPLETE => '已領取',
    ],
    'total_data' => [
        'total_unreviewed_amount' => '未稽核',
        'total_reject_amount' => '已拒絕',
        'total_pass_amount' => '已通過',
        'total_complete_amount' => '已領取',
        'total_count' => '記錄總數',
    ],
    'notice' => [
        'lottery_payout_title' => '彩金派彩',
        'lottery_payout_content' => '恭喜您在{machine_type}{machine_code}機台獲得了{lottery_name}的彩金獎勵彩金金額',
    ],
    'machine_type' => [
        'slot' => '斯洛',
        'steel_ball' => '鋼珠',
    ],
];
