<?php

return [
    // 菜单
    'menu' => [
        'main' => '摸獎券管理',
        'dashboard' => '進行中的活動',
        'history' => '歷史活動記錄',
        'records' => '中獎記錄',
    ],

    // 标题
    'title' => [
        'main' => '摸獎券管理',
        'activity_detail' => '活動詳情',
        'ticket_list' => '摸獎券列表',
        'record_list' => '中獎記錄',
    ],

    // 字段
    'fields' => [
        'id' => 'ID',
        'name' => '活動名稱',
        'activity_name' => '活動名稱',
        'description' => '活動說明',
        'cover_image' => '活動封面',
        'cover_image_upload' => '活動封面圖片',
        'live_url' => '直播地址',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
        'status' => '活動狀態',
        'total_tickets' => '總派獎數量',
        'used_tickets' => '已使用數量',
        'usage_rate' => '使用率',
        'pending_count' => '待派獎',
        'max_ticket_no' => '已發最大券號',  // ⭐ 當前已派獎的最大券號（如：000123）
        'prize_config' => '獎品配置',
        'created_at' => '創建時間',
        'prize_level_config' => '獎品等級配置',
        'total_probability' => '概率總和',
        'level' => '等級',
        'time_range' => '活動時間',
        'player_account' => '玩家賬號',
        'prize_level' => '中獎等級',
        'remark' => '備註',
        'distribution_remark' => '派獎備註',
        'vip_level' => 'VIP等級',
        'bet_amount_required' => '所需打碼量',
        'ticket_count' => '派獎券數',
        'prize_amount' => '獎勵金額',
        'prize_count' => '獎品數量',
        'ticket_no_input' => '中獎券號',

        // 中奖记录
        'player_name' => '玩家名稱',
        'player_uuid' => '玩家UUID',
        'player_username' => '玩家賬號',
        'ticket_no' => '券號',
        'prize_type' => '獎品類型',
        'prize_name' => '獎品名稱',
        'record_status' => '派獎狀態',
        'created_time_range' => '創建時間範圍',
        'source' => '來源',
        'used_at' => '使用時間',
        'expired_at' => '過期時間',

        // 统计字段
        'total_winners' => '中獎總數量',      // ⭐ 历史记录页面
        'total_prize_amount' => '中獎總金額',  // ⭐ 历史记录页面
    ],

    // 占位符
    'placeholder' => [
        'name' => '請輸入活動名稱',
        'description' => '請輸入活動說明',
        'start_time' => '請選擇開始時間',
        'end_time' => '請選擇結束時間',
        'level_rank' => '請選擇等級排名',
        'prize_type' => '請選擇獎品類型',
        'player_account' => '請輸入玩家賬號/手機號/UUID',
        'prize_level' => '請選擇中獎等級',
        'remark' => '選填，可備註中獎詳情',
        'live_url' => '例如: rtmp://live.example.com/stream/12345',
        'ticket_no' => '請輸入6位券號',
        'distribute_remark' => '選填，可備註派獎說明',
    ],

    // 模态框
    'modal' => [
        'record_win_title' => '錄入中獎記錄',
        'live_url_title' => '添加直播地址',
        'live_url_prompt' => '請輸入直播流地址:',
        'live_url_required' => '請輸入直播地址',
        'batch_distribute_title' => '批量派獎獎勵',
        'distribute_by_ticket_title' => '錄入券號派獎獎勵',
        'ticket_list_title' => '摸獎券派獎列表',
    ],

    // 活动状态
    'status' => [
        'all' => '全部',
        'not_started' => '未開始',
        'ongoing' => '進行中',
        'pending_draw' => '待開獎',      // ⭐ 新增：活動結束，等待開奖
        'drawing' => '開獎中',
        'ended' => '已結束',
        'closed' => '已關閉',
        'preheating' => '預熱期',        // 已廢棄
        'betting' => '打碼中',           // 已廢棄
        'drawn' => '已開獎待派獎',       // 已廢棄
        'unknown' => '未知狀態',
    ],

    // 直播状态
    'live_status' => [
        'not_started' => '未開播',
        'ongoing' => '直播中',
        'ended' => '已結束',
        'unknown' => '未知狀態',
    ],

    // 摸奖券状态
    'ticket_status' => [
        'unused' => '未使用',
        'used' => '已使用',
        'expired' => '已過期',
        'unknown' => '未知狀態',
    ],

    // 来源
    'source' => [
        'betting' => '打碼獲得',
        'recharge' => '充值贈送',
        'activity' => '活動贈送',
        'manual' => '手動派獎',
        'unknown' => '未知來源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => '待派獎',
        'claimed' => '已派獎', // ⭐ 更新
        'expired' => '已過期', // ⭐ 新增
        'cancelled' => '已取消', // ⭐ 新增
        'processing' => '派獎中', // ⭐ 新增
        'failed' => '派獎失敗',
        'granted' => '已派獎', // 兼容舊代碼
        'unknown' => '未知狀態',
    ],

    // 奖品类型
    'prize_type' => [
        'cash' => '現金',
        'bonus' => '紅利',
        'item' => '實物',
        'points' => '積分',
        'empty' => '未中獎',
        'unknown' => '未知類型',
    ],

    // 中奖等级名称
    'level_name' => [
        'special' => '特等獎',
        'first' => '一等獎',
        'second' => '二等獎',
        'third' => '三等獎',
        'fourth' => '四等獎',
        'fifth' => '五等獎',
        'sixth' => '六等獎',
        'seventh' => '七等獎',
        'eighth' => '八等獎',
        'ninth' => '九等獎',
        'default' => '等級:rank',
    ],

    // 中奖记录字段 ⭐ 新增
    'record_fields' => [
        'prize_type' => '獎品類型',
        'prize_level_name' => '獎品等級',
        'created_at' => '創建時間',
    ],

    // 中奖等级字段
    'prize_level_fields' => [
        'level_rank' => '等級排名',
        'level_name' => '等級名稱',
        'prize_type' => '獎品類型',
        'prize_amount' => '獎品金額',
        'prize_item_name' => '實物名稱',
        'prize_item_image' => '實物圖片',
        'prize_count' => '獎品數量',
        'won_count' => '已中獎數',  // ⭐ 新增
        'remaining_count' => '剩餘數量',  // ⭐ 新增
        'win_probability' => '中獎概率(%)',
        'description' => '獎品描述',
    ],

    // 操作
    'action' => [
        'create' => '創建活動',
        'create_first' => '立即創建',
        'edit' => '編輯活動',
        'view' => '查看詳情',
        'view_detail' => '查看詳情',
        'operation' => '操作',  // ⭐ 新增
        'prize_config' => '獎品配置',
        'close' => '關閉活動',
        'export' => '導出記錄',
        'add_prize_level' => '添加獎品等級',
        'record_win' => '錄入中獎',
        'start_drawing' => '開始開獎',
        'stop_drawing' => '停止開獎',
        'add_live_url' => '添加直播地址',
        'expand' => '展開',
        'collapse' => '收起',
        'distribute' => '派獎',
        'batch_distribute' => '批量派獎',
        'batch_distribute_selected' => '批量派獎選中',
        'distribute_by_ticket' => '錄入券號派獎',
        'distribute_all_pending' => '派獎獎勵',  // ⭐ 新增:批量派獎所有待派獎記錄
        'view_ticket_list' => '查看派獎列表',
        'add_ticket' => '添加券號',
        'select_image' => '選擇圖片',
        'confirm_distribute' => '確認派獎',
    ],

    // 统计
    'stats' => [
        'total_activities' => '總活動數',
        'ongoing_activities' => '進行中活動',
        'total_draws' => '總抽獎次數',
        'total_winners' => '總中獎人數',
        'total_prize_amount' => '總獎金金額',
        'pending_count' => '待派獎記錄',
        'pending_amount' => '待派獎金額',
        'claimed_count' => '已派獎記錄',
        'claimed_amount' => '已派獎金額',
        'count_suffix' => '筆',
        'panel_header' => '查看統計數據',
        'loading' => '數據加載中...',
        'refresh' => '刷新數據',
        'load_error' => '數據加載失敗',
        'retry' => '重試',
        'click_to_view' => '點擊展開查看統計數據',
        'load_failed_msg' => '數據加載失敗，請重試',
    ],

    // 消息
    'message' => [
        'create_success' => '活動創建成功',
        'update_success' => '活動更新成功',
        'close_success' => '活動關閉成功',
        'activity_not_found' => '活動不存在',
        'activity_closed' => '活動已關閉',
        'activity_not_ongoing' => '只能關閉進行中的活動',
        'time_conflict' => '活動時間衝突',
        'prize_level_saved' => '獎品等級保存成功',
        'prize_level_deleted' => '獎品等級刪除成功',
        'no_activities' => '暫無活動',
        'no_prize_config' => '尚未配置獎品等級',
        'prize_level_hint' => '最多可配置10個獎品等級,中獎概率總和不能超過100%',
        'upload_success' => '上傳成功',
        'image_upload_success' => '圖片上傳成功',
        'image_upload_failed' => '圖片上傳失敗',
        'distribute_success' => '派獎成功',
        'distribute_failed' => '派獎失敗',
        'batch_complete' => '批量派獎完成：成功 {success} 條，失敗 {fail} 條',
        'batch_distribute_selected' => '批量派獎選中記錄',
        'export_in_development' => '導出功能開發中',
        'live_url_updated' => '直播地址設置成功',
        'live_url_generated' => '直播地址生成成功',
        'player_config_loaded' => '播放器配置加載成功',
        'player_config_loaded_with_region' => '播放器配置加載成功（使用{region}域名）',
        'record_success' => '中獎記錄錄入成功',
        'record_success_count' => '成功錄入 {count} 條中獎記錄',
        'live_started' => '直播已開始',
        'live_ended' => '直播已結束',
        'status_updated' => '狀態更新成功',
        'admin_manual_update' => '管理員手動更新',
        'fetch_failed' => '獲取活動列表失敗',
        'fetch_detail_failed' => '獲取活動詳情失敗',
        'close_activity_failed' => '關閉活動失敗',
        'min_one_ticket' => '請至少輸入一個券號',
        'please_input_ticket' => '請輸入券號',
        'ticket_must_6_digits' => '券號必須是6位數字',
        'ticket_format_error' => '券號格式錯誤，只能包含數字且不超過6位',
        'no_prize_level' => '該活動尚未配置獎品等級',
        'distribute_hint' => '請輸入中獎券號，系統將根據券號自動識別獎品等級並派獎獎勵',
        'drawing_started' => '開獎已開始，進入開獎階段',
        'activity_ended' => '活動已結束',
        'select_tencent_config' => '請選擇騰訊雲配置',
        'stream_name_required' => '流名稱不能為空',
        'tencent_config_not_found' => '騰訊雲配置不存在',
    ],

    // 错误信息
    'error' => [
        // 時間衝突
        'time_conflict_with_activity' => '活動時間與現有活動「{name}」衝突（{start_time} ~ {end_time}），同一時間段只能有一個活動在進行中',
        'record_not_found' => '記錄不存在',
        'live_url_required' => '請填寫直播地址',
        'live_url_too_long' => '直播地址過長（最多500字符）',
        'live_already_started' => '直播已開始，無法重複開啟',
        'live_already_ended' => '直播已結束',
        'live_not_started' => '直播尚未開始，無法結束',
        'cannot_start_drawing' => '活動進行中無法開獎，請等待活動結束後再開獎（只有已結束狀態的活動才能開獎）',
        'cannot_stop_drawing' => '當前狀態無法停止開獎（只有開獎中的活動可以結束）',
        'cannot_record_win_in_current_status' => '當前狀態無法錄入中獎（只能在進行中或開獎中狀態錄入）',
        // 輸入驗證
        'invalid_record_id' => '參數錯誤：記錄ID無效',
        'invalid_activity_id' => '參數錯誤：活動ID無效',
        'invalid_record_ids' => '參數錯誤：記錄ID必須是數組',
        'invalid_record_id_value' => '參數錯誤：記錄ID包含非法值',
        'note_too_long' => '派獎備註不能超過255個字符',
        'no_selection' => '請指定活動ID或選擇記錄',
        'no_pending_records' => '沒有待派獎的記錄',
        // 業務邏輯驗證
        'invalid_status' => '記錄狀態不正確，只能派獎待派獎的記錄',
        'status_changed' => '狀態已變更',
        'empty_prize' => '空獎無需派獎',
        'invalid_amount' => '獎品金額必須大於0',
        'player_not_found' => '玩家不存在',
        'player_disabled' => '玩家已被禁用，無法派獎獎勵',
        'activity_not_found' => '活動不存在',
        'activity_invalid_status' => '活動狀態錯誤，只能派獎已開獎待派獎的活動獎勵',
        'activity_not_in_drawing_status' => '活動狀態錯誤，只能在開獎中或已結束狀態派獎獎勵',
        'amount_exceeded' => '派獎金額超出總獎金額度',
        'ticket_not_found_or_used' => '券號 {ticket_no} 不存在或已使用',
        'ticket_already_won' => '券號 {ticket_no} 已錄入過中獎記錄，不能重複錄入',
        'prize_level_not_found_for_ticket' => '券號 {ticket_no} 的獎品等級不存在',
        'invalid_ticket_format' => '券號 {ticket_no} 格式錯誤，只能包含數字且不超過6位',
        'bet_progress_not_found' => '未找到打碼進度記錄',
        // 停止開獎確認
        'stop_drawing_no_records_confirm' => '⚠️ 尚未錄入任何中獎券號！\n\n停止開獎後將無法再錄入中獎券號，活動將進入已結束狀態。\n\n確定要停止開獎嗎？',
        'stop_drawing_with_records_confirm' => '確認停止開獎？\n\n📊 中獎統計：\n• 已錄入券號數：{count} 張\n• 獎金總額：NT$ {amount}\n• 待派獎：{pending} 筆\n• 已派獎：{granted} 筆\n\n⚠️ 停止後將無法再錄入中獎券號',
        // 其他
        'too_many_levels' => '最多只能設置 {max} 個獎品等級',
        'no_prize_levels' => '請至少設置一個獎品等級',
        'no_prizes' => '獎品數量不能為0',
        'probability_exceed' => '中獎概率總和不能超過100%，當前總和：{total}%',
        'level_rank_exists' => '該等級排名已存在',
        'invalid_prize_type' => '無效的獎品類型',
        'name_required' => '請輸入活動名稱',
        'time_required' => '請選擇活動時間',
        'invalid_time' => '結束時間必須大於開始時間',
        'invalid_time_format' => '時間格式錯誤，請選擇有效的日期時間',
        'end_before_start' => '結束時間必須晚於開始時間',
        'start_time_in_past' => '開始時間不能早於當前時間',
        'duration_too_short' => '活動時長過短，至少需要 {min}',
        'duration_too_long' => '活動時長過長，最多 {max}',
        'cannot_edit_started' => '只能編輯未開始的活動',
        'invalid_file' => '無效的文件',
        'invalid_image_type' => '只支持 jpg、png 格式圖片',
        'file_too_large' => '文件大小不能超過2MB',
        'upload_failed' => '上傳失敗，請重試',
        'invalid_params' => '參數錯誤',
        'activity_not_ongoing' => '只能在進行中的活動錄入中獎',
        'prize_level_not_found' => '獎品等級不存在',
        'live_url_required' => '請先設置直播地址',
        'invalid_status_value' => '無效的狀態值',
    ],

    // 帮助文本
    'help' => [
        'cover_image' => '建議尺寸：750x400px，支持jpg、png格式，文件大小不超過2MB',
        'cover_alt' => '活動封面',
        'cover_preview' => '封面預覽',
        'vip_config_hint' => '為每個VIP等級配置達到指定打碼量後派獎的摸獎券數量',
        'prize_config_hint' => '配置獎品等級和獎勵金額(僅現金獎勵)',
        'input_ticket_no' => '輸入券號:',
    ],

    // 详情视图标签
    'view' => [
        'detail_title' => '中獎記錄詳情',
        'basic_info' => '基本信息',
        'prize_info' => '獎品信息',
        'distribution_info' => '派獎信息',
        'activity_name' => '活動名稱',
        'ticket_no' => '券號',
        'player_name' => '玩家',
        'player_phone' => '手機號',
        'prize_name' => '獎品名稱',
        'prize_type' => '獎品類型',
        'prize_amount' => '獎品金額',
        'status' => '狀態',
        'distributed_at' => '派獎時間',
        'distributed_by' => '派獎人',
        'distribution_note' => '派獎備註',
        'created_at' => '創建時間',
        'updated_at' => '更新時間',
    ],

    // 确认对话框
    'confirm' => [
        'distribute' => '確認派獎此獎品到玩家賬戶？',
        'distribute_all_pending' => '確認派獎該活動所有已錄入但未派獎的獎勵？\n此操作將批量派獎所有待派獎記錄,請謹慎操作。',  // ⭐ 新增
    ],

    // 风险警告
    'warning' => [
        'batch_distribute_title' => '批量派獎風險提示',
        'batch_distribute_point1' => '此操作將自動派發該活動下所有待派獎記錄，無需手動選擇',
        'batch_distribute_point2' => '派獎成功後將立即增加玩家錢包餘額，無法撤銷',
        'batch_distribute_point3' => '建議先核對中獎記錄和金額是否正確',
        'batch_distribute_point4' => '大額獎金請謹慎確認後再操作',
    ],

    // 表单标签
    'form' => [
        'select_activity' => '選擇活動',
        'select_activity_help' => '只顯示已開獎待派獎的活動',
        'distribution_note' => '派獎備註',
        'distribution_note_placeholder' => '請填寫派獎備註（選填）',
        'vip_config_section' => 'VIP等級打碼量配置',
        'prize_config_section' => '獎品等級配置',
        'no_vip_data' => '暫無VIP等級數據',
        'no_vip_config' => '未配置VIP等級',
    ],

    // 筛选器 ⭐ 新增
    'filter' => [
        'time_range' => '時間範圍',
        'create_time_range' => '創建時間範圍',
        'activity_time_range' => '活動時間範圍',
    ],

    // 验证消息
    'validation' => [
        'name_required' => '請輸入活動名稱',
        'name_max_length' => '活動名稱不能超過100個字符',
        'start_time_required' => '請選擇開始時間',
        'end_time_required' => '請選擇結束時間',
        'ticket_no_required' => '請輸入券號',
        'image_format_error' => '只能上傳 JPG/PNG 格式的圖片！',
        'image_size_error' => '圖片大小不能超過 2MB！',
    ],

    // 其他文本
    'ui' => [
        'total_records' => '共 {total} 條',
        'yuan' => '元',
        'upload_failed' => '上傳失敗',
        // ⭐ 第一批：按钮、菜单、状态（2026-07-01）
        'create_from_scratch' => '從零創建',
        'create_from_history' => '從歷史活動創建',
        'live_streaming' => '直播中',
        'not_live' => '未開播',
        'preview_live' => '預覽直播',
        'start_live' => '開始直播',
        'end_live' => '結束直播',
        'view_ticket_list' => '查看發放列表',
        'select_image' => '選擇圖片',
        'no_history_activities' => '暫無歷史活動',
        'select_history_activity' => '選擇歷史活動',
        'end_live_confirm_title' => '結束直播',
        'end_live_confirm_content' => '確認結束直播嗎？結束後玩家將無法繼續觀看。',
        'ending_live' => '正在結束直播...',
        'start_live_failed' => '開始直播失敗',
        'end_live_failed' => '結束直播失敗',
    ],
];
