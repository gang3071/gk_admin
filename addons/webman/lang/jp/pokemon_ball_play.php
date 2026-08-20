<?php

return [
    'title' => 'ポケモンボールプレイ設定',
    'fields' => [
        'play_type' => 'プレイタイプ',
        'light_count' => 'ライト数',
        'multiplier' => '倍率',
        'sort' => 'ソート',
    ],
    'play_type' => [
        'one_ball_multi_light' => '一球多灯',
        'three_ball' => '三球',
        'three_guan' => '三関',
    ],
    'play_type_desc' => [
        'one_ball_multi_light' => '一球多灯：1灯8倍、2灯4倍、3灯3倍、4灯2倍、5灯1.7倍',
        'three_ball' => '三球：3灯38倍、4灯18倍、5灯6倍、6灯3倍',
        'three_guan' => '三関：5-4-3 10倍、4-3-2 25倍、3-2-1 70倍',
    ],
    'placeholder' => [
        'select_play_type' => 'プレイタイプを選択',
    ],
    'validation' => [
        'play_type_required' => 'プレイタイプを選択してください',
        'light_count_required' => 'ライト数を入力してください',
        'multiplier_required' => '倍率を入力してください',
    ],
];
