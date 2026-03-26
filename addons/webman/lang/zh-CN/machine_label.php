<?php

return [
    'title' => '机器标签',
    'fields' => [
        'id' => 'ID',
        'name' => '名称',
        'type' => '机器类型',
        'picture_url' => '图片',
        'status' => '状态',
        'point' => '游戏点',
        'turn' => '转数',
        'score' => '分数',
        'courtyard' => '天井',
        'correct_rate' => '确率',
        'sort' => '排序',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],
    'delete_has_machine_error' => '该标签尚有机台使用无法删除',
    'form' => [
        'label_name' => '标签名称',
        'multilingual_config' => '多语言配置',
    ],
    'validation' => [
        'please_fill_label_name' => '请填写标签名称',
        'please_fill_simplified_chinese_name' => '请填写中文简体名称',
        'please_upload_simplified_chinese_image' => '请上传中文简体图',
    ],
];
