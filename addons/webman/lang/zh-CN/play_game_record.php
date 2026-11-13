<?php

use addons\webman\model\PlayGameRecord;

return [
    'title' => '玩家游戏记录',
    'replay' => '回放',
    'replay_not_supported' => '此平台不支持回放功能',
    'sync_unsettled' => '同步未结算订单',
    'sync_unsettled_confirm' => '将调用厂商历史记录接口，对最近 24 小时内的未结算订单进行补结算（含玩家钱包入账、彩金与打码量补发），目前仅处理 ATG 厂商，确认执行？',
    'sync_result' => '同步完成：共 {total} 笔未结算订单，补结算 {settled} 笔（钱包入账 {wallet} 笔），厂商尚未结算 {open} 笔，厂商查无注单 {not_found} 笔，失败 {failed} 笔，剩余 {remaining} 笔未处理（受厂商接口限频，可再次点击继续）。',
    'fields' => [
        'id' => 'ID',
        'game_code' => '游戏编号',
        'bet' => '押注额',
        'win' => '贏取额',
        'diff' => '输赢金额',
        'balance_before' => '下注前余额',
        'balance_after' => '下注后余额',
        'reward' => '奖金(不计入贏取)',
        'order_no' => '单号(游戏平台)',
        'status' => '状态',
        'settlement_status' => '结算状态',
        'platform_action_at' => '结算时间(游戏平台)',
        'action_at' => '结算时间',
        'create_at' => '创建时间',
        'vip_level_id' => 'VIP等级',
        'cashback_ratio' => '反水比例',
        'cashback_amount' => '反水金额',
    ],
    'status' => [
        PlayGameRecord::STATUS_UNSETTLED => '未分润',
        PlayGameRecord::STATUS_SETTLED => '已分润',
    ],
    'settlement_status' => [
        PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED => '未结算',
        PlayGameRecord::SETTLEMENT_STATUS_SETTLED => '已结算',
        PlayGameRecord::SETTLEMENT_STATUS_CANCELLED => '已取消',
        PlayGameRecord::SETTLEMENT_STATUS_CONFIRM => '确认',
    ],
    'all_bet' => '总押注',
    'all_diff' => '总输赢',
    'all_reward' => '总奖金',
];
