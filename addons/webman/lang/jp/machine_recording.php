<?php

use addons\webman\model\MachineRecording;

return [
    'title' => 'ビデオ録画リスト',
    'fields' => [
        'id' => 'ID',
        'status' => '状態',
        'type' => 'タイプ',
        'start_time' => '録画開始時間',
        'end_time' => '録画終了時間',
    ],
    'status' => [
        MachineRecording::STATUS_STARTING => '録画中',
        MachineRecording::STATUS_FAIL => '録画に失敗しました',
        MachineRecording::STATUS_COMPLETE => '録画完了',
    ],
    'type' => [
        MachineRecording::TYPE_TEST => 'テスト',
        MachineRecording::TYPE_WASH => '洗分操作',
        MachineRecording::TYPE_OPEN => '開分操作',
        MachineRecording::TYPE_REWARD => 'テーブル当選',
    ],
];
