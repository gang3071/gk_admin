<?php

namespace app\service\machine;

use addons\webman\model\Machine;
use Exception;
use support\Log;

/**
 * Slot 机台服务
 *
 * 职责：
 * - 数据读取：从 Redis 读取机台实时状态（继承自 AbstractMachineService）
 * - 数据写入：将状态写入 Redis（继承自 AbstractMachineService）
 * - 指令发送：通过 MachineApiService 调用 gk_work（继承自 AbstractMachineService）
 * - 业务逻辑：Slot 特有的业务逻辑（开分、洗分等）
 *
 * 优化特性：
 * - 内存缓存：减少 Redis 访问（1秒TTL）
 * - 批量读取：一次性加载所有字段
 * - 自动重试：Redis 操作失败自动重试
 * - 统一指令发送：通过 gk_work 统一通信
 *
 * @property int $auto 自动状态
 * @property int $move_point 移分状态
 * @property int $reward_status 开奖状态
 * @property int $rb_status rb状态
 * @property int $bb_status bb状态
 * @property int $play_start_time 开始游戏时间
 * @property int $gaming_user_id 游戏中玩家
 * @property int $gaming 是否游戏中
 * @property int $point 当前分数
 * @property int $score 当前得分
 * @property int $bet 机台压分
 * @property int $last_play_time 最后游戏时间
 * @property int $win 机台总得分
 * @property int $bb bb
 * @property int $rb rb
 * @property int $open_point 开分次数
 * @property int $wash_point 洗分次数
 * @property int $keep_seconds 保留时长
 * @property int $keeping 保留状态
 * @property int $keeping_user_id 保留玩家
 * @property int $last_keep_at 最后保留时间
 * @property int $player_pressure 玩家進入時原始壓分
 * @property int $player_score 玩家進入時原始得分
 * @property int $player_open_point 玩家开分
 * @property int $player_wash_point 玩家洗分
 * @property int $last_point_at 玩家最后上下分时间
 * @property int $action_time 操作时间
 * @property int $change_point_card_status 开分卡状态
 * @property int $gift_bet 玩家开分增点时押注
 * @property int $gift_condition 增点完成条件
 * @property int $now_turn 当前转数
 * @property int $has_lock 机台锁
 *
 * @package app\service\machine
 */
class Slot extends AbstractMachineService implements BaseMachine
{
    const PREFIX = 'A2'; //前缀
    
    const ALL = 'all'; //机台状态
    const OPEN_ONE = '41'; //开分一次
    const OPEN_TEN = '42'; //开分10次
    const WASH_ZERO = '43'; //洗分&清零
    const WASH_POINT = '44'; //洗分
    const MOVE_POINT_ON = '45'; //移分 長ON
    const MOVE_POINT_OFF = '46'; //移分 OFF
    const ALL_DOWN = '47'; //清除 BET WIN BB RB
    const OPEN_FIVE = '49'; //開分*5
    const OPEN_ANY_POINT = '4A'; //开任意数
    const REWARD_SWITCH = '2D'; //大賞燈切換
    const REWARD_SWITCH_OPT = '64'; // 大赏灯操作
    const MACHINE_BUSY = '1F'; // 忙碌中
    
    const OUTPUT = '4B'; //
    const ALL_OFF = '00'; //全OFF
    const U1_ON = '01'; //U1 ON
    const U2_ON = '02'; //U2 ON
    const U3_ON = '03'; //U3 ON
    const U4_ON = '04'; //U4 ON
    const U5_ON = '05'; //U5 ON
    const U6_ON = '06'; //U6 ON
    const U7_ON = '07'; //U7 ON
    const U8_ON = '08'; //U8 ON
    const U1_PULSE = '21'; //U1 PULSE
    const U2_PULSE = '22'; //U2 PULSE
    const U3_PULSE = '23'; //U3 PULSE
    const U4_PULSE = '24'; //U4 PULSE
    const U5_PULSE = '25'; //U5 PULSE
    const U6_PULSE = '26'; //U6 PULSE
    const U7_PULSE = '27'; //U7 PULSE
    const U8_PULSE = '28'; //U8 PULSE
    
