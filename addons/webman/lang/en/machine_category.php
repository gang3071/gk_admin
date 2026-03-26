<?php

return [
    'title' => 'Machine category',
    'fields' => [
        'id' => 'ID',
        'name' => 'Category name',
        'game_id' => 'Game category',
        'type' => 'Machine type',
        'picture_url' => 'picture',
        'status' => 'status',
        'sort' => 'sort',
        /** TODO translation */
        'keep_minutes' => 'Keep a few seconds per revolution/pressure',
        'minutes' => 'minutes',
        'second' => 'second',
        'lottery_point' => 'Each spin/pressure spin increases bonus pool points',
        'lottery_rate' => 'Fixed threshold payout coefficient',
        'lottery_add_status' => 'Lottery accumulation status',
        'lottery_assign_status' => 'Lottery assignment status',
        'turn_used_point' => 'turn used point',
    ],
    'opening_gift' => [
        'opening_gift' => 'opening points',
        'give_rule_num' => 'Number of times per day',
        'open_num' => 'Open minute number',
        'give_num' => 'Give points',
        'condition' => 'Satisfies completion conditions',
    ],
    'delete_has_machine_error' => 'A machine has been added under the changed category, please delete the machine first',
    'lottery_setting' => 'Lottery configuration',
    'lottery_point_help' => 'During the game, every time Sluo presses a point, or every turn of the steel ball, a part of the amount will be accumulated to the corresponding bonus pool',
    'lottery_rate_help' => 'The actual activity amount will be multiplied by the payout coefficient',
    'lottery_add_status_help' => 'Whether this type participates in the accumulation of the lottery pool',
    'lottery_assign_status_help' => 'Whether this type participates in lottery',
    'form' => [
        'category_name' => 'Category Name',
        'multilingual_config' => 'Multilingual Configuration',
    ],
    'validation' => [
        'please_fill_category_name' => 'Please fill in category name',
        'please_fill_simplified_chinese_name' => 'Please fill in Simplified Chinese name',
    ],
];
