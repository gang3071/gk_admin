<?php

return [
    'title' => '入金ボーナスアクティビティ管理',

    'fields' => [
        'id' => 'ID',
        'activity_name' => 'アクティビティ名',
        'store_id' => 'チャネル',
        'time_range' => 'アクティビティ期間',
        'bet_multiple' => 'ベット倍数',
        'valid_days' => '有効日数',
        'tier_count' => 'ティア数',
        'status' => 'ステータス',
        'created_at' => '作成日時',
        'unlock_type' => 'アンロックタイプ',
        'limit_per_player' => '参加制限',
        'limit_period' => '制限期間',
        'description' => 'アクティビティ説明',
        'tiers' => 'ボーナスティア設定',
        'limit_info' => '参加制限',
    ],

    'tier' => [
        'deposit_amount' => '入金金額',
        'bonus_amount' => 'ボーナス金額',
        'bonus_ratio' => 'ボーナス比率',
        'sort_order' => '並び順',
    ],

    'help' => [
        'activity_name' => 'アクティビティ名を入力してください。例：1000入金で500獲得',
        'store_id' => 'このアクティビティのチャネルを選択',
        'time_range' => 'アクティビティの開始時間と終了時間を設定',
        'bet_multiple' => 'ボーナス金額のベット倍数。例：5倍は500ボーナスで2500ベットが必要',
        'valid_days' => '注文有効日数。この日数を超えてベット量が完了しない場合は失効',
        'unlock_type' => 'アンロックタイプを選択：ベット量アンロックまたはマシンなしアンロック',
        'limit_per_player' => '制限期間内に各プレーヤーが参加できる回数。0は制限なし',
        'limit_period' => '参加回数制限の統計期間',
        'description' => 'アクティビティ説明。プレーヤーに表示されます',
        'tiers' => '異なる入金金額に対応するボーナス金額を設定。少なくとも1つのティアが必要',
    ],

    'status_enabled' => '有効',
    'status_disabled' => '無効',

    'unlock_type_bet' => 'ベット量アンロック',
    'unlock_type_no_machine' => 'マシンなしアンロック',

    'period_day' => '毎日',
    'period_week' => '毎週',
    'period_month' => '毎月',

    'days' => '日',
    'times' => '回',
    'no_limit' => '制限なし',

    'not_found' => 'アクティビティが見つかりません',
    'tier_required' => '少なくとも1つのボーナスティアを設定してください',

    'created_at_start' => '作成開始時間',
    'created_at_end' => '作成終了時間',
];
