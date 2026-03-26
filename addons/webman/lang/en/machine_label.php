<?php

return [
    'title ' => ' Machine Label ',
    'fields' => [
        'id' => 'ID',
        'name' => 'name',
        'type ' => ' machine type ',
        'picture_url' => 'picture',
        'status' => 'state',
        'point' => 'game point',
        'turn ' => ' revolutions',
        'score ' => ' fraction ',
        'Courtyard' => 'Courtyard',
        'correct_rate' => 'accuracy',
        'sort ' => ' sort ',
        'created_at' => 'creation time',
        'updated_at' => 'update time',
    ],
    'delete_has_machine_error' => 'This tag still has machines in use and cannot be deleted',
    'form' => [
        'label_name' => 'Label Name',
        'multilingual_config' => 'Multilingual Configuration',
    ],
    'validation' => [
        'please_fill_label_name' => 'Please fill in label name',
        'please_fill_simplified_chinese_name' => 'Please fill in Simplified Chinese name',
        'please_upload_simplified_chinese_image' => 'Please upload Simplified Chinese image',
    ],
];
