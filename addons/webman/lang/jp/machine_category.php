<?php

return [
    'title' => 'マシンカテゴリ',
    'fields' => [
        'id' => 'ID',
        'name' => 'カテゴリ名',
        'game_id' => 'ゲームカテゴリ',
        'type' => 'マシンタイプ',
        'picture_url' => '画像',
        'status' => 'ステータス',
        'sort' => '並べ替え',
        /** TODO 翻訳 */
        'keep_ minutes' => '回転/圧力あたり数秒を維持',
        'minutes' => '分',
        'second' => '秒',
        'lottery_point' => 'スピン/プレッシャースピンごとにボーナスプールポイントの数が増加します',
        'lottery_rate' => '固定閾値ペイアウト係数',
        'lottery_add_status' => '宝くじ累計ステータス',
        'lottery_assign_status' => '宝くじの割り当てステータス',
        'turn_used_point' => '1回転あたりのゲームポイント消費',
    ],
    'opening_gift' => [
        'opening_gift' => 'オープニングポイント',
        'give_rule_num' => '1日あたりの回数',
        'open_num' => '開始分番号',
        'give_num' => 'ポイントを与える',
        'condition' => '完了条件を満たす',
    ],
    'delete_has_machine_error' => '変更されたカテゴリにマシンが追加されました。最初にマシンを削除してください',
    'lottery_setting' => '宝くじ設定',
    'lottery_point_help' => 'ゲーム中、Sluo がポイントを押すたび、または鋼球の回転ごとに、金額の一部が対応するボーナス プールに蓄積されます',
    'lottery_rate_help' => '実際のアクティビティ額に支払い係数が乗算されます',
    'lottery_add_status_help' => 'このタイプが宝くじプールの蓄積に参加するかどうか',
    'lottery_assign_status_help' => 'このタイプが宝くじに参加するかどうか',
];
