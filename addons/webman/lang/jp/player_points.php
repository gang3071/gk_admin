<?php

use addons\webman\model\PlayerPointsRecord;

return [
    'title' => 'プレイヤーポイント',
    'records_title' => 'ポイント記録',

    'fields' => [
        'id' => 'ID',
        'player_id' => 'プレイヤーID',
        'player_name' => 'プレイヤーアカウント',
        'total_points' => '総ポイント',
        'available_points' => '利用可能ポイント',
        'frozen_points' => '凍結ポイント',
        'used_points' => '使用済みポイント',
        'type' => 'タイプ',
        'source' => 'ソース',
        'points' => 'ポイント変動',
        'points_before' => '変動前',
        'points_after' => '変動後',
        'remark' => '備考',
        'admin_name' => '操作者',
        'admin_ip' => '操作者IP',
        'created_at' => '作成日時',
    ],

    'type' => [
        PlayerPointsRecord::TYPE_BETTING_SUMMARY => 'ベット集計',
        PlayerPointsRecord::TYPE_EXCHANGE => '交換消費',
        PlayerPointsRecord::TYPE_EXPIRE => '期限切れ',
        PlayerPointsRecord::TYPE_ADMIN_ADJUST => '管理者調整',
        PlayerPointsRecord::TYPE_ACTIVITY => 'アクティビティ報酬',
        PlayerPointsRecord::TYPE_REFUND => '返金',
    ],

    'action' => [
        'add_points' => 'ポイント追加',
        'deduct_points' => 'ポイント減算',
        'freeze_points' => 'ポイント凍結',
        'unfreeze_points' => 'ポイント凍結解除',
        'view_records' => 'ポイント記録',
    ],

    'form' => [
        'add_points_title' => 'ポイント追加',
        'deduct_points_title' => 'ポイント減算',
        'freeze_points_title' => 'ポイント凍結',
        'unfreeze_points_title' => 'ポイント凍結解除',
        'current_points' => '現在の利用可能ポイント',
        'current_frozen_points' => '現在の凍結ポイント',
        'points_amount' => 'ポイント数',
        'remark_placeholder' => '理由を入力してください',
    ],

    'message' => [
        'add_success' => 'ポイント追加成功',
        'add_failed' => 'ポイント追加失敗',
        'deduct_success' => 'ポイント減算成功',
        'deduct_failed' => 'ポイント減算失敗',
        'freeze_success' => 'ポイント凍結成功',
        'freeze_failed' => 'ポイント凍結失敗',
        'unfreeze_success' => 'ポイント凍結解除成功',
        'unfreeze_failed' => 'ポイント凍結解除失敗',
        'insufficient_points' => '利用可能ポイント不足',
        'insufficient_frozen_points' => '凍結ポイント不足',
        'player_not_found' => 'プレイヤーが見つかりません',
        'points_not_found' => 'ポイント記録が見つかりません',
        'invalid_points_amount' => 'ポイント数は0より大きくする必要があります',
        'permission_denied' => 'このプレイヤーのポイントデータへのアクセス権限がありません',
    ],

    'statistics' => [
        'available_points' => '利用可能ポイント',
        'frozen_points' => '凍結ポイント',
        'total_points' => '総ポイント',
        'player_account' => 'プレイヤーアカウント',
    ],

    'filter' => [
        'all_types' => 'すべてのタイプ',
        'select_type' => 'タイプを選択',
    ],
];
