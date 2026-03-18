<?php

use addons\webman\model\GameType;

return [
    'title' => '遊戲類別',
    'fields' => [
        'id' => 'ID',
        'picture_url' => '圖片',
        'type' => '機台類型',
        'cate' => '遊戲類型',
        'name' => '名稱',
        'sort' => '排序',
        'status' => '狀態',
    ],
    'game_type' => [
        GameType::TYPE_SLOT => '斯洛',
        GameType::TYPE_STEEL_BALL => '鋼珠',
        GameType::TYPE_FISH => '魚機',
    ],
    'game_type_cate' => [
        GameType::CATE_PHYSICAL_MACHINE => '實體平臺',
        GameType::CATE_COMPUTER_GAME => '電子遊戲',
        GameType::CATE_LIVE_VIDEO => '真人視頻',
        GameType::CATE_TABLE => '牌桌',
        GameType::CATE_SLO => '老虎機',
        GameType::CATE_FISH => '捕魚',
        GameType::CATE_P2P => '棋牌',
        GameType::CATE_SPORT => '體育',
        GameType::CATE_ARCADE => '街機',
        GameType::CATE_LOTTERY => '彩票',
    ],
    'help' => [
        'picture_url_size' => '建議圖片尺寸352 * 410',
    ]
];
