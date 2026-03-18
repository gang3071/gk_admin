<?php

use addons\webman\model\PhoneSmsLog;

return [
    'title' => '簡訊日誌',
    'fields' => [
        'id' => 'ID',
        'phone' => '手機號',
        'code' => '驗證碼',
        'status' => '發送狀態',
        'type' => '類型',
        'created_at' => '創建時間',
        'updated_at' => '更新時間',
    ],
    'type' => [
        PhoneSmsLog::TYPE_LOGIN => '登入',
        PhoneSmsLog::TYPE_REGISTER => '注册',
        PhoneSmsLog::TYPE_CHANGE_PASSWORD => '修改密碼',
        PhoneSmsLog::TYPE_CHANGE_PAY_PASSWORD => '修改支付密碼',
        PhoneSmsLog::TYPE_CHANGE_PHONE => '修改手機號',
        PhoneSmsLog::TYPE_BIND_NEW_PHONE => '綁定新手機號',
        PhoneSmsLog::TYPE_TALK_BIND => 'QTalk綁定帳號',
        PhoneSmsLog::TYPE_LINE_BIND => 'LINE綁定帳號',
    ],
];
