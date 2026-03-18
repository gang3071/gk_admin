<?php

use addons\webman\model\AdminDepartment;

return [
    'title' => '部門管理',
    'normal' => '普通',
    'disable' => '無効',
    'parent_id_repeat' => '上位部門を部門にすることはできません',
    'fields' => [
        'pid' => '上級オフィス',
        'name' => '部署名',
        'leader' => '主要',
        'mobile' => '電話番号',
        'status' => '状態',
        'sort' => '分類する',
        'create_at' => '作成時間',
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '駅長',
        AdminDepartment::TYPE_CHANNEL => 'チャンネルマネージャー',
        AdminDepartment::TYPE_AGENT => 'エージェント管理者',
        AdminDepartment::TYPE_STORE => 'ストア管理者',
    ],
];
