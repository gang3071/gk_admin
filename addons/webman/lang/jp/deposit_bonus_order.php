<?php

return [
    'title' => '入金ボーナス注文管理',
    'generate_title' => '入金ボーナス注文生成',

    'fields' => [
        'id' => 'ID',
        'order_no' => '注文番号',
        'activity_id' => 'アクティビティ',
        'activity_name' => 'アクティビティ名',
        'player_id' => 'プレーヤー',
        'player_account' => 'プレーヤーアカウント',
        'player_info' => 'プレーヤー情報',
        'deposit_amount' => '入金金額',
        'bonus_amount' => 'ボーナス金額',
        'required_bet_amount' => '必要ベット量',
        'current_bet_amount' => '現在のベット量',
        'bet_progress' => 'ベット進捗',
        'status' => '注文ステータス',
        'qrcode_token' => 'QRコードトークン',
        'qrcode_expires_at' => 'QRコード有効期限',
        'expires_at' => '注文有効期限',
        'verified_at' => '検証日時',
        'completed_at' => '完了日時',
        'created_at' => '作成日時',
        'remark' => '備考',
    ],

    'help' => [
        'activity_id' => '参加するアクティビティを選択',
        'deposit_amount' => '入金金額を入力。アクティビティティアに一致する必要があります',
        'player_id' => 'プレーヤーアカウントを検索して選択',
    ],

    'status_pending' => '保留中',
    'status_verified' => '検証済み',
    'status_completed' => '完了',
    'status_expired' => '期限切れ',
    'status_cancelled' => 'キャンセル',
    'status_unknown' => '不明なステータス',

    'cannot_edit' => '注文は編集できません。追加のみ可能です',
    'activity_invalid' => 'アクティビティが存在しないか期限切れです',
    'tier_not_match' => '入金金額がどのアクティビティティアとも一致しません',
    'player_not_found' => 'プレーヤーが見つかりません',
    'player_limit_exceeded' => 'プレーヤーの参加制限を超えました',
    'generate_success' => '注文が正常に生成されました',
    'generate_fail' => '注文生成に失敗しました',

    'stats' => [
        'total_count' => '総注文数',
        'total_deposit' => '総入金',
        'total_bonus' => '総ボーナス',
        'completed_count' => '完了',
    ],
];
