<?php

use addons\webman\model\PlayerDeliveryRecord;

return [
    'title' => '充提记录',
    'fields' => [
        'id' => 'ID',
        'player_id' => '玩家',
        'recharge_total' => '开分金额',
        'withdraw_total' => '洗分金额',
        'total_amount'=>'累计金额',
        'type' => '类型',
        'source' => '交易对象',
        'amount' => '游戏点',
        'user_id' => '管理员id',
        'user_name' => '操作人',
        'amount_before' => '变更前点数',
        'amount_after' => '变更后点数',
        'tradeno' => '单号',
        'remark' => '备注',
        'updated_at' => '更新时间',
        'created_at' => '创建时间',
    ],
    'type' => [
        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD => '(管理后台)加点',
        PlayerDeliveryRecord::TYPE_PRESENT_IN => '点数转入',
        PlayerDeliveryRecord::TYPE_PRESENT_OUT => '点数转出',
        PlayerDeliveryRecord::TYPE_MACHINE_UP => '机台上分',
        PlayerDeliveryRecord::TYPE_MACHINE_DOWN => '机台下分',
        PlayerDeliveryRecord::TYPE_RECHARGE => '开分',
        PlayerDeliveryRecord::TYPE_WITHDRAWAL => '洗分',
        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT => '(管理后台)扣点',
        PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK => '洗分回退',
        PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS => '活动奖励',
        PlayerDeliveryRecord::TYPE_REGISTER_PRESENT => '系统赠点',
        PlayerDeliveryRecord::TYPE_PROFIT => '推广分润',
        PlayerDeliveryRecord::TYPE_LOTTERY => '彩金中奖',
        PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT => '平台转入电子游戏',
        PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN => '电子游戏转入平台',
    ],
    'detail' => '详情',
    'chart' => '图表',
];
