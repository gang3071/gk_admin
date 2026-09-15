<?php

return [
    'title' => '販売機スコア購入設定',
    'score_settings' => 'スコア購入选項設定',
    'player_exists' => 'この店舗には既にスコア購入設定が存在します',
    'at_least_one_score' => '少なくとも1つのスコア購入选項が必要です',
    'reset_to_default' => 'デフォルト設定にリセット',
    'reset_confirm_message' => 'スコア購入設定をデフォルト値にリセットしてもよろしいですか？',
    'reset_success' => 'デフォルト設定に正常にリセットされました',
    'reset_failed' => 'デフォルト設定へのリセットに失敗しました',
    'not_set' => '未設定',
    'fields' => [
        'id' => 'ID',
        'store_admin_id' => '店舗',
        'store_admin_name' => '店舗名',
        'scores' => '現在のスコア選項',
        'default_scores' => 'デフォルトスコア',
        'system_default' => 'システムデフォルトスコア',
        'score_1' => 'スコア選項1',
        'score_2' => 'スコア選項2',
        'score_3' => 'スコア選項3',
        'score_4' => 'スコア選項4',
        'score_5' => 'スコア選項5',
        'score_6' => 'スコア選項6',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
    ],
    'help' => [
        'store_admin_id' => 'スコア購入を設定する店舗を選択',
        'score' => 'スコア金額を入力、0はこの選項が無効であることを意味します',
        'default_scores' => 'デフォルトスコア値を設定、デフォルト設定にリセットする時に使用',
    ],
    'error' => [
        'must_be_positive_integer' => ':field は正の整数でなければなりません',
    ],
];
