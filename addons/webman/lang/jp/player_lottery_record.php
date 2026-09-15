<?php

use addons\webman\model\PlayerLotteryRecord;

return [
    'title' => 'ボーナスを受け取る',
    'audit_title' => 'ボーナスレビュー',
    'fields' => [
        'id' => '番号',
        'uuid' => 'プレイヤー uuid',
        'player_phone' => 'プレイヤーの電話番号',
        'department_id' => 'チャンネル',
        'machine_name' => 'マシン名',
        'machine_code' => 'マシン番号',
        'odds' => '機械比率',
        'lottery_name' => '宝くじ名',
        'amount' => '支払額',
        'lottery_pool_amount' => '宝くじプール金額',
        'lottery_rate' => '金額比率',
        'cate_rate' => 'ペイアウト係数',
        'reject_reason' => '拒否理由',
        'user_name' => 'レビュー担当者',
        'status' => '状态',
        'created_at' => '作成時刻',
        'audit_at' => '監査時間',
        'source' => 'ソース',
        'distribute_type' => '配布タイプ',
        'pokemon_ball_machine_id' => 'ポケモンボールマシン',
    ],
    'double' => 'ダブル',
    'btn' => [
        'action' => 'アクション',
        'examine_pass' => '試験に合格しました',
        'examine_reject' => '審査拒否',
        'examine_pass_confirm' => '確認して承認ボタンをクリックしてください。審査に合格すると、システムが自動的に請求メッセージを送信します。',
        'examine_reject_confirm' => '審査拒否、審査拒否をクリックした後、プレイヤーはボーナスポイントを獲得できなくなります',
        'retry_pokemon_ball' => '再配布',
        'retry_pokemon_ball_confirm' => 'ポケモンボールの配布を再試行しますか？システムがプレイヤーに通知を再送信します。',
    ],
    'lottery_record_error' => '宝くじコレクション レコード タイプ エラー',
    'lottery_record_has_pass' => '宝くじの回収記録が審査に合格しました',
    'lottery_record_has_reject' => '宝くじの記録は拒否されました',
    'lottery_record_has_complete' => 'プレイヤーは受け取りました',
    'action_error' => '操作は失敗しました',
    'action_success' => 'アクションは成功しました',
    'retry_success' => '再配布が成功しました。プレイヤーに通知を送信しました',
    'not_fount' => 'ボーナスコレクションレコードが存在しません',
    'max_amount' => '最高',
    'bath_error' => 'レコード番号: ',
    'bath_not_found' => '操作項目が見つかりません',
    'bath_action' => 'バッチ操作',
    'source' => [
        PlayerLotteryRecord::SOURCE_MACHINE => 'アーケードゲーム機',
        PlayerLotteryRecord::SOURCE_GAME => 'ビデオゲーム',
        PlayerLotteryRecord::SOURCE_MANUAL => '手動配布',
    ],
    'distribute_type' => [
        0 => '通常配布',
        1 => 'ポケモンボール配布',
    ],

    // お知らせメッセージ
    'notice' => [
        'title' => 'ジャックポット配当',
        'content_machine' => 'おめでとうございます！{machine_type}マシン{machine_code}で{lottery_name}ジャックポット報酬を獲得しました',
    ],

    // マシンタイプ
    'machine_type' => [
        'slot' => 'スロット',
        'steel_ball' => 'スチールボール',
    ],
    'status' => [
        PlayerLotteryRecord::STATUS_UNREVIEWED => '未レビュー',
        PlayerLotteryRecord::STATUS_REJECT => '拒否されました',
        PlayerLotteryRecord::STATUS_PASS => '合格',
        PlayerLotteryRecord::STATUS_COMPLETE => '受信',
        PlayerLotteryRecord::STATUS_POKEMON_BALL_FAILED => '配布失敗',
    ],
    'total_data' => [
        'total_unreviewed_amount' => '未検討',
        'total_reject_amount' => '拒否されました',
        'total_pass_amount' => '合格',
        'total_complete_amount' => '受領済み',
        'total_count' => 'レコード総数',
    ],
    'notice' => [
        'lottery_payout_title' => 'ジャックポット配当',
        'lottery_payout_content' => 'おめでとうございます。{machine_type}マシン{machine_code}で{lottery_name}ジャックポット報酬を獲得しました',
    ],
    'machine_type' => [
        'slot' => 'スロット',
        'steel_ball' => 'パチンコ',
    ],
];
