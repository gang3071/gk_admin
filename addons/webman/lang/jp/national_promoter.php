<?php

return [
    'title' => '全国民エージェントの設定',
    'report_title' => '全国民エージェント・レポート',
    'national_profit_record' => '分潤明細',
    'fields' => [
        'id' => 'ID',
        'uid' => 'プレイヤーID',
        'chip_amount' => '現在の打刻量',
        'level_sort' => 'ユーザー・レベル',
        'level' => 'ユーザレベル',
        'invite_num' => '招待プレイヤー数',
        'pending_amount' => '未決済ポイント',
        'settlement_amount' => '決済済ポイント',
        'created_at' => '作成時間',
        'updated_at' => '更新日時',
        'last_national_profit_money' => '前期決算点数',
        'last_national_profit_created_at' => '前期決算時間',
        'recommend_promoter_uuid' => '所属推薦人UUID',
        'type' => '返品タイプ',
    ],
    'level_list' => [
        'title' => 'アップグレード条件',
        'sort' => 'レベルウエイトレベル',
        'name' => '等級名',
        'level' => '等級分類',
        'must_chip_amount' => 'ふごうかく',
        'damage_rebate_ratio' => '客損戻入比例',
        'recharge_ratio' => '初回充填手数料',
        'reverse_water' => '反水比率',
    ],
    'must_chip_amount_error' => 'ヤードを打つ量は必ず下級より高く，上級より低くなければならない',
    'invite' =>[
        'min' => '最小人数',
        'max' => '最大人数',
        'number' => '人数要件',
        'interval' => '奨励間隔',
        'money' => '奨励金ポイント',
        'status' => 'ステータス',
    ],
    'invite_num_error' => '招待者数区間の重複',
    'machine_amount' => 'テーブルの分潤',
    'game_amount' => '電子ゲームの分潤',
    'type' => [
        '初充填奉還',
        '客損返礼'
    ],
    'settlement' => '分潤決済',
    'settlement_confirm' => '全国民エージェント {uuid} の決済操作を行っています。現在決済可能な利益 {amount} は、決済されているかどうかを確認してください?',
    'level_prefix' => 'レベル',
    'level_suffix' => '',
    'player' => 'プレイヤー',
    'admin' => '管理者',

    'log' => [
        'settlement_failed' => '清算失敗',
    ],
];
