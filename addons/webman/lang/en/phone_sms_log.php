<?php

use addons\webman\model\PhoneSmsLog;

return [
    'title' => 'SMS Log',
    'fields' => [
        'id' => 'ID',
        'phone' => 'Phone Number',
        'code' => 'Verification Code',
        'status' => 'Sending Status',
        'type' => 'Type',
        'created_at' => 'Creation Time',
        'updated_at' => 'Update Time',
    ],
    'type' => [
        PhoneSmsLog::TYPE_LOGIN => 'Login',
        PhoneSmsLog::TYPE_REGISTER => 'Register',
        PhoneSmsLog::TYPE_CHANGE_PASSWORD => 'Change Password',
        PhoneSmsLog::TYPE_CHANGE_PAY_PASSWORD => 'Change payment password',
        PhoneSmsLog::TYPE_CHANGE_PHONE => 'Change phone number',
        PhoneSmsLog::TYPE_BIND_NEW_PHONE => 'Bind new phone number',
        PhoneSmsLog::TYPE_TALK_BIND => 'QTalk Bind Account',
        PhoneSmsLog::TYPE_LINE_BIND => 'LINE Bind Account',
    ],
];
