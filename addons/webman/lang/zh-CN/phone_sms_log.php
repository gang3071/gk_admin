<?php

use addons\webman\model\PhoneSmsLog;

return [
    'title' => '短信日志',
    'fields' => [
        'id' => 'ID',
        'phone' => '手机号',
        'code' => '验证码',
        'status' => '发送状态',
        'type' => '类型',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],
    'type' => [
        PhoneSmsLog::TYPE_LOGIN => '登录',
        PhoneSmsLog::TYPE_REGISTER => '注册',
        PhoneSmsLog::TYPE_CHANGE_PASSWORD => '修改密码',
        PhoneSmsLog::TYPE_CHANGE_PAY_PASSWORD => '修改支付密码',
        PhoneSmsLog::TYPE_CHANGE_PHONE => '修改手机号',
        PhoneSmsLog::TYPE_BIND_NEW_PHONE => '绑定新手机号',
        PhoneSmsLog::TYPE_TALK_BIND => 'QTalk绑定账号',
        PhoneSmsLog::TYPE_LINE_BIND => 'LINE绑定账号',
    ],
];
