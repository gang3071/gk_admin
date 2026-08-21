<?php

declare(strict_types=1);

namespace app\service\machine;

use addons\webman\model\Machine;
use Exception;
use support\Log;
use Psr\Log\LoggerInterface;

/**
 * 精灵球机台服务
 *
 * 协议格式：
 * 命令开头(0xFA 0xEA) | 命令类别(1B) | 具体命令(1B) | 数据长度(1B) | 数据域(NB) | 异或(1B) | 和(1B) | 结尾(0xFB 0xEB)
 *
 * 异或：命令类别开始到数据域的单字节异或
 * 和：命令类别开始到异或的单字节和
 * 波特率：115200
 *
 * 职责：
 * - 数据读取：从 Redis 读取机台实时状态（继承自 AbstractMachineService）
 * - 数据写入：将状态写入 Redis（继承自 AbstractMachineService）
 * - 指令发送：通过 MachineApiService 调用 gk_work（继承自 AbstractMachineService）
 * - 业务逻辑：精灵球特有的业务逻辑（彩金互动、关卡设置等）
 *
 * @property int $point 当前分数
 * @property int $score 当前得分
 * @property int $bet 本次压分
 * @property int $win 游戏结果
 * @property int $open_point 总上分
 * @property int $wash_point 总洗分
 * @property int $insert_money 总投入
 * @property int $gaming 游戏状态
 * @property int $gaming_user_id 游戏中玩家
 * @property int $keep_seconds 保留时长
 * @property int $keeping 保留状态
 * @property int $keeping_user_id 保留玩家
 * @property int $last_keep_at 最后保留时间
 * @property int $has_lock 机台锁
 * @property string $light_holes 亮灯洞口
 * @property string $fall_holes 落入洞口
 * @property int $jp_level 当前JP等级(1-5)
 * @property int $jackpot_score 彩金分数
 * @property int $ball_count 球数设置
 * @property int $light_count 灯数设置
 * @property int $game_type 游戏类型(1=A, 2=B)
 * @property string $multiplier 关卡倍数
 * @property string $uid 设备UID
 * @property string $version 主板版本号
 * @property int $door_status 开关门状态(1=开, 0=关)
 * @property int $game_enabled 游戏使能状态
 * @property int $last_point_at 最后上分时间
 * @property int $action_time 操作时间
 *
 * @package app\service\machine
 */
class PokemonBall extends AbstractMachineService implements BaseMachine
{
    // ==================== 帧格式常量 ====================
    const FRAME_START_1 = 0xFA;
    const FRAME_START_2 = 0xEA;
    const FRAME_END_1 = 0xFB;
    const FRAME_END_2 = 0xEB;

    // ==================== 命令类别 ====================
    const CMD_CATEGORY_SERVER = '01';   // 服务器/安卓 → 主板
    const CMD_CATEGORY_BOARD = '02';    // 主板 → 服务器/安卓
    const CMD_CATEGORY_RESPONSE = 'F1'; // 主板回复（服务器/安卓命令）
    const CMD_CATEGORY_ACK = 'F2';      // 安卓回复（主板上传命令）

    // ==================== 连接管理指令 ====================
    const CMD_CONNECT = '0101';         // 发起连接
    const CMD_HEARTBEAT = '0102';       // 联机心跳
    const CMD_GAME_ENABLE = '0103';     // 游戏使能
    const CMD_VERSION = '0104';         // 获取版本号

    // ==================== 主板上传指令（主板 → 服务器）====================
    const CMD_INSERT_MONEY = '0201';    // 投入金额
    const CMD_SCORE_UP = '0202';        // 上分金额
    const CMD_WASH_SCORE = '0203';      // 洗分金额
    const CMD_GAME_START = '0105';      // 游戏开始
    const CMD_BET_AMOUNT = '0106';      // 本次压分
    const CMD_LIGHT_HOLES = '0107';     // 亮灯洞口
    const CMD_FALL_HOLES = '0108';      // 落入洞口
    const CMD_GAME_RESULT = '0109';     // 游戏结果
    const CMD_BUTTON_SIGNAL = '010B';   // 按钮信号
    const CMD_ASK_TIME = '010D';        // 询问时间
    const CMD_DOOR_SWITCH = '010E';     // 开门微动
    const CMD_JP_ENTER = '0113';        // 主板上传进入JP1-JP5

