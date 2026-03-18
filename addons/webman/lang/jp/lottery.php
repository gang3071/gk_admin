<?php

use addons\webman\model\Lottery;

return [
    'title' => 'ボーナスリスト',
    'lottery_info' => '宝くじ情報',
    'fields' => [
        'id' => '番号',
        'name' => 'ボーナス名',
        'rate' => '金額比率',
        'game_type' => 'ゲームタイプ',
        'lottery_type' => '宝くじの種類',
        'condition' => 'トリガー条件',
        'start_num' => '開始番号',
        'end_num' => '終了番号',
        'random_num' => 'ランダムな値',
        'last_player_id' => '最後に勝ったプレイヤー ID',
        'last_player_name' => '最後に勝ったプレイヤーの名前',
        'last_award_amount' => '最後の賞金額',
        'max_amount' => '上限額',
        'lottery_times' => 'リリース回数',
        'status' => 'ステータス',
        'sort' => '並べ替え',
        'created_at' => '作成時刻',
        'updated_at' => '更新時刻',
        'deleted_at' => '削除された時間',
        'win_ratio' => '勝利の確率',
    ],
    'created_at_start' => '開始時刻',
    'created_at_end' => '終了時刻',
    'lottery_type' => [
        Lottery::LOTTERY_TYPE_FIXED => '固定しきい値',
        Lottery::LOTTERY_TYPE_RANDOM => 'ランダムトリガー',
    ],
    'placeholder_start_num' => '開始番号を入力してください',
    'placeholder_end_num' => 'エンドポイント番号を入力してください',
    'placeholder_max_amount' => '上限額を入力してください',
    'ルール' => [
        'start_num_required' => '開始番号が必要です',
        'start_num_min_1' => '開始番号を 0 にすることはできません',
        'start_num_max_100000000' => '開始番号を 1 億に設定します',
        'end_num_required' => '終了番号が必要です',
        'end_num_min_1' => '終了番号を 0 にすることはできません',
        'end_num_max_100000000' => 'エンドポイントの最大数は 1 億に設定されます',
        'end_num_gt_start_num' => '終了番号は開始番号より大きくなければなりません',
        'max_amount_min_1' => '上限額を0にすることはできません',
        'max_amount_max_100000000' => '上限額は 1 億に設定されています',
        'condition_required' => '条件は必須です',
        'max_count_five' => '各テーブルタイプのカラーゴールドは5本を超えることはできません',
    ],
    'ヘルプ' => [
        'rate_help' => '賞金プールの一定割合の報酬を獲得',
        'max_amount_help' => '最大のボーナスプール報酬を獲得',
        'condition_slot_help' => 'スコアボードの合計スコアが一定のポイントに達すると、ボーナスプール報酬を獲得できます',
        'condition_jac_help' => '一度にドロップされるビーズの量が一定の数に達すると、ボーナスプール報酬を獲得できます',
        'start_jac_help' => '現在のボーナス計算ラウンドの開始時から、x 回目のロールをしたプレイヤーが賞金を獲得できます',
        'start_slot_help' => 'ボーナス計算の現在のラウンドが開始されると、x 回目に得点したプレイヤーが賞金を獲得します',
        'double_amount_help' => 'ボーナスプールが設定値に達すると、2倍の支払いが可能になります',
        'amount_help' => 'Sluo がポイントを押すたび、または鋼球が回転するたびに、金額の一部が対応するボーナス プールに蓄積されます',
        'max_amount' => 'カラープールの最大累積金額',
    ],
    'slot_condition_msg' => 'スコアボードの合計スコアに達しました',
    'jac_condition_msg' => '一度に到達したビーズの数',
    'random_rang' => '範囲',
    'edit_slot_lottery_pool' => 'スロット宝くじプールを変更',
    'edit_jack_lottery_pool' => '鋼球宝くじプールを変更',
    'lottery_pool_not_fount' => '宝くじプールが見つかりません',
    'edit_slot_double_amount' => 'スロットのダブルボーナス設定を変更',
    'edit_jack_double_amount' => 'スチールボールダブルボーナス設定を変更',
    'edit_slot_max_amount' => 'スロウ最大累積年金設定の変更',
    'edit_jack_max_amount' => 'ビーズの最大列挙カラー設定を変更するには',
    'slot_lottery_pool' => 'スロット宝くじプール',
    'jack_lottery_pool' => '鋼球宝くじプール',
    'slot_double_lottery_pool' => 'スロットダブルペイアウト',
    'jack_double_lottery_pool' => 'スティールボールのダブル配当',
    'slot_max_lottery_pool' => 'スロウ最大彩池',
    'jack_max_lottery_pool' => 'ボール最大カラープール',
    'not_fount' => 'ボーナスが見つかりません',
    'action_success' => 'アクションは成功しました',
    'game_amount' => 'ゲーミングジャックポット',

    'game_amount_1' => 'ゲーミングジャックポットは0にできません',

    'game_amount_100000000' => 'ゲーミングジャックポットの上限設定：1億ドル',

    'game_amount_help' => 'ゲーミングゲームでの各ベットは、その額の対応する割合がこのジャックポットに蓄積されます',

    'placeholder_game_amount' => 'ゲーミングジャックポットの金額を入力してください',

    'double_amount' => 'ダブルペイアウトの金額',

    'double_status' => 'ダブルペイアウトを有効にするかどうか',

    'double_amount_help' => 'ジャックポットが設定額に達すると、ダブルペイアウトが有効になります',

    'placeholder_double_amount' => 'ダブルペイアウトのトリガーを入力してください',

    'max_double_amount_min_1' => '最小ダブルペイアウト額は0です',
    'max_double_amount_100000000' => '最大ダブルペイアウト額は1億ドルに設定されています',

    'max_status' => '最大ペイアウトプールを有効にするかどうか',

    'max_amount_help' => '最大ペイアウトプール。設定金額に達すると、賞金プールはそれ以上積み立てられません。',

    'placeholder_game_max_amount' => '最大支払額を入力してください。',

    'max_amount' => '最大支払額',

    'max_amount_min_1' => '最小支払額は0に設定されています。',

    'max_amount_100000000' => '最大支払額は1億円に設定されています。',

    'pool_ratio' => 'プール比率',

    'pool_ratio_help' => 'ビデオゲームでは、賭け金ごとに一定の割合が賞金プールに積み立てられます。',
    'base_bet_amount' => 'ベット額制限',

    'base_bet_amount_0' => '最低ベット額制限を0に設定',

    'base_bet_amount_100000000' => '最高ベット額制限を1億に設定',

    'base_bet_amount_help' => 'ベット額制限。抽選に参加するには、各ベット額がこの設定額以上である必要があります',
];
