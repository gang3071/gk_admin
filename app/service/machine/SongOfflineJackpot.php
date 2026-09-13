<?php

namespace app\service\machine;

use addons\webman\model\Machine;
use Exception;
use support\Log;
use Webman\Push\PushException;

/**
 * 小淞线下版钢珠机台服务（46协议）
 *
 * 协议：小淞线下版钢珠机台协议
 *
 * ⚠️ 架构说明：
 * - gk_admin: 只负责业务逻辑和数据读取（从 Redis）
 * - gk_work: 负责与机台的 Gateway 通信和指令处理
 * - 所有指令通过 MachineApiService 调用 gk_work
 *
 * @property int $auto 自动状态
 * @property int $reward_status 开奖状态
 * @property int $rush_status rush状态
 * @property int $bb_status bb状态
 * @property int $play_start_time 开始游戏时间
 * @property int $gaming_user_id 游戏中玩家
 * @property int $gaming 是否游戏中
 * @property int $turn 当前转数
 * @property int $point 当前分数
 * @property int $score 当前珠数
 * @property int $last_play_time 最后游戏时间
 * @property int $open_point 开分次数
 * @property int $wash_point 洗分次数
 * @property int $keep_seconds 保留时长
 * @property int $keeping 保留状态
 * @property int $keeping_user_id 保留玩家
 * @property int $last_keep_at 最后保留时间
 * @property int $player_win_number 玩家使用转数
 * @property int $player_open_point 玩家开分
 * @property int $player_wash_point 玩家洗分
 * @property int $last_point_at 玩家最后上下分时间
 * @property int $player_turn_base 玩家转数基准点
 * @property int $handle_status 图柄确认状态
 * @property int $win_number 读取中洞对奖次数
 * @property int $action_time 操作时间
 * @property int $push_auto push auto状态
 * @property int $change_point_card_status 开分卡状态
 * @property int $gift_bet 玩家开分增点时押注
 * @property int $now_turn 当前转数
 * @property int $has_lock 机台锁
 * @property int $pre_wash_point 预洗分点数
 *
 * @package app\service\machine
 */
class SongOfflineJackpot extends MachineServices implements BaseMachine
{
    const ALL = 'all'; // 机台状态

    // ========== 查询指令（46 CE 前缀）==========
    const MACHINE_POINT = '46cea2';      // 46 CE A2 - 查询机台目前分数
    const MACHINE_SCORE = '46cea5';      // 46 CE A5 - 查询机台目前得分WIN
    const MACHINE_TURN = '46cea6';       // 46 CE A6 - 查询机台目前剩余转数
    const WIN_NUMBER = '46cea9';         // 46 CE A9 - 查询机台累积转数
    const QUERY_EXTERNAL_TABLE = '46ceac'; // 46 CE AC - 查询外部开洗分码表
    const QUERY_REWARD_LIGHT = '46ceb8'; // 46 CE B8 - 查询大赏灯状态

    // ========== 开分洗分（46 CA/CC 前缀）==========
    const OPEN_ANY_POINT = '46ca';       // 46 CA - 开任意分数（可输入）
    const WASH_ZERO = '46cc';            // 46 CC - 洗分并清零

    // ========== 转数操作（46 CE C1/C8/C9/CA/CB 前缀）==========
    const POINT_TO_TURN = '46cec1';      // 46 CE C1 - 分数变转数1次（上转）
    const TURN_UP_ALL = '46cecb';        // 46 CE CB - 分数全部变为转数
    const TURN_TO_POINT = '46ceca';      // 46 CE CA - 转数→分数（下转一次）
    const TURN_DOWN_ALL = '46cec9';      // 46 CE C9 - 转数全部换回分数
    const SCORE_TO_POINT = '46cec8';     // 46 CE C8 - 得分WIN换回分数

