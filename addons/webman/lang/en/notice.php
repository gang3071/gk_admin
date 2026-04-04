<?php

use addons\webman\model\Notice;

return [
    'title' => [
        Notice::TYPE_EXAMINE_RECHARGE => 'Notification of player open score pending review',
        Notice::TYPE_EXAMINE_WITHDRAW => 'Notification of player wash score pending review',
        Notice::TYPE_EXAMINE_ACTIVITY => 'Notice of activity reward pending review',
        Notice::TYPE_EXAMINE_LOTTERY => 'Notice of bonus pending review',
        Notice::TYPE_MACHINE => 'Machine equipment offline notification',
        Notice::TYPE_MACHINE_BET => 'Machine equipment bet (pressure points) data abnormality',
        Notice::TYPE_MACHINE_WIN => 'Machine equipment win (score) data abnormality',
        Notice::TYPE_MACHINE_WIN_NUMBER => 'Abnormal notification of prize redemption times (pressure rotation) in the machine hole',
        Notice::TYPE_MACHINE_LOCK => 'Machine abnormal locking notification',
        Notice::TYPE_MACHINE_CRASH => 'Device Crash Notification',
    ],
    'content' => [
        Notice::TYPE_EXAMINE_RECHARGE => 'New open score order pending review, player: {player_name}, open score game points: {point} open score amount: {money}!',
        Notice::TYPE_EXAMINE_WITHDRAW => 'New wash score order is pending review, player: {player_name}, wash score game points: {point}, wash score amount: {money}!',
        Notice::TYPE_EXAMINE_ACTIVITY => 'Activity rewards are pending review, player: {player_name}, on machine: {machine_code}, achieve activity: {activity_content_name}, reward requirements, reward game points {point}!',
        Notice::TYPE_EXAMINE_LOTTERY => 'The bonus reward is pending review, player: {player_name}, on the machine: {machine_code}, achieve bonus: {lottery_name}, reward requirements, reward game points {point}!',
        Notice::TYPE_MACHINE => 'Machine equipment {machine_code} is now offline. Maintenance personnel are requested to troubleshoot the problem in time!',
        Notice::TYPE_MACHINE_BET => 'Machine equipment {machine_code}, abnormal bet (pressure points) data is found, please maintenance personnel to troubleshoot the problem in time!',
        Notice::TYPE_MACHINE_WIN => 'Machine device {machine_code}, abnormal win (score) data is found, please maintain the personnel to troubleshoot the problem in time!',
        Notice::TYPE_MACHINE_WIN_NUMBER => 'Steel ball machine equipment {machine_code}, Discovered the number of times the prize has been redeemed in the middle hole (pressure conversion), please have maintenance personnel investigate the problem in a timely manner!',
        Notice::TYPE_MACHINE_LOCK => 'The machine equipment {machine_code} has experienced a vertical separation abnormality (the machine is locked). Please have maintenance personnel investigate the problem in a timely manner!！',
        Notice::TYPE_MACHINE_CRASH => 'Device crashed: Player {player_name} (UID:{player_uuid}) balance reached {current_amount}, exceeded crash amount {crash_amount}, please contact administrator!',
    ],
];
