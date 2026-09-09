<?php

use addons\webman\model\PlayerPointsRecord;

return [
    'title' => '玩家積分',
    'records_title' => '積分記錄',

    'fields' => [
        'id' => '編號',
        'player_id' => '玩家ID',
        'player_name' => '玩家帳號',
        'total_points' => '總積分',
        'available_points' => '可用積分',
        'frozen_points' => '凍結積分',
        'used_points' => '已使用積分',
        'type' => '類型',
        'source' => '來源',
        'points' => '積分變動',
        'points_before' => '變動前',
        'points_after' => '變動後',
        'remark' => '備註',
        'admin_name' => '操作人',
        'admin_ip' => '操作IP',
        'created_at' => '操作時間',
    ],

    'type' => [
        PlayerPointsRecord::TYPE_BETTING_SUMMARY => '打碼彙總',
        PlayerPointsRecord::TYPE_EXCHANGE => '兌換消耗',
        PlayerPointsRecord::TYPE_EXPIRE => '過期扣除',
        PlayerPointsRecord::TYPE_ADMIN_ADJUST => '後台調整',
        PlayerPointsRecord::TYPE_ACTIVITY => '活動獎勵',
        PlayerPointsRecord::TYPE_REFUND => '訂單退款',
    ],

    'action' => [
        'add_points' => '增加積分',
        'deduct_points' => '扣除積分',
        'freeze_points' => '凍結積分',
        'unfreeze_points' => '解凍積分',
        'view_records' => '積分記錄',
    ],

    'form' => [
        'add_points_title' => '增加積分',
        'deduct_points_title' => '扣除積分',
        'freeze_points_title' => '凍結積分',
        'unfreeze_points_title' => '解凍積分',
        'current_points' => '當前可用積分',
        'current_frozen_points' => '當前凍結積分',
        'points_amount' => '積分數量',
        'remark_placeholder' => '請填寫操作原因',
    ],

    'message' => [
        'add_success' => '增加積分成功',
        'add_failed' => '增加積分失敗',
        'deduct_success' => '扣除積分成功',
        'deduct_failed' => '扣除積分失敗',
        'freeze_success' => '凍結積分成功',
        'freeze_failed' => '凍結積分失敗',
        'unfreeze_success' => '解凍積分成功',
        'unfreeze_failed' => '解凍積分失敗',
        'insufficient_points' => '可用積分不足',
        'insufficient_frozen_points' => '凍結積分不足',
        'player_not_found' => '玩家不存在',
        'points_not_found' => '積分記錄不存在',
        'invalid_points_amount' => '積分數量必須大於0',
        'permission_denied' => '無權限訪問該玩家的積分數據',
    ],

    'statistics' => [
        'available_points' => '可用積分',
        'frozen_points' => '凍結積分',
        'total_points' => '總積分',
        'player_account' => '玩家帳號',
    ],

    'filter' => [
        'all_types' => '全部類型',
        'select_type' => '選擇類型',
    ],
];
