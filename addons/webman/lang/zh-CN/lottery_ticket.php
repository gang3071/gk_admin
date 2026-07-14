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
        'stream_name' => '流名称',
        'push_url' => '推流地址',
        'push_server' => '推流服务器',
        'stream_key' => '串流密钥',
        'expire_time' => '有效期',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'status' => '活动状态',
        'total_tickets' => '总发放数量',
        'used_tickets' => '已使用数量',
        'usage_rate' => '使用率',
        'pending_count' => '派奖数量',
        'max_ticket_no' => '已发最大券号',  // ⭐ 当前已发放的最大券号（如：000123）
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
        'player_uuid' => '玩家UUID',
        'player_username' => '玩家账号',
        'ticket_no' => '券号',
        'prize_type' => '奖品类型',
        'prize_name' => '奖品名称',
        'record_status' => '发放状态',
        'created_time_range' => '创建时间范围',
        'source' => '來源',
        'used_at' => '使用时间',
        'expired_at' => '过期时间',

        // 统计字段
        'total_winners' => '中奖总数量',      // ⭐ 历史记录页面
        'total_prize_amount' => '中奖总金额',  // ⭐ 历史记录页面
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
        'stream_name' => '请输入流名称（仅英文、数字、下划线、横线）',
        'ticket_no' => '请输入6位券号',
        'distribute_remark' => '选填，可备註发放说明',
    ],

    // 模态框
    'modal' => [
        'record_win_title' => '录入中奖记录',
        'live_url_title' => '添加直播地址',
        'live_url_prompt' => '请输入直播流地址:',
        'live_url_required' => '请输入直播地址',
        'stream_name_prompt' => '请输入流名称:',
        'generate_push_url_title' => '生成推流地址',
        'batch_distribute_title' => '批量发放奖勵',
        'distribute_by_ticket_title' => '发放奖勵',
        'ticket_list_title' => '摸奖券发放列表',
    ],

    // 活动状态
    'status' => [
        'all' => '全部',
        'not_started' => '未开始',
        'ongoing' => '进行中',
        'pending_draw' => '待开奖',      // ⭐ 新增：活动结束，等待开奖
        'drawing' => '开奖中',
        'ended' => '已结束',
        'closed' => '已关閉',
        'preheating' => '预熱期',        // 已废弃
        'betting' => '打码中',           // 已废弃
        'drawn' => '已开奖待派奖',       // 已废弃
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
        'betting' => '打码获得',
        'recharge' => '充值贈送',
        'activity' => '活动贈送',
        'manual' => '手动发放',
        'unknown' => '未知來源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => '待派奖',
        'claimed' => '已派奖', // ⭐ 更新
        'expired' => '已过期', // ⭐ 新增
        'cancelled' => '已取消', // ⭐ 新增
        'processing' => '派奖中', // ⭐ 新增
        'failed' => '派奖失败',
        'granted' => '已派奖', // 兼容舊代码
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
        'created_at' => '创建时间',
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
        'start_drawing' => '开始开奖',
        'stop_drawing' => '停止开奖',
        'add_live_url' => '添加直播地址',
        'generate_push_url' => '生成推流地址',
        'copy_push_server' => '复制服务器',
        'copy_stream_key' => '复制密钥',
        'expand' => '展开',
        'collapse' => '收起',
        'distribute' => '发放',
        'retry_distribute' => '重新发放',  // ⭐ 新增：针对失败记录的补救措施
        'batch_distribute' => '批量发放',  // ⭐ 已弃用
        'batch_distribute_selected' => '批量派奖选中',  // ⭐ 已弃用
        'distribute_by_ticket' => '录入券号派奖',
        'distribute_all_pending' => '派奖',  // ⭐ 新增:批量派奖所有待派奖记录
        'view_ticket_list' => '查看发放列表',
        'add_ticket' => '添加券号',
        'select_image' => '选择图片',
        'confirm_distribute' => '确认发放',
        'retry_distribute_confirm' => '确认重新发放',  // ⭐ 新增：确认重新发放
    ],

    // 统计
    'stats' => [
        'total_activities' => '总活动数',
        'ongoing_activities' => '进行中活动',
        'total_draws' => '总抽奖次数',
        'total_winners' => '总中奖人数',
        'total_prize_amount' => '总奖金金额',
        'pending_count' => '派奖记录',
        'pending_amount' => '派奖金额',
        'claimed_count' => '已派奖记录',
        'claimed_amount' => '已派奖金额',
        'count_suffix' => '笔',
        'panel_header' => '查看统计数据',
        'loading' => '数据加载中...',
        'refresh' => '刷新数据',
        'load_error' => '数据加载失败',
        'retry' => '重试',
        'click_to_view' => '点击展开查看统计数据',
        'load_failed_msg' => '数据加载失败，请重试',
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
        'live_url_generated' => '直播地址生成成功',
        'push_url_generated' => '推流地址生成成功',
        'push_url_generated_success' => '推流地址生成成功，有效期3天',
        'copy_success' => '复制成功',
        'copy_failed' => '复制失败，请手动复制',
        'player_config_loaded' => '播放器配置加载成功',
        'player_config_loaded_with_region' => '播放器配置加载成功（使用{region}域名）',
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
        'drawing_started' => '开奖已开始，进入开奖阶段',
        'activity_ended' => '活动已结束',
        'select_tencent_config' => '请选择腾讯云配置',
        'stream_name_required' => '流名称不能为空',
        'tencent_config_not_found' => '腾讯云配置不存在',
    ],

    // 错误信息
    'error' => [
        // 时间冲突
        'time_conflict_with_activity' => '活动时间与现有活动「{name}」冲突（{start_time} ~ {end_time}），同一时间段只能有一个活动在进行中',
        'record_not_found' => '记录不存在',
        'live_url_required' => '请填写直播地址',
        'live_url_too_long' => '直播地址过长（最多500字符）',
        'live_already_started' => '直播已开始，无法重复开启',
        'live_already_ended' => '直播已结束',
        'live_not_started' => '直播尚未开始，无法结束',
        'cannot_start_drawing' => '活动进行中无法开奖，请等待活动结束后再开奖（只有已结束状态的活动才能开奖）',
        'cannot_stop_drawing' => '当前状态无法停止开奖（只有开奖中的活动可以结束）',
        'cannot_record_win_in_current_status' => '当前状态无法录入中奖（只能在进行中或开奖中状态录入）',
        // 输入驗證
        'invalid_record_id' => '參数错誤：记录ID无效',
        'invalid_activity_id' => '參数错誤：活动ID无效',
        'invalid_record_ids' => '參数错誤：记录ID必須是数組',
        'invalid_record_id_value' => '參数错誤：记录ID包含非法值',
        'note_too_long' => '发放备註不能超过255个字符',
        'no_selection' => '请指定活动ID或选择记录',
        'no_pending_records' => '沒有待派奖的记录',
        // 业务邏輯驗證
        'invalid_status' => '记录状态不正确，只能派奖待派奖的记录',
        'status_changed' => '状态已变更',
        'empty_prize' => '空奖无需派奖',
        'invalid_amount' => '奖品金额必須大於0',
        'player_not_found' => '玩家不存在',
        'player_disabled' => '玩家已被禁用，无法派奖',
        'activity_not_found' => '活动不存在',
        'activity_invalid_status' => '活动状态错誤，只能派奖已开奖待派奖的活动奖勵',
        'activity_not_in_drawing_status' => '活动状态错误，只能在开奖中或已结束状态派奖',
        'amount_exceeded' => '派奖金额超出总奖金额度',
        'ticket_not_found_or_used' => '券号 {ticket_no} 不存在或已使用',
        'ticket_already_won' => '券号 {ticket_no} 已录入过中奖记录，不能重复录入',
        'prize_level_not_found_for_ticket' => '券号 {ticket_no} 的奖品等级不存在',
        'invalid_ticket_format' => '券号 {ticket_no} 格式错误，只能包含数字且不超过6位',
        'distribute_failed' => '券号 {ticket_no} 派奖失败：{reason}',  // ⭐ 新增：录入自动派奖失败提示
        'bet_progress_not_found' => '未找到打码进度记录',
        // ⭐ 单个录入查询玩家信息
        'activity_id_required' => '活动ID不能为空',
        'ticket_no_required' => '券号不能为空',
        'activity_not_exist' => '活动不存在',
        'ticket_not_exist_or_not_belong' => '券号不存在或不属于该活动',
        'ticket_already_used' => '该券号已使用，无法录入中奖',
        'ticket_already_recorded' => '该券号已录入中奖记录，请勿重复录入',
        'player_not_found_for_ticket' => '未找到该券号对应的玩家',
        // 停止开奖确认
        'stop_drawing_no_records_confirm' => '⚠️ 尚未录入任何中奖券号！\n\n停止开奖后将无法再录入中奖券号，活动将进入已结束状态。\n\n确定要停止开奖吗？',
        'stop_drawing_with_records_confirm' => '确认停止开奖？\n\n📊 中奖统计：\n• 已录入券号数：{count} 张\n• 奖金总额：{amount}分\n• 待派奖：{pending} 笔\n• 已派奖：{granted} 笔\n\n⚠️ 停止后将无法再录入中奖券号',
        // 推流相关错误
        'stream_name_required' => '流名称不能为空',
        'invalid_stream_name_format' => '流名称格式错误，只能包含英文字母、数字、下划线、中横线',
        'no_tencent_config' => '未找到腾讯云直播配置，请先在系统中配置推流参数',
        'incomplete_tencent_config' => '腾讯云直播配置不完整，缺少推流域名或推流密钥',
        'generate_push_url_failed' => '生成推流地址失败',
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
        'invalid_time_format' => '时间格式错误，请选择有效的日期时间',
        'end_before_start' => '结束时间必须晚于开始时间',
        'start_time_in_past' => '开始时间不能早于当前时间',
        'duration_too_short' => '活动时长过短，至少需要 {min}',
        'duration_too_long' => '活动时长过长，最多 {max}',
        'cannot_edit_started' => '只能编輯未开始的活动',
        'invalid_file' => '无效的文件',
        'invalid_image_type' => '只支持 jpg、png 格式图片',
        'file_too_large' => '文件大小不能超过2MB',
        'upload_failed' => '上传失败，请重试',
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
        'activity_name_hint' => '活动名称将显示在玩家端，请简洁明了',
        'description_hint' => '活动说明支持富文本格式，可添加图片、表格等内容',
        'start_time_hint' => '玩家可开始打码获取摸奖券的时间',
        'end_time_hint' => '活动结束后自动进入待开奖状态',
        'vip_config_detail' => '玩家在活动期间累计达到指定打码量后，系统将自动发放对应数量的摸奖券',
        'prize_config_detail' => '可添加最多10个奖项，开奖时将从所有中奖券号中抽取对应数量的奖品发放给玩家',
        'prize_name_hint' => '自定义奖项名称，最多20字',
        'prize_amount_hint' => '中奖玩家将获得的现金奖励',
        'prize_count_hint' => '此奖项的奖品总数量',
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
        'distribution_note' => '派奖备註',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],

    // 确认对话框
    'confirm' => [
        'distribute' => '确认派奖此奖品到玩家賬戶？',
        'retry_distribute' => '此记录之前派奖失败，确认要重新派奖吗？',  // ⭐ 新增：针对失败记录的重试确认
        'distribute_all_pending' => '确认派奖该活动所有已录入但未派奖的奖励？\n此操作将批量派奖所有待派奖记录,请谨慎操作。',  // ⭐ 新增
    ],

    // 风险警告
    'warning' => [
        'batch_distribute_title' => '批量派奖风险提示',
        'batch_distribute_point1' => '此操作将自动派发该活动下所有待派奖记录，无需手动选择',
        'batch_distribute_point2' => '派奖成功后将立即增加玩家钱包余额，无法撤销',
        'batch_distribute_point3' => '建议先核对中奖记录和金额是否正确',
        'batch_distribute_point4' => '大额奖金请谨慎确认后再操作',
    ],

    // 表单标签
    'form' => [
        'select_activity' => '选择活动',
        'select_activity_help' => '只顯示已开奖待派奖的活动',
        'distribution_note' => '派奖备註',
        'distribution_note_placeholder' => '请填寫派奖备註（选填）',
        'vip_config_section' => 'VIP等级打码量配置',
        'prize_config_section' => '奖品等级配置',
        'no_vip_data' => '暫无VIP等级数據',
        'no_vip_config' => '未配置VIP等级',
        // ⭐ 第二批：表单标签（2026-07-01）
        'cover_image' => '活动封面图片',
        'vip_level' => 'VIP等级',
        'bet_amount_required' => '所需打码量',
        'ticket_count' => '发放券数',
        'prize_amount_label' => '奖励金额',
        'prize_count' => '奖品数量',
        'level_label' => '等级',
        'vip_config_hint' => '为每个VIP等级配置达到指定打码量后发放的摸奖券数量',
        'prize_config_hint' => '配置奖品等级和奖励金额(仅现金奖励)',
        'prize_name_label' => '奖项名称',
        'prize_name_placeholder' => '例如：特等奖、一等奖',
        'input_ticket_hint' => '输入数字，如: 12 或 000012',
        'add_ticket_no' => '添加券号',
        'cover_alt' => '活动封面',
        'end_live_confirm_content_full' => '确认结束直播吗？结束后玩家将无法继续观看。',
        'confirm_end' => '确认结束',
        'live_stream_name_required' => '请输入直播流名称',
        'at_least_one_ticket' => '请至少输入一个券号',
    ],

    // 筛选器 ⭐ 新增
    'filter' => [
        'time_range' => '时间范围',
        'create_time_range' => '创建时间范围',
        'activity_time_range' => '活动时间范围',
    ],

    // 表格列标题 ⭐ 第二批（2026-07-01）
    'table' => [
        'level' => '等级',
        'vip_level' => 'VIP等级',
        'bet_amount_required' => '所需打码量',
        'ticket_count' => '发放券数',
        'prize_amount' => '奖励金额',
        'created_at' => '发放时间',
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
        // ⭐ 第一批：按钮、菜单、状态（2026-07-01）
        'create_from_scratch' => '从零创建',
        'create_from_history' => '从历史活动创建',
        'live_streaming' => '直播中',
        'not_live' => '未开播',
        'preview_live' => '预览直播',
        'start_live' => '开始直播',
        'end_live' => '结束直播',
        'view_ticket_list' => '查看发放列表',
        'select_image' => '选择图片',
        'no_history_activities' => '暂无历史活动',
        'select_history_activity' => '选择历史活动',
        'end_live_confirm_title' => '结束直播',
        'end_live_confirm_content' => '确认结束直播吗？结束后玩家将无法继续观看。',
        'ending_live' => '正在结束直播...',
        'start_live_failed' => '开始直播失败',
        'end_live_failed' => '结束直播失败',
        'ticket_list_title' => '摸奖券发放列表',
        'search' => '搜索',
        'reset' => '重置',
        // ⭐ 第三批：帮助文本、提示信息（2026-07-01）
        'cover_hint' => '建议尺寸：750x400px，支持jpg、png格式，文件大小不超过2MB',
        'cover_preview_alt' => '封面预览',
        'no_vip_data_desc' => '暂无VIP等级数据',
        'set_live_stream_title' => '设置直播流名称',
        'live_stream_hint' => '💡 只需填写流名称，系统会自动生成腾讯云直播地址',
        'live_stream_label' => '直播流名称',
        'live_stream_placeholder' => '例如：mojiangjuan',
        'live_stream_name_hint' => '建议使用英文、数字、下划线，此名称需与OBS推流配置一致',
        'live_preview_title' => '直播预览 - {name}',
        'live_url_label' => '直播地址：',
        'copy_url' => '复制地址',
        'open_new_window' => '新窗口打开',
        'rtmp_protocol_warning' => 'RTMP 协议播放提示',
        'no_live_url' => '未获取到直播地址',
        'generating_live_url' => '正在生成直播地址...',
        'live_url_copied' => '直播地址已复制到剪贴板',
        'stream_name_format_error' => '流名称只能包含英文、数字和下划线',
        'stream_name_too_long' => '流名称不能超过50个字符',
        'activity_no_live_url' => '该活动尚未设置直播流名称',
        'generate_live_url_failed' => '生成直播地址失败',
        'edit_live_url' => '编辑直播地址',
        'cancel_enter_win' => '取消，先录入中奖',
    ],
];
