<?php

use addons\webman\model\MachineRecording;

return [
    'title' => '视讯录制列表',
    'fields' => [
        'id' => 'ID',
        'status' => '状态',
        'type' => '类型',
        'start_time' => '开始录制时间',
        'end_time' => '结束录制时间',
    ],
    'status' => [
        MachineRecording::STATUS_STARTING => '录制中',
        MachineRecording::STATUS_FAIL => '录制失败',
        MachineRecording::STATUS_COMPLETE => '录制完成',
    ],
    'type' => [
        MachineRecording::TYPE_TEST => '测试',
        MachineRecording::TYPE_WASH => '洗分操作',
        MachineRecording::TYPE_OPEN => '开分操作',
        MachineRecording::TYPE_REWARD => '机台中奖',
    ],
];
