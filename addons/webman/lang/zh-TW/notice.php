<?php

use addons\webman\model\Notice;

return [
    'title' => [
        Notice::TYPE_EXAMINE_RECHARGE => '玩家充值待稽核通知',
        Notice::TYPE_EXAMINE_WITHDRAW => '玩家提現待稽核通知',
        Notice::TYPE_EXAMINE_ACTIVITY => '活動獎勵待稽核通知',
        Notice::TYPE_EXAMINE_LOTTERY => '彩金獎勵待稽核通知',
        Notice::TYPE_MACHINE => '機台設備離線通知',
        Notice::TYPE_MACHINE_BET => '機台設備bet（壓分）數據异常',
        Notice::TYPE_MACHINE_WIN => '機台設備win（得分）數據异常',
        Notice::TYPE_MACHINE_WIN_NUMBER => '機台中洞兌獎次數（壓轉）异常通知',
        Notice::TYPE_MACHINE_LOCK => '機台异常锁定通知',
        Notice::TYPE_MACHINE_CRASH => '設備爆機通知',
    ],
    'content' => [
        Notice::TYPE_EXAMINE_RECHARGE => '新的充值訂單待稽核,玩家:{player_name},充值遊戲點:{point}充值金額:{money}！',
        Notice::TYPE_EXAMINE_WITHDRAW => '新的提現訂單待稽核,玩家:{player_name},提現遊戲點:{point}提現金額:{money}！',
        Notice::TYPE_EXAMINE_ACTIVITY => '活動獎勵待稽核,玩家:{player_name},在機台:{machine_code},達成活動:{activity_content_name},的獎勵要求,獎勵遊戲點{point}！',
        Notice::TYPE_EXAMINE_LOTTERY => '彩金獎勵待稽核,玩家:{player_name},在機台:{machine_code},達成彩金:{lottery_name},的獎勵要求,獎勵遊戲點{point}！',
        Notice::TYPE_MACHINE => '機台設備{machine_code},現已下線,請維護人員及時排查問題！',
        Notice::TYPE_MACHINE_BET => '機台設備{machine_code},發現bet（壓分）數據异常,請維護人員及時排查問題！',
        Notice::TYPE_MACHINE_WIN => '機台設備{machine_code},發現win（得分）數據异常,請維護人員及時排查問題！',
        Notice::TYPE_MACHINE_WIN_NUMBER => '鋼珠機台設備{machine_code}， 發現中洞兌獎次數（壓轉），請維護人員及時排查問題！',
        Notice::TYPE_MACHINE_LOCK => '機台設備{machine_code}， 發生上下分异常（機台已鎖），請維護人員及時排查問題！',
        Notice::TYPE_MACHINE_CRASH => '設備已爆機：玩家 {player_name} (UID:{player_uuid}) 餘額達到 {current_amount}，超過爆機金額 {crash_amount}，請聯繫管理員處理！',
    ],
];
