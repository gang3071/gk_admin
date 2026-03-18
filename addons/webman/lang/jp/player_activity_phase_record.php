<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityPhaseRecord;

return [
    'title' => 'プレイヤーアクティビティ参加記録',
    'examine_title' => '報酬コレクションのレビュー',
    'receive_title' => 'アクティビティ受信記録',
    'fields' => [
        'id' => 'ID',
        'fields' => 'ステータス',
        'condition' => '受け取り条件',
        'bonus' => 'アクティビティ報酬',
        'player_score' => 'プレイヤーの記録',
        'user_name' => 'レビュー担当者',
        'reject_reason' => '拒否理由',
        'created_at' => '達成時間',
        'updated_at' => '更新（レビュー）時刻',
    ],
    
    'status' => [
        PlayerActivityPhaseRecord::STATUS_UNRECEIVED => '未受信',
        PlayerActivityPhaseRecord::STATUS_RECEIVED => '受信済み (レビュー保留)',
        PlayerActivityPhaseRecord::STATUS_COMPLETE => '発行済み (承認済み)',
        PlayerActivityPhaseRecord::STATUS_REJECT => '拒否されました',
    ],
    'created_at_start' => '開始時刻',
    'created_at_end' => '終了時刻',
    'player_activity_phase_record' => 'プレーヤーはレコードを受信します',
    'record_unreceived' => 'プレーヤーはまだ受信していません',
    'record_complete' => '報酬は発行されました',
    'record_reject' => '拒否されました',
    'not_fount' => '領収書レコードが見つかりません',
    'action_error' => '操作は失敗しました',
    'action_success' => 'アクションは成功しました',
    'bath_error' => 'レコード番号: ',
    'bath_not_found' => '操作項目が見つかりません',
    'bath_action' => 'バッチ操作',
    'btn' => [
        'action' => 'アクション',
        'examine_pass' => '試験に合格しました',
        'examine_reject' => '審査拒否',
        'examine_pass_confirm' => '確認してレビューボタンをクリックしてください。レビューに合格すると、システムが自動的にゲームポイントを発行します。',
        'examine_reject_confirm' => '審査拒否。審査拒否をクリックすると、プレイヤーはイベント報酬ポイントを獲得できなくなります',
    ],
];
