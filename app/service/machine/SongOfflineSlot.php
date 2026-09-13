<?php

namespace app\service\machine;

use addons\webman\model\Machine;
use Exception;
use support\Log;

/**
 * 小淞线下版 Slot 机台服务（收账小卡协议）
 *
 * 协议：GD 收账小卡协议（2026-07-30）
 *
 * ⚠️ 架构说明：
 * - gk_admin: 只负责业务逻辑和数据读取（从 Redis）
 * - gk_work: 负责与机台的 Gateway 通信和指令处理
 * - 所有指令通过 MachineApiService 调用 gk_work
 *
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
 * @property int $keep_seconds 保留时长
 * @property int $keeping 保留状态
 * @property int $keeping_user_id 保留玩家
 * @property int $last_keep_at 最后保留时间
 * @property int $player_pressure 玩家进入时原始压分
 * @property int $player_score 玩家进入时原始得分
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
 * @property int $login_status 登入状态
 * @property int $card_score 开分卡分数
 * @property int $machine_score 机台分数
 * @property int $total_bet 总押分数
 * @property int $total_win 总得分数
 * @property int $open_table 开分码表
 * @property int $wash_table 洗分码表
 *
 * @package app\service\machine
 */
class SongOfflineSlot extends AbstractMachineService implements BaseMachine
{
    // ========== 收账小卡协议常量（GD 2026-07-30）==========

    const ALL = 'all'; // 机台状态

    // 查询指令（EA 前缀）
    const QUERY_ACCOUNT = 'eac4';        // EA C4 - 查询开分码表+洗分码表+开分卡分数+机台分数
    const QUERY_TOTAL = 'ead8';          // EA D8 - 查询总押分数+总赢分数
    const QUERY_STATUS = 'ead4';         // EA D4 - 查询开分状态+洗分状态+转数

    // 登入登出（EA 前缀）
    const LOGIN = 'eac3';                // EA C3 - 玩家登入机台（必须登入才能上下分）
    const CHECK_LOGIN = 'eac5';          // EA C5 - 检查是否已登入

    // 资金操作（A5 前缀）
    const ADD_POINT = 'a5xxc0';          // A5 XX C0 - 上分指令（XX=次数，100分/次）
    const WITHDRAW_POINT = 'a500c1';     // A5 00 C1 - 下分指令（全部洗分）

    // 管理指令
    const CLEAR_ACCOUNT = 'eade';        // EA DE - 清除开洗分账+回补数
    const RESET_BOARD = 'a37005e0f8ce';  // A3 70 05 E0 F8 CE - 归0机板（清空所有数据，故障排除）

    // 心跳指令
    const TESTING = 'eac0';              // EA C0 - 心跳
    const TESTING2 = 'eac6';             // EA C6 - 心跳备用

    // ========== 兼容性别名（用于统一接口）==========
    const READ_SCORE = 'eac4';           // 读取分数（映射到 QUERY_ACCOUNT）
    const READ_WIN = 'ead8';             // 读取得分（映射到 QUERY_TOTAL）
    const READ_BET = 'ead4';             // 读取押分（映射到 QUERY_STATUS）
    const OPEN_ANY_POINT = 'a5xxc0';     // 开任意分（映射到 ADD_POINT）
    const WASH_ZERO = 'a500c1';          // 洗分清零（映射到 WITHDRAW_POINT）
    const ALL_DOWN = 'eade';             // 清除历史记录（映射到 CLEAR_ACCOUNT）

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
            $this->cacheDataKey . '_login_status',
            $this->cacheDataKey . '_card_score',
            $this->cacheDataKey . '_machine_score',
            $this->cacheDataKey . '_total_bet',
            $this->cacheDataKey . '_total_win',
            $this->cacheDataKey . '_open_table',
            $this->cacheDataKey . '_wash_table',
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
            'login_status',
        ];
    }

    protected function initializeLogger(): \Psr\Log\LoggerInterface
    {
        return Log::channel('song_offline_slot_machine') ?? Log::channel('default');
    }

    /**
     * 构建机台推送信息
     *
     * @param array $machineCacheInfo 机台缓存数据
     * @return array 机台信息数组
     */
    private function buildMachineInfo(array $machineCacheInfo): array
    {
        return [
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
            'login_status' => $machineCacheInfo[$this->cacheDataKey . '_login_status'] ?? 0,
        ];
    }

    /**
     * 处理字段更新后的推送逻辑
     * 覆盖基类方法以实现 SongOfflineSlot 特定的推送逻辑
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

            // ✅ 使用提取的方法构建机台信息
            $info = $this->buildMachineInfo($machineCacheInfo);

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
                case 'login_status':
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
            $this->log->warning('SongOfflineSlot 推送逻辑异常', [
                'machine_id' => $this->machine->id,
                'field' => $name,
                'error' => $e->getMessage()
            ]);
        }
    }

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
        string $source = 'admin',
        int $source_id = 0,
        int $isSystem = 0
    ): bool {
        try {
            // ✅ 提取管理员ID用于日志记录
            $adminId = $source === 'admin' ? $source_id : 0;

            // ✅ 统一通过 MachineApiService 调用 gk_work
            $result = \app\service\MachineApiService::sendCmd(
                $this->machine->id,
                $cmd,
                $data,
                $adminId,
                $this->lang
            );

            $this->log->info('✅ 小淞线下Slot指令已发送到 gk_work', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'data' => $data,
                'admin_id' => $adminId,
                'protocol' => 'SongOfflineSlot (收账小卡协议)'
            ]);

            return true;

        } catch (Exception $e) {
            $this->log->error('❌ 小淞线下Slot指令发送失败', [
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
}
