<?php

use addons\webman\model\MachineRecording;

return [
    'title' => '視訊錄製清單',
    'fields' => [
        'id' => 'ID',
        'status' => '狀態',
        'type' => '類型',
        'start_time' => '開始錄製時間',
        'end_time' => '結束錄製時間',
    ],
    'status' => [
        MachineRecording::STATUS_STARTING => '錄製中',
        MachineRecording::STATUS_FAIL => '錄製失敗',
        MachineRecording::STATUS_COMPLETE => '錄製完成',
    ],
    'type' => [
        MachineRecording::TYPE_TEST => '測試',
        MachineRecording::TYPE_WASH => '洗分操作',
        MachineRecording::TYPE_OPEN => '開分操作',
        MachineRecording::TYPE_REWARD => '機台中獎',
    ],
];
