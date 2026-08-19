<?php

use addons\webman\model\PlayGameRecord;

return [
    'title' => '玩家游戏记录',
    'replay' => '回放',
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
