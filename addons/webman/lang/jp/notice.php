<?php

use addons\webman\model\Notice;

return [
    'title' => [
        Notice::TYPE_EXAMINE_RECHARGE => 'レビュー保留中のプレーヤーの開分の通知',
        Notice::TYPE_EXAMINE_WITHDRAW => '審査保留中のプレーヤーの退会通知',
        Notice::TYPE_EXAMINE_ACTIVITY => 'アクティビティ報酬の審査保留中の通知',
        Notice::TYPE_EXAMINE_LOTTERY => 'レビュー保留中のボーナスに関するお知らせ',
        Notice::TYPE_MACHINE => 'マシン機器のオフライン通知',
        Notice::TYPE_MACHINE_BET => 'マシン機器ベット(圧力点)データ異常',
        Notice::TYPE_MACHINE_WIN => 'マシンデバイス勝利(スコア)データ異常',
        Notice::TYPE_MACHINE_WIN_NUMBER => 'テーブル中のホール当選回数（圧転）異常通知',
        Notice::TYPE_MACHINE_LOCK => 'テーブル異常ロック通知',
        Notice::TYPE_MACHINE_CRASH => 'デバイスクラッシュ通知',
    ],
    'content' => [
        Notice::TYPE_EXAMINE_RECHARGE => '新しい開分注文は審査待ちです、プレイヤー: {player_name}、開分 ゲーム ポイント: {point} 開分金額: {money}!',
        Notice::TYPE_EXAMINE_WITHDRAW => '新しい洗分注文は審査待ちです、プレイヤー: {player_name}、洗分ゲームポイント: {point}、洗分金額: {money}!',
        Notice::TYPE_EXAMINE_ACTIVITY => 'アクティビティ報酬は審査待ちです、プレーヤー: {player_name}、マシン: {machine_code}、アクティビティを達成: {activity_content_name}、報酬要件、報酬ゲーム ポイント {point}!',
        Notice::TYPE_EXAMINE_LOTTERY => 'ボーナス報酬は審査待ちです、プレイヤー: {player_name}、マシン上: {machine_code}、ボーナス達成: {lottery_name}、報酬要件、報酬ゲーム ポイント {point}!',
        Notice::TYPE_MACHINE => 'マシン機器 {machine_code} は現在オフラインです。メンテナンス担当者が時間内に問題のトラブルシューティングを行うよう要求されています。',
        Notice::TYPE_MACHINE_BET => 'マシン機器 {machine_code}、異常なベット (圧力ポイント) データが見つかりました。メンテナンス担当者に時間内に問題のトラブルシューティングを行ってください!',
        Notice::TYPE_MACHINE_WIN => 'マシン機器 {machine_code}、異常な勝利 (スコア) データが見つかりました。メンテナは時間内に問題のトラブルシューティングを行ってください!',
        Notice::TYPE_MACHINE_WIN_NUMBER => 'パチンコ台設備{machine _ code}、中洞の授賞回数（圧転）を発見した場合、メンテナンススタッフは直ちに問題を調査してください！',
        Notice::TYPE_MACHINE_LOCK => '机台設備{machine _ code}、上下分異常（机台がロックされている）が発生しました。メンテナンス担当者は直ちに問題を調査してください！',
        Notice::TYPE_MACHINE_CRASH => 'デバイスがクラッシュしました: プレイヤー {player_name} (UID:{player_uuid}) の残高が {current_amount} に達し、クラッシュ金額 {crash_amount} を超えました。管理者に連絡してください!',
    ],
];
