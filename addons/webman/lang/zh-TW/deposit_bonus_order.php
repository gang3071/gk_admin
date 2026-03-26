<?php

return [
    'title' => '充值滿贈訂單管理',
    'generate_title' => '生成充值滿贈訂單',

    'fields' => [
        'id' => 'ID',
        'order_no' => '訂單號',
        'activity_id' => '所屬活動',
        'activity_name' => '活動名稱',
        'player_id' => '玩家',
        'player_account' => '玩家賬號',
        'player_info' => '玩家信息',
        'deposit_amount' => '充值金額',
        'bonus_amount' => '贈送金額',
        'required_bet_amount' => '需要打碼量',
        'current_bet_amount' => '當前打碼量',
        'bet_progress' => '打碼進度',
        'status' => '訂單狀態',
        'qrcode_token' => '二維碼Token',
        'qrcode_expires_at' => '二維碼過期時間',
        'expires_at' => '訂單過期時間',
        'verified_at' => '核銷時間',
        'completed_at' => '完成時間',
        'created_at' => '創建時間',
        'remark' => '備註',
    ],

    'help' => [
        'activity_id' => '選擇要參與的活動',
        'deposit_amount' => '輸入充值金額，必須匹配活動檔位',
        'player_id' => '搜索並選擇玩家賬號',
        'player_username' => '請輸入玩家賬號',
    ],

    'status_pending' => '待核銷',
    'status_verified' => '已核銷',
    'status_completed' => '已完成',
    'status_expired' => '已過期',
    'status_cancelled' => '已取消',
    'status_unknown' => '未知狀態',

    'cannot_edit' => '訂單不允許編輯，只能新增',
    'activity_invalid' => '活動不存在或已失效',
    'tier_not_match' => '充值金額不匹配任何活動檔位',
    'player_not_found' => '玩家不存在',
    'player_limit_exceeded' => '玩家參與次數已達上限',
    'parent_agent_not_found' => '未找到上級代理',
    'no_available_activity' => '上級代理暫無可用的充值滿贈活動',
    'generate_success' => '訂單生成成功',
    'generate_success_with_orderno' => '訂單生成成功！訂單號：{order_no}',
    'generate_fail' => '訂單生成失敗',

    'stats' => [
        'total_count' => '訂單總數',
        'total_deposit' => '充值總額',
        'total_bonus' => '贈送總額',
        'completed_count' => '已完成',
    ],
];
