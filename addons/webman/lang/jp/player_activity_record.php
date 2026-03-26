<?php

/** TODO 翻译 */

use addons\webman\model\PlayerActivityRecord;

return [
    'title' => 'プレイヤーアクティビティ参加記録',
    'fields' => [
        'id' => 'ID',
        'status' => 'ステータス',
        'score' => '現在の成績',
        'finish_at' => '終了時刻',
        'created_at' => 'パラメータ時間',
    ],
    'status' => [
        PlayerActivityRecord::STATUS_FINISH => '終了',
        PlayerActivityRecord::STATUS_BEGIN => '進行中',
    ],
    'created_at_start' => '開始時刻',
    'created_at_end' => '終了時刻',
    'player_activity_phase_record' => 'プレーヤーはレコードを受信します',
    'notice' => [
        'activity_pass_title' => 'アクティビティ報酬監査に合格しました',
        'activity_pass_content_with_machine' => 'おめでとうございます。マシン{machine_code}のアクティビティ報酬監査が承認されました。ゲームポイント報酬 {bonus}',
        'activity_pass_content' => 'おめでとうございます。アクティビティ報酬監査が承認されました。ゲームポイント報酬 {bonus}',
        'activity_reject_title' => 'アクティビティ報酬監査に失敗しました',
        'activity_reject_content' => '申し訳ございませんが、アクティビティ報酬監査に失敗しました。理由は次のとおりです: {reason}',
    ],
];
