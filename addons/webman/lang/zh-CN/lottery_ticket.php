<?php

return [
    'title' => '摸奖券管理',

    // 菜单
    'menu' => [
        'main' => '摸奖券管理',
        'dashboard' => '进行中的活动',
        'history' => '历史活动记录',
        'records' => '中奖记录',
    ],

    // 字段
    'fields' => [
        'id' => 'ID',
        'activity_name' => '活动名称',
        'description' => '活动说明',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'status' => '活动状态',
        'total_tickets' => '总发放数量',
        'used_tickets' => '已使用数量',
        'usage_rate' => '使用率',
        'prize_config' => '奖品配置',
        'created_at' => '创建时间',

        // 中奖记录
        'player_name' => '玩家名称',
        'player_phone' => '玩家手机',
        'ticket_no' => '摸奖券编号',
        'prize_type' => '奖品类型',
        'prize_name' => '奖品名称',
        'prize_amount' => '奖品金额',
        'record_status' => '发放状态',
        'remark' => '备注',
        'draw_time' => '抽奖时间',
    ],

    // 活动状态
    'status' => [
        'not_started' => '未开始',
        'ongoing' => '进行中',
        'ended' => '已结束',
        'closed' => '已关闭',
        'unknown' => '未知状态',
    ],

    // 摸奖券状态
    'ticket_status' => [
        'unused' => '未使用',
        'used' => '已使用',
        'expired' => '已过期',
        'unknown' => '未知状态',
    ],

    // 来源
    'source' => [
        'recharge' => '充值赠送',
        'activity' => '活动赠送',
        'manual' => '手动发放',
        'unknown' => '未知来源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => '待发放',
        'granted' => '已发放',
        'failed' => '发放失败',
        'unknown' => '未知状态',
    ],

    // 奖品类型
    'prize_type' => [
        'cash' => '现金',
        'bonus' => '红利',
        'item' => '实物',
        'points' => '积分',
        'empty' => '未中奖',
        'unknown' => '未知类型',
    ],

    // 中奖等级名称
    'level_name' => [
        'special' => '特等奖',
        'first' => '一等奖',
        'second' => '二等奖',
        'third' => '三等奖',
        'fourth' => '四等奖',
        'fifth' => '五等奖',
        'sixth' => '六等奖',
        'seventh' => '七等奖',
        'eighth' => '八等奖',
        'ninth' => '九等奖',
    ],

    // 中奖等级字段
    'prize_level_fields' => [
        'level_rank' => '等级排名',
        'level_name' => '等级名称',
        'prize_type' => '奖品类型',
        'prize_amount' => '奖品金额',
        'prize_item_name' => '实物名称',
        'prize_item_image' => '实物图片',
        'prize_count' => '奖品数量',
        'win_probability' => '中奖概率(%)',
        'description' => '奖品描述',
    ],

    // 操作
    'action' => [
        'create' => '创建活动',
        'edit' => '编辑活动',
        'view' => '查看详情',
        'close' => '关闭活动',
        'export' => '导出记录',
        'grant' => '发放奖品',
    ],

    // 统计
    'stats' => [
        'total_activities' => '总活动数',
        'ongoing_activities' => '进行中活动',
        'total_draws' => '总抽奖次数',
        'total_winners' => '总中奖人数',
        'total_prize_amount' => '总奖金金额',
    ],

    // 消息
    'message' => [
        'create_success' => '活动创建成功',
        'update_success' => '活动更新成功',
        'close_success' => '活动关闭成功',
        'close_confirm' => '确定要关闭此活动吗？',
        'activity_not_found' => '活动不存在',
        'activity_closed' => '活动已关闭',
        'time_conflict' => '活动时间冲突',
        'prize_level_saved' => '奖品等级保存成功',
        'prize_level_deleted' => '奖品等级删除成功',
    ],

    // 错误信息
    'error' => [
        'too_many_levels' => '最多只能设置 {max} 个奖品等级',
        'no_prize_levels' => '请至少设置一个奖品等级',
        'no_prizes' => '奖品数量不能为0',
        'probability_exceed' => '中奖概率总和不能超过100%，当前总和：{total}%',
        'level_rank_exists' => '该等级排名已存在',
        'invalid_prize_type' => '无效的奖品类型',
    ],
];
