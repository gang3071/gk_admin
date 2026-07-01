<?php

namespace app\constants;

/**
 * 机台指令常量类
 *
 * 统一定义所有机台类型的指令常量
 * 替代原有的机台服务类常量定义
 */
class MachineCommand
{
    // ==================== 小淞斯洛机台 (SongSlot) ====================

    // 基础指令
    const SONG_SLOT_ALL = 'all';
    const SONG_SLOT_OPEN_ANY_POINT = 'afca';
    const SONG_SLOT_WASH_ZERO = 'afcc';
    const SONG_SLOT_TESTING = 'afc0';
    const SONG_SLOT_TESTING2 = 'afc6';

    // 读取指令
    const SONG_SLOT_READ_SCORE = 'afcbc5';
    const SONG_SLOT_READ_WIN = 'afcbc9';
    const SONG_SLOT_READ_BET = 'afcbc7';
    const SONG_SLOT_READ_STATUS = 'afcbc3';

    // 获取指令
    const SONG_SLOT_GET_SCORE = 'afc5';
    const SONG_SLOT_GET_WIN = 'afc9';
    const SONG_SLOT_GET_BET = 'afc7';
    const SONG_SLOT_GET_STATUS = 'afc3';

    // 控制指令
    const SONG_SLOT_REWARD_SWITCH = 'afceb8';
    const SONG_SLOT_CHECK = 'afcfb4';
    const SONG_SLOT_START = 'afceb2';
    const SONG_SLOT_OUT_ON = 'afceb6';
    const SONG_SLOT_OUT_OFF = 'afceb2';
    const SONG_SLOT_STOP_ONE = 'afceb3';
    const SONG_SLOT_STOP_TWO = 'afceb4';
    const SONG_SLOT_STOP_THREE = 'afceb5';
    const SONG_SLOT_MACHINE_OPEN = 'afcebe';
    const SONG_SLOT_MACHINE_CLOSE = 'afcebc';
    const SONG_SLOT_ALL_DOWN = 'afcfba';

    // ==================== 双美斯洛机台 (Slot) ====================

    // 基础指令
    const SLOT_ALL = 'all';
    const SLOT_OPEN_ANY_POINT = 25;
    const SLOT_WASH_SCORE = 28;
    const SLOT_TESTING = 0;
    const SLOT_TESTING2 = 246;

    // 读取指令
    const SLOT_READ_SCORE = 50;
    const SLOT_READ_WIN = 61;
    const SLOT_READ_BET = 60;
    const SLOT_READ_STATUS = 243;

    // 获取指令
    const SLOT_GET_SCORE = 5;
    const SLOT_GET_WIN = 9;
    const SLOT_GET_BET = 7;
    const SLOT_GET_STATUS = 3;

    // 移分控制
    const SLOT_MOVE_POINT_ON = 13;
    const SLOT_MOVE_POINT_OFF = 14;

    // 自动控制
    const SLOT_OUT_ON = 8;
    const SLOT_OUT_OFF = 9;

    // 停止控制
    const SLOT_STOP_ONE = 10;
    const SLOT_STOP_TWO = 11;
    const SLOT_STOP_THREE = 12;

    // 其他控制
    const SLOT_CHECK = 244;
    const SLOT_ALL_DOWN = 46;

    // ==================== 小淞彩金机台 (SongJackpot) ====================

    // 基础指令
    const SONG_JACKPOT_ALL = 'all';
    const SONG_JACKPOT_OPEN_ANY_POINT = 'ac16c1';
    const SONG_JACKPOT_MACHINE_POINT = 'ac11c2';
    const SONG_JACKPOT_MACHINE_TURN = 'ac11c3';
    const SONG_JACKPOT_MACHINE_SCORE = 'ac11c5';
    const SONG_JACKPOT_WIN_NUMBER = 'ac11c4';
    const SONG_JACKPOT_WASH_ZERO = 'ac17c4';
    const SONG_JACKPOT_CLEAR_LOG = 'ac17c7';
    const SONG_JACKPOT_TESTING = 'ac00c0';
    const SONG_JACKPOT_TESTING2 = 'ac00c1';

    // 控制指令
    const SONG_JACKPOT_AUTO_UP_TURN = 'ac12c1';
    const SONG_JACKPOT_PUSH = 'ac15';
    const SONG_JACKPOT_PUSH_START = 'c1';
    const SONG_JACKPOT_PUSH_STOP = 'c2';
    const SONG_JACKPOT_SCORE_TO_POINT = 'ac13c1';
    const SONG_JACKPOT_TURN_DOWN_ALL = 'ac14c1';
    const SONG_JACKPOT_CHECK = 'ac18c1';

    // ==================== 双美彩金机台 (Jackpot) ====================

    // 基础指令
    const JACKPOT_ALL = 'all';
    const JACKPOT_OPEN_ANY_POINT = 97;
    const JACKPOT_MACHINE_POINT = 66;
    const JACKPOT_MACHINE_TURN = 67;
    const JACKPOT_MACHINE_SCORE = 69;
    const JACKPOT_WIN_NUMBER = 68;
    const JACKPOT_WASH_ZERO = 100;
    const JACKPOT_CLEAR_LOG = 103;
    const JACKPOT_TESTING = 0;
    const JACKPOT_TESTING2 = 129;

    // 控制指令
    const JACKPOT_AUTO_UP_TURN = 73;
    const JACKPOT_PUSH = 89;
    const JACKPOT_PUSH_START = 90;
    const JACKPOT_PUSH_STOP = 91;
    const JACKPOT_SCORE_TO_POINT = 81;
    const JACKPOT_TURN_DOWN_ALL = 85;
    const JACKPOT_CHECK = 104;

    /**
     * 根据机台类型和控制类型获取对应的常量前缀
     *
     * @param int $machineType 机台类型 (GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL)
     * @param int $controlType 控制类型 (Machine::CONTROL_TYPE_MEI, Machine::CONTROL_TYPE_SONG)
     * @return string 常量前缀 (SLOT_, SONG_SLOT_, JACKPOT_, SONG_JACKPOT_)
     */
    public static function getPrefix(int $machineType, int $controlType): string
    {
        // GameType::TYPE_SLOT = 1, GameType::TYPE_STEEL_BALL = 2
        // Machine::CONTROL_TYPE_MEI = 1, Machine::CONTROL_TYPE_SONG = 2

        if ($machineType == 1 && $controlType == 1) {
            return 'SLOT_';
        } elseif ($machineType == 1 && $controlType == 2) {
            return 'SONG_SLOT_';
        } elseif ($machineType == 2 && $controlType == 1) {
            return 'JACKPOT_';
        } elseif ($machineType == 2 && $controlType == 2) {
            return 'SONG_JACKPOT_';
        }

        return 'SLOT_'; // 默认值
    }

    /**
     * 获取指令值
     *
     * @param int $machineType 机台类型
     * @param int $controlType 控制类型
     * @param string $command 指令名称 (如 'MOVE_POINT_ON', 'OPEN_ANY_POINT')
     * @return int|string 指令值
     */
    public static function get(int $machineType, int $controlType, string $command)
    {
        $prefix = self::getPrefix($machineType, $controlType);
        $constantName = 'self::' . $prefix . $command;

        if (defined($constantName)) {
            return constant($constantName);
        }

        throw new \Exception("机台指令常量不存在: {$constantName}");
    }
}
