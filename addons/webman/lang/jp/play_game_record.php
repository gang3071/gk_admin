<?php

use addons\webman\model\PlayGameRecord;

return [
    'title' => 'プレイヤープレイ履歴',
    'replay' => '再生',
    'replay_not_supported' => 'このプラットフォームはリプレイ機能をサポートしていません',
    'sync_unsettled' => '未決算注文を同期',
    'sync_unsettled_confirm' => 'ベンダー履歴APIを呼び出し、直近24時間以内の未決算注文を補精算します（ウォレット入金・賞金・ベット統計を含む）。現時点ではATGベンダーのみ対応。実行しますか？',
    'sync_result' => '同期完了：未決算注文 {total} 件、補精算 {settled} 件（ウォレット入金 {wallet} 件）、ベンダー未決算 {open} 件、ベンダー未検出 {not_found} 件、失敗 {failed} 件、残り {remaining} 件（ベンダーAPI制限のため、再度クリックして継続してください）。',
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
