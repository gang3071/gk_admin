<?php

namespace app\service\machine;

use addons\webman\model\Machine;
use Exception;
use support\Log;

/**
 * Class SongSlot
 * @property int $auto 自动状态
 * @property int $reward_status 开奖状态
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
 * @property int $pre_wash_point 预洗分点数
 *
 * @package app\service\machine
 */
class SongSlot extends AbstractMachineService implements BaseMachine
{
    const ALL = 'all'; //机台状态
    const OPEN_ANY_POINT = 'afca'; //开任意数
    const WASH_ZERO = 'afcc'; //洗分&清零
    const TESTING = 'afc0'; //心跳
    const TESTING2 = 'afc6'; //心跳
    
    const READ_SCORE = 'afcbc5'; //读取开分
    const READ_WIN = 'afcbc9'; //讀取得分
    const READ_BET = 'afcbc7'; //读取压分
    const READ_STATUS = 'afcbc3'; //读取当前状态
    
    const GET_SCORE = 'afc5'; //读取开分卡分数
    const GET_WIN = 'afc9'; //讀取 得分
    const GET_BET = 'afc7'; //读取 BET
    const GET_STATUS = 'afc3'; //读取当前状态
    
    const REWARD_SWITCH = 'afceb8'; //大賞燈切換
    const CHECK = 'afcfb4'; //故排
    const START = 'afceb2'; //启动
    const OUT_ON = 'afceb6'; //启动自动
    const OUT_OFF = 'afceb2'; //停止自动
    const STOP_ONE = 'afceb3'; //停1
    const STOP_TWO = 'afceb4'; //停2
    const STOP_THREE = 'afceb5'; //停3
    const MACHINE_OPEN = 'afcebe'; //开机
    const MACHINE_CLOSE = 'afcebc'; //关机
    const ALL_DOWN = 'afcfba'; //清除历史记录

    // ✅ cacheData, expirationTime, log 已在基类 AbstractMachineService 中定义

    public function __construct(Machine $machine, $lang = 'zh_CN')
    {
        parent::__construct($machine, $lang);
    }

    protected function initializeCacheKeys(): void
    {
        $this->cacheDataKeyArr = [
            $this->cacheDataKey . '_auto',
            $this->cacheDataKey . '_reward_status',
            $this->cacheDataKey . '_play_start_time',
            $this->cacheDataKey . '_gaming_user_id',
            $this->cacheDataKey . '_gaming',
            $this->cacheDataKey . '_point',
            $this->cacheDataKey . '_score',
            $this->cacheDataKey . '_bet',
            $this->cacheDataKey . '_last_play_time',
            $this->cacheDataKey . '_win',
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
            $this->cacheDataKey . '_has_lock',
            $this->cacheDataKey . '_pre_wash_point',
        ];
    }

    protected function initializeMachineInfo(): void
    {
        $this->machineInfo = [
            'auto',
            'reward_status',
            'bet',
            'win',
            'has_lock',
        ];
    }

    protected function initializeLogger(): \Psr\Log\LoggerInterface
    {
        return Log::channel('song_slot_machine');
    }
    
    // ✅ __get 方法已删除 - 使用 AbstractMachineService 的优化实现
    // 基类提供：内存缓存、批量读取、自动重试

    // ✅ __set 方法已删除 - 使用 AbstractMachineService 的优化实现
    // 基类提供：自动重试、关键字段告警、统一推送
    // SongSlot 特定推送逻辑已移至 handleFieldUpdatePush()

    /**
     * 处理字段更新后的推送逻辑
     * 覆盖基类方法以实现 SongSlot 特定的推送逻辑
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
                'reward_status' => $machineCacheInfo[$this->cacheDataKey . '_reward_status'] ?? 0,
                'play_start_time' => $machineCacheInfo[$this->cacheDataKey . '_play_start_time'] ?? 0,
                'point' => $machineCacheInfo[$this->cacheDataKey . '_point'] ?? 0,
                'score' => $machineCacheInfo[$this->cacheDataKey . '_score'] ?? 0,
                'bet' => $machineCacheInfo[$this->cacheDataKey . '_bet'] ?? 0,
                'last_play_time' => $machineCacheInfo[$this->cacheDataKey . '_last_play_time'] ?? 0,
                'win' => $machineCacheInfo[$this->cacheDataKey . '_win'] ?? 0,
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
                case 'reward_status':
                case 'bet':
                case 'last_point_at':
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
            $this->log->warning('SongSlot 推送逻辑异常', [
                'machine_id' => $this->machine->id,
                'field' => $name,
                'error' => $e->getMessage()
            ]);
        }
    }

    // [已删除] 消息处理方法和辅助方法（共 306 行）
    // 删除的方法：
    // 1. slotCmd() - 机台消息处理（236行）
    // 2. calculateS1() - S1校验位计算（9行）
    // 3. calculateS2() - S2校验位计算（10行）
    // 4. parseHeartbeat() - 心跳指令解析（16行）
    // 5. parseScore() - 分数解析（9行）
    //
    // 原因：这些方法只在 gk_work 的 Gateway Events 中被调用
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
            // 2. 机台锁检查
            // 3. 指令格式化（createCmd）
            // 4. Gateway 发送（sendToUid）
            // 5. 轮询等待（openPoint、washPoint 逻辑）
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
    
    // ✅ getAllData 方法已删除 - 使用 AbstractMachineService 的优化实现
    // 基类提供：内存缓存（1秒TTL）、批量读取（getMultiple）

    // ✅ 已删除 5 个未使用的方法（约 75 行）
    // 1. getDescription() - 获取机台操作描述（43行）
    // 2. getActionVersion() - 获取操作版本号（5行）
    // 3. scoreToBytes() - 分数转字节（16行）
    // 4. toHexString() - 字节转十六进制（6行）
    // 5. bytesToScore() - 字节转分数（16行）
    // 6. getAllData() - 已使用基类实现（3行）
    //
    // 原因：这些方法在 gk_admin 中完全未被调用
    // - getDescription/getActionVersion: 未被使用
    // - scoreToBytes/toHexString/bytesToScore: 只在 gk_work 中用于指令格式化
    // - getAllData: 基类已提供更优化的实现
}
