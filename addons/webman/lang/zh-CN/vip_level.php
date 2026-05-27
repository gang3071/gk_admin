<?php

return [
    'title' => 'VIP等级管理',
    'cashback' => '反水比例',
    'cashback_title' => ':name - 反水比例设置',
    'fields' => [
        'id' => 'ID',
        'name' => '等级名称',
        'upgrade_limit_days' => '升级限制时间（天）',
        'retain_level_days' => '保级时间（天）',
        'retain_level_bet_amount' => '保级所需打码量',
        'upgrade_bet_amount' => '升级所需打码量',
        'min_claim_amount' => '最小领取额',
        'birthday_bonus' => '生日礼金',
        'sort' => '排序',
        'status' => '状态',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],
    'help' => [
        'upgrade_limit_days' => '升级限制时间，单位为天',
        'retain_level_days' => '保级时间，单位为天',
        'retain_level_bet_amount' => '保级所需打码量',
        'upgrade_bet_amount' => '升级所需打码量',
        'min_claim_amount' => '最小领取额',
        'birthday_bonus' => '生日礼金',
        'sort' => '数值越小越靠前',
        'cashback_ratio' => '反水比例，例如输入0.01表示0.01%',
    ],
    'status' => [
        0 => '禁用',
        1 => '启用',
    ],
    'messages' => [
        'cashback_saved' => '反水比例保存成功',
    ],
];
