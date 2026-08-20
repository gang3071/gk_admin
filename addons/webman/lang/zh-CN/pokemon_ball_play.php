<?php

return [
    'title' => '精灵球玩法配置',
    'fields' => [
        'play_type' => '玩法类型',
        'light_count' => '灯数',
        'multiplier' => '倍率',
        'sort' => '排序',
    ],
    'play_type' => [
        'one_ball_multi_light' => '一球多灯',
        'three_ball' => '三球',
        'three_guan' => '三关',
    ],
    'play_type_desc' => [
        'one_ball_multi_light' => '一球多灯：1灯8倍、2灯4倍、3灯3倍、4灯2倍、5灯1.7倍',
        'three_ball' => '三球：3灯38倍、4灯18倍、5灯6倍、6灯3倍',
        'three_guan' => '三关：5-4-3 10倍、4-3-2 25倍、3-2-1 70倍',
    ],
    'placeholder' => [
        'select_play_type' => '请选择玩法',
    ],
    'validation' => [
        'play_type_required' => '请选择玩法类型',
        'light_count_required' => '请填写灯数',
        'multiplier_required' => '请填写倍率',
    ],
];
