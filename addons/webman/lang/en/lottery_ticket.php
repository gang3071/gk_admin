<?php

return [
    // 菜单
    'menu' => [
        'main' => 'Lottery Ticket Management',
        'dashboard' => 'Ongoing Activities',
        'history' => 'Historical Activities',
        'records' => 'Winning Records',
    ],

    // 标题
    'title' => [
        'main' => 'Lottery Ticket Management',
        'activity_detail' => '活动詳情',
        'ticket_list' => '摸奖券列表',
        'record_list' => '中奖记录',
    ],

    // 字段
    'fields' => [
        'id' => 'ID',
        'name' => 'Activity Name',
        'activity_name' => 'Activity Name',
        'description' => 'Description',
        'cover_image' => 'Activity Cover',
        'cover_image_upload' => 'Activity Cover Image',
        'live_url' => 'Live URL',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'status' => 'Status',
        'total_tickets' => 'Total Tickets',
        'used_tickets' => 'Used Tickets',
        'usage_rate' => 'Usage Rate',
        'pending_count' => 'Pending Award',
        'max_ticket_no' => 'Max Issued Ticket',  // ⭐ Current maximum issued ticket number (e.g.: 000123)
        'operation' => '操作',  // ⭐ 新增
        'prize_config' => 'Prize Config',
        'created_at' => 'Created At',
        'prize_level_config' => 'Prize Level Config',
        'total_probability' => 'Total Probability',
        'level' => 'Level',
        'time_range' => 'Time Range',
        'player_account' => '玩家賬号',
        'prize_level' => 'Prize Level',
        'remark' => '备註',
        'distribution_remark' => 'Award Distribution Note',
        'vip_level' => 'VIP Level',
        'bet_amount_required' => 'Bet Amount Required',
        'ticket_count' => 'Ticket Count',
        'prize_amount' => '奖勵金额',
        'prize_count' => 'Prize Count',
        'ticket_no_input' => 'Winning Ticket No',

        // 中奖记录
        'player_name' => 'Player Name',
        'player_uuid' => 'Player UUID',
        'player_username' => 'Player Account',
        'ticket_no' => 'Ticket No',
        'prize_type' => 'Prize Type',
        'prize_name' => 'Prize Name',
        'record_status' => 'Award Distribution Status',
        'created_time_range' => 'Created Time Range',
        'source' => '來源',
        'used_at' => 'Used At',
        'expired_at' => 'Expired At',

        // Statistics fields
        'total_winners' => 'Total Winners',        // ⭐ History list page
        'total_prize_amount' => 'Total Prize Amount',  // ⭐ History list page
    ],

    // 占位符
    'placeholder' => [
        'name' => 'Please enter activity name',
        'description' => 'Please enter description',
        'start_time' => 'Please select start time',
        'end_time' => 'Please select end time',
        'level_rank' => 'Please select level rank',
        'prize_type' => 'Please select prize type',
        'player_account' => '请输入玩家賬号/手机号/UUID',
        'prize_level' => 'Please select prize level',
        'remark' => '选填，可备註中奖詳情',
        'live_url' => 'e.g.: rtmp://live.example.com/stream/12345',
        'ticket_no' => 'Please enter 6-digit ticket number',
        'award_remark' => '选填，可备註发放说明',
    ],

    // 模态框
    'modal' => [
        'record_win_title' => 'Record Winning Entry',
        'live_url_title' => 'Add Live URL',
        'live_url_prompt' => 'Please enter live stream URL:',
        'live_url_required' => 'Please enter live URL',
        'batch_award_title' => '批量发放奖勵',
        'award_by_ticket_title' => '发放奖勵',
        'ticket_list_title' => 'Ticket Award Distribution List',
    ],

    // 活动状态
    'status' => [
        'all' => 'All',
        'not_started' => 'Not Started',
        'ongoing' => 'Ongoing',
        'pending_draw' => 'Pending Award Draw',    // ⭐ New: Activity ended, waiting for draw
        'drawing' => 'Drawing',
        'ended' => 'Ended',
        'closed' => 'Closed',
        'preheating' => 'Preheating',        // Deprecated
        'betting' => 'Betting',              // Deprecated
        'drawn' => 'Drawn Pending Award Award Distribution', // Deprecated
        'unknown' => 'Unknown',
    ],

    // 直播状态
    'live_status' => [
        'not_started' => 'Not Started',
        'ongoing' => 'Live',
        'ended' => 'Ended',
        'unknown' => 'Unknown',
    ],

    // 摸奖券状态
    'ticket_status' => [
        'unused' => 'Unused',
        'used' => 'Used',
        'expired' => 'Expired',
        'unknown' => 'Unknown',
    ],

    // 来源
    'source' => [
        'recharge' => '充值贈送',
        'activity' => '活动贈送',
        'manual' => 'Manual Award Distribution',
        'unknown' => '未知來源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => 'Pending Award',
        'claimed' => 'Awardd', // ⭐ 更新
        'expired' => 'Expired', // ⭐ 新增
        'cancelled' => 'Cancelled', // ⭐ 新增
        'processing' => 'Processing', // ⭐ 新增
        'failed' => 'Award Distribution Failed',
        'granted' => 'Awardd', // 兼容舊代码
        'unknown' => 'Unknown',
    ],

    // 奖品类型
    'prize_type' => [
        'cash' => 'Cash',
        'bonus' => 'Bonus',
        'item' => 'Physical Item',
        'points' => '積分',
        'empty' => 'No Prize',
        'unknown' => 'Unknown Type',
    ],

    // 中奖等级名称
    'level_name' => [
        'special' => '特等奖',
        'first' => 'First Prize',
        'second' => 'Second Prize',
        'third' => 'Third Prize',
        'fourth' => 'Fourth Prize',
        'fifth' => 'Fifth Prize',
        'sixth' => 'Sixth Prize',
        'seventh' => 'Seventh Prize',
        'eighth' => 'Eighth Prize',
        'ninth' => 'Ninth Prize',
        'default' => '等级:rank',
    ],

    // 中奖记录字段 ⭐ Added
    'record_fields' => [
        'prize_type' => 'Prize Type',
        'prize_level_name' => 'Prize Level',
        'created_at' => 'Created At',
    ],

    // 中奖等级字段
    'prize_level_fields' => [
        'level_rank' => 'Level Rank',
        'level_name' => 'Level Name',
        'prize_type' => 'Prize Type',
        'prize_amount' => 'Prize Amount',
        'prize_item_name' => 'Item Name',
        'prize_item_image' => 'Item Image',
        'prize_count' => 'Prize Count',
        'won_count' => 'Won Count',  // ⭐ Added
        'remaining_count' => 'Remaining',  // ⭐ Added
        'win_probability' => 'Win Probability (%)',
        'description' => 'Prize Description',
    ],

    // 操作
    'action' => [
        'create' => 'Create Activity',
        'create_first' => 'Create Now',
        'edit' => '编輯活动',
        'view' => '查看詳情',
        'view_detail' => '查看詳情',
        'operation' => '操作',  // ⭐ 新增
        'prize_config' => 'Prize Config',
        'close' => '关閉活动',
        'export' => 'Export Records',
        'add_prize_level' => 'Add Prize Level',
        'record_win' => 'Record Winning',
        'start_drawing' => 'Start Drawing',
        'stop_drawing' => 'Stop Drawing',
        'add_live_url' => 'Add Live URL',
        'expand' => 'Expand',
        'collapse' => 'Collapse',
        'award' => 'Award',
        'batch_award' => 'Batch Award Distribution',
        'batch_award_selected' => 'Batch Award Selected',
        'award_by_ticket' => 'Award by Ticket',
        'award_all_pending' => 'Award Rewards',  // ⭐ Added: Batch award all pending records
        'view_ticket_list' => 'View Award Distribution List',
        'add_ticket' => 'Add Ticket',
        'select_image' => 'Select Image',
        'confirm_award' => 'Confirm Award Distribution',
    ],

    // 统计
    'stats' => [
        'total_activities' => 'Total Activities',
        'ongoing_activities' => 'Ongoing Activities',
        'total_draws' => 'Total Draws',
        'total_winners' => 'Total Winners',
        'total_prize_amount' => 'Total Prize Amount',
        'pending_count' => 'Pending Records',
        'pending_amount' => 'Pending Amount',
        'claimed_count' => 'Claimed Records',
        'claimed_amount' => 'Claimed Amount',
        'count_suffix' => '',
        'panel_header' => 'View Statistics',
        'loading' => 'Loading data...',
        'refresh' => 'Refresh',
        'load_error' => 'Failed to load data',
        'retry' => 'Retry',
        'click_to_view' => 'Click to view statistics',
        'load_failed_msg' => 'Failed to load data, please retry',
    ],

    // 消息
    'message' => [
        'create_success' => 'Activity created successfully',
        'update_success' => 'Activity updated successfully',
        'close_success' => '活动关閉成功',
        'activity_not_found' => 'Activity not found',
        'activity_closed' => '活动已关閉',
        'activity_not_ongoing' => '只能关閉进行中的活动',
        'time_conflict' => '活动时间衝突',
        'prize_level_saved' => '奖品等级保存成功',
        'prize_level_deleted' => '奖品等级刪除成功',
        'no_activities' => '暫无活动',
        'no_prize_config' => 'Prize levels not configured yet',
        'prize_level_hint' => '最多可配置10个奖品等级,中奖概率总和不能超过100%',
        'upload_success' => '上传成功',
        'image_upload_success' => 'Image uploaded successfully',
        'image_upload_failed' => '图片上传失敗',
        'award_success' => 'Award Distribution successful',
        'award_failed' => '发放失敗',
        'batch_complete' => '批量发放完成：成功 {success} 条，失敗 {fail} 条',
        'batch_award_selected' => '批量发放选中记录',
        'export_in_development' => '导出功能开发中',
        'live_url_updated' => 'Live stream URL updated successfully',
        'live_url_generated' => 'Live stream URL generated successfully',
        'player_config_loaded' => 'Player configuration loaded successfully',
        'player_config_loaded_with_region' => 'Player configuration loaded successfully (using {region} domain)',
        'record_success' => 'Winning record entered successfully',
        'record_success_count' => 'Successfully recorded {count} winning entries',
        'live_started' => 'Live started',
        'live_ended' => 'Live ended',
        'select_tencent_config' => 'Please select Tencent Cloud configuration',
        'stream_name_required' => 'Stream name is required',
        'tencent_config_not_found' => 'Tencent Cloud configuration not found',
        'status_updated' => 'Status updated successfully',
        'admin_manual_update' => '管理員手动更新',
        'fetch_failed' => 'Failed to fetch activities',
        'fetch_detail_failed' => 'Failed to fetch details',
        'close_activity_failed' => 'Failed to close activity',
        'min_one_ticket' => 'Please enter at least one ticket number',
        'please_input_ticket' => 'Please enter ticket number',
        'ticket_must_6_digits' => 'Ticket number must be 6 digits',
        'ticket_format_error' => 'Invalid ticket number format, must contain only digits and not exceed 6 characters',
        'no_prize_level' => 'Prize levels not configured for this activity',
        'award_hint' => 'Please enter winning ticket number, the system will automatically identify the prize level and award rewards',
        'drawing_started' => 'Drawing started, entered drawing phase',
        'activity_ended' => 'Activity ended',
    ],

    // 错误信息
    'error' => [
        // Time conflict
        'time_conflict_with_activity' => 'Activity time conflicts with existing activity "{name}" ({start_time} ~ {end_time}). Only one activity can be active at the same time.',
        'record_not_found' => 'Record not found',
        'live_url_required' => 'Please enter live stream URL',
        'live_url_too_long' => 'Live stream URL too long (max 500 characters)',
        'live_already_started' => 'Live stream already started, cannot start again',
        'live_already_ended' => 'Live stream already ended',
        'live_not_started' => 'Live stream not started yet, cannot end',
        'cannot_start_drawing' => 'Cannot start drawing while activity is ongoing. Please wait until activity ends (only ended activities can start drawing)',
        'cannot_stop_drawing' => 'Cannot stop drawing in current status (only drawing activities can be ended)',
        'cannot_record_win_in_current_status' => 'Cannot record winners in current status (only allowed during ongoing or drawing status)',
        // 输入驗證
        'invalid_record_id' => '參数错誤：记录ID无效',
        'invalid_activity_id' => '參数错誤：活动ID无效',
        'invalid_record_ids' => '參数错誤：记录ID必須是数組',
        'invalid_record_id_value' => '參数错誤：记录ID包含非法值',
        'note_too_long' => 'Award Distribution remark cannot exceed 255 characters',
        'no_selection' => 'Please specify activity ID or select records',
        'no_pending_records' => 'No pending distribution records',
        // 业务邏輯驗證
        'invalid_status' => 'Invalid status, can only award pending records',
        'status_changed' => 'Status changed',
        'empty_prize' => 'No prize, no distribution needed',
        'invalid_amount' => 'Prize amount must be greater than 0',
        'player_not_found' => 'Player not found',
        'player_disabled' => 'Player disabled, cannot award',
        'activity_not_found' => 'Activity not found',
        'activity_invalid_status' => 'Invalid activity status',
        'activity_not_in_drawing_status' => 'Invalid activity status, can only award in DRAWING or ENDED status',
        'amount_exceeded' => 'Award Distribution amount exceeds total prize',
        'ticket_not_found_or_used' => 'Ticket {ticket_no} not found or used',
        'prize_level_not_found_for_ticket' => 'Prize level not found for ticket {ticket_no}',
        'invalid_ticket_format' => 'Invalid format for ticket {ticket_no}, must contain only digits and not exceed 6 characters',
        'bet_progress_not_found' => 'Bet progress record not found',
        // Stop drawing confirmation
        'stop_drawing_no_records_confirm' => '⚠️ No winning tickets recorded yet!\n\nAfter stopping drawing, you will not be able to record winning tickets. The activity will enter ENDED status.\n\nAre you sure to stop drawing?',
        'stop_drawing_with_records_confirm' => 'Confirm to stop drawing?\n\n📊 Winning Statistics:\n• Recorded tickets: {count}\n• Total prize amount: NT$ {amount}\n• Pending awards: {pending}\n• Granted awards: {granted}\n\n⚠️ Cannot record more tickets after stopping',
        // 其他
        'too_many_levels' => 'Max {max} prize levels allowed',
        'no_prize_levels' => 'Please set at least one prize level',
        'no_prizes' => 'Prize count cannot be 0',
        'probability_exceed' => 'Total probability cannot exceed 100%, current: {total}%',
        'level_rank_exists' => 'Level rank already exists',
        'invalid_prize_type' => 'Invalid prize type',
        'name_required' => 'Please enter activity name',
        'time_required' => 'Please select activity time',
        'invalid_time' => 'End time must be after start time',
        'invalid_time_format' => 'Invalid time format, please select valid date time',
        'end_before_start' => 'End time must be later than start time',
        'start_time_in_past' => 'Start time cannot be earlier than current time',
        'duration_too_short' => 'Activity duration too short, minimum {min}',
        'duration_too_long' => 'Activity duration too long, maximum {max}',
        'cannot_edit_started' => 'Can only edit activities not started',
        'invalid_file' => 'Invalid file',
        'invalid_image_type' => 'Only jpg, png format supported',
        'file_too_large' => 'File size cannot exceed 2MB',
        'upload_failed' => 'Upload failed, please try again',
        'invalid_params' => 'Invalid parameter',
        'activity_not_ongoing' => 'Can only record wins for ongoing activities',
        'prize_level_not_found' => 'Prize level not found',
        'invalid_status_value' => 'Invalid status value',
    ],

    // 帮助文本
    'help' => [
        'cover_image' => 'Recommended: 750x400px, jpg/png, max 2MB',
        'cover_alt' => 'Activity Cover',
        'cover_preview' => 'Cover Preview',
        'vip_config_hint' => 'Configure ticket count for each VIP level upon reaching bet amount',
        'prize_config_hint' => 'Configure prize levels and amounts (cash only)',
        'input_ticket_no' => 'Enter ticket number:',
    ],

    // 详情视图标签
    'view' => [
        'detail_title' => 'Winning Record Details',
        'basic_info' => 'Basic Info',
        'prize_info' => 'Prize Info',
        'distribution_info' => 'Award Distribution Info',
        'activity_name' => 'Activity Name',
        'ticket_no' => 'Ticket No',
        'player_name' => 'Player',
        'player_phone' => 'Phone',
        'prize_name' => 'Prize Name',
        'prize_type' => 'Prize Type',
        'prize_amount' => 'Prize Amount',
        'status' => 'Status',
        'awardd_at' => 'Award Distribution Time',
        'awardd_by' => 'Distributor',
        'distribution_note' => 'Award Distribution Note',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    // 确认对话框
    'confirm' => [
        'award' => 'Confirm award this prize to player account?',
        'award_all_pending' => 'Confirm to award all pending rewards for this activity?\nThis will batch award all pending records. Please proceed with caution.',  // ⭐ Added
    ],

    // Risk Warning
    'warning' => [
        'batch_distribute_title' => 'Batch Distribution Risk Warning',
        'batch_distribute_point1' => 'This will automatically distribute ALL pending records for the selected activity, no manual selection needed',
        'batch_distribute_point2' => 'Successful distribution will immediately credit player wallets and CANNOT be reversed',
        'batch_distribute_point3' => 'Please verify winning records and amounts before proceeding',
        'batch_distribute_point4' => 'Exercise extra caution with large prize amounts',
    ],

    // 表单标签
    'form' => [
        'select_activity' => 'Select Activity',
        'select_activity_help' => 'Only showing drawn activities pending distribution',
        'distribution_note' => 'Award Distribution Note',
        'distribution_note_placeholder' => 'Enter distribution note (optional)',
        'vip_config_section' => 'VIP Bet Amount Config',
        'prize_config_section' => 'Prize Level Config',
        'no_vip_data' => 'No VIP data available',
        'no_vip_config' => 'VIP not configured',
    ],

    // Filter ⭐ Added
    'filter' => [
        'time_range' => 'Time Range',
        'create_time_range' => 'Create Time Range',
        'activity_time_range' => 'Activity Time Range',
    ],

    // 验证消息
    'validation' => [
        'name_required' => 'Please enter activity name',
        'name_max_length' => 'Activity name cannot exceed 100 characters',
        'start_time_required' => 'Please select start time',
        'end_time_required' => 'Please select end time',
        'ticket_no_required' => 'Please enter ticket number',
        'image_format_error' => 'Only JPG/PNG images allowed!',
        'image_size_error' => 'Image size cannot exceed 2MB!',
    ],

    // 其他文本
    'ui' => [
        'total_records' => 'Total {total} records',
        'yuan' => 'Yuan',
        'upload_failed' => 'Upload failed',
    ],
];
