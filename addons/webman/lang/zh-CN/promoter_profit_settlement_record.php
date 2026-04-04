<?php

use addons\webman\model\PromoterProfitRecord;
use addons\webman\model\PromoterProfitSettlementRecord;

return [
    /** TODO 翻译 */
    'title' => '结算记录',
    'fields' => [
        'id' => 'ID',
        'total_withdraw_amount' => '洗分金额',
        'total_recharge_amount' => '开分金额',
        'total_bonus_amount' => '活动赠送金额',
        'total_admin_deduct_amount' => '管理员扣点',
        'total_admin_add_amount' => '管理员加点',
        'total_present_amount' => '赠送金额',
        'total_machine_up_amount' => '机台上点',
        'total_machine_down_amount' => '机台下点',
        'total_lottery_amount' => '彩金奖金',
        'total_profit_amount' => '结算分润',
        'total_game_amount' => '电子游戏金额',
        'tradeno' => '结算单号',
        'type' => '类型',
        'last_profit_amount' => '上次结算分润(个人)',
        'adjust_amount' => '结算调整金额',
        'actual_amount' => '实际到账金额',
        'total_commission_amount' => '开分手续费',
        'user_id' => '机台下点',
        'user_name' => '结算管理员',
        'created_at' => '结算时间',
        'updated_at' => '更新时间',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => '未结算',
        PromoterProfitRecord::STATUS_COMPLETED => '已结算',
    ],
    'type' => [
        PromoterProfitSettlementRecord::TYPE_SETTLEMENT => '结算',
        PromoterProfitSettlementRecord::TYPE_CLEAR => '清算',
    ],
    'player_promoter' => [
        'phone' => '推广员手机号',
        'uuid' => '推广员UUID'
    ],
    'settlement_time_start' => '结算开始时间',
    'settlement_time_end' => '结算结束时间',
    'profit_settlement_info' => '分润数据',
    'settlement_data' => '结算数据',
    'settlement_detail' => '分润报表',
    'channel_settlement_promoter_null' => '没有需要结算的推广员',
    'success' => '推广员结算成功',
    'channel_promotion_closed' => '推广员功能已关闭',
    'channel_closed' => '该渠道已关闭',
];
