<?php

return [
    'title' => 'Pokemon Ball Play Config',
    'fields' => [
        'play_type' => 'Play Type',
        'light_count' => 'Light Count',
        'multiplier' => 'Multiplier',
        'sort' => 'Sort',
    ],
    'play_type' => [
        'one_ball_multi_light' => 'One Ball Multi Light',
        'three_ball' => 'Three Ball',
        'three_guan' => 'Three Guan',
    ],
    'play_type_desc' => [
        'one_ball_multi_light' => 'One Ball Multi Light: 1 Light 8x, 2 Light 4x, 3 Light 3x, 4 Light 2x, 5 Light 1.7x',
        'three_ball' => 'Three Ball: 3 Light 38x, 4 Light 18x, 5 Light 6x, 6 Light 3x',
        'three_guan' => 'Three Guan: 5-4-3 10x, 4-3-2 25x, 3-2-1 70x',
    ],
    'placeholder' => [
        'select_play_type' => 'Select Play Type',
    ],
    'validation' => [
        'play_type_required' => 'Please select play type',
        'light_count_required' => 'Please enter light count',
        'multiplier_required' => 'Please enter multiplier',
    ],
];
