<?php

use addons\webman\model\PlayerGameRecord;

return [
    'title' => 'マシンゲーム記録',
    'fields' => [
        'id' => 'ID',
        'game_id' => 'ゲームID',
        'machine_id' => 'マシンID',
        'player_id' => 'プレイヤーID',
        'machine_name' => 'マシン名',
        'machine_code' => 'マシンコード',
        'player_name' => 'プレイヤー名',
        'player_uuid' => 'プレイヤーUUID',
        'type' => 'タイプ',
        'open_point' => 'ポイント追加',
        'wash_point' => 'ポイント交換',
        'open_amount' => 'マシン追加額',
        'wash_amount' => 'マシン交換額',
        'after_game_amount' => 'ゲーム後残高',
        'give_amount' => 'プレゼントポイント',
        'odds' => 'オッズ',
        'status' => 'ステータス',
        'national_damage_ratio' => '全国代理損失率',
        'vip_level_id' => 'VIPレベル',
        'cashback_ratio' => 'キャッシュバック率',
        'cashback_amount' => 'キャッシュバック額',
        'chip_amount' => 'チップ量',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
    ],
    'status' => [
        PlayerGameRecord::STATUS_START => '進行中',
        PlayerGameRecord::STATUS_END => '終了',
    ],
];
