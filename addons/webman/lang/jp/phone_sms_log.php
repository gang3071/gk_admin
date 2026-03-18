<?php

use addons\webman\model\PhoneSmsLog;

return [
    'title' => 'メールログ',
    'fields' => [
        'id' => 'ID',
        'phone' => '携帯番号',
        'code' => '認証コード',
        'status' => '送信ステータス',
        'type' => 'タイプ',
        'created_at' => '作成時間',
        'updated_at' => '更新時間',
    ],
    'type' => [
        PhoneSmsLog::TYPE_LOGIN => 'ログイン',
        PhoneSmsLog::TYPE_REGISTER => '登録',
        PhoneSmsLog::TYPE_CHANGE_PASSWORD => 'パスワードの変更',
        PhoneSmsLog::TYPE_CHANGE_PAY_PASSWORD => '支払パスワードの変更',
        PhoneSmsLog::TYPE_CHANGE_PHONE => '携帯電話番号の変更',
        PhoneSmsLog::TYPE_BIND_NEW_PHONE => '新しい携帯電話番号をバインドする',
        PhoneSmsLog::TYPE_TALK_BIND => 'QTalkバインドアカウント',
        PhoneSmsLog::TYPE_LINE_BIND => 'LINEバインドアカウント',
    ],
];