    // ==================== 服务器下发指令（服务器 → 主板）====================
    const CMD_GAME_END = '010C';        // 本次游戏结束
    const CMD_CONFIRM_CLOSE = '010F';   // 确认关门
    const CMD_SCORE_UP_AMOUNT = '0110'; // 上分数量
    const CMD_SCORE_DOWN = '0111';      // 下分
    const CMD_ENTER_JP1 = '0112';       // 进入JP1
    const CMD_JACKPOT_SCORE = '0114';   // 彩金分数（高字节在前）
    const CMD_ADD_SUB_START = '0115';   // 加分/减分/启动
    const CMD_QUERY_UID = '0116';       // 查询UID
    const CMD_SET_BALL_LIGHT = '0121';  // 设置球数/游戏类型/灯数
    const CMD_SET_MULTIPLIER = '0122';  // 设置关卡倍数
    const CMD_ASK_ACCOUNT = '0123';     // 查询帐目

    // ==================== 操作指令常量（用于UI按钮）====================
    const ALL = 'all';                      // 机台状态
    const WASH_ZERO = 'wash_zero';          // 洗分&清零
    const OPEN_ANY_POINT = 'open_any';      // 开任意分
    const SCORE_UP = 'score_up';            // 上分
    const SCORE_DOWN = 'score_down';        // 下分
    const GAME_ENABLE_ON = 'game_on';       // 允许游戏
    const GAME_ENABLE_OFF = 'game_off';     // 禁止游戏
    const GAME_END = 'game_end';            // 游戏结束
    const ENTER_JP1 = 'enter_jp1';          // 进入JP1
    const SET_JACKPOT = 'set_jackpot';      // 设置彩金分数
    const QUERY_UID = 'query_uid';          // 查询UID
    const QUERY_ACCOUNT = 'query_account';  // 查询帐目
    const SET_MULTIPLIER = 'set_multiplier'; // 设置关卡倍数
    const ADD_SCORE = 'add_score';          // 加分
    const SUB_SCORE = 'sub_score';          // 减分
    const START_GAME = 'start_game';        // 启动
    const AUTO_START = 'auto_start';        // 自动启动
    const SET_BALL_COUNT = 'set_ball';      // 设置球数
    const SET_LIGHT_COUNT = 'set_light';    // 设置灯数

    // ==================== 加分/减分/启动子命令 ====================
    const ADD_SUB_ADD = 1;      // 加分
    const ADD_SUB_SUB = 2;      // 减分
    const ADD_SUB_START = 3;    // 启动
    const ADD_SUB_AUTO = 4;     // 自动启动

    // ==================== 游戏使能值 ====================
    const GAME_ENABLE_VALUE = 0x01;     // 可进行游戏
    const GAME_DISABLE_VALUE = 0x00;    // 不可进行游戏

    // ==================== 兼容性常量（用于MachineController）====================
    // 这些常量是为了兼容 Slot/Jackpot 的操作接口
    const MOVE_POINT_ON = 'add_score';      // 移分ON -> 加分
    const MOVE_POINT_OFF = 'sub_score';     // 移分OFF -> 减分
    const PRESSURE = 'query_account';       // 压分 -> 查询帐目
    const START = 'start_game';             // 启动
    const OUT_ON = 'auto_start';            // 自动ON -> 自动启动
    const OUT_OFF = 'game_end';             // 自动OFF -> 游戏结束
    const STOP_ONE = 'start_game';          // 停1 -> 启动
    const STOP_TWO = 'start_game';          // 停2 -> 启动
    const STOP_THREE = 'start_game';        // 停3 -> 启动
    const GET_AUTO_STATUS = 'query_account'; // 获取自动状态 -> 查询帐目
    const WASH_POINT = 'wash_zero';         // 洗分 -> 洗分清零

    /**
     * 构造函数
     *
     * @param Machine $machine 机台对象
     * @param string $lang 语言代码
     */
    public function __construct(Machine $machine, string $lang = 'zh_CN')
    {
        parent::__construct($machine, $lang);
    }

