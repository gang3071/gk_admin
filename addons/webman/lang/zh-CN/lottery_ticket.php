<?php

return [
    // 菜单
    'menu' => [
        'main' => '摸奖券管理',
        'dashboard' => '进行中的活动',
        'history' => '历史活动记录',
        'records' => '中奖记录',
    ],

    // 标题
    'title' => [
        'main' => '摸奖券管理',
        'activity_detail' => '活动詳情',
        'ticket_list' => '摸奖券列表',
        'record_list' => '中奖记录',
    ],

    // 字段
    'fields' => [
        'id' => 'ID',
        'name' => '活动名称',
        'activity_name' => '活动名称',
        'description' => '活动说明',
        'cover_image' => '活动封面',
        'cover_image_upload' => '活动封面图片',
        'live_url' => '直播地址',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'status' => '活动状态',
        'total_tickets' => '总发放数量',
        'used_tickets' => '已使用数量',
        'usage_rate' => '使用率',
        'pending_count' => '待发放',
        'max_ticket_no' => '最大券号',  // ⭐ 新增：抽奖时放球的最大号码
        'operation' => '操作',  // ⭐ 新增
        'prize_config' => '奖品配置',
        'created_at' => '创建时间',
        'prize_level_config' => '奖品等级配置',
        'total_probability' => '概率总和',
        'level' => '等级',
        'time_range' => '活动时间',
        'player_account' => '玩家賬号',
        'prize_level' => '中奖等级',
        'remark' => '备註',
        'distribution_remark' => '发放备註',
        'vip_level' => 'VIP等级',
        'bet_amount_required' => '所需打码量',
        'ticket_count' => '发放券数',
        'prize_amount' => '奖勵金额',
        'prize_count' => '奖品数量',
        'ticket_no_input' => '中奖券号',

        // 中奖记录
        'player_name' => '玩家名称',
        'player_phone' => '玩家手机',
        'ticket_no' => '券号',
        'prize_type' => '奖品类型',
        'prize_name' => '奖品名称',
        'record_status' => '发放状态',
        'draw_time' => '抽奖时间',
        'source' => '來源',
        'used_at' => '使用时间',
        'expired_at' => '过期时间',
    ],

    // 占位符
    'placeholder' => [
        'name' => '请输入活动名称',
        'description' => '请输入活动说明',
        'start_time' => '请选择开始时间',
        'end_time' => '请选择结束时间',
        'level_rank' => '请选择等级排名',
        'prize_type' => '请选择奖品类型',
        'player_account' => '请输入玩家賬号/手机号/UUID',
        'prize_level' => '请选择中奖等级',
        'remark' => '选填，可备註中奖詳情',
        'live_url' => '例如: rtmp://live.example.com/stream/12345',
        'ticket_no' => '请输入6位券号',
        'distribute_remark' => '选填，可备註发放说明',
    ],

    // 模态框
    'modal' => [
        'record_win_title' => '录入中奖记录',
        'live_url_title' => '添加直播地址',
        'live_url_prompt' => '请输入直播流地址:',
        'live_url_required' => '请输入直播地址',
        'batch_distribute_title' => '批量发放奖勵',
        'distribute_by_ticket_title' => '发放奖勵',
        'ticket_list_title' => '摸奖券发放列表',
    ],

    // 活动状态
    'status' => [
        'all' => '全部',
        'not_started' => '未开始',
        'ongoing' => '进行中',
        'ended' => '已结束',
        'closed' => '已关閉',
        'preheating' => '预熱期',
        'betting' => '打码中',
        'drawing' => '开奖中',
        'drawn' => '已开奖待发放', // ⭐ 新增
        'unknown' => '未知状态',
    ],

    // 直播状态
    'live_status' => [
        'not_started' => '未开播',
        'ongoing' => '直播中',
        'ended' => '已结束',
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
        'recharge' => '充值贈送',
        'activity' => '活动贈送',
        'manual' => '手动发放',
        'unknown' => '未知來源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => '待发放',
        'claimed' => '已发放', // ⭐ 更新
        'expired' => '已过期', // ⭐ 新增
        'cancelled' => '已取消', // ⭐ 新增
        'processing' => '发放中', // ⭐ 新增
        'failed' => '发放失敗',
        'granted' => '已发放', // 兼容舊代码
        'unknown' => '未知状态',
    ],

    // 奖品类型
    'prize_type' => [
        'cash' => '现金',
        'bonus' => '红利',
        'item' => '实物',
        'points' => '積分',
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

    // 中奖记录字段 ⭐ 新增
    'record_fields' => [
        'prize_type' => '奖品类型',
        'prize_level_name' => '奖品等级',
        'draw_time' => '抽奖时间',
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
        'won_count' => '已中奖数',  // ⭐ 新增
        'remaining_count' => '剩余数量',  // ⭐ 新增
        'win_probability' => '中奖概率(%)',
        'description' => '奖品描述',
    ],

    // 操作
    'action' => [
        'create' => '创建活动',
        'create_first' => '立即创建',
        'edit' => '编輯活动',
        'view' => '查看詳情',
        'view_detail' => '查看詳情',
        'operation' => '操作',  // ⭐ 新增
        'prize_config' => '奖品配置',
        'close' => '关閉活动',
        'export' => '导出记录',
        'add_prize_level' => '添加奖品等级',
        'record_win' => '录入中奖',
        'add_live_url' => '添加直播地址',
        'expand' => '展开',
        'collapse' => '收起',
        'distribute' => '发放',
        'batch_distribute' => '批量发放',
        'batch_distribute_selected' => '批量发放选中',
        'distribute_by_ticket' => '录入券号发放',
        'distribute_all_pending' => '发放奖励',  // ⭐ 新增:批量发放所有待发放记录
        'view_ticket_list' => '查看发放列表',
        'add_ticket' => '添加券号',
        'select_image' => '选择图片',
        'confirm_distribute' => '确认发放',
    ],

    // 统计
    'stats' => [
        'total_activities' => '总活动数',
        'ongoing_activities' => '进行中活动',
        'total_draws' => '总抽奖次数',
        'total_winners' => '总中奖人数',
        'total_prize_amount' => '总奖金金额',
        'pending_count' => '待发放记录',       // ⭐ 新增
        'pending_amount' => '待发放金额',      // ⭐ 新增
        'claimed_count' => '已发放记录',       // ⭐ 新增
        'claimed_amount' => '已发放金额',      // ⭐ 新增
    ],

    // 消息
    'message' => [
        'create_success' => '活动创建成功',
        'update_success' => '活动更新成功',
        'close_success' => '活动关閉成功',
        'activity_not_found' => '活动不存在',
        'activity_closed' => '活动已关閉',
        'activity_not_ongoing' => '只能关閉进行中的活动',
        'time_conflict' => '活动时间衝突',
        'prize_level_saved' => '奖品等级保存成功',
        'prize_level_deleted' => '奖品等级刪除成功',
        'no_activities' => '暫无活动',
        'no_prize_config' => '尚未配置奖品等级',
        'prize_level_hint' => '最多可配置10个奖品等级,中奖概率总和不能超过100%',
        'upload_success' => '上传成功',
        'image_upload_success' => '图片上传成功',
        'image_upload_failed' => '图片上传失敗',
        'distribute_success' => '发放成功',
        'distribute_failed' => '发放失敗',
        'batch_complete' => '批量发放完成：成功 {success} 条，失敗 {fail} 条',
        'batch_distribute_selected' => '批量发放选中记录',
        'export_in_development' => '导出功能开发中',
        'live_url_updated' => '直播地址设置成功',
        'record_success' => '中奖记录录入成功',
        'record_success_count' => '成功录入 {count} 条中奖记录',
        'live_started' => '直播已开始',
        'live_ended' => '直播已结束',
        'status_updated' => '状态更新成功',
        'admin_manual_update' => '管理員手动更新',
        'fetch_failed' => '获取活动列表失敗',
        'fetch_detail_failed' => '获取活动詳情失敗',
        'close_activity_failed' => '关閉活动失敗',
        'min_one_ticket' => '请至少输入一个券号',
        'please_input_ticket' => '请输入券号',
        'ticket_must_6_digits' => '券号必須是6位数字',
        'ticket_format_error' => '券号格式错误，只能包含数字且不超过6位',
        'no_prize_level' => '該活动尚未配置奖品等级',
        'distribute_hint' => '请输入中奖券号，系统將根據券号自动识別奖品等级並发放奖勵',
    ],

    // 错误信息
    'error' => [
        'record_not_found' => '记录不存在',
        // 输入驗證
        'invalid_record_id' => '參数错誤：记录ID无效',
        'invalid_activity_id' => '參数错誤：活动ID无效',
        'invalid_record_ids' => '參数错誤：记录ID必須是数組',
        'invalid_record_id_value' => '參数错誤：记录ID包含非法值',
        'note_too_long' => '发放备註不能超过255个字符',
        'no_selection' => '请指定活动ID或选择记录',
        'no_pending_records' => '沒有待发放的记录',
        // 业务邏輯驗證
        'invalid_status' => '记录状态不正确，只能发放待发放的记录',
        'status_changed' => '状态已变更',
        'empty_prize' => '空奖无需发放',
        'invalid_amount' => '奖品金额必須大於0',
        'player_not_found' => '玩家不存在',
        'player_disabled' => '玩家已被禁用，无法发放奖勵',
        'activity_not_found' => '活动不存在',
        'activity_invalid_status' => '活动状态错誤，只能发放已开奖待发放的活动奖勵',
        'amount_exceeded' => '发放金额超出总奖金额度',
        'ticket_not_found_or_used' => '券号 {ticket_no} 不存在或已使用',
        'prize_level_not_found_for_ticket' => '券号 {ticket_no} 的奖品等级不存在',
        'invalid_ticket_format' => '券号 {ticket_no} 格式错误，只能包含数字且不超过6位',
        'bet_progress_not_found' => '未找到打码进度记录',
        // 其他
        'too_many_levels' => '最多只能设置 {max} 个奖品等级',
        'no_prize_levels' => '请至少设置一个奖品等级',
        'no_prizes' => '奖品数量不能为0',
        'probability_exceed' => '中奖概率总和不能超过100%，當前总和：{total}%',
        'level_rank_exists' => '該等级排名已存在',
        'invalid_prize_type' => '无效的奖品类型',
        'name_required' => '请输入活动名称',
        'time_required' => '请选择活动时间',
        'invalid_time' => '结束时间必須大於开始时间',
        'cannot_edit_started' => '只能编輯未开始的活动',
        'invalid_file' => '无效的文件',
        'invalid_image_type' => '只支持 jpg、png 格式图片',
        'file_too_large' => '文件大小不能超过2MB',
        'invalid_params' => '參数错誤',
        'activity_not_ongoing' => '只能在进行中的活动录入中奖',
        'prize_level_not_found' => '奖品等级不存在',
        'live_url_required' => '请先设置直播地址',
        'invalid_status_value' => '无效的状态值',
    ],

    // 帮助文本
    'help' => [
        'cover_image' => '建議尺寸：750x400px，支持jpg、png格式，文件大小不超过2MB',
        'cover_alt' => '活动封面',
        'cover_preview' => '封面预覽',
        'vip_config_hint' => '为每个VIP等级配置达到指定打码量后发放的摸奖券数量',
        'prize_config_hint' => '配置奖品等级和奖勵金额(仅现金奖勵)',
        'input_ticket_no' => '输入券号:',
    ],

    // 详情视图标签
    'view' => [
        'detail_title' => '中奖记录詳情',
        'basic_info' => '基本信息',
        'prize_info' => '奖品信息',
        'distribution_info' => '发放信息',
        'activity_name' => '活动名称',
        'ticket_no' => '券号',
        'player_name' => '玩家',
        'player_phone' => '手机号',
        'prize_name' => '奖品名称',
        'prize_type' => '奖品类型',
        'prize_amount' => '奖品金额',
        'status' => '状态',
        'distributed_at' => '发放时间',
        'distributed_by' => '发放人',
        'distribution_note' => '发放备註',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],

    // 确认对话框
    'confirm' => [
        'distribute' => '确认发放此奖品到玩家賬戶？',
        'distribute_all_pending' => '确认发放该活动所有已录入但未发放的奖励？\n此操作将批量发放所有待发放记录,请谨慎操作。',  // ⭐ 新增
    ],

    // 表单标签
    'form' => [
        'select_activity' => '选择活动',
        'select_activity_help' => '只顯示已开奖待发放的活动',
        'distribution_note' => '发放备註',
        'distribution_note_placeholder' => '请填寫发放备註（选填）',
        'vip_config_section' => 'VIP等级打码量配置',
        'prize_config_section' => '奖品等级配置',
        'no_vip_data' => '暫无VIP等级数據',
        'no_vip_config' => '未配置VIP等级',
    ],

    // 筛选器 ⭐ 新增
    'filter' => [
        'time_range' => '时间范围',
        'create_time_range' => '创建时间范围',
        'activity_time_range' => '活动时间范围',
    ],

    // 验证消息
    'validation' => [
        'name_required' => '请输入活动名称',
        'name_max_length' => '活动名称不能超过100个字符',
        'start_time_required' => '请选择开始时间',
        'end_time_required' => '请选择结束时间',
        'ticket_no_required' => '请输入券号',
        'image_format_error' => '只能上传 JPG/PNG 格式的图片！',
        'image_size_error' => '图片大小不能超过 2MB！',
    ],

    // 其他文本
    'ui' => [
        'total_records' => '共 {total} 条',
        'yuan' => '元',
        'upload_failed' => '上传失敗',
    ],
];
