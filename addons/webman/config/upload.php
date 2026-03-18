<?php

return [

    //上传配置
    'upload' => [
        //config/filesystems.php
        'disk' => 'google_oss',
        //保存目录
        'directory' => [
            'image' => 'images',
            'file' => 'files',
        ],
        //禁止上传后缀
        'disabled_ext' => ['php']
    ]
];
