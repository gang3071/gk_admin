<?php

return [
    'title' => 'Meals',
    'fields' => [
        'id' => 'ID',
        'department_id' => 'Channel/Department',
        'admin_user_id' => 'Store',
        'category_id' => 'Category',
        'title' => 'Name',
        'content' => 'Content',
        'picture' => 'Image',
        'price' => 'Price',
        'daily_limit' => 'Daily limit per person',
        'status' => 'Status',
        'top' => 'Top',
        'sort' => 'Sort',
        'remark' => 'Remark',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At'
    ],
    'status' => [
        0 => 'Disabled',
        1 => 'Enabled'
    ],
    'help' => [
        'daily_limit' => 'Enter 0 for unlimited',
        'remark' => 'Please use a slash (/) to separate categories and a semicolon (;) to separate options. Ex. less ice;light ice;no ice/full sugar;half sugar;no sugar'
    ]
];