    /**
     * 初始化缓存 Key 数组
     * 实现父类抽象方法
     */
    protected function initializeCacheKeys(): void
    {
        $this->cacheDataKeyArr = [
            $this->cacheDataKey . '_point',
            $this->cacheDataKey . '_score',
            $this->cacheDataKey . '_bet',
            $this->cacheDataKey . '_win',
            $this->cacheDataKey . '_open_point',
            $this->cacheDataKey . '_wash_point',
            $this->cacheDataKey . '_insert_money',
            $this->cacheDataKey . '_gaming',
            $this->cacheDataKey . '_gaming_user_id',
            $this->cacheDataKey . '_keep_seconds',
            $this->cacheDataKey . '_keeping',
            $this->cacheDataKey . '_keeping_user_id',
            $this->cacheDataKey . '_last_keep_at',
            $this->cacheDataKey . '_has_lock',
            $this->cacheDataKey . '_light_holes',
            $this->cacheDataKey . '_fall_holes',
            $this->cacheDataKey . '_jp_level',
            $this->cacheDataKey . '_jackpot_score',
            $this->cacheDataKey . '_ball_count',
            $this->cacheDataKey . '_light_count',
            $this->cacheDataKey . '_game_type',
            $this->cacheDataKey . '_multiplier',
            $this->cacheDataKey . '_uid',
            $this->cacheDataKey . '_version',
            $this->cacheDataKey . '_door_status',
            $this->cacheDataKey . '_game_enabled',
            $this->cacheDataKey . '_last_point_at',
            $this->cacheDataKey . '_action_time',
        ];
    }

    /**
     * 初始化机台信息字段列表
     * 实现父类抽象方法
     */
    protected function initializeMachineInfo(): void
    {
        $this->machineInfo = [
            'point',
            'score',
            'bet',
            'win',
            'gaming',
            'has_lock',
            'jp_level',
            'jackpot_score',
            'game_enabled',
            'door_status',
        ];
    }

    /**
     * 构建协议帧
     *
     * 帧格式：0xFA 0xEA | 命令类别 | 具体命令 | 数据长度 | 数据域 | 异或 | 和 | 0xFB 0xEB
     *
     * @param string $cmdCategory 命令类别（2字符hex）
     * @param string $cmd 具体命令（2字符hex）
     * @param string $dataHex 数据域（hex字符串，可为空）
     * @return string 完整帧的hex字符串
     */
    public static function buildFrame(string $cmdCategory, string $cmd, string $dataHex = ''): string
    {
        // 命令类别 + 具体命令 + 数据长度 + 数据域
        $dataLen = strlen($dataHex) / 2;
        $dataLenHex = sprintf('%02X', $dataLen);

        // 计算异或：命令类别开始到数据域的单字节异或
        $xor = hexdec($cmdCategory);
        $xor ^= hexdec($cmd);
        $xor ^= hexdec($dataLenHex);

        // 数据域逐字节异或
        if (!empty($dataHex)) {
            for ($i = 0; $i < strlen($dataHex); $i += 2) {
                $xor ^= hexdec(substr($dataHex, $i, 2));
            }
        }

        $xorHex = sprintf('%02X', $xor & 0xFF);

        // 计算和：命令类别开始到异或的单字节和
        $sum = hexdec($cmdCategory);
        $sum += hexdec($cmd);
        $sum += hexdec($dataLenHex);

        if (!empty($dataHex)) {
            for ($i = 0; $i < strlen($dataHex); $i += 2) {
                $sum += hexdec(substr($dataHex, $i, 2));
            }
        }
        $sum += hexdec($xorHex);

        $sumHex = sprintf('%02X', $sum & 0xFF);

        // 组装完整帧
        $frame = sprintf('FAEA%s%s%s%s%s%sFBEB',
            $cmdCategory,
            $cmd,
            $dataLenHex,
            $dataHex,
            $xorHex,
            $sumHex
        );

        return strtoupper($frame);
    }

    /**
     * 构建带唯一码的协议帧（无安卓版时使用）
     *
     * @param string $uid 4字节唯一码（8字符hex）
     * @param string $cmdCategory 命令类别
     * @param string $cmd 具体命令
     * @param string $dataHex 数据域
     * @return string 完整帧的hex字符串
     */
    public static function buildFrameWithUid(string $uid, string $cmdCategory, string $cmd, string $dataHex = ''): string
    {
        $frame = self::buildFrame($cmdCategory, $cmd, $dataHex);
        // 在帧开头（FAEA之后）插入唯一码
        return substr($frame, 0, 4) . $uid . substr($frame, 4);
    }

