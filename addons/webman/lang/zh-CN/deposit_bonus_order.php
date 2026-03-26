<?php

return [
    'title' => '充值满赠订单管理',
    'generate_title' => '生成充值满赠订单',

    'fields' => [
        'id' => 'ID',
        'order_no' => '订单号',
        'activity_id' => '所属活动',
        'activity_name' => '活动名称',
        'player_id' => '玩家',
        'player_account' => '玩家账号',
        'player_info' => '玩家信息',
        'deposit_amount' => '充值金额',
        'bonus_amount' => '赠送金额',
        'required_bet_amount' => '需要打码量',
        'current_bet_amount' => '当前打码量',
        'bet_progress' => '打码进度',
        'status' => '订单状态',
        'qrcode_token' => '二维码Token',
        'qrcode_expires_at' => '二维码过期时间',
        'expires_at' => '订单过期时间',
        'verified_at' => '核销时间',
        'completed_at' => '完成时间',
        'created_at' => '创建时间',
        'remark' => '备注',
    ],

    'help' => [
        'activity_id' => '选择要参与的活动',
        'deposit_amount' => '输入充值金额，必须匹配活动档位',
        'player_id' => '搜索并选择玩家账号',
        'player_username' => '请输入玩家账号',
    ],

    'status_pending' => '待核销',
    'status_verified' => '已核销',
    'status_completed' => '已完成',
    'status_expired' => '已过期',
    'status_cancelled' => '已取消',
    'status_unknown' => '未知状态',

    'cannot_edit' => '订单不允许编辑，只能新增',
    'activity_invalid' => '活动不存在或已失效',
    'tier_not_match' => '充值金额不匹配任何活动档位',
    'player_not_found' => '玩家不存在',
    'player_limit_exceeded' => '玩家参与次数已达上限',
    'parent_agent_not_found' => '未找到上级代理',
    'no_available_activity' => '上级代理暂无可用的充值满赠活动',
    'generate_success' => '订单生成成功',
    'generate_success_with_orderno' => '订单生成成功！订单号：{order_no}',
    'generate_fail' => '订单生成失败',

    'stats' => [
        'total_count' => '订单总数',
        'total_deposit' => '充值总额',
        'total_bonus' => '赠送总额',
        'completed_count' => '已完成',
    ],
];
