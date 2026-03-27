<?php

use addons\webman\model\Notice;

return [
    'title' => [
        Notice::TYPE_EXAMINE_RECHARGE => '玩家充值待审核通知',
        Notice::TYPE_EXAMINE_WITHDRAW => '玩家提现待审核通知',
        Notice::TYPE_EXAMINE_ACTIVITY => '活动奖励待审核通知',
        Notice::TYPE_EXAMINE_LOTTERY => '彩金奖励待审核通知',
        Notice::TYPE_MACHINE => '机台设备离线通知',
        Notice::TYPE_MACHINE_BET => '机台设备bet(压分)数据异常',
        Notice::TYPE_MACHINE_WIN => '机台设备win(得分)数据异常',
        Notice::TYPE_MACHINE_WIN_NUMBER => '機台中洞兑奖次数（压转）异常通知',
        Notice::TYPE_MACHINE_LOCK => '机台异常锁定通知',Notice::TYPE_MACHINE_CRASH => '设备爆机通知',
    ],
    'content' => [
        Notice::TYPE_EXAMINE_RECHARGE => '新的充值订单待审核, 玩家: {player_name}, 充值游戏点: {point} 充值金额: {money}!',
        Notice::TYPE_EXAMINE_WITHDRAW => '新的提现订单待审核, 玩家: {player_name}, 提现游戏点: {point} 提现金额: {money}!',
        Notice::TYPE_EXAMINE_ACTIVITY => '活动奖励待审核, 玩家: {player_name},在机台: {machine_code}, 达成活动: {activity_content_name},的奖励要求,奖励游戏点{point}!',
        Notice::TYPE_EXAMINE_LOTTERY => '彩金奖励待审核, 玩家: {player_name},在机台: {machine_code}, 达成彩金: {lottery_name},的奖励要求,奖励游戏点{point}!',
        Notice::TYPE_MACHINE => '机台设备{machine_code}, 现已下线, 请维护人员及时排查问题!',
        Notice::TYPE_MACHINE_BET => '机台设备{machine_code}, 发现bet(压分)数据异常, 请维护人员及时排查问题!',
        Notice::TYPE_MACHINE_WIN => '机台设备{machine_code}, 发现win(得分)数据异常, 请维护人员及时排查问题!',
        Notice::TYPE_MACHINE_WIN_NUMBER => '钢珠机台设备{machine_code}, 发现中洞兑奖次数（压转）, 请维护人员及时排查问题!',
        Notice::TYPE_MACHINE_LOCK => '机台设备{machine_code}，发生上下分异常（机台已锁），请维护人员及时排查问题！',
        Notice::TYPE_MACHINE_CRASH => '设备已爆机：玩家 {player_name} (UID:{player_uuid}) 余额达到 {current_amount}，超过爆机金额 {crash_amount}，请联系管理员处理！',
    ],
];
