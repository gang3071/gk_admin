<?php

use addons\webman\model\PromoterProfitRecord;
use addons\webman\model\PromoterProfitSettlementRecord;

return [
    /** TODO翻譯*/
    'title' => '結算記錄',
    'fields' => [
        'id' => 'ID',
        'total_withdraw_amount' => '提現金額',
        'total_recharge_amount' => '充值金額',
        'total_bonus_amount' => '活動贈送金額',
        'total_admin_deduct_amount' => '管理員扣點',
        'total_admin_add_amount' => '管理員加點',
        'total_present_amount' => '贈送金額',
        'total_machine_up_amount' => '機台上點',
        'total_machine_down_amount' => '機台下點',
        'total_lottery_amount' => '彩金獎金',
        'total_profit_amount' => '結算分潤',
        'total_game_amount' => '電子遊戲金額',
        'tradeno' => '結算單號',
        'type' => '類型',
        'last_profit_amount' => '上次結算分潤（個人）',
        'adjust_amount' => '結算調整金額',
        'actual_amount' => '實際到賬金額',
        'total_commission_amount' => '充值手續費',
        'user_id' => '機台下點',
        'user_name' => '結算管理員',
        'created_at' => '結算時間',
        'updated_at' => '更新時間',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => '未結算',
        PromoterProfitRecord::STATUS_COMPLETED => '已結算',
    ],
    'type' => [
        PromoterProfitSettlementRecord::TYPE_SETTLEMENT => '結算',
        PromoterProfitSettlementRecord::TYPE_CLEAR => '清算',
    ],
    'player_promoter' => [
        'phone' => '推廣員手機號',
        'uuid' => '推廣員UUID'
    ],
    'settlement_time_start' => '結算開始時間',
    'settlement_time_end' => '結算結束時間',
    'profit_settlement_info' => '分潤數據',
    'settlement_data' => '結算數據',
    'settlement_detail' => '分潤報表',
    'channel_settlement_promoter_null' => '沒有需要結算的推廣員',
    'success' => '推廣員結算成功',
    'channel_promotion_closed' => '推廣員功能已關閉',
    'channel_closed' => '該通路已關閉',
];
