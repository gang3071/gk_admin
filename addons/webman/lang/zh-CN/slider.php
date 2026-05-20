<?php

return [
    'title' => '轮播图',
    'fields' => [
        'id' => 'ID',
        'url' => '链接地址',
        'department_id' => '渠道',
        'content' => '内容',
        'picture_url' => '图片',
        'status' => '状态',
        'sort' => '排序',
        'ad_position' => '广告位',
        'created_at' => '创建时间',
    ],
    'ad_position' => [
        0 => '未选择',
        1 => '电子游戏大厅',
        2 => '实体大厅',
        3 => '待机页面',
    ],
    'url_max_length'=>'链接地址最多200个字符',
    'help' => [
        'picture_url_size' => '建議圖片尺寸 1080 * 458',
    ]
];
