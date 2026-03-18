<?php

use addons\webman\model\GameType;

return [
    'title' => 'ゲームカテゴリ',
    'fields' => [
        'id' => 'ID',
        'picture_url' => '絵',
        'type' => 'マシンタイプ',
        'cate' => 'ゲームの種類',
        'name' => '名前',
        'sort' => '分類する',
        'status' => '狀態',
    ],
    'game_type' => [
        GameType::TYPE_SLOT => 'スロット',
        GameType::TYPE_STEEL_BALL => 'パチンコ',
        GameType::TYPE_FISH => 'フィッシュマシン',
    ],
    'game_type_cate' => [
        GameType::CATE_PHYSICAL_MACHINE => 'ロボット',
        GameType::CATE_COMPUTER_GAME => 'プレイヤーアカウント',
        GameType::CATE_LIVE_VIDEO => 'リアルビュー',
        GameType::CATE_TABLE => 'タイガー',
        GameType::CATE_SLO => 'スロットマシン',
        GameType::CATE_FISH => '誓い',
        GameType::CATE_P2P => 'ボード',
        GameType::CATE_SPORT => 'スポーツ',
        GameType::CATE_ARCADE => 'ストリート',
        GameType::CATE_LOTTERY => '宝くじ',
    ],
    'help' => [
        'picture_url_size' => '推奨画像サイズ 352 * 410',
    ]
];
