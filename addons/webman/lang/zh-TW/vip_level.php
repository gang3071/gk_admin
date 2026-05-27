<?php

return [
    'title' => 'VIP等級管理',
    'cashback' => '反水比例',
    'cashback_title' => ':name - 反水比例設置',
    'fields' => [
        'id' => 'ID',
        'name' => '等級名稱',
        'upgrade_limit_days' => '升級限制時間（天）',
        'retain_level_days' => '保級時間（天）',
        'retain_level_bet_amount' => '保級所需打碼量',
        'upgrade_bet_amount' => '升級所需打碼量',
        'min_claim_amount' => '最小領取額',
        'birthday_bonus' => '生日禮金',
        'sort' => '排序',
        'status' => '狀態',
        'created_at' => '創建時間',
        'updated_at' => '更新時間',
    ],
    'help' => [
        'upgrade_limit_days' => '升級限制時間，單位為天',
        'retain_level_days' => '保級時間，單位為天',
        'retain_level_bet_amount' => '保級所需打碼量',
        'upgrade_bet_amount' => '升級所需打碼量',
        'min_claim_amount' => '最小領取額',
        'birthday_bonus' => '生日禮金',
        'sort' => '數值越小越靠前',
        'cashback_ratio' => '反水比例，例如輸入0.01表示0.01%',
    ],
    'status' => [
        0 => '禁用',
        1 => '啟用',
    ],
    'messages' => [
        'cashback_saved' => '反水比例保存成功',
    ],
];
