<?php

return [
    'title' => '精靈球玩法配置',
    'fields' => [
        'play_type' => '玩法類型',
        'light_count' => '燈數',
        'multiplier' => '倍率',
        'sort' => '排序',
    ],
    'play_type' => [
        'one_ball_multi_light' => '一球多燈',
        'three_ball' => '三球',
        'three_guan' => '三關',
    ],
    'play_type_desc' => [
        'one_ball_multi_light' => '一球多燈：1燈8倍、2燈4倍、3燈3倍、4燈2倍、5燈1.7倍',
        'three_ball' => '三球：3燈38倍、4燈18倍、5燈6倍、6燈3倍',
        'three_guan' => '三關：5-4-3 10倍、4-3-2 25倍、3-2-1 70倍',
    ],
    'placeholder' => [
        'select_play_type' => '請選擇玩法',
    ],
    'validation' => [
        'play_type_required' => '請選擇玩法類型',
        'light_count_required' => '請填寫燈數',
        'multiplier_required' => '請填寫倍率',
    ],
];
