<?php

use addons\webman\model\PromoterProfitRecord;

return [
    'title' => 'ルートの分潤',
    'fields' => [
        'id' => 'ID',
        'department_id' => 'チャンネルID',
        'status' => '決済ステータス',
        'withdraw_amount' => '洗分金額',
        'recharge_amount' => '開分額',
        'bonus_amount' => 'アクティビティ報酬額',
        'admin_deduct_amount' => '管理者控除ポイント',
        'admin_add_amount' => '管理者がポイントを追加',
        'present_amount' => 'システムギフト',
        'machine_up_amount' => 'マシンポイント',
        'machine_down_amount' => 'マシンダウンポイント',
        'lottery_amount' => '宝くじボーナス',
        'profit_amount' => 'チャネルの現在の分散',
        'self_profit_amount' => 'プラットフォームの現在の分潤',
        'water_amount' => '電子ゲームの返水金額',
        'settlement_tradeno' => '決済注文番号',
        'ratio' => '利益分配率',
        'settlement_time' => '決済時間',
        'created_at' => '作成時刻',
        'date' => 'データ生成日',
        'updated_at' => '更新時刻',
        'total_amount' => '金額',
        'open_point' => '上の点',
        'wash_point' => '下位ポイント',
        'game_amount' => 'ゲームの金額',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => '未確定',
        PromoterProfitRecord::STATUS_COMPLETED => '決済済み',
    ],
    'player_promoter' => [
        'phone' => 'プロモーターの携帯電話番号',
        'uuid' => 'プロモーター UUID'
    ],
    'settlement_time_start' => '決済開始時刻',
    'settlement_time_end' => '決済終了時刻',
    'date_tip' => 'データは前日の午前3時0時から24時まで更新されます',
    'profit_amount_tip' => '利益決済式 (マシンポイント + 管理者減点ポイント) - (アクティビティ報酬 + システムギフト + 管理者ポイント + マシンポイント + ボーナスボーナス) ',
    'channel_settlement' => 'チャネル決済',
];
