<?php

use addons\webman\model\PlayGameRecord;

return [
    'title' => '玩家遊戲記錄',
    'replay' => '回放',
    'replay_not_supported' => '此平台不支援回放功能',
    'sync_unsettled' => '同步未結算訂單',
    'sync_unsettled_confirm' => '將呼叫廠商歷史紀錄介面，對最近 24 小時內的未結算訂單進行補結算（含玩家錢包入帳、彩金與打碼量補發），目前僅處理 ATG 廠商，確認執行？',
    'sync_result' => '同步完成：共 {total} 筆未結算訂單，補結算 {settled} 筆（錢包入帳 {wallet} 筆），廠商尚未結算 {open} 筆，廠商查無注單 {not_found} 筆，失敗 {failed} 筆，剩餘 {remaining} 筆未處理（受廠商介面限頻，可再次點擊繼續）。',
    'fields' => [
        'id' => 'ID',
        'game_code' => '遊戲編號',
        'bet' => '押注額',
        'win' => '贏取額',
        'diff' => '輸贏金額',
        'balance_before' => '下注前餘額',
        'balance_after' => '下注後餘額',
        'reward' => '獎金（不計入贏取）',
        'order_no' => '單號（遊戲平臺）',
        'status' => '狀態',
        'settlement_status' => '結算狀態',
        'platform_action_at' => '結算時間（遊戲平臺）',
        'action_at' => '結算時間',
        'create_at' => '創建時間',
        'vip_level_id' => 'VIP等級',
        'cashback_ratio' => '反水比例',
        'cashback_amount' => '反水金額',
    ],
    'status' => [
        PlayGameRecord::STATUS_UNSETTLED => '未分潤',
        PlayGameRecord::STATUS_SETTLED => '已分潤',
    ],
    'settlement_status' => [
        PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED => '未結算',
        PlayGameRecord::SETTLEMENT_STATUS_SETTLED => '已結算',
        PlayGameRecord::SETTLEMENT_STATUS_CANCELLED => '已取消',
        PlayGameRecord::SETTLEMENT_STATUS_CONFIRM => '確認',
    ],
    'all_bet' => '總押注',
    'all_diff' => '總輸贏',
    'all_reward' => '總獎金',
];
