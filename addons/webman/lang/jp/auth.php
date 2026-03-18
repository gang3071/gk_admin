<?php

use addons\webman\model\AdminDepartment;

return [
    'title' => 'アクセス権管理',
    'parent' => '父親',
    'field_title_grant' => 'フィールド権限（選択したフィールドを非表示にする）',
    'field_grant' => 'フィールド権限',
    'data_grant' => 'データ権限',
    'auth_grant' => '機能的な権限',
    'menu_grant' => 'メニューの権限',
    'select_user' => '個人を選択',
    'select_group' => '選挙組織',
    'select_user_tip' => '選択したユーザーを含めるデータの表示権限がある',
    'select_group_tip' => '選択した組織を含むデータの表示権限を持っています',
    'all' => 'すべて選択',
    'father_son_linkage' => '父と子のつながり',
    'fields' => [
        'name' => '名前',
        'desc' => '説明',
        'status' => '状態',
        'sort' => '分類する',
        'data_type' => 'データ範囲',
        'department' => '部門リスト',
        'type' => '役割の種類',
    ],
    'options' => [
        'data_type' => [
            'full_data_rights' => 'フルデータアクセス',
            'data_permissions_for_this_department' => 'この部門のデータ許可',
            'this_department_and_the_following_data_permissions' => 'この部門と次のデータ権限',
            'personal_data_rights' => '私のデータの権利',
            'custom_data_permissions' => 'カスタムデータ権限',
            'channel_and_the_following_data_permissions' => 'サブステーションのすべてのデータ権限',
            'agent_and_the_following_data_permissions' => 'エージェントのすべてのデータ権限'
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '終端の役割',
        AdminDepartment::TYPE_CHANNEL => 'チャネルの役割',
        AdminDepartment::TYPE_AGENT => 'エージェントの役割',
        AdminDepartment::TYPE_STORE => 'ストアの役割',
    ],
];
