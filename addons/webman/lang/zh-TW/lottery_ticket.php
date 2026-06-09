<?php

return [
    'title' => '摸獎券管理',

    // 菜单
    'menu' => [
        'main' => '摸獎券管理',
        'dashboard' => '進行中的活動',
        'history' => '歷史活動記錄',
        'records' => '中獎記錄',
    ],

    // 字段
    'fields' => [
        'id' => 'ID',
        'name' => '活動名稱',
        'activity_name' => '活動名稱',
        'description' => '活動說明',
        'cover_image' => '活動封面',
        'live_url' => '直播地址',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
        'status' => '活動狀態',
        'total_tickets' => '總發放數量',
        'used_tickets' => '已使用數量',
        'usage_rate' => '使用率',
        'prize_config' => '獎品配置',
        'created_at' => '創建時間',
        'prize_level_config' => '獎品等級配置',
        'total_probability' => '概率總和',
        'level' => '等級',
        'time_range' => '活動時間',
        'player_account' => '玩家賬號',
        'prize_level' => '中獎等級',
        'remark' => '備註',

        // 中奖记录
        'player_name' => '玩家名稱',
        'player_phone' => '玩家手機',
        'ticket_no' => '摸獎券編號',
        'prize_type' => '獎品類型',
        'prize_name' => '獎品名稱',
        'prize_amount' => '獎品金額',
        'record_status' => '發放狀態',
        'remark' => '備註',
        'draw_time' => '抽獎時間',
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
    ],

    // 对话框
    'modal' => [
        'record_win_title' => '錄入中獎記錄',
        'live_url_title' => '添加直播地址',
        'live_url_prompt' => '請輸入直播流地址:',
        'live_url_required' => '請輸入直播地址',
    ],

    // 标题
    'title' => [
        'activity_detail' => '活動詳情',
    ],

    // 活动状态
    'status' => [
        'all' => '全部',
        'not_started' => '未開始',
        'ongoing' => '進行中',
        'ended' => '已結束',
        'closed' => '已關閉',
        'preheating' => '預熱期',
        'betting' => '打碼中',
        'drawing' => '開獎中',
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
        'recharge' => '充值贈送',
        'activity' => '活動贈送',
        'manual' => '手動發放',
        'unknown' => '未知來源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => '待發放',
        'granted' => '已發放',
        'failed' => '發放失敗',
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

    // 中奖等级字段
    'prize_level_fields' => [
        'level_rank' => '等級排名',
        'level_name' => '等級名稱',
        'prize_type' => '獎品類型',
        'prize_amount' => '獎品金額',
        'prize_item_name' => '實物名稱',
        'prize_item_image' => '實物圖片',
        'prize_count' => '獎品數量',
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
        'prize_config' => '獎品配置',
        'close' => '關閉活動',
        'export' => '導出記錄',
        'add_prize_level' => '添加獎品等級',
        'record_win' => '錄入中獎',
        'add_live_url' => '添加直播地址',
        'expand' => '展開',
        'collapse' => '收起',
    ],

    // 统计
    'stats' => [
        'total_activities' => '總活動數',
        'ongoing_activities' => '進行中活動',
        'total_draws' => '總抽獎次數',
        'total_winners' => '總中獎人數',
        'total_prize_amount' => '總獎金金額',
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
        'live_url_updated' => '直播地址設置成功',
        'record_success' => '中獎記錄錄入成功',
        'live_started' => '直播已開始',
        'live_ended' => '直播已結束',
        'status_updated' => '狀態更新成功',
    ],

    // 错误信息
    'error' => [
        'too_many_levels' => '最多只能設置 {max} 個獎品等級',
        'no_prize_levels' => '請至少設置一個獎品等級',
        'no_prizes' => '獎品數量不能為0',
        'probability_exceed' => '中獎概率總和不能超過100%，當前總和：{total}%',
        'level_rank_exists' => '該等級排名已存在',
        'invalid_prize_type' => '無效的獎品類型',
        'name_required' => '請輸入活動名稱',
        'time_required' => '請選擇活動時間',
        'invalid_time' => '結束時間必須大於開始時間',
        'cannot_edit_started' => '只能編輯未開始的活動',
        'invalid_file' => '無效的文件',
        'invalid_image_type' => '只支持 jpg、png 格式圖片',
        'file_too_large' => '文件大小不能超過2MB',
        'invalid_params' => '參數錯誤',
        'activity_not_ongoing' => '只能在進行中的活動錄入中獎',
        'prize_level_not_found' => '獎品等級不存在',
        'player_not_found' => '玩家不存在',
        'live_url_required' => '請先設置直播地址',
        'invalid_status' => '無效的狀態值',
    ],

    // 帮助文本
    'help' => [
        'cover_image' => '建議尺寸：750x400px，支持jpg、png格式，文件大小不超過2MB',
    ],
];
