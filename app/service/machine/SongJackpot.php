<?php

namespace app\service\machine;

use addons\webman\model\Machine;
use Exception;
use support\Cache;
use support\Log;
use Webman\Push\PushException;

/**
 * Class Jackpot
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
 * @property int $player_turn_base 玩家转数基准点（缓存）
 * @property int $handle_status 圖柄確認状态
 * @property int $win_number 讀取中洞對獎次數
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
class SongJackpot extends MachineServices implements BaseMachine
{
    const ALL = 'all'; //机台状态
    const MACHINE_POINT = '46cea2'; //读取机台当前分
    const MACHINE_SCORE = '46cea5'; //读取机台当前得分
    const MACHINE_TURN = '46cea6'; //读取机台当前转数
    const WIN_NUMBER = '46cea9'; //讀取中洞對獎次數

    const GET_MACHINE_POINT = '46c0'; //读取机台当前分
    const AUTO_MACHINE_POINT = '46c6'; //读取机台当前分自动
    const GET_MACHINE_SCORE = '46da'; //读取机台当前得分
    const FAULT1_MACHINE_SCORE = '46db'; //读取机台当前得分
    const FAULT_MACHINE_SCORE = '46dc'; //读取机台当前得分
    const GET_MACHINE_TURN = '46de'; //读取机台当前转数
    const GET_WIN_NUMBER = '46d0'; //读取机台当前转数
    const REWARD_WIN_NUMBER = '46d5'; //读取机台当前转数开奖

    const CHECK = '46cfb4'; //故排
    const MACHINE_OPEN = '46cebe'; //开机
    const MACHINE_CLOSE = '46cebc'; //关机
    const REWARD_SWITCH = '46ceb8';// 大賞燈切換
    const PUSH_THREE = '46ceb6'; //(連發PUSH)
    const PUSH_ONE = '46ceb2'; //(单發PUSH)
    const TURN_DOWN_ALL = '46cec9'; //全部下转
    const TURN_UP_ALL = '46cecb'; //全部上转
    const SCORE_TO_POINT = '46cec8'; //得分转分数
    const OPEN_ANY_POINT = '46ca'; //开任意分数
    const CLEAR_LOG = '46ccba'; //清除历史记录
    const WASH_ZERO = '46cc'; //洗分清零
    const AUTO_UP_TURN = '46cecd'; //自动上转(开始游戏)
    const AUTO_STOP = '46cece'; //停止游戏
    const TURN_TO_POINT = '46ceca'; //下转一次
    const POINT_TO_TURN = '46cec1'; //上转一次

    const TESTING = '46c0'; //心跳
    const TESTING2 = '46c6'; //心跳

    // ✅ cacheData, expirationTime, log 已在基类 MachineServices 中定义

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
        $this->log = Log::channel('song_jackpot_machine') ?? Log::channel('default');
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
                // 尝试从缓存获取
                $value = Cache::get($key, 0);
                return $value;
            } catch (\Exception $e) {
                // 获取失败时立即重试1次
                try {
                    $value = Cache::get($key, 0);
                    \support\Log::warning('Redis缓存读取失败后重试成功', [
                        'machine_id' => $this->machine->id,
                        'field' => $name,
                        'error' => $e->getMessage()
                    ]);
                    return $value;
                } catch (\Exception $e2) {
                    // 重试仍失败，记录错误日志并返回默认值
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
                // 保存到缓存，失败时立即重试1次
                $saveResult = Cache::set($this->cacheDataKey . '_' . $name, $value);
                if (!$saveResult) {
                    $saveResult = Cache::set($this->cacheDataKey . '_' . $name, $value);
                }
            } catch (\Exception $e) {
                // 捕获异常后再重试1次
                try {
                    $saveResult = Cache::set($this->cacheDataKey . '_' . $name, $value);
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

            // 关键字段保存失败时记录额外日志
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
    // [已删除] 以下方法（共约 686 行）：
    // - getAllData() - 使用基类的实现
    // - jackPotCmd() - 机台消息处理（只在 gk_work Gateway Events 中调用）
    // - calculateS1(), calculateS2() - 校验位计算
    // - parseHeartbeat() - 心跳指令解析
    // - machineAction() - 机台操作轮询（gk_work 负责）
    // - createCmd() - 指令创建（gk_work 负责）
    // - scoreToBytes(), toHexString() - 数据转换（gk_work 负责）
    // - openPoint() - 开分逻辑（gk_work 负责）
    // - washPoint() - 洗分逻辑（gk_work 负责）
    //
    // 原因：gk_admin 不处理机台消息，统一通过 sendCmd() HTTP 调用 gk_work

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
            
            $this->log->info('机台指令已发送到 gk_work', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'data' => $data,
                'admin_id' => $adminId
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->log->error('机台指令发送失败', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // ✅ 已删除 2 个未使用的方法（约 40 行）
    // 1. getActionVersion() - 获取操作版本号（5行）
    // 2. getDescription() - 获取机台操作描述（35行）
    //
    // 原因：这些方法在 gk_admin 中完全未被调用
    // - getActionVersion(): 只在 gk_work 中用于轮询等待机台响应
    // - getDescription(): 用于返回中文描述，但从未被使用
}