    // ========== 控制指令（46 CE B2/B6/CD/CE 前缀）==========
    const AUTO_UP_TURN = '46cecd';       // 46 CE CD - 启动机台（开始游戏）
    const AUTO_STOP = '46cece';          // 46 CE CE - 停止机台
    const PUSH_THREE = '46ceb6';         // 46 CE B6 - 连发PUSH
    const PUSH_ONE = '46ceb2';           // 46 CE B2 - 单发PUSH

    // ========== 管理指令（46 CE BE/BC/CC B3/B4/BA 前缀）==========
    const MACHINE_OPEN = '46cebe';       // 46 CE BE - 开机
    const MACHINE_CLOSE = '46cebc';      // 46 CE BC - 关机
    const CHECK = '46cfb4';              // 46 CF B4 - 故障排除
    const CLEAR_EXTERNAL_BUTTON = '46ccb3'; // 46 CC B3 - 清除外部按钮码表
    const CLEAR_LOG = '46ccba';          // 46 CC BA - 清除押得数值

    // ========== 大赏灯控制 ==========
    const REWARD_SWITCH = '46ceb8';      // 46 CE B8 - 大赏灯切换

    // ========== 心跳指令 ==========
    const TESTING = '46c0';              // 46 C0 - 心跳
    const TESTING2 = '46c6';             // 46 C6 - 心跳备用

    // ========== 读取指令（用于控制器查询）==========
    const GET_MACHINE_POINT = '46c0';    // 读取机台当前分
    const AUTO_MACHINE_POINT = '46c6';   // 读取机台当前分自动
    const GET_MACHINE_SCORE = '46da';    // 读取机台当前得分
    const FAULT1_MACHINE_SCORE = '46db'; // 读取机台当前得分（故障1）
    const FAULT_MACHINE_SCORE = '46dc';  // 读取机台当前得分（故障）
    const GET_MACHINE_TURN = '46de';     // 读取机台当前转数
    const GET_WIN_NUMBER = '46d0';       // 读取机台当前转数
    const REWARD_WIN_NUMBER = '46d5';    // 读取机台当前转数开奖

    public function __construct(Machine $machine, $lang = 'zh_CN')
    {
        $this->machine = $machine;
        $this->cacheKey = self::CACHE_PREFIX . $this->machine->id;
        $this->cacheDataKey = self::MACHINE_DATA_PREFIX . $this->machine->id;
        $this->cacheDataKeyArr = [
            $this->cacheDataKey . '_auto',
            $this->cacheDataKey . '_move_point',
            $this->cacheDataKey . '_reward_status',
            $this->cacheDataKey . '_play_start_time',
            $this->cacheDataKey . '_gaming_user_id',
            $this->cacheDataKey . '_gaming',
            $this->cacheDataKey . '_turn',
            $this->cacheDataKey . '_point',
            $this->cacheDataKey . '_score',
            $this->cacheDataKey . '_last_play_time',
            $this->cacheDataKey . '_open_point',
            $this->cacheDataKey . '_wash_point',
            $this->cacheDataKey . '_keep_seconds',
            $this->cacheDataKey . '_keeping',
            $this->cacheDataKey . '_keeping_user_id',
            $this->cacheDataKey . '_last_keep_at',
            $this->cacheDataKey . '_player_win_number',
            $this->cacheDataKey . '_player_open_point',
            $this->cacheDataKey . '_player_wash_point',
            $this->cacheDataKey . '_last_point_at',
            $this->cacheDataKey . '_player_turn_base',
            $this->cacheDataKey . '_action_time',
            $this->cacheDataKey . '_win_number',
            $this->cacheDataKey . '_push_auto',
            $this->cacheDataKey . '_change_point_card_status',
            $this->cacheDataKey . '_gift_bet',
            $this->cacheDataKey . '_now_turn',
            $this->cacheDataKey . '_rush_status',
            $this->cacheDataKey . '_has_lock',
            $this->cacheDataKey . '_pre_wash_point',
        ];
        $this->machineInfo = [
            'auto',
            'reward_status',
            'turn',
            'point',
            'score',
            'win_number',
            'push_auto',
            'has_lock',
        ];
        $this->lang = $lang;
        $this->cacheData = $this->getMachineCache();
        $this->log = Log::channel('song_offline_jackpot_machine') ?? Log::channel('default');
    }

