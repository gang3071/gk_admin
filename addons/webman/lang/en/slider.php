<?php

return [
    'title' => 'Carousel image',
    'fields' => [
        'id' => 'ID',
        'url' => 'Link address',
        'department_id' => 'channel',
        'content' => 'content',
        'picture_url' => 'picture',
        'status' => 'status',
        'sort' => 'sort',
        'ad_position' => 'Ad Position',
        'created_at' => 'Creation time',
    ],
    'ad_position' => [
        0 => 'Not selected',
        1 => 'Electronic Game Hall',
        2 => 'Physical Hall',
        3 => 'Standby Page',
    ],
    'url_max_length' => 'The link address can be up to 200 characters',
    'help' => [
        'picture_url_size' => 'Recommended picture size 1080 * 458',
    ]
];
