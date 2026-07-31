<?php

use addons\webman\model\PlayerGameRecord;

return [
    'title' => '机台游戏记录',
    'fields' => [
        'id' => 'ID',
        'game_id' => '游戏ID',
        'machine_id' => '机台ID',
        'player_id' => '玩家ID',
        'machine_name' => '机台名称',
        'machine_code' => '机台编号',
        'player_name' => '玩家名称',
        'player_uuid' => '玩家UUID',
        'type' => '类型',
        'open_point' => '上分',
        'wash_point' => '下分',
        'open_amount' => '机台上分',
        'wash_amount' => '机台下分',
        'after_game_amount' => '余点数',
        'give_amount' => '赠送点数',
        'odds' => '比值',
        'status' => '状态',
        'national_damage_ratio' => '全民代理返佣比例',
        'vip_level_id' => 'VIP等级',
        'cashback_ratio' => '反水比例',
        'cashback_amount' => '反水金额',
        'chip_amount' => '打码量',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],
    'status' => [
        PlayerGameRecord::STATUS_START => '进行中',
        PlayerGameRecord::STATUS_END => '已结束',
    ],
];
