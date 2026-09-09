<?php

use addons\webman\model\PlayerPointsRecord;

return [
    'title' => '玩家积分',
    'records_title' => '积分记录',

    'fields' => [
        'id' => '编号',
        'player_id' => '玩家ID',
        'player_name' => '玩家账号',
        'total_points' => '总积分',
        'available_points' => '可用积分',
        'frozen_points' => '冻结积分',
        'used_points' => '已使用积分',
        'type' => '类型',
        'source' => '来源',
        'points' => '积分变动',
        'points_before' => '变动前',
        'points_after' => '变动后',
        'remark' => '备注',
        'admin_name' => '操作人',
        'admin_ip' => '操作IP',
        'created_at' => '操作时间',
    ],

    'type' => [
        PlayerPointsRecord::TYPE_BETTING_SUMMARY => '打码汇总',
        PlayerPointsRecord::TYPE_EXCHANGE => '兑换消耗',
        PlayerPointsRecord::TYPE_EXPIRE => '过期扣除',
        PlayerPointsRecord::TYPE_ADMIN_ADJUST => '后台调整',
        PlayerPointsRecord::TYPE_ACTIVITY => '活动奖励',
        PlayerPointsRecord::TYPE_REFUND => '订单退款',
    ],

    'action' => [
        'add_points' => '增加积分',
        'deduct_points' => '扣除积分',
        'freeze_points' => '冻结积分',
        'unfreeze_points' => '解冻积分',
        'view_records' => '积分记录',
    ],

    'form' => [
        'add_points_title' => '增加积分',
        'deduct_points_title' => '扣除积分',
        'freeze_points_title' => '冻结积分',
        'unfreeze_points_title' => '解冻积分',
        'current_points' => '当前可用积分',
        'current_frozen_points' => '当前冻结积分',
        'points_amount' => '积分数量',
        'remark_placeholder' => '请填写操作原因',
    ],

    'message' => [
        'add_success' => '增加积分成功',
        'add_failed' => '增加积分失败',
        'deduct_success' => '扣除积分成功',
        'deduct_failed' => '扣除积分失败',
        'freeze_success' => '冻结积分成功',
        'freeze_failed' => '冻结积分失败',
        'unfreeze_success' => '解冻积分成功',
        'unfreeze_failed' => '解冻积分失败',
        'insufficient_points' => '可用积分不足',
        'insufficient_frozen_points' => '冻结积分不足',
        'player_not_found' => '玩家不存在',
        'points_not_found' => '积分记录不存在',
        'invalid_points_amount' => '积分数量必须大于0',
        'permission_denied' => '无权限访问该玩家的积分数据',
    ],

    'statistics' => [
        'available_points' => '可用积分',
        'frozen_points' => '冻结积分',
        'total_points' => '总积分',
        'player_account' => '玩家账号',
    ],

    'filter' => [
        'all_types' => '全部类型',
        'select_type' => '选择类型',
    ],
];
