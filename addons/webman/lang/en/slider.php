<?php

return [
    'title' => 'Carousel image',
    'fields' => [
        'id' => 'ID',
        'url' => 'Link address',
        'department_id' => 'channel',
        'content' => 'content',
        'picture_url' => 'picture',
        'type' => 'Type',
        'video' => 'Video',
        'status' => 'status',
        'sort' => 'sort',
        'ad_position' => 'Ad Position',
        'created_at' => 'Creation time',
    ],
    'ad_position' => [
        1 => 'Electronic Game Hall',
        2 => 'Physical Hall',
        3 => 'Standby Page',
        4 => 'Horizontal Background',
    ],
    'type' => [
        1 => 'Image',
        2 => 'Video',
    ],
    'url_max_length' => 'The link address can be up to 200 characters',
    'help' => [
        'picture_size_1' => 'Recommended size: 1080 * 350 or 1080 * 533',
        'picture_size_2' => 'Recommended size: 1080 * 545',
        'picture_size_3' => 'Recommended size: 1080 * 1920',
        'video_format' => 'Supported formats: mp4, webm, mov',
    ]
];
