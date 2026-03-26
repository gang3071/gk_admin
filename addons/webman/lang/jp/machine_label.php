<?php

return [
    'title ' => 'マシンタグ',
    'fields' => [
        'id' => 'ID',
        'name' => '名前',
        'type ' => 'マシンタイプ',
        'picture_url' => 'ピクチャ',
        'status' => '状態',
        'point' => 'ゲームポイント',
        'turn ' => ' 回転数',
        'score ' => ' スコア ',
        'Courtyard' => '天井',
        'correct_rate' => '確度',
        'sort ' => 'ソート',
        'created_at' => '作成時間',
        'updated_at' => '更新時間',
    ],
    'delete_has_machine_error' => 'このラベルはまだ削除できない机があります',
    'form' => [
        'label_name' => 'ラベル名',
        'multilingual_config' => '多言語設定',
    ],
    'validation' => [
        'please_fill_label_name' => 'ラベル名を入力してください',
        'please_fill_simplified_chinese_name' => '簡体字中国語名を入力してください',
        'please_upload_simplified_chinese_image' => '簡体字中国語画像をアップロードしてください',
    ],
];
