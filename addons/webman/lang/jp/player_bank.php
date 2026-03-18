<?php

/** TODO 翻译 */

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => 'プレイヤーのキャッシュカード',
    'fields' => [
        'id' => 'ID',
        'status' => 'ステータス',
        'bank_name' => '銀行名',
        'account' => '銀行口座番号',
        'account_name' => 'アカウント名',
        'created_at' => '作成時刻',
        'wallet_address' => 'ウォレットアドレス',
        'qr_code' => 'ウォレットコード/入金コード',
        'type' => 'アカウントタイプ',
    ],
    'type' => [
        ChannelRechargeMethod::TYPE_BANK => '銀行口座',
        ChannelRechargeMethod::TYPE_ALI => 'アリペイペイペイ',
        ChannelRechargeMethod::TYPE_WECHAT => 'ウィーチャットペイ',
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
    ],
    'created_at_start' => '開始時刻',
    'created_at_end' => '終了時刻',
];
