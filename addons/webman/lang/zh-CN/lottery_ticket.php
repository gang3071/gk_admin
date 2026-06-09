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
        'name' => '活动名称',
        'activity_name' => '活动名称',
        'description' => '活动说明',
        'cover_image' => '活动封面',
        'live_url' => '直播地址',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'status' => '活动状态',
        'total_tickets' => '总发放数量',
        'used_tickets' => '已使用数量',
        'usage_rate' => '使用率',
        'prize_config' => '奖品配置',
        'created_at' => '创建时间',
        'prize_level_config' => '奖品等级配置',
        'total_probability' => '概率总和',
        'level' => '等级',
        'time_range' => '活动时间',
        'player_account' => '玩家账号',
        'prize_level' => '中奖等级',
        'remark' => '备注',

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

    // 占位符
    'placeholder' => [
        'name' => '请输入活动名称',
        'description' => '请输入活动说明',
        'start_time' => '请选择开始时间',
        'end_time' => '请选择结束时间',
        'level_rank' => '请选择等级排名',
        'prize_type' => '请选择奖品类型',
        'player_account' => '请输入玩家账号/手机号/UUID',
        'prize_level' => '请选择中奖等级',
        'remark' => '选填，可备注中奖详情',
        'live_url' => '例如: rtmp://live.example.com/stream/12345',
    ],

    // 对话框
    'modal' => [
        'record_win_title' => '录入中奖记录',
        'live_url_title' => '添加直播地址',
        'live_url_prompt' => '请输入直播流地址:',
        'live_url_required' => '请输入直播地址',
    ],

    // 标题
    'title' => [
        'activity_detail' => '活动详情',
    ],

    // 活动状态
    'status' => [
        'all' => '全部',
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
        'default' => '等级:rank',
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
        'create_first' => '立即创建',
        'edit' => '编辑活动',
        'view' => '查看详情',
        'view_detail' => '查看详情',
        'prize_config' => '奖品配置',
        'close' => '关闭活动',
        'export' => '导出记录',
        'add_prize_level' => '添加奖品等级',
        'record_win' => '录入中奖',
        'add_live_url' => '添加直播地址',
        'expand' => '展开',
        'collapse' => '收起',
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
        'activity_not_found' => '活动不存在',
        'activity_closed' => '活动已关闭',
        'activity_not_ongoing' => '只能关闭进行中的活动',
        'time_conflict' => '活动时间冲突',
        'prize_level_saved' => '奖品等级保存成功',
        'prize_level_deleted' => '奖品等级删除成功',
        'no_activities' => '暂无活动',
        'no_prize_config' => '尚未配置奖品等级',
        'prize_level_hint' => '最多可配置10个奖品等级,中奖概率总和不能超过100%',
        'upload_success' => '上传成功',
        'live_url_updated' => '直播地址设置成功',
        'record_success' => '中奖记录录入成功',
    ],

    // 错误信息
    'error' => [
        'too_many_levels' => '最多只能设置 {max} 个奖品等级',
        'no_prize_levels' => '请至少设置一个奖品等级',
        'no_prizes' => '奖品数量不能为0',
        'probability_exceed' => '中奖概率总和不能超过100%,当前总和:{total}%',
        'level_rank_exists' => '该等级排名已存在',
        'invalid_prize_type' => '无效的奖品类型',
        'name_required' => '请输入活动名称',
        'time_required' => '请选择活动时间',
        'invalid_time' => '结束时间必须大于开始时间',
        'cannot_edit_started' => '只能编辑未开始的活动',
        'invalid_file' => '无效的文件',
        'invalid_image_type' => '只支持 jpg、png 格式图片',
        'file_too_large' => '文件大小不能超过2MB',
        'invalid_params' => '参数错误',
        'activity_not_ongoing' => '只能在进行中的活动录入中奖',
        'prize_level_not_found' => '奖品等级不存在',
        'player_not_found' => '玩家不存在',
    ],
];