    /**
     * 发送指令到机台
     *
     * @param string $cmd 指令代码（操作常量）
     * @param int $data 数据
     * @param string $source 操作来源 (player/admin)
     * @param int $source_id 来源ID
     * @return bool
     * @throws Exception
     */
    public function sendCmd(
        string $cmd,
        int $data = 0,
        string $source = 'admin',
        int $source_id = 0
    ): bool {
        // 将操作常量转换为协议帧
        $frame = $this->buildCommandFrame($cmd, $data);

        if ($frame === null) {
            $this->log->warning('未知的精灵球操作指令', [
                'machine_id' => $this->machine->id,
                'cmd' => $cmd,
                'data' => $data,
            ]);
            return false;
        }

        try {
            $adminId = $source === 'admin' ? $source_id : 0;

            $result = \app\service\MachineApiService::sendCmd(
                $this->machine->id,
                $frame,
                $data,
                $adminId,
                $this->lang
            );

            $operatorType = $source === 'admin' ? '【管理员操作】' : '【玩家操作】';
            $this->log->info($operatorType . '精灵球指令', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'frame' => $frame,
                'data' => $data,
                'source' => $source,
                'source_id' => $source_id,
                'result' => $result,
            ]);

            return true;

        } catch (Exception $e) {
            $this->log->error('精灵球指令发送失败', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'frame' => $frame ?? '',
                'data' => $data,
                'source' => $source,
                'source_id' => $source_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 将操作常量转换为协议帧
     *
     * @param string $cmd 操作常量
     * @param int $data 数据
     * @return string|null 协议帧hex字符串，未知指令返回null
     */
    protected function buildCommandFrame(string $cmd, int $data = 0): ?string
    {
        switch ($cmd) {
            case self::ALL:
                // 查询机台状态（查询帐目）
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '23');

            case self::WASH_ZERO:
                // 洗分&清零（下分指令）
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '11');

            case self::OPEN_ANY_POINT:
                // 开任意分（上分数量，数据为分数值）
                $dataHex = sprintf('%06X', $data); // 3字节，高字节在前
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '10', $dataHex);

            case self::SCORE_UP:
                // 上分
                $dataHex = sprintf('%06X', $data);
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '10', $dataHex);

            case self::SCORE_DOWN:
                // 下分
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '11');

            case self::GAME_ENABLE_ON:
                // 允许游戏
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '03', '01');

            case self::GAME_ENABLE_OFF:
                // 禁止游戏
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '03', '00');

            case self::GAME_END:
                // 游戏结束
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '0C');

            case self::ENTER_JP1:
                // 进入JP1
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '12');

            case self::SET_JACKPOT:
                // 设置彩金分数（高字节在前，3字节）
                $dataHex = sprintf('%06X', $data);
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '14', $dataHex);

            case self::QUERY_UID:
                // 查询UID
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '16');

            case self::QUERY_ACCOUNT:
                // 查询帐目
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '23');

            case self::SET_MULTIPLIER:
                // 设置关卡倍数（10字节数据，每关2字节）
                // data 为倍数数组的编码值，需要外部传入正确的hex
                $dataHex = sprintf('%020X', $data); // 10字节
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '22', $dataHex);

            case self::ADD_SCORE:
                // 加分
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '15', sprintf('%02X', self::ADD_SUB_ADD));

            case self::SUB_SCORE:
                // 减分
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '15', sprintf('%02X', self::ADD_SUB_SUB));

            case self::START_GAME:
                // 启动
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '15', sprintf('%02X', self::ADD_SUB_START));

            case self::AUTO_START:
                // 自动启动
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '15', sprintf('%02X', self::ADD_SUB_AUTO));

            case self::SET_BALL_COUNT:
                // 设置球数（data: 1-3个球）
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '21', sprintf('%02X', $data));

            case self::SET_LIGHT_COUNT:
                // 设置灯数
                return self::buildFrame(self::CMD_CATEGORY_SERVER, '21', sprintf('%02X', $data));

            default:
                return null;
        }
    }

    /**
     * 处理字段更新后的推送逻辑
     *
     * @param string $name 字段名
     * @param mixed $value 字段值
     * @return void
     */
    protected function handleFieldUpdatePush(string $name, mixed $value): void
    {
        // 精灵球特有的推送逻辑
        try {
            $pushData = match ($name) {
                'gaming', 'gaming_user_id' => [
                    'type' => 'gaming_status',
                    'machine_id' => $this->machine->id,
                    'gaming' => $this->gaming,
                    'gaming_user_id' => $this->gaming_user_id,
                ],
                'jp_level' => [
                    'type' => 'jp_change',
                    'machine_id' => $this->machine->id,
                    'jp_level' => $value,
                ],
                'jackpot_score' => [
                    'type' => 'jackpot_update',
                    'machine_id' => $this->machine->id,
                    'jackpot_score' => $value,
                ],
                'has_lock' => [
                    'type' => 'lock_change',
                    'machine_id' => $this->machine->id,
                    'has_lock' => $value,
                ],
                default => null,
            };

            if ($pushData !== null) {
                sendPushMsg($this->machine, $this->machineInfo, $this->lang);
            }
        } catch (Exception $e) {
            $this->log->warning('精灵球推送失败', [
                'machine_id' => $this->machine->id,
                'field' => $name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
