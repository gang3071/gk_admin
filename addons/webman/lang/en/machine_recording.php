<?php

use addons\webman\model\MachineRecording;

return [
    'title' => ' Video Recording List ',
    'fields' => [
        'id' => 'ID',
        'status' => 'state',
        'type' => 'type',
        'tart_time' => 'Start recording time ',
        'end_time' => 'End recording time',
    ],
    'status' => [
        MachineRecording::STATUS_STARTING => 'Recording in progress',
        MachineRecording::STATUS_FAIL => 'Recording failed',
        MachineRecording::STATUS_COMPLETE => 'Recording completed',
    ],
    'type' => [
        MachineRecording::TYPE_TEST => 'Test',
        MachineRecording::TYPE_WASH => 'Washing and Sorting Operation',
        MachineRecording::TYPE_OPEN => 'Split Operation',
        MachineRecording::TYPE_REWARD => 'Machine Winning',
    ],
];