    /**
     * 获取属性
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        $key = $this->cacheDataKey . '_' . $name;
        if (in_array($key, $this->cacheDataKeyArr)) {
            try {
                $value = \support\Cache::get($key, 0);
                return $value;
            } catch (\Exception $e) {
                try {
                    $value = \support\Cache::get($key, 0);
                    \support\Log::warning('Redis缓存读取失败后重试成功', [
                        'machine_id' => $this->machine->id,
                        'field' => $name,
                        'error' => $e->getMessage()
                    ]);
                    return $value;
                } catch (\Exception $e2) {
                    \support\Log::error('Redis缓存读取失败（重试1次后仍失败）', [
                        'machine_id' => $this->machine->id,
                        'machine_code' => $this->machine->code,
                        'field' => $name,
                        'key' => $key,
                        'error' => $e2->getMessage()
                    ]);
                    return 0;
                }
            }
        }
        return null;
    }

    /**
     * 设置属性
     * @param $name
     * @param $value
     * @return void
     * @throws PushException
     */
    public function __set($name, $value)
    {
        $key = $this->cacheDataKey . '_' . $name;
        if (in_array($key, $this->cacheDataKeyArr)) {
            try {
                $saveResult = \support\Cache::set($this->cacheDataKey . '_' . $name, $value);
                if (!$saveResult) {
                    $saveResult = \support\Cache::set($this->cacheDataKey . '_' . $name, $value);
                }
            } catch (\Exception $e) {
                try {
                    $saveResult = \support\Cache::set($this->cacheDataKey . '_' . $name, $value);
                    \support\Log::warning('Redis缓存保存异常后重试成功', [
                        'machine_id' => $this->machine->id,
                        'field' => $name,
                        'error' => $e->getMessage()
                    ]);
                } catch (\Exception $e2) {
                    $saveResult = false;
                    \support\Log::error('Redis缓存保存异常（重试1次后仍失败）', [
                        'machine_id' => $this->machine->id,
                        'machine_code' => $this->machine->code,
                        'field' => $name,
                        'value' => $value,
                        'error' => $e2->getMessage()
                    ]);
                }
            }

            if (!$saveResult) {
                $criticalFields = ['gaming', 'gaming_user_id', 'last_play_time', 'point', 'turn', 'keeping', 'win_number'];
                if (in_array($name, $criticalFields)) {
                    \support\Log::error('关键字段Redis保存失败', [
                        'machine_id' => $this->machine->id,
                        'machine_code' => $this->machine->code,
                        'field' => $name,
                        'value' => $value
                    ]);
                }
            }

            $machineCacheInfo = $this->getAllData() ?? [];
            if (!empty($machineCacheInfo)) {
                $info = [
                    'id' => $this->machine->id,
                    'last_game_at' => $this->machine->last_game_at,
                    'odds_x' => $this->machine->odds_x,
                    'odds_y' => $this->machine->odds_y,
                    'type' => $this->machine->type,
                    'gaming_user_id' => $this->machine->gaming_user_id,
                    'gaming' => $this->machine->gaming,
                    'auto' => $machineCacheInfo[$this->cacheDataKey . '_auto'],
                    'move_point' => $machineCacheInfo[$this->cacheDataKey . '_move_point'],
                    'reward_status' => $machineCacheInfo[$this->cacheDataKey . '_reward_status'],
                    'play_start_time' => $machineCacheInfo[$this->cacheDataKey . '_play_start_time'],
                    'turn' => $machineCacheInfo[$this->cacheDataKey . '_turn'],
                    'point' => $machineCacheInfo[$this->cacheDataKey . '_point'],
                    'score' => $machineCacheInfo[$this->cacheDataKey . '_score'],
                    'last_play_time' => $machineCacheInfo[$this->cacheDataKey . '_last_play_time'],
                    'open_point' => $machineCacheInfo[$this->cacheDataKey . '_open_point'],
                    'wash_point' => $machineCacheInfo[$this->cacheDataKey . '_wash_point'],
                    'keep_seconds' => $machineCacheInfo[$this->cacheDataKey . '_keep_seconds'],
                    'keeping' => $machineCacheInfo[$this->cacheDataKey . '_keeping'],
                    'keeping_user_id' => $machineCacheInfo[$this->cacheDataKey . '_keeping_user_id'],
                    'last_keep_at' => $machineCacheInfo[$this->cacheDataKey . '_last_keep_at'],
                    'player_win_number' => $machineCacheInfo[$this->cacheDataKey . '_player_win_number'],
                    'player_open_point' => $machineCacheInfo[$this->cacheDataKey . '_player_open_point'],
                    'player_wash_point' => $machineCacheInfo[$this->cacheDataKey . '_player_wash_point'],
                    'last_point_at' => $machineCacheInfo[$this->cacheDataKey . '_last_point_at'],
                    'player_turn_base' => $machineCacheInfo[$this->cacheDataKey . '_player_turn_base'] ?? 0,
                    'action_time' => $machineCacheInfo[$this->cacheDataKey . '_action_time'],
                    'win_number' => $machineCacheInfo[$this->cacheDataKey . '_win_number'],
                    'push_auto' => $machineCacheInfo[$this->cacheDataKey . '_push_auto'],
                    'change_point_card_status' => $machineCacheInfo[$this->cacheDataKey . '_change_point_card_status'],
                    'now_turn' => $machineCacheInfo[$this->cacheDataKey . '_now_turn'],
                    'rush_status' => $machineCacheInfo[$this->cacheDataKey . '_rush_status'],
                    'has_lock' => $machineCacheInfo[$this->cacheDataKey . '_has_lock'],
                ];
                switch ($name) {
                    case 'gaming_user_id':
                        if (!empty($this->machine->gamingPlayer)) {
                            $this->sendMachineRealTimeInformation($this->machine->gamingPlayer->department_id,
                                'game_start', $info);
                        }
                        break;
                    case 'auto':
                    case 'turn':
                    case 'win_number':
                    case 'push_auto':
                    case 'reward_status':
                    case 'last_point_at':
                    case 'wash_point':
                    case 'keep_seconds':
                    case 'score':
                    case 'rush_status':
                    case 'bb_status':
                        if (!empty($this->machine->gamingPlayer)) {
                            $this->sendMachineRealTimeInformation($this->machine->gamingPlayer->department_id,
                                'game_info_change', $info);
                        }
                        break;
                }
                if (in_array($name, $this->machineInfo) && !empty($this->machine->gaming_user_id)) {
                    $this->sendMachineNowInfoMessage($this->machine->gaming_user_id, $this->machine->id, $name, $info);
                }
            }
        }
    }

    /**
     * 发送机台指令
     *
     * ✅ 架构改造：所有指令通过 MachineApiService 调用 gk_work
     *
     * @param string $cmd 指令代码
     * @param int $data 数据值
     * @param string $source 来源类型（admin/player）
     * @param int $source_id 来源ID
     * @param int $isSystem 是否系统指令
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
            $adminId = $source === 'admin' ? $source_id : 0;

            $result = \app\service\MachineApiService::sendCmd(
                $this->machine->id,
                $cmd,
                $data,
                $adminId,
                $this->lang
            );

            $this->log->info('✅ 小淞线下钢珠指令已发送到 gk_work', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'data' => $data,
                'admin_id' => $adminId,
                'protocol' => 'SongOfflineJackpot (46协议)'
            ]);

            return true;
        } catch (Exception $e) {
            $this->log->error('❌ 小淞线下钢珠指令发送失败', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
