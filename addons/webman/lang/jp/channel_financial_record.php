<?php

use addons\webman\model\ChannelFinancialRecord;

return [
    'title' => '財務業務記録',
    'content' => 'シリアルナンバー{setting_id}',
    'fields' => [
        'id' => 'ID',
        'department_id' => 'デパートメント/チャンネルID',
        'player_id' => 'プレイヤーID',
        'player' => 'プレイヤー情報',
        'target' => 'データシート',
        'target_id' => 'データテーブルID',
        'action' => '運用上の動作',
        'tradeno' => '操作順序',
        'user_id' => '操作順序',
        'user_name' => 'オペレーター',
        'created_at' => '稼働時間',
    ],
    'action' => [
        ChannelFinancialRecord::ACTION_RECHARGE_PASS => 'リチャージ監査に合格しました',
        ChannelFinancialRecord::ACTION_RECHARGE_REJECT => 'リチャージ監査が拒否されました',
        ChannelFinancialRecord::ACTION_WITHDRAW_PASS => '出金が承認されました',
        ChannelFinancialRecord::ACTION_WITHDRAW_REJECT => '出金監査が拒否されました',
        ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT => '支払いを完了する',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD => 'リチャージアカウントを追加する',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP => 'トップアップアカウントを無効にする',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE => 'トップアップアカウントを有効にする',
        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT => 'リチャージアカウントを編集する',
        ChannelFinancialRecord::ACTION_WITHDRAW_GB_ERROR => '宝の現金引き出し支払いに失敗しました',
        ChannelFinancialRecord::ACTION_WITHDRAW_EH_ERROR => '現金引き出し支払いに失敗しました',
    ]
];
