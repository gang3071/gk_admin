<?php

return [
    'title' => 'オフライン機台管理',

    'fields' => [
        'code' => '機台番号',
        'name' => '機台名',
        'label' => '機台ラベル',
        'type' => '機台タイプ',
        'channel' => 'チャネル',
        'store' => '紐付け店舗',
        'ip' => '機台IP',
        'port' => '機台ポート',
        'domain' => '機台Domain',
        'control_type' => '制御タイプ',
        'status' => 'ステータス',
        'gaming' => 'ゲームステータス',
        'sort' => 'ソート',
        'remark' => '備考',
    ],

    'status' => [
        'unassigned' => '未割当',
        'unbound' => '未紐付け',
        'gaming' => 'ゲーム中',
        'idle' => 'アイドル',
    ],

    'error' => [
        'no_media_config' => 'オフライン機台はライブストリーム設定をサポートしていません',
        'not_offline_machine' => 'この機台はオフライン機台ではないため、編集できません',
    ],

    'command_test' => [
        'danger_command_title' => '危険コマンド警告',
        'danger_command_content' => '危険コマンドを実行しようとしています：{name}',
        'danger_command_desc' => 'コマンド説明：{desc}',
        'danger_command_confirm' => 'このコマンドを実行してもよろしいですか？',
        'cancel' => 'キャンセル',
        'confirm' => '実行',
        'success' => 'コマンド実行成功：{name}',
        'failed' => 'コマンド実行失敗：{msg}',
        'request_failed' => 'リクエスト失敗：{error}',
        'machine_status' => '機台ステータス',
        'player_status' => 'プレイヤーステータス',
        'current_data' => '現在のデータ',
        'idle' => 'アイドル',
        'gaming' => 'ゲーム中',
        'player_name' => 'プレイヤー',
        'refresh' => 'データ更新',
        'refreshing' => '更新中...',
        'refresh_success' => 'データ更新完了',
        'no_player' => 'プレイヤーなし',
    ],

    'one_way_verify' => [
        // 46ccb4 - 故障排除
        'fault_normal' => '機台は正常、故障なし',
        'fault_cleared' => '故障が解除されました（DA正常状態）',
        'fault_not_cleared' => '故障が解除されていません、機台が故障状態にあります、ハードウェアを確認してください',
        'fault_abnormal' => '状態異常：正常だったのに故障になりました',

        // 46ccb3 - 外部ボタンカウンタクリア
        'external_already_zero' => '外部ボタンカウンタは既に0です、クリア不要',
        'external_cleared' => '外部ボタンカウンタが0にクリアされました（B5={open}, B7={wash}）',
        'external_both_not_cleared' => 'B5とB7カウンタが共にクリアされていません（B5={open}, B7={wash}）',
        'external_open_not_cleared' => 'B5開分カウントがクリアされていません（現在={count}）',
        'external_wash_not_cleared' => 'B7洗分カウントがクリアされていません（現在={count}）',

        // 46ccba - ベット値クリア
        'score_already_zero' => 'ベット値は既に0です、クリア不要',
        'score_cleared' => 'ベット値が0にクリアされました',
        'score_not_cleared' => 'ベット値がクリアされていません（現在スコア={score}）',
    ],
];
