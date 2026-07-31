<?php

use addons\webman\model\PlayerGameRecord;

return [
    'title' => '機台遊戲記錄',
    'fields' => [
        'id' => 'ID',
        'game_id' => '遊戲ID',
        'machine_id' => '機台ID',
        'player_id' => '玩家ID',
        'machine_name' => '機台名稱',
        'machine_code' => '機台編號',
        'player_name' => '玩家名稱',
        'player_uuid' => '玩家UUID',
        'type' => '類型',
        'open_point' => '上分',
        'wash_point' => '下分',
        'open_amount' => '機台上分',
        'wash_amount' => '機台下分',
        'after_game_amount' => '餘點數',
        'give_amount' => '贈送點數',
        'odds' => '比值',
        'status' => '狀態',
        'national_damage_ratio' => '全民代理返佣比例',
        'vip_level_id' => 'VIP等級',
        'cashback_ratio' => '反水比例',
        'cashback_amount' => '反水金額',
        'chip_amount' => '打碼量',
        'created_at' => '創建時間',
        'updated_at' => '更新時間',
    ],
    'status' => [
        PlayerGameRecord::STATUS_START => '進行中',
        PlayerGameRecord::STATUS_END => '已結束',
    ],
];