    const OPEN_TESTING = '20'; //开分卡测试
    const READ_SCORE = '21'; //读取开分卡分数
    const READ_CREDIT2 = '22'; //讀取CREDIT2
    const READ_BET = '23'; //读取 BET
    const READ_WIN = '24'; //读取 WIN
    const READ_BB = '25'; //读取 BB
    const READ_RB = '26'; //读取 RB
    const OPEN_TABLE = '27'; //讀取 開分錶
    const WASH_TABLE = '28'; //請取 洗分錶
    const INSERT_COIN_TABLE = '29'; //讀取 投幣錶
    const OUT_COIN_TABLE = '2A'; //讀取 退幣錶
    const ALL_UP = '4C'; //全部上转
    //自动卡
    const OUT_ON = 'AA5708000001150D'; //启动自动
    const OUT_OFF = 'AA5708000002F70D'; //停止自动
    const PRESSURE = 'AA5708000003A90D'; //押分
    const START = 'AA57080000042A0D'; //启动
    const STOP_ONE = 'AA5708000005740D'; //停1
    const STOP_TWO = 'AA5708000006960D'; //停2
    const STOP_THREE = 'AA5708000007C80D'; //停3
    const TESTING = 'AA57080000004B0D'; //测试连接
    const GET_AUTO_STATUS = 'AA52082000000D0D'; //获取自动状态
    const AUTO_START = 'AA5208200081DF0D'; //开启自动
    const AUTO_STOP = 'AA5208200080810D'; //停止自动
    const AUTO = 'AA520820'; //自动状态
    
    const TYPE_OPEN_CARD = 1; //开分卡命令
    const TYPE_OUT_CARD = 2; //自动卡

    // ✅ cacheData, expirationTime, log 已在基类 AbstractMachineService 中定义

    /**
     * 构造函数
     *
     * @param Machine $machine 机台对象
     * @param string $lang 语言代码
     */
    public function __construct(Machine $machine, $lang = 'zh_CN')
    {
        // 调用父类构造函数（父类会调用 initializeCacheKeys 和 initializeMachineInfo）
        parent::__construct($machine, $lang);
    }

    /**
     * 初始化缓存 Key 数组
     * 实现父类抽象方法
     */
    protected function initializeCacheKeys(): void
    {
        $this->cacheDataKeyArr = [
            $this->cacheDataKey . '_auto',
            $this->cacheDataKey . '_move_point',
            $this->cacheDataKey . '_reward_status',
            $this->cacheDataKey . '_play_start_time',
            $this->cacheDataKey . '_gaming_user_id',
            $this->cacheDataKey . '_gaming',
            $this->cacheDataKey . '_point',
            $this->cacheDataKey . '_score',
            $this->cacheDataKey . '_bet',
            $this->cacheDataKey . '_last_play_time',
            $this->cacheDataKey . '_win',
            $this->cacheDataKey . '_bb',
            $this->cacheDataKey . '_rb',
            $this->cacheDataKey . '_open_point',
            $this->cacheDataKey . '_wash_point',
            $this->cacheDataKey . '_keep_seconds',
            $this->cacheDataKey . '_keeping',
            $this->cacheDataKey . '_keeping_user_id',
            $this->cacheDataKey . '_last_keep_at',
            $this->cacheDataKey . '_player_pressure',
            $this->cacheDataKey . '_player_score',
            $this->cacheDataKey . '_player_open_point',
            $this->cacheDataKey . '_player_wash_point',
            $this->cacheDataKey . '_last_point_at',
            $this->cacheDataKey . '_action_time',
            $this->cacheDataKey . '_change_point_card_status',
            $this->cacheDataKey . '_gift_bet',
            $this->cacheDataKey . '_gift_condition',
            $this->cacheDataKey . '_now_turn',
            $this->cacheDataKey . '_rb_status',
            $this->cacheDataKey . '_bb_status',
            $this->cacheDataKey . '_has_lock',
        ];
    }

    /**
     * 初始化机台信息字段列表
     * 实现父类抽象方法
     */
    protected function initializeMachineInfo(): void
    {
        $this->machineInfo = [
            'auto',
            'move_point',
            'reward_status',
            'bet',
            'win',
            'bb',
            'rb',
            'rb_status',
            'bb_status',
            'has_lock',
        ];
    }

