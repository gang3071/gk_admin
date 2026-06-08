<?php

return [
    'title' => 'VIPレベル管理',
    'cashback' => 'キャッシュバック率',
    'cashback_title' => ':name - キャッシュバック率設定',
    'import_template' => 'テンプレートをインポート',
    'import_confirm' => 'デフォルトのVIPテンプレートをインポートしてもよろしいですか？10個のデフォルトVIPレベル（VIP1-VIP10）が作成されます',
    'import_error_exists' => 'このチャネルには既に{count}個のVIPレベルがあります。再度インポートする必要はありません',
    'fields' => [
        'id' => 'ID',
        'name' => 'レベル名',
        'upgrade_limit_days' => 'アップグレード制限時間（日）',
        'retain_level_days' => 'レベル維持期間（日）',
        'retain_level_bet_amount' => 'レベル維持ベット金額',
        'upgrade_bet_amount' => 'アップグレードベット金額',
        'min_claim_amount' => '最小受取額',
        'birthday_bonus' => '誕生日ボーナス',
        'sort' => '並べ替え',
        'status' => 'ステータス',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
    ],
    'help' => [
        'upgrade_limit_days' => 'アップグレード制限時間（日数）',
        'retain_level_days' => 'レベル維持期間（日数）',
        'retain_level_bet_amount' => 'レベル維持に必要なベット金額',
        'upgrade_bet_amount' => 'アップグレードに必要なベット金額',
        'min_claim_amount' => '最小受取額',
        'birthday_bonus' => '誕生日ボーナス金額',
        'sort' => '小さい値が最初に表示されます',
        'cashback_ratio' => 'キャッシュバック率。100=100%、0.1=0.1%',
    ],
    'status' => [
        0 => '無効',
        1 => '有効',
    ],
    'messages' => [
        'cashback_saved' => 'キャッシュバック率が保存されました',
    ],
];
