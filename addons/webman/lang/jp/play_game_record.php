<?php

use addons\webman\model\PlayGameRecord;

return [
    'title' => 'プレイヤープレイ履歴',
    'replay' => '再生',
    'fields' => [
        'id' => 'ID',
        'game_code' => 'ゲーム番号',
        'bet' => '敷注額',
        'win' => '勝利額',
        'diff' => '損益額',
        'balance_before' => 'ベット前残高',
        'balance_after' => 'ベット後残高',
        'reward' => 'ボーナス（勝ち点は計上しない）',
        'order_no' => '単号（ゲームプラットフォーム）',
        'status' => '状態',
        'settlement_status' => '決済状態',
        'platform_action_at' => '決済時間（ゲームプラットフォーム）',
        'action_at' => '決済時間',
        'create_at' => '作成時間',
        'vip_level_id' => 'VIPレベル',
        'cashback_ratio' => 'キャッシュバック率',
        'cashback_amount' => 'キャッシュバック額',
    ],
    'status' => [
        PlayGameRecord::STATUS_UNSETTLED => '未分潤',
        PlayGameRecord::STATUS_SETTLED => '分潤済み',
    ],
    'settlement_status' => [
        PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED => '未決済',
        PlayGameRecord::SETTLEMENT_STATUS_SETTLED => '決済済み',
        PlayGameRecord::SETTLEMENT_STATUS_CANCELLED => 'キャンセル済み',
        PlayGameRecord::SETTLEMENT_STATUS_CONFIRM => '確認',
    ],
    'all_bet' => '総注釈',
    'all_diff' => '総勝ち負け',
    'all_reward' => '総ボーナス',
];
