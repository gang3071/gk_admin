<?php

use addons\webman\model\ChannelRechargeMethod;

return [
    'title' => '開分方法の設定',
    'placeholder_name' => '開分方法名を入力してください',
    'placeholder_min' => '最低チャージ金額を入力してください',
    'placeholder_max' => '最大チャージ金額を入力してください',
    'limitation' => '制限',
    'no_limit' => '制限なし',
    'fields' => [
        'id' => 'ID',
        'name' => 'チャージ方法',
        'department_id' => 'デパートメント/チャンネルID',
        'user_id' => '管理者ID',
        'user_name' => '創設者',
        'max' => '最大チャージ金額',
        'min' => '最低チャージ金額',
        'status' => '状態',
        'created_at' => '作成時間',
        'amount_limit' => '金額を制限するかどうか',
        'type' => 'チャージ方式',
    ],
    'rul' => [
        'min_required' => '最低チャージ金額が必要です',
        'min_min_1' => '最低金額を0にすることはできません',
        'min_max_100000000' => '上限額は1億円に設定されています',
        'max_required' => '最低チャージ金額が必要です',
        'max_min_1' => '最低金額を0にすることはできません',
        'max_max_100000000' => '上限額は1億円に設定されています',
        'max_gt_min' => '最大開分額は最小開分額より大きくなければなりません',
    ],
    'recharge_method_not_found' => 'チャージ方式は存在しません',
    'wechat_account' => 'マイクロ信号',
    'ali_account' => 'Alipayアカウント',
    'type' => [
        ChannelRechargeMethod::TYPE_USDT => 'USDT',
        ChannelRechargeMethod::TYPE_ALI => 'アリペイペイペイ',
        ChannelRechargeMethod::TYPE_WECHAT => 'ウィーチャットペイ',
        ChannelRechargeMethod::TYPE_BANK => '銀行カード',
        ChannelRechargeMethod::TYPE_GB => '宝の財布',
        ChannelRechargeMethod::TYPE_COIN => '貨幣商控除点',
    ]
];
