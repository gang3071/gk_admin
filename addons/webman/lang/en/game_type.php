<?php

use addons\webman\model\GameType;

return [
    'title' => 'Game category',
    'fields' => [
        'id' => 'ID',
        'picture_url' => 'picture',
        'type' => 'Machine type',
        'cate' => 'Game type',
        'name' => 'name',
        'sort' => 'sort',
        'status' => 'status',
    ],
    'game_type' => [
        GameType::TYPE_SLOT => 'SLOT',
        GameType::TYPE_STEEL_BALL => 'Steel Ball',
        GameType::TYPE_FISH => 'Fish Machine',
    ],
    'game_type_cate' => [
        GameType::CATE_PHYSICAL_MACHINE => 'Physical Machine',
        GameType::CATE_COMPUTER_GAME => 'Electronic Game',
        GameType::CATE_LIVE_VIDEO => 'Real person video',
        GameType::CATE_TABLE => 'Table',
        GameType::CATE_SLO => 'Slot Machine',
        GameType::CATE_FISH => 'Fishing',
        GameType::CATE_P2P => 'Chess and Card',
        GameType::CATE_SPORT => 'Sports',
        GameType::CATE_ARCADE => 'Arcade',
        GameType::CATE_LOTTERY => 'Lottery',
    ],
    'help' => [
        'picture_url_size' => 'Recommended picture size 352 * 410',
    ]
];
