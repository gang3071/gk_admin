<?php

use addons\webman\model\GameType;

return [
    'title' => '游戏类别',
    'fields' => [
        'id' => 'ID',
        'picture_url' => '图片',
        'type' => '机台类型',
        'cate' => '游戏类型',
        'name' => '名称',
        'sort' => '排序',
        'status' => '状态',
    ],
    'game_type' => [
        GameType::TYPE_SLOT => '斯洛',
        GameType::TYPE_STEEL_BALL => '钢珠',
        GameType::TYPE_FISH => '鱼机',
    ],
    'game_type_cate' => [
        GameType::CATE_PHYSICAL_MACHINE => '实体机台',
        GameType::CATE_COMPUTER_GAME => '电子游戏',
        GameType::CATE_LIVE_VIDEO => '真人视讯',
        GameType::CATE_TABLE => '牌桌',
        GameType::CATE_SLO => '老虎机',
        GameType::CATE_FISH => '捕鱼',
        GameType::CATE_P2P => '棋牌',
        GameType::CATE_SPORT => '体育',
        GameType::CATE_ARCADE => '街机',
        GameType::CATE_LOTTERY => '彩票',
    ],
    'help' => [
        'picture_url_size' => '建议图片片尺寸 352 * 410',
    ]
];