    /**
     * 初始化日志实例
     * 覆盖父类方法以使用 Slot 专用日志通道
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function initializeLogger(): \Psr\Log\LoggerInterface
    {
        return Log::channel('slot_machine');
    }
    
    // ✅ __get 方法已删除 - 使用 AbstractMachineService 的优化实现
    // 基类提供：内存缓存、批量读取、自动重试
    
    // ✅ __set 方法已删除 - 使用 AbstractMachineService 的优化实现
    // 基类提供：自动重试、关键字段告警、统一推送
    // Slot 特定推送逻辑已移至 handleFieldUpdatePush()

    /**
     * 处理字段更新后的推送逻辑
     * 覆盖基类方法以实现 Slot 特定的推送逻辑
     *
     * @param string $name 字段名
     * @param mixed $value 字段值
     * @return void
     */
    protected function handleFieldUpdatePush(string $name, mixed $value): void
    {
        try {
            $machineCacheInfo = $this->getAllData() ?? [];
            if (empty($machineCacheInfo)) {
                return;
            }

            // 构建机台信息
            $info = [
                'id' => $this->machine->id,
                'last_game_at' => $this->machine->last_game_at,
                'odds_x' => $this->machine->odds_x,
                'odds_y' => $this->machine->odds_y,
                'type' => $this->machine->type,
                'gaming_user_id' => $this->machine->gaming_user_id,
                'gaming' => $this->machine->gaming,
                'auto' => $machineCacheInfo[$this->cacheDataKey . '_auto'] ?? 0,
                'move_point' => $machineCacheInfo[$this->cacheDataKey . '_move_point'] ?? 0,
                'reward_status' => $machineCacheInfo[$this->cacheDataKey . '_reward_status'] ?? 0,
                'play_start_time' => $machineCacheInfo[$this->cacheDataKey . '_play_start_time'] ?? 0,
                'point' => $machineCacheInfo[$this->cacheDataKey . '_point'] ?? 0,
                'score' => $machineCacheInfo[$this->cacheDataKey . '_score'] ?? 0,
                'bet' => $machineCacheInfo[$this->cacheDataKey . '_bet'] ?? 0,
                'last_play_time' => $machineCacheInfo[$this->cacheDataKey . '_last_play_time'] ?? 0,
                'win' => $machineCacheInfo[$this->cacheDataKey . '_win'] ?? 0,
                'bb' => $machineCacheInfo[$this->cacheDataKey . '_bb'] ?? 0,
                'rb' => $machineCacheInfo[$this->cacheDataKey . '_rb'] ?? 0,
                'open_point' => $machineCacheInfo[$this->cacheDataKey . '_open_point'] ?? 0,
                'wash_point' => $machineCacheInfo[$this->cacheDataKey . '_wash_point'] ?? 0,
                'keep_seconds' => $machineCacheInfo[$this->cacheDataKey . '_keep_seconds'] ?? 0,
                'keeping' => $machineCacheInfo[$this->cacheDataKey . '_keeping'] ?? 0,
                'keeping_user_id' => $machineCacheInfo[$this->cacheDataKey . '_keeping_user_id'] ?? 0,
                'last_keep_at' => $machineCacheInfo[$this->cacheDataKey . '_last_keep_at'] ?? 0,
                'player_pressure' => $machineCacheInfo[$this->cacheDataKey . '_player_pressure'] ?? 0,
                'player_score' => $machineCacheInfo[$this->cacheDataKey . '_player_score'] ?? 0,
                'player_open_point' => $machineCacheInfo[$this->cacheDataKey . '_player_open_point'] ?? 0,
                'player_wash_point' => $machineCacheInfo[$this->cacheDataKey . '_player_wash_point'] ?? 0,
                'last_point_at' => $machineCacheInfo[$this->cacheDataKey . '_last_point_at'] ?? 0,
                'action_time' => $machineCacheInfo[$this->cacheDataKey . '_action_time'] ?? 0,
                'change_point_card_status' => $machineCacheInfo[$this->cacheDataKey . '_change_point_card_status'] ?? 0,
                'now_turn' => $machineCacheInfo[$this->cacheDataKey . '_now_turn'] ?? 0,
                'rb_status' => $machineCacheInfo[$this->cacheDataKey . '_rb_status'] ?? 0,
                'bb_status' => $machineCacheInfo[$this->cacheDataKey . '_bb_status'] ?? 0,
                'has_lock' => $machineCacheInfo[$this->cacheDataKey . '_has_lock'] ?? 0,
            ];

            // 根据字段类型发送不同的实时消息
            switch ($name) {
                case 'gaming_user_id':
                    // 游戏开始推送
                    if (!empty($this->machine->gamingPlayer)) {
                        $this->sendMachineRealTimeInformation(
                            $this->machine->gamingPlayer->department_id,
                            'game_start',
                            $info
                        );
                    }
                    break;

                case 'auto':
                case 'move_point':
                case 'reward_status':
                case 'bet':
                case 'last_point_at':
                case 'wash_point':
                case 'keep_seconds':
                case 'has_lock':
                    // 游戏信息变化推送
                    if (!empty($this->machine->gamingPlayer)) {
                        $this->sendMachineRealTimeInformation(
                            $this->machine->gamingPlayer->department_id,
                            'game_info_change',
                            $info
                        );
                    }
                    break;
            }

            // 发送当前机台信息消息
            if (in_array($name, $this->machineInfo) && !empty($this->machine->gaming_user_id)) {
                $this->sendMachineNowInfoMessage($this->machine->gaming_user_id, $this->machine->id, $name, $info);
            }
        } catch (\Exception $e) {
            $this->log->warning('Slot 推送逻辑异常', [
                'machine_id' => $this->machine->id,
                'field' => $name,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // ✅ getAllData 方法已删除 - 使用 AbstractMachineService 的优化实现
    // 基类提供：内存缓存（1秒TTL）、批量读取（getMultiple）
    
    // ✅ 已删除 slotAutoCmd() 方法（46行）
    // 原因：该方法只在 gk_work 的 Gateway Events 中被调用
    // gk_admin 不处理机台消息，统一通过 sendCmd() HTTP 调用 gk_work

    /**
     * 发送机台指令
     *
     * ✅ 架构改造：所有指令通过 MachineApiService 调用 gk_work
     * - gk_work 负责与机台的 Gateway 通信
     * - gk_admin 只负责业务逻辑和数据读取（从 Redis）
     *
     * @param string $cmd 指令代码
     * @param int $data 数据值
     * @param string $source 来源类型（admin/player）
     * @param int $source_id 来源ID（管理员ID或玩家ID）
     * @param int $isSystem 是否系统指令（保留兼容性）
     * @return bool
     * @throws Exception
     */
    public function sendCmd(
        string $cmd,
        int $data = 0,
        string $source = 'admin',  // ← gk_admin 默认 admin
        int $source_id = 0,
        int $isSystem = 0
    ): bool {
        try {
            // ✅ 提取管理员ID用于日志记录
            $adminId = $source === 'admin' ? $source_id : 0;

            // ✅ 统一通过 MachineApiService 调用 gk_work
            // gk_work 会处理：
            // 1. 在线检查（Gateway::isUidOnline）
            // 2. 指令格式化（createCmd、PREFIX 处理）
            // 3. Gateway 发送（sendToUid）
            // 4. 轮询等待（openPoint、washPoint 逻辑）
            $result = \app\service\MachineApiService::sendCmd(
                $this->machine->id,
                $cmd,
                $data,
                $adminId,
                $this->lang
            );

            $this->log->info('✅ 机台指令已发送到 gk_work', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'data' => $data,
                'admin_id' => $adminId
            ]);

            return true;

        } catch (Exception $e) {
            $this->log->error('❌ 机台指令发送失败', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'data' => $data,
                'source' => $source,
                'source_id' => $source_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    // ✅ 已删除 4 个未使用的方法（约 240 行）
    // 1. slotCmd() - 机台消息处理（180行）
    // 2. getDescription() - 获取机台操作描述（57行）
    // 3. setActionVersion() - 设置操作版本号（7行）
    // 4. getActionVersion() - 获取操作版本号（5行）
    //
    // 原因：这些方法在 gk_admin 中完全未被调用
    // - slotCmd(): 只在 gk_work 的 Gateway Events 中被调用
    // - getDescription(): 用于返回中文描述，但从未被使用
    // - setActionVersion/getActionVersion: 只在 gk_work 中用于轮询等待机台响应
    // gk_admin 不处理机台消息，统一通过 sendCmd() HTTP 调用 gk_work
}
