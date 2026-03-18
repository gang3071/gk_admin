<?php

use addons\webman\model\PromoterProfitRecord;
use addons\webman\model\PromoterProfitSettlementRecord;

return [
    /** TODO 翻译 */
    'title' => 'Settlement record',
    'fields' => [
        'id' => 'ID',
        'total_withdraw_amount' => 'Withdrawal amount',
        'total_recharge_amount' => 'Recharge amount',
        'total_bonus_amount' => 'Activity gift amount',
        'total_admin_deduct_amount' => 'Administrator deduct points',
        'total_admin_add_amount' => 'Administrator adds points',
        'total_present_amount' => 'Gift amount',
        'total_machine_up_amount' => 'Machine point',
        'total_machine_down_amount' => 'Machine lower point',
        'total_lottery_amount' => 'Lottery bonus',
        'total_profit_amount' => 'Settlement profit sharing',
        'total_game_amount' => 'Electronic game amount',
        'tradeno' => 'Settlement order number',
        'type' => 'type',
        'last_profit_amount' => 'Last settlement profit distribution (individual)',
        'adjust_amount' => 'Settlement adjustment amount',
        'actual_amount' => 'Actual amount received',
        'total_commission_amount' => 'Recharge fee',
        'user_id' => 'Machine click',
        'user_name' => 'Settlement Administrator',
        'created_at' => 'Settlement time',
        'updated_at' => 'Update time',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => 'Unsettled',
        PromoterProfitRecord::STATUS_COMPLETED => 'Settled',
    ],
    'type' => [
        PromoterProfitSettlementRecord::TYPE_SETTLEMENT => 'Settlement',
        PromoterProfitSettlementRecord::TYPE_CLEAR => 'Clear',
    ],
    'player_promoter' => [
        'phone' => "Promoter's mobile phone number",
        'uuid' => 'Promoter UUID'
    ],
    'settlement_time_start' => 'Settlement start time',
    'settlement_time_end' => 'Settlement end time',
    'profit_settlement_info' => 'Profit sharing data',
    'settlement_data' => 'settlement data',
    'settlement_detail' => 'Profit distribution report',
    'channel_settlement_promoter_null' => 'No promoters who need settlement',
    'success' => 'Promoter settlement successful',
    'channel_promotion_closed' => 'Promoter function has been closed',
    'channel_closed' => 'The channel has been closed',
];
