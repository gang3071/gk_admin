<?php

use addons\webman\model\PlayerLotteryRecord;

return [
    'title' => '彩金领取',
    'audit_title' => '彩金审核',
    'fields' => [
        'id' => '编号',
        'uuid' => '玩家uuid',
        'player_phone' => '玩家手机号',
        'department_id' => '渠道',
        'machine_name' => '机台名',
        'machine_code' => '机台编号',
        'odds' => '机台比值',
        'lottery_name' => '彩金名',
        'amount' => '派彩金额',
        'lottery_pool_amount' => '彩池金额',
        'lottery_rate' => '金额比例',
        'cate_rate' => '派彩系数',
        'reject_reason' => '拒绝原因',
        'user_name' => '审核人',
        'status' => '状态',
        'created_at' => '创建时间',
        'audit_at' => '审核时间',
        'source' => '来源',
    ],
    'double' => '双倍',
    'btn' => [
        'action' => '操作',
        'examine_pass' => '审核通过',
        'examine_reject' => '审核拒绝',
        'examine_pass_confirm' => '请确认点击审核通过后, 系统将会自动发送领取消息',
        'examine_reject_confirm' => '审核拒绝, 点击审核拒绝后, 玩家将无法获得彩金奖励点数',
    ],
    'lottery_record_error' => '彩金领取记录类型出错误',
    'lottery_record_has_pass' => '彩金领取记录已通过审核',
    'lottery_record_has_reject' => '彩金领取记录已拒绝',
    'lottery_record_has_complete' => '玩家已领取',
    'action_error' => '操作失败',
    'action_success' => '操作成功',
    'not_fount' => '彩金领取记录不存在',
    'max_amount' => '最高',
    'bath_error' => '记录编号: ',
    'bath_not_found' => '未找到操作项',
    'bath_action' => '批量操作',
    'source' => [
        PlayerLotteryRecord::SOURCE_MACHINE => '实体机台',
        PlayerLotteryRecord::SOURCE_GAME => '电子游戏',
        PlayerLotteryRecord::SOURCE_MANUAL => '手动发放',
    ],

    // 站内信消息
    'notice' => [
        'title' => '彩金派彩',
        'content_machine' => '恭喜您在{machine_type}{machine_code}机台获得了{lottery_name}的彩金奖励彩金金额',
    ],

    // 机台类型
    'machine_type' => [
        'slot' => '斯洛',
        'steel_ball' => '钢珠',
    ],
    'status' => [
        PlayerLotteryRecord::STATUS_UNREVIEWED => '未审核',
        PlayerLotteryRecord::STATUS_REJECT => '已拒绝',
        PlayerLotteryRecord::STATUS_PASS => '已通过',
        PlayerLotteryRecord::STATUS_COMPLETE => '已领取',
    ],
    'total_data' => [
        'total_unreviewed_amount' => '未审核',
        'total_reject_amount' => '已拒绝',
        'total_pass_amount' => '已通过',
        'total_complete_amount' => '已领取',
        'total_count' => '记录总数',
    ],
    'notice' => [
        'lottery_payout_title' => '彩金派彩',
        'lottery_payout_content' => '恭喜您在{machine_type}{machine_code}机台获得了{lottery_name}的彩金奖励彩金金额',
    ],
    'machine_type' => [
        'slot' => '斯洛',
        'steel_ball' => '钢珠',
    ],
];
