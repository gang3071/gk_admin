<?php

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\GameType;
use addons\webman\model\LevelList;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineKeepingLog;
use addons\webman\model\MachineLabel;
use addons\webman\model\MachineMedia;
use addons\webman\model\MachineMediaPush;
use addons\webman\model\MachineTencentPlay;
use addons\webman\model\mongo\MachineOperationLog;
use addons\webman\model\mongo\MachineReceiveLog;
use addons\webman\model\NationalInvite;
use addons\webman\model\NationalProfitRecord;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerGameRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerMoneyEditLog;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerPromoter;
use addons\webman\model\PlayerRegisterRecord;
use addons\webman\model\PlayerWashRecord;
use addons\webman\model\SystemSetting;
use addons\webman\service\FishServices;
use addons\webman\service\JackpotService;
use addons\webman\service\MediaServer;
use addons\webman\service\SlotService;
use addons\webman\validator\ValidatorFactory;
use app\service\ActivityServices;
use app\service\LotteryServices;
use app\service\machine\Jackpot;
use app\service\machine\MachineServices;
use app\service\machine\Slot;
use app\service\machine\SongJackpot;
use app\service\machine\SongSlot;
use ExAdmin\ui\support\Arr;
use ExAdmin\ui\support\Container;
use GatewayWorker\Lib\Gateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\DatabasePresenceVerifier;
use support\Cache;
use support\Db;
use support\Log;
use support\Translation;
use Webman\Push\PushException;
use Webman\RedisQueue\Client as queueClient;


if (!function_exists('admin_sysconf')) {

    /**
     * 配置系统参数
     * @param string|null $name 参数名称
     * @param string|null $value 无值为获取
     * @return mixed
     */
    function admin_sysconf(string $name = null, string $value = null): mixed
    {
        $model = plugin()->webman->config('database.config_model');
        if (is_null($name)) {
            return $model::get()->toArray();
        }
        if (is_null($value)) {
            $value = $model::where('name', $name)->value('value');
            if (is_null($value)) {
                return $value;
            }
            $json = json_decode($value, true);
            if (is_array($json)) {
                return $json;
            } else {
                return $value;
            }
        } else {
            return $model::updateOrCreate(['name' => $name], ['value' => $value]);
        }
    }
}

if (!function_exists('validator')) {
    /**
     * Create a new Validator instance.
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return ValidatorFactory
     */
    function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = [])
    {
        $factory = new ValidatorFactory();
        if (func_num_args() === 0) {
            return $factory;
        }
        $factory->setPresenceVerifier(new DatabasePresenceVerifier(Model::getConnectionResolver()));

        return $factory->make($data, $rules, $messages, $customAttributes);
    }
}
if (!function_exists('getGameTypeOptions')) {
    /**
     * 获取游戏类型
     * @return array
     */
    function getGameTypeOptions(): array
    {
        return [
            GameType::TYPE_SLOT => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
            GameType::TYPE_STEEL_BALL => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
            GameType::TYPE_FISH => admin_trans('game_type.game_type.' . GameType::TYPE_FISH)
        ];
    }
}

if (!function_exists('getGameTypeName')) {
    /**
     * 获取机台类型转义名称
     * @param $val
     * @return string
     */
    function getGameTypeName($val): string
    {
        return admin_trans('game_type.game_type.' . $val);
    }
}

if (!function_exists('getGameTypeCateName')) {
    /**
     * 获取游戏类型转义名称
     * @param $val
     * @return string
     */
    function getGameTypeCateName($val): string
    {
        return admin_trans('game_type.game_type_cate.' . $val);
    }
}

if (!function_exists('saveMachineOperationLog')) {
    /**
     * @param Machine $machine
     * @param Player|null $player
     * @param string $content 内容
     * @param string $action 功能
     * @param int $status 状态
     * @param int $isSystem
     * @param int $point
     * @return bool
     */
    function saveMachineOperationLog(
        Machine $machine,
        Player  $player = null,
        string  $content = '',
        string  $action = '',
        int     $status = 1,
        int     $isSystem = 0,
        int     $point = 0
    ): bool
    {
        $machineOperationLog = new MachineOperationLog;
        $machineOperationLog->id = 0;
        $machineOperationLog->department_id = $player->department_id ?? 0;
        $machineOperationLog->machine_id = $machine->id;
        $machineOperationLog->producer_id = $machine->producer_id;
        $machineOperationLog->machine_name = $machine->name;
        $machineOperationLog->machine_type = $machine->type;
        $machineOperationLog->machine_cate = $machine->cate_id;
        $machineOperationLog->machine_code = $machine->code;
        $machineOperationLog->uuid = $player->uuid ?? '';
        $machineOperationLog->player_id = $player->id ?? 0;
        $machineOperationLog->player_phone = $player->phone ?? '';
        $machineOperationLog->player_name = $player->name ?? '';
        $machineOperationLog->status = $status;
        $machineOperationLog->is_system = $isSystem;
        $machineOperationLog->content = $content;
        $machineOperationLog->action = $action;
        $machineOperationLog->remark = request()?->input('data')['remark'] ?? '';
        $machineOperationLog->user_id = Admin::id() ?? 0;
        $machineOperationLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : admin_trans('message.system_automatic');
        if ($action == 41) {
            $point = 100;
        } elseif ($action == 42) {
            $point = 1000;
        }
        $machineOperationLog->point = $point;
        return $machineOperationLog->save();
    }
}

if (!function_exists('saveMachineReceiveLog')) {
    /**
     * @param Machine $machine
     * @param string $msg 指令
     * @param Player|null $player
     * @param string $content 内容
     * @param string $action 功能
     * @param int $status 状态
     * @return bool
     */
    function saveMachineReceiveLog(
        Machine $machine,
        string  $msg,
        Player  $player = null,
        string  $content = '',
        string  $action = '',
        int     $status = 1
    ): bool
    {
        $machineOperationLog = new MachineReceiveLog();
        $machineOperationLog->id = 0;
        $machineOperationLog->department_id = $player->department_id ?? 0;
        $machineOperationLog->machine_id = $machine->id;
        $machineOperationLog->machine_name = $machine->name;
        $machineOperationLog->machine_type = $machine->type;
        $machineOperationLog->machine_code = $machine->code;
        $machineOperationLog->uuid = $player->uuid ?? '';
        $machineOperationLog->player_id = $player->id ?? 0;
        $machineOperationLog->player_phone = $player->phone ?? '';
        $machineOperationLog->player_name = $player->name ?? '';
        $machineOperationLog->msg = $msg;
        $machineOperationLog->content = $content;
        $machineOperationLog->action = $action;
        $machineOperationLog->status = $status;

        return $machineOperationLog->save();
    }
}

if (!function_exists('machineOpenAnyFree')) {
    /**
     * 任意開分免扣點
     * @param Player $player
     * @param Machine $machine
     * @param int $openScore
     * @return bool
     * @throws Exception
     */
    function machineOpenAnyFree(Player $player, Machine $machine, int $openScore): bool
    {
        DB::beginTransaction();
        try {
            $services = MachineServices::createServices($machine, Container::getInstance()->translator->getLocale());
            if (strtotime($services->last_point_at) + 5 >= time()) {
                throw new Exception(admin_trans('message.exception_msg.point_must_5seconds'));
            }
            $openScore = checkMachineOpenAny($machine, $openScore, 0);
            //測試連線
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
            } else {
                if ($services->point + $openScore > 4000) {
                    throw new Exception(admin_trans('message.machine_wash_limit_msg1'));
                }
            }
            //上任意分
            $odds = $machine->odds_x . ':' . $machine->odds_y;
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
                $odds = $machine->machineCategory->name;
            }
            /** @var PlayerPlatformCash $player_platform_wallet */
            $player_platform_wallet = PlayerPlatformCash::query()->where([
                'player_id' => $player->id,
                'platform_id' => PlayerPlatformCash::PLATFORM_SELF
            ])->first();
            $playerGameLog = new PlayerGameLog;
            $playerGameLog->player_id = $player->id;
            $playerGameLog->department_id = $player->department_id;
            $playerGameLog->parent_player_id = $player->recommend_id ?? 0;
            $playerGameLog->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
            $playerGameLog->game_id = $machine->machineCategory->game_id;
            $playerGameLog->machine_id = $machine->id;
            $playerGameLog->type = $machine->type;
            $playerGameLog->odds = $odds;
            $playerGameLog->control_open_point = $machine->control_open_point;
            $playerGameLog->open_point = $openScore;
            $playerGameLog->wash_point = 0;
            $playerGameLog->gift_point = 0;
            $playerGameLog->game_amount = 0;
            $playerGameLog->before_game_amount = $player_platform_wallet->money ?? 0;
            $playerGameLog->after_game_amount = $player_platform_wallet->money ?? 0;
            $playerGameLog->user_id = Admin::id() ?? 0;
            $playerGameLog->action = PlayerGameLog::ACTION_OPEN;
            $playerGameLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
            $playerGameLog->is_test = $player->is_test; //标记测试数据
            $playerGameLog->save();
            //首次上分
            if ($machine->gaming_user_id == 0) {
                //斯洛 移分off
                if ($machine->type == GameType::TYPE_SLOT && $machine->control_type == Machine::CONTROL_TYPE_MEI) {
                    $services->sendCmd($services::MOVE_POINT_OFF, 0, 'admin', Admin::id());
                }
            }
            //累計該玩家開分
            $services->gaming = 1;
            $services->gaming_user_id = $player->id;
            $services->player_open_point = bcadd($services->player_open_point, $openScore);
            $services->last_point_at = time();

            $machine->gaming = 1;
            $machine->gaming_user_id = $player->id;
            $machine->save();
            $services->sendCmd($services::OPEN_ANY_POINT, $openScore, 'admin', Admin::id());
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage());
        }

        return true;
    }
}
if (!function_exists('checkMachineOpenAny')) {
    /**
     * 上任意分
     * @param Machine $machine
     * @param int $money
     * @param int $giftScore
     * @return float|int
     * @throws Exception
     */
    function checkMachineOpenAny(Machine $machine, int $money, int $giftScore): float|int
    {
        if (!is_numeric($money) || $money <= 0) {
            throw new InvalidArgumentException('Invalid money value');
        }
        if (!is_numeric($machine->odds_x) || $machine->odds_x <= 0) {
            throw new InvalidArgumentException('Invalid odds_x value');
        }
        if (!is_numeric($machine->odds_y) || $machine->odds_y <= 0) {
            throw new InvalidArgumentException('Invalid odds_y value');
        }
        if ($machine->odds_x == 0) {
            throw new Exception(admin_trans('message.machine_odds_error'));
        }
        $yx = $machine->odds_y / $machine->odds_x;
        if ($machine->odds_y > $machine->odds_x && floor($yx) != $yx) {
            throw new Exception(admin_trans('message.machine_odds_error'));
        }
        $open_score = $money * $machine->odds_y / $machine->odds_x;

        return floor($open_score) + $giftScore;
    }
}
//斯洛 下分限制
if (!function_exists('checkSlotWashLimit')) {
    /**
     * @param Machine $machine
     * @return mixed
     * @throws Exception
     */
    function checkSlotWashLimit(Machine $machine): Machine
    {
        //抓取當前七段分數  執行洗分餘數 七段分數就會等於餘數
        $services = MachineServices::createServices($machine);
        $services->sendCmd($services::READ_SCORE);
        $score = $services->point;
        if ($score == 0) {
            $machine->wash_limit = 0;
        } elseif ($score > 0 && $machine->wash_limit > $score) {
            throw new Exception(admin_trans('machine_action.exception_msg.unable_to_score_again'));
        }

        return $machine;
    }
}

if (!function_exists('checkJackPotWashLimit')) {
    /**
     * @param Machine $machine
     * @return Machine
     * @throws Exception
     */
    function checkJackPotWashLimit(Machine $machine): Machine
    {
        $services = MachineServices::createServices($machine);

        // 根据机器类型选择不同的计算方式
        if ($machine->control_type == Machine::CONTROL_TYPE_SONG) {
            // 小淞机器：使用实时追踪的 player_win_number
            $gamingTurnPoint = $services->player_win_number;
        } else {
            // 双美机器：使用原有逻辑（基于 win_number 和 player_turn_point）
            $gamingTurnPoint = $services->win_number - $machine->player_turn_point;
        }

        if ($gamingTurnPoint <= 0 || $machine->gaming_user_id == 0) {
            $gamingTurnPoint = 0;
        }

        if ($machine->wash_limit > $gamingTurnPoint) {
            throw new Exception(admin_trans('machine_action.exception_msg.limit_has_not_been', '',
                ['{wash_limit}' => $machine->wash_limit, '{gaming_turn_point}' => $gamingTurnPoint]));
        }

        return $machine;
    }
}
if (!function_exists('getGivePoints')) {
    /**
     * 获取增点缓存
     * @param $playerId
     * @param $machineId
     * @return mixed
     */
    function getGivePoints($playerId, $machineId): mixed
    {
        return Cache::get('gift_cache_' . $machineId . '_' . $playerId);
    }
}

if (!function_exists('machineWash')) {
    /**
     * 洗分
     * @param Player $player
     * @param Machine $machine
     * @param string $path
     * @param int $is_system
     * @param bool $hasLottery
     * @return PlayerLotteryRecord|true
     * @throws Exception
     * @throws PushException
     */
    function machineWash(
        Player  $player,
        Machine $machine,
        string  $path = 'leave',
        int     $is_system = 0,
        bool    $hasLottery = false
    ): bool|PlayerLotteryRecord
    {
        try {
            $lang = Translation::getLocale();
            $services = MachineServices::createServices($machine, $lang);
            if ($services->last_point_at + 5 >= time()) {
                throw new Exception(admin_trans('message.exception_msg.point_must_5seconds'));
            }
            // 洗分限制（强制退出洗分）
            $giftPoint = getGivePoints($player->id, $machine->id);
            $gamingTurnPoint = 0; // 转数
            $gamingPressure = 0; // 压分
            $gamingScore = 0; // 得分
            $money = 0; // 机台下分
            //斯洛 需要判斷下分限制
            switch ($machine->type) {
                case GameType::TYPE_STEEL_BALL:
                    // 弃台需要下转,下珠
                    if ($path == 'leave') {
                        if ($machine->control_type == Machine::CONTROL_TYPE_MEI) {
                            $services->sendCmd($services::PUSH . $services::PUSH_STOP, 0, 'player', $player->id,
                                $is_system);
                            if ($services->auto == 1) {
                                $services->sendCmd($services::AUTO_UP_TURN, 0, 'player', $player->id, $is_system);
                            }
                            if ($services->score > 0) {
                                $services->sendCmd($services::SCORE_TO_POINT, 0, 'player', $player->id, $is_system);
                            }
                            if ($services->turn > 0) {
                                $services->sendCmd($services::TURN_DOWN_ALL, 0, 'player', $player->id, $is_system);
                            }
                        }
                        if ($machine->control_type == Machine::CONTROL_TYPE_SONG) {
                            if ($services->auto == 1) {
                                $services->sendCmd($services::AUTO_UP_TURN, 0, 'player', $player->id, $is_system);
                            }
                            $services->sendCmd($services::MACHINE_TURN, 0, 'player', $player->id, $is_system);
                            $services->sendCmd($services::MACHINE_SCORE, 0, 'player', $player->id, $is_system);
                            if ($services->score > 0) {
                                $services->sendCmd($services::SCORE_TO_POINT, 0, 'player', $player->id, $is_system);
                            }
                            if ($services->turn > 0) {
                                $services->sendCmd($services::TURN_DOWN_ALL, 0, 'player', $player->id, $is_system);
                            }
                        }
                    }
                    $services->sendCmd($services::MACHINE_POINT, 0, 'player', $player->id, $is_system);
                    $services->sendCmd($services::WIN_NUMBER, 0, 'player', $player->id, $is_system);
                    $gamingTurnPoint = $services->player_win_number;
                    $money = $services->point;
                    if (!empty($giftPoint) && $path == 'leave') {
                        $money = max($money - $giftPoint['gift_point'], 0);
                    }
                    break;
                case GameType::TYPE_SLOT:
                    if ($services->move_point == 1 && $machine->control_type == Machine::CONTROL_TYPE_MEI) {
                        $services->sendCmd($services::MOVE_POINT_OFF, 0, 'player', $player->id, $is_system);
                    }
                    if ($services->auto == 1) {
                        $services->sendCmd($services::OUT_OFF, 0, 'player', $player->id, $is_system);
                    }
                    $services->sendCmd($services::STOP_ONE, 0, 'player', $player->id, $is_system);
                    $services->sendCmd($services::STOP_TWO, 0, 'player', $player->id, $is_system);
                    $services->sendCmd($services::STOP_THREE, 0, 'player', $player->id, $is_system);
                    $services->sendCmd($services::READ_SCORE, 0, 'player', $player->id, $is_system);
                    Log::channel('song_slot_machine')->info('slot -> wash', [
                        'point' => $money,
                        'code' => $machine->code,
                        'bet' => $services->bet,
                        'player_pressure' => $services->player_pressure,
                    ]);
                    $services->sendCmd($services::READ_BET, 0, 'player', $player->id, $is_system);
                    $gamingPressure = bcsub($services->bet, $services->player_pressure);
                    $gamingScore = bcsub($services->win, $services->player_score);
                    $money = $services->point;
                    Log::channel('slot_machine')->info('slot -> wash', [
                        'point' => $money,
                        'code' => $machine->code,
                    ]);
                    if (!empty($giftPoint)) {
                        if ($money < $giftPoint['open_point'] * $giftPoint['condition']) {
                            $money = max($money - $giftPoint['gift_point'], 0);
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        /** 彩金预留检查 */
        if ($hasLottery && $machine->type == GameType::TYPE_SLOT && $path == 'down' && $money > 0) {
            try {
                $playerLotteryRecord = (new LotteryServices())->setMachine($machine)->setPlayer($player)->fixedPotCheckLottery($money,
                    true);
                if ($playerLotteryRecord) {
                    return $playerLotteryRecord;
                }
            } catch (\Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
        DB::beginTransaction();
        try {
            if ($money >= 0) {
                $machine = machineWashZero($player, $machine, $money, $is_system, max($gamingPressure, 0),
                    max($gamingScore, 0), max($gamingTurnPoint, 0), $path);
            }
            if ($path == 'leave') {
                if ($services->keeping == 1) {
                    // 更新保留日志
                    updateKeepingLog($machine->id, $player->id);
                }
                $machine->gaming = 0;
                $machine->gaming_user_id = 0;
                $machine->save();

                if ($machine->type == GameType::TYPE_STEEL_BALL) {
                    $activityServices = new ActivityServices($machine, $player);
                    $activityServices->playerFinishActivity(true);
                }
                /** TODO 计算打码量 */
            }
            // 斯洛离开机台或弃台下分重置活动 检查彩金中奖情况
            if ($machine->type == GameType::TYPE_SLOT) {
                // 离开机台参与活动结束
                $activityServices = new ActivityServices($machine, $player);
                $activityServices->playerFinishActivity(true);
                // 下分检查彩金获奖情况
                if ($money > 0) {
                    $playerLotteryRecord = (new LotteryServices())->setMachine($machine)->setPlayer($player)->fixedPotCheckLottery($money,
                        false, $path == 'leave');
                }
            }
            DB::commit();
            // 执行下分操作
            switch ($machine->type) {
                case GameType::TYPE_STEEL_BALL:
                    $services->sendCmd($services::WASH_ZERO, 0, 'player', $player->id, $is_system);
                    $services->sendCmd($services::CLEAR_LOG, 0, 'player', $player->id, $is_system);
                    $services->player_win_number = 0;
                    break;
                case GameType::TYPE_SLOT:
                    $services->sendCmd($services::WASH_ZERO, 0, 'player', $player->id, $is_system);
                    $services->sendCmd($services::ALL_DOWN, 0, 'player', $player->id, $is_system);
                    $services->player_pressure = 0;
                    $services->player_score = 0;
                    $services->bet = 0;
                    break;
            }
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage());
        }
        // 游戏结束同步Redis彩金到数据库（新版：独立彩池模式）
        // 强制同步所有彩金的Redis数据到数据库
        try {
            LotteryServices::forceSyncRedisToDatabase();
        } catch (\Exception $e) {
            Log::error('游戏结束同步彩金失败: ' . $e->getMessage());
        }
        queueClient::send('media-recording', [
            'machine_id' => $machine->id,
            'action' => 'stop',
        ], 10);
        //下分成功 下分&下轉限制歸零 開獎中結束 關閉 push auto
        $services->last_play_time = time();
        if ($path == 'leave') {
            $services->gaming_user_id = 0;
            $services->gaming = 0;
            $services->keeping_user_id = 0;
            $services->keeping = 0;
            $services->last_keep_at = 0;
            $services->keep_seconds = 0;
            if ($machine->type == GameType::TYPE_SLOT) {
                $services->player_pressure = 0;
                $services->player_score = 0;
            }
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
                $services->player_win_number = 0;
            }
            $services->player_open_point = 0;
            $services->player_wash_point = 0;
        }
        switch ($machine->type) {
            case GameType::TYPE_STEEL_BALL:
                if ($path == 'leave') {
                    $services->gift_bet = 0;
                    Cache::delete('gift_cache_' . $machine->id . '_' . $player->id);
                }
                break;
            case GameType::TYPE_SLOT:
                Cache::delete('gift_cache_' . $machine->id . '_' . $player->id);
                break;
        }

        // 清理消息缓存
        LotteryServices::clearNoticeCache($player->id, $machine->id);

        return $playerLotteryRecord ?? true;
    }
}

if (!function_exists('updateKeepingLog')) {
    /**
     * 更新保留日志
     * @param $machineId
     * @param $playerId
     * @return void
     */
    function updateKeepingLog($machineId, $playerId): void
    {
        /** @var MachineKeepingLog $machineKeepingLog */
        $machineKeepingLog = MachineKeepingLog::query()->where([
            'machine_id' => $machineId,
            'player_id' => $playerId
        ])->where('status', MachineKeepingLog::STATUS_STAR)->first();
        if ($machineKeepingLog) {
            // 更新保留日志
            $machineKeepingLog->keep_seconds = time() - strtotime($machineKeepingLog->created_at);
            $machineKeepingLog->status = MachineKeepingLog::STATUS_END;
            $machineKeepingLog->save();
        }
    }
}

if (!function_exists('machineWashRemainder')) {
    /**
     * 洗分餘數算法
     * @param Player $player
     * @param Machine $machine
     * @param $money
     * @param int $is_system
     * @return Machine
     */
    function machineWashRemainder(Player $player, Machine $machine, $money, int $is_system = 0): Machine
    {
        //记录游戏局记录
        /** @var PlayerGameRecord $gameRecord */
        $gameRecord = PlayerGameRecord::query()->where('machine_id', $machine->id)
            ->where('player_id', $player->id)
            ->where('status', PlayerGameRecord::STATUS_START)
            ->orderBy('created_at', 'desc')
            ->first();

        $control_open_point = !empty($machine->control_open_point) ? $machine->control_open_point : 100;
        //除100後無條件捨去
        $floor_money = floor($money / $control_open_point);
        if ($floor_money > 0) {
            $floor_money = $floor_money * $control_open_point;
            //api洗分
            $wash_point = $floor_money;
            //依照比值轉成錢包幣值 無條件捨去
            $game_amount = floor($floor_money * ($machine->odds_x ?? 1) / ($machine->odds_y ?? 1));

            /** @var PlayerPlatformCash $machineWallet */
            $machineWallet = PlayerPlatformCash::query()->where('platform_id',
                PlayerPlatformCash::PLATFORM_SELF)->where('player_id', $player->id)->first();
            $beforeGameAmount = $machineWallet->money;
            $machineWallet->money = bcadd($machineWallet->money, $game_amount, 2);
            $machineWallet->save();
            $afterGameAmount = $machineWallet->money;

            if (!empty($gameRecord)) {
                $gameRecord->wash_point = bcadd($gameRecord->wash_point, $wash_point, 2);
                $gameRecord->wash_amount = bcadd($gameRecord->wash_amount, $game_amount, 2);
                $gameRecord->after_game_amount = $afterGameAmount;
                $gameRecord->status = PlayerGameRecord::STATUS_END;
                $gameRecord->save();
            }
            $odds = $machine->odds_x . ':' . $machine->odds_y;
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
                $odds = $machine->machineCategory->name;
            }
            $playerGameLog = new PlayerGameLog;
            $playerGameLog->player_id = $player->id;
            $playerGameLog->parent_player_id = $player->recommend_id ?? 0;
            $playerGameLog->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
            $playerGameLog->department_id = $player->department_id;
            $playerGameLog->game_id = $machine->machineCategory->game_id;
            $playerGameLog->machine_id = $machine->id;
            $playerGameLog->game_record_id = $gameRecord->id;
            $playerGameLog->type = $machine->type;
            $playerGameLog->odds = $odds;
            $playerGameLog->control_open_point = $control_open_point;
            $playerGameLog->open_point = 0;
            $playerGameLog->wash_point = $wash_point;
            $playerGameLog->game_amount = $game_amount;
            $playerGameLog->before_game_amount = $beforeGameAmount;
            $playerGameLog->after_game_amount = $afterGameAmount;
            $playerGameLog->is_system = $is_system;
            $playerGameLog->user_id = Admin::id() ?? 0;
            $playerGameLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
            $playerGameLog->is_test = $player->is_test; //标记测试数据
            $playerGameLog->save();

            //寫入金流明細
            $playerDeliveryRecord = new PlayerDeliveryRecord;
            $playerDeliveryRecord->player_id = $player->id;
            $playerDeliveryRecord->department_id = $player->department_id;
            $playerDeliveryRecord->target = $playerGameLog->getTable();
            $playerDeliveryRecord->target_id = $playerGameLog->id;
            $playerDeliveryRecord->machine_id = $machine->id;
            $playerDeliveryRecord->machine_name = $machine->name;
            $playerDeliveryRecord->machine_type = $machine->type;
            $playerDeliveryRecord->code = $machine->code;
            $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_MACHINE_DOWN;
            $playerDeliveryRecord->source = 'game_machine';
            $playerDeliveryRecord->amount = $game_amount;
            $playerDeliveryRecord->amount_before = $beforeGameAmount;
            $playerDeliveryRecord->amount_after = $afterGameAmount;
            $playerDeliveryRecord->tradeno = $playerGameLog->tradeno ?? '';
            $playerDeliveryRecord->remark = $playerGameLog->remark ?? '';
            $playerDeliveryRecord->save();

            //保存下分時間 累计洗分
            $machine->last_point_at = date('YmdHis');
            $machine->wash_point = bcadd($machine->wash_point, $wash_point);
        }
        if ($floor_money == 0) {
            if (!empty($gameRecord)) {
                $gameRecord->after_game_amount = $player->machine_wallet->money;
                $gameRecord->status = PlayerGameRecord::STATUS_END;
                $gameRecord->save();
            }
        }
        return $machine;
    }
}

if (!function_exists('floorToPointSecond')) {
    function floorToPointSecond($number)
    {
        if (!is_numeric($number)) {
            return $number;
        }

        return number_format(($number * 100) / 100, 2);
    }
}

if (!function_exists('isGivePoint')) {
    /**
     * 是否参与开分赠点
     * @param PlayerGameLog $playerGameLog
     * @return bool
     * @throws Exception
     */
    function isGivePoint(PlayerGameLog $playerGameLog): bool
    {
        //下分时，是否参与开分赠点
        $giftPoint = $playerGameLog->gift_point;
        if ($giftPoint > 0) { // 参与了开分赠点
            return true;
        }
        return false;
    }
}


if (!function_exists('machineWashZero')) {
    /**
     * 洗分清零算法
     * @param Player $player
     * @param Machine $machine
     * @param $money
     * @param int $is_system
     * @param int $gamingPressure
     * @param int $gamingScore
     * @param int $gamingTurnPoint
     * @param string $action
     * @return Machine
     * @throws Exception
     */
    function machineWashZero(
        Player  $player,
        Machine $machine,
                $money,
        int     $is_system = 0,
        int     $gamingPressure = 0,
        int     $gamingScore = 0,
        int     $gamingTurnPoint = 0,
        string  $action = 'leave'
    ): Machine
    {
        try {
            $services = MachineServices::createServices($machine);
            $control_open_point = !empty($machine->control_open_point) ? $machine->control_open_point : 100;
            //记录游戏局记录
            /** @var PlayerGameRecord $gameRecord */
            $gameRecord = PlayerGameRecord::where('machine_id', $machine->id)
                ->where('player_id', $player->id)
                ->where('status', PlayerGameRecord::STATUS_START)
                ->orderBy('created_at', 'desc')
                ->first();
            /** @var PlayerPlatformCash $machineWallet */
            $machineWallet = PlayerPlatformCash::query()->where('platform_id',
                PlayerPlatformCash::PLATFORM_SELF)->where('player_id', $player->id)->lockForUpdate()->first();
            $beforeGameAmount = $machineWallet->money;
            if ($money > 0) {
                //api洗分
                $wash_point = $money;
                //依照比值轉成錢包幣值 無條件捨去
                $game_amount = floor($money * ($machine->odds_x ?? 1) / ($machine->odds_y ?? 1));
                $machineWallet->money = bcadd($machineWallet->money, $game_amount, 2);
                $machineWallet->save();
                if (!empty($gameRecord)) {
                    $gameRecord->wash_point = bcadd($gameRecord->wash_point, $wash_point, 2);
                    $gameRecord->wash_amount = bcadd($gameRecord->wash_amount, $game_amount, 2);
                    $gameRecord->after_game_amount = $machineWallet->money;
                    if ($action == 'leave') {
                        $gameRecord->status = PlayerGameRecord::STATUS_END;
                        /** TODO 计算客损 */
                        $diff = bcsub($gameRecord->wash_amount, $gameRecord->open_amount, 2);
                        nationalPromoterSettlement([
                            ['player_id' => $player->id, 'bet' => 0, 'diff' => $diff]
                        ]);
                        if (!empty($player->recommend_id)) {
                            $recommendPromoter = Player::query()->find($player->recommend_id);
                            $gameRecord->national_damage_ratio = $recommendPromoter->national_promoter->level_list->damage_rebate_ratio ?? 0;
                        }
                    }
                    $gameRecord->save();
                }

                //添加机台点数转换记录
                $playerGameLog = addPlayerGameLog($player, $machine, $gameRecord, $control_open_point);
                $playerGameLog->wash_point = $wash_point;
                $playerGameLog->game_amount = $game_amount;
                $playerGameLog->before_game_amount = $beforeGameAmount;
                $playerGameLog->after_game_amount = $machineWallet->money;
                $playerGameLog->action = ($action == 'leave' ? PlayerGameLog::ACTION_LEAVE : PlayerGameLog::ACTION_DOWN);
                $playerGameLog->chip_amount = 0;
                if ($machine->type == GameType::TYPE_SLOT) {
                    $ratio = ($machine->odds_x ?? 1) / ($machine->odds_y ?? 1);
                    $playerGameLog->chip_amount = bcmul($gamingPressure, $ratio, 2);
                } elseif ($machine->type == GameType::TYPE_STEEL_BALL) {
                    $playerGameLog->chip_amount = bcmul($machine->machineCategory->turn_used_point, $gamingTurnPoint);
                }
                extracted($is_system, $playerGameLog, $gamingPressure, $gamingScore, $gamingTurnPoint);

                //寫入金流明細
                $playerDeliveryRecord = new PlayerDeliveryRecord;
                $playerDeliveryRecord->player_id = $player->id;
                $playerDeliveryRecord->department_id = $player->department_id;
                $playerDeliveryRecord->target = $playerGameLog->getTable();
                $playerDeliveryRecord->target_id = $playerGameLog->id;
                $playerDeliveryRecord->machine_id = $machine->id;
                $playerDeliveryRecord->machine_name = $machine->name;
                $playerDeliveryRecord->machine_type = $machine->type;
                $playerDeliveryRecord->code = $machine->code;
                $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_MACHINE_DOWN;
                $playerDeliveryRecord->source = 'game_machine';
                $playerDeliveryRecord->amount = $game_amount;
                $playerDeliveryRecord->amount_before = $beforeGameAmount;
                $playerDeliveryRecord->amount_after = $machineWallet->money;
                $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
                $playerDeliveryRecord->remark = $target->remark ?? '';
                $playerDeliveryRecord->user_id = Admin::id() ?? 0;
                $playerDeliveryRecord->user_name = Admin::user()->username ?? '';
                $playerDeliveryRecord->save();

                //保存下分時間
                $services->last_point_at = time();
                //累計該玩家洗分
                $services->player_wash_point = bcadd($services->player_wash_point, $wash_point);
            } else {
                //添加机台点数转换记录
                $playerGameLog = addPlayerGameLog($player, $machine, $gameRecord, $control_open_point);
                $playerGameLog->wash_point = 0;
                $playerGameLog->game_amount = 0;
                $playerGameLog->before_game_amount = $machineWallet->money;
                $playerGameLog->after_game_amount = $machineWallet->money;
                $playerGameLog->action = ($action == 'leave' ? PlayerGameLog::ACTION_LEAVE : PlayerGameLog::ACTION_DOWN);
                extracted($is_system, $playerGameLog, $gamingPressure, $gamingScore, $gamingTurnPoint);

                if (!empty($gameRecord)) {
                    $gameRecord->after_game_amount = $machineWallet->money;
                    if ($action == 'leave') {
                        $gameRecord->status = PlayerGameRecord::STATUS_END;
                        /** TODO 计算客损 */
                        $diff = bcsub($gameRecord->wash_amount, $gameRecord->open_amount, 2);
                        nationalPromoterSettlement([
                            ['player_id' => $player->id, 'bet' => 0, 'diff' => $diff]
                        ]);
                        if (!empty($player->recommend_id)) {
                            $recommendPromoter = Player::query()->find($player->recommend_id);
                            $gameRecord->national_damage_ratio = $recommendPromoter->national_promoter->level_list->damage_rebate_ratio ?? 0;
                        }
                    }
                    $gameRecord->save();
                }
                //保存下分時間
                $services->last_point_at = time();
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $machine;
    }

    /**
     * @param int $is_system
     * @param PlayerGameLog $playerGameLog
     * @param int $gamingPressure 押分
     * @param int $gamingScore 得分
     * @param int $gamingTurnPoint 转数
     * @return void
     */
    function extracted(
        int           $is_system,
        PlayerGameLog $playerGameLog,
        int           $gamingPressure,
        int           $gamingScore,
        int           $gamingTurnPoint
    ): void
    {
        $playerGameLog->is_system = $is_system;
        $playerGameLog->pressure = $gamingPressure;
        $playerGameLog->score = $gamingScore;
        $playerGameLog->turn_point = $gamingTurnPoint;
        $playerGameLog->user_id = Admin::id() ?? 0;
        $playerGameLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
        $playerGameLog->save();
    }

    /**
     * @param Player $player
     * @param Machine $machine
     * @param PlayerGameRecord|null $gameRecord
     * @param int $control_open_point
     * @return PlayerGameLog
     */
    function addPlayerGameLog(
        Player            $player,
        Machine           $machine,
        ?PlayerGameRecord $gameRecord,
        int               $control_open_point
    ): PlayerGameLog
    {
        $odds = $machine->odds_x . ':' . $machine->odds_y;
        if ($machine->type == GameType::TYPE_STEEL_BALL) {
            $odds = $machine->machineCategory->name;
        }
        $playerGameLog = new PlayerGameLog;
        $playerGameLog->player_id = $player->id;
        $playerGameLog->parent_player_id = $player->recommend_id ?? 0;
        $playerGameLog->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
        $playerGameLog->department_id = $player->department_id;
        $playerGameLog->machine_id = $machine->id;
        $playerGameLog->game_record_id = isset($gameRecord) && !empty($gameRecord->id) ? $gameRecord->id : 0;
        $playerGameLog->game_id = $machine->machineCategory->game_id;
        $playerGameLog->type = $machine->type;
        $playerGameLog->odds = $odds;
        $playerGameLog->control_open_point = $control_open_point;
        $playerGameLog->open_point = 0;
        $playerGameLog->turn_used_point = $machine->machineCategory->turn_used_point;
        $playerGameLog->is_test = $player->is_test; //标记测试数据

        return $playerGameLog;
    }
}

if (!function_exists('nationalPromoterSettlement')) {
    /**
     * 全民代理返佣
     * @param $data
     * @return true
     */
    function nationalPromoterSettlement($data): bool
    {
        foreach ($data as $item) {
            /** @var Player $player */
            $player = Player::query()->find($item['player_id']);
            //玩家上级详情
            $recommendPromoter = Player::query()->find($player->recommend_id);
            //计算所有玩家打码量
            if ($item['bet'] > 0) {
                //当前玩家打码量
                $player->national_promoter->chip_amount = bcadd($player->national_promoter->chip_amount, $item['bet'],
                    2);
                //根据打码量查询玩家当前全民代理等级
                $levelId = LevelList::query()->where('department_id', $player->department_id)
                    ->where('must_chip_amount', '<=',
                        $player->national_promoter->chip_amount)->orderBy('must_chip_amount', 'desc')->first();
                if (!empty($levelId) && isset($levelId->id)) {
                    //根据打码量提升玩家全民代理等级
                    $player->national_promoter->level = $levelId->id;
                }
                $player->push();
            }
            //当前玩家渠道未开通全民代理功能
            if ($player->channel->national_promoter_status == 0) {
                continue;
            }
            //上级是全民代理,并且当前玩家已充值激活全民代理身份
            if (!empty($recommendPromoter) && !empty($recommendPromoter->national_promoter) && $item['diff'] != 0 && !empty($player->national_promoter) && $player->national_promoter->status == 1 && $recommendPromoter->is_promoter < 1) {
                $damageRebateRatio = isset($recommendPromoter->national_promoter->level_list->damage_rebate_ratio) ? $recommendPromoter->national_promoter->level_list->damage_rebate_ratio : 0;
                $money = bcdiv(bcmul(-$item['diff'], $damageRebateRatio, 2), 100, 2);
                $recommendPromoter->national_promoter->pending_amount = bcadd($recommendPromoter->national_promoter->pending_amount,
                    $money, 2);
                $recommendPromoter->push();
                /** @var NationalProfitRecord $nationalProfitRecord */
                $nationalProfitRecord = NationalProfitRecord::query()->where('uid', $player->id)
                    ->where('type', 1)
                    ->whereDate('created_at', date('Y-m-d'))->first();
                if (!empty($nationalProfitRecord)) {
                    $nationalProfitRecord->money = bcadd($nationalProfitRecord->money, $money, 2);
                } else {
                    $nationalProfitRecord = new NationalProfitRecord();
                    $nationalProfitRecord->uid = $player->id;
                    $nationalProfitRecord->recommend_id = $player->recommend_id;
                    $nationalProfitRecord->money = $money;
                    $nationalProfitRecord->type = 1;
                }
                $nationalProfitRecord->save();
            }
        }
        return true;
    }
}
if (!function_exists('resetMachineTrans')) {
    /**
     * 重置机台(开启事务)
     * @param Machine $machine
     * @param Player $player
     * @return true
     * @throws Exception
     */
    function resetMachineTrans(Machine $machine, Player $player): bool
    {
        DB::beginTransaction();
        try {
            /** @var Jackpot|Slot $services */
            $services = MachineServices::createServices($machine);
            $gamingTurn = 0;
            $gamingScore = 0;
            $gamingPressure = 0;
            $isOnLine = true;
            $uid = $machine->domain . ':' . $machine->port;
            if (!Gateway::isUidOnline($uid)) {
                $isOnLine = false;
            }
            //取得玩家遊玩轉數/得分
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
                // 根据机器类型选择不同的计算方式
                if ($machine->control_type == Machine::CONTROL_TYPE_SONG) {
                    // 小淞机器：使用实时追踪的 player_win_number
                    $gamingTurn = $services->player_win_number;
                } else {
                    // 双美机器：使用原有逻辑（基于 win_number 和 player_turn_point）
                    $gamingTurn = bcsub($services->win_number, $machine->player_turn_point);
                }
            }
            if ($machine->type == GameType::TYPE_SLOT) {
                $autoUid = $machine->auto_card_domain . ':' . $machine->auto_card_port;
                $gamingScore = bcsub($services->win, $services->player_score);
                $gamingPressure = bcsub($services->bet, $services->player_pressure);
                if (!Gateway::isUidOnline($autoUid)) {
                    $isOnLine = false;
                }
            }
            if ($isOnLine) {
                switch ($machine->type) {
                    case GameType::TYPE_STEEL_BALL:
                        if ($machine->control_type == Machine::CONTROL_TYPE_MEI) {
                            $services->sendCmd($services::PUSH . $services::PUSH_STOP, 0, 'player', $player->id);
                        }
                        if ($services->auto == 1) {
                            $services->sendCmd($services::AUTO_UP_TURN, 0, 'player', $player->id);
                        }
                        if ($services->score > 0) {
                            $services->sendCmd($services::SCORE_TO_POINT, 0, 'player', $player->id);
                        }
                        if ($services->turn > 0) {
                            $services->sendCmd($services::TURN_DOWN_ALL, 0, 'player', $player->id);
                        }
                        break;
                    case GameType::TYPE_SLOT:
                        if ($services->move_point == 1 && $machine->control_type == Machine::CONTROL_TYPE_MEI) {
                            $services->sendCmd($services::MOVE_POINT_OFF, 0, 'player', $player->id);
                        }
                        if ($services->auto == 1) {
                            $services->sendCmd($services::OUT_OFF, 0, 'player', $player->id);
                        } else {
                            $services->sendCmd($services::STOP_ONE, 0, 'player', $player->id);
                            $services->sendCmd($services::STOP_TWO, 0, 'player', $player->id);
                            $services->sendCmd($services::STOP_THREE, 0, 'player', $player->id);
                        }
                        break;
                }
            }

            $player_platform_wallet = PlayerPlatformCash::query()->where([
                'player_id' => $player->id,
                'platform_id' => PlayerPlatformCash::PLATFORM_SELF,
            ])->first();

            //记录游戏局记录
            /** @var PlayerGameRecord $gameRecord */
            $gameRecord = PlayerGameRecord::query()->where('machine_id', $machine->id)
                ->where('player_id', $player->id)
                ->where('status', PlayerGameRecord::STATUS_START)
                ->orderBy('created_at', 'desc')
                ->first();
            if (!empty($gameRecord)) {
                $gameRecord->status = PlayerGameRecord::STATUS_END;
                $gameRecord->save();
            }
            $odds = $machine->odds_x . ':' . $machine->odds_y;
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
                $odds = $machine->machineCategory->name;
            }
            //添加机台点数转换记录
            $playerGameLog = new PlayerGameLog;
            $playerGameLog->player_id = $machine->gaming_user_id;
            $playerGameLog->parent_player_id = $player->recommend_id ?? 0;
            $playerGameLog->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
            $playerGameLog->department_id = $player->department_id;
            $playerGameLog->machine_id = $machine->id;
            $playerGameLog->game_id = $machine->machineCategory->game_id;
            $playerGameLog->game_record_id = $gameRecord->id ?? 0;
            $playerGameLog->type = $machine->type;
            $playerGameLog->odds = $odds;
            $playerGameLog->control_open_point = $machine->control_open_point;
            $playerGameLog->open_point = 0;
            $playerGameLog->wash_point = 0;
            $playerGameLog->gift_point = 0;
            $playerGameLog->game_amount = 0;
            $playerGameLog->pressure = max($gamingPressure, 0);
            $playerGameLog->score = max($gamingScore, 0);
            $playerGameLog->turn_point = max($gamingTurn, 0);
            $playerGameLog->before_game_amount = $player_platform_wallet->money ?? 0;
            $playerGameLog->after_game_amount = $player_platform_wallet->money ?? 0;
            $playerGameLog->is_system = 1;
            $playerGameLog->action = PlayerGameLog::ACTION_LEAVE;
            $playerGameLog->user_id = Admin::id() ?? 0;
            $playerGameLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
            $playerGameLog->is_test = $player->is_test; //标记测试数据
            $playerGameLog->save();

            $machine->gaming_user_id = 0;
            $machine->gaming = 0;
            $machine->player_turn_point = 0;
            $machine->player_seven_turn_point = 0;
            $machine->player_pressure = 0;
            $machine->player_score = 0;
            $machine->wash_limit = 0;
            $machine->open_point = 0;
            $machine->push_auto = 0;
            $machine->bonus_accumulate = 0;
            $machine->keep_seconds = 0;
            $machine->amount = 0;
            $machine->is_open = 0;
            $machine->save();

            $services->gaming_user_id = 0;
            $services->gaming = 0;
            $services->keeping_user_id = 0;
            $services->keeping = 0;
            $services->last_keep_at = 0;
            $services->keep_seconds = 0;
            if ($machine->type == GameType::TYPE_SLOT) {
                $services->player_pressure = 0;
                $services->player_score = 0;
            }
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
                $services->player_win_number = 0;
            }
            $services->player_open_point = 0;
            $services->player_wash_point = 0;
            // 下分参与活动结束
            $activityServices = new ActivityServices($machine, $player);
            $activityServices->playerFinishActivity(true);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage());
        }

        return true;
    }
}

if (!function_exists('getGameTypeCateOptions')) {
    /**
     * 获取游戏类型
     * @return array
     */
    function getGameTypeCateOptions(): array
    {
        return [
            GameType::CATE_PHYSICAL_MACHINE => admin_trans('game_type.game_type_cate.' . GameType::CATE_PHYSICAL_MACHINE),
            GameType::CATE_COMPUTER_GAME => admin_trans('game_type.game_type_cate.' . GameType::CATE_COMPUTER_GAME),
            GameType::CATE_LIVE_VIDEO => admin_trans('game_type.game_type_cate.' . GameType::CATE_LIVE_VIDEO)
        ];
    }
}

if (!function_exists('playerManualSystem')) {
    /**
     * 系统加点
     * @param $data
     * @throws Exception
     */
    function playerManualSystem($data): void
    {
        $money = (float)$data['money'];
        if ($money <= 0) {
            throw new Exception(admin_trans('player.wallet.operation_amount_error'));
        }
        if ($data['type'] == PlayerMoneyEditLog::TYPE_INCREASE) {
            if (!in_array($data['increase_action'], [
                PlayerMoneyEditLog::RECHARGE,
                PlayerMoneyEditLog::VIP_RECHARGE,
                PlayerMoneyEditLog::TESTING_MACHINE,
                PlayerMoneyEditLog::ACTIVITY_GIVE,
                PlayerMoneyEditLog::TRIPLE_SEVEN_GIVE,
                PlayerMoneyEditLog::COMPOSITE_MACHINE_GIVE,
                PlayerMoneyEditLog::REAL_PERSON_GIVE,
                PlayerMoneyEditLog::ELECTRONIC_GIVE,
                PlayerMoneyEditLog::OTHER,
                PlayerMoneyEditLog::ADMIN_DEDUCT_OTHER,
                PlayerMoneyEditLog::ACTIVITY,
                PlayerMoneyEditLog::COIN_INCREASE,
                PlayerMoneyEditLog::SPECIAL,
            ])) {
                throw new Exception(admin_trans('player.wallet.wallet_type_error'));
            }
            $action = $data['increase_action'];
        } else {
            if ($data['type'] != PlayerMoneyEditLog::TYPE_DEDUCT) {
                throw new Exception(admin_trans('player.wallet.wallet_type_error'));
            }
            if (!in_array($data['deduct_action'], [
                PlayerMoneyEditLog::OTHER,
                PlayerMoneyEditLog::ADMIN_DEDUCT_OTHER,
                PlayerMoneyEditLog::ACTIVITY,
                PlayerMoneyEditLog::COIN_DEDUCT,
                PlayerMoneyEditLog::SPECIAL,
            ])) {
                throw new Exception(admin_trans('player.wallet.wallet_type_error'));
            }
            $action = $data['deduct_action'];
        }
        /** @var Player $player */
        $player = Player::find($data['id']);
        $tradeno = date('YmdHis') . rand(10000, 99999);
        $originMoney = $player->machine_wallet->money;

        $playerMoneyEditLog = new PlayerMoneyEditLog;
        $playerMoneyEditLog->player_id = $player->id;
        $playerMoneyEditLog->department_id = $player->department_id;
        $playerMoneyEditLog->type = $data['type'];
        $playerMoneyEditLog->action = $action;
        $playerMoneyEditLog->tradeno = $tradeno;
        $playerMoneyEditLog->currency = $player->currency;
        $playerMoneyEditLog->money = $money;
        $playerMoneyEditLog->inmoney = $money;
        $playerMoneyEditLog->activity = $data['activity'];
        $playerMoneyEditLog->remark = $data['remark'] ?? '';
        $playerMoneyEditLog->user_id = Admin::id() ?? 0;
        $playerMoneyEditLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : admin_trans('message.system_automatic');
        $playerMoneyEditLog->save();

        $afterMoney = playerUpdateMoney($player, $playerMoneyEditLog, $data['type'], $money,
            $data['source'] ?? 'wallet_modify', $data['delivery_type'], $action);

        $playerMoneyEditLog->origin_money = $originMoney;
        $playerMoneyEditLog->after_money = $afterMoney;
        $playerMoneyEditLog->save();
    }
}


if (!function_exists('playerUpdateMoney')) {
    /**
     * 玩家主錢包加扣點
     * @param Player $player 玩家信息
     * @param object $target 资料表
     * @param int $type 加点扣点
     * @param float $money 金额
     * @param string $source 来源
     * @param int $deliveryType 类型
     * @throws Exception
     */
    function playerUpdateMoney(
        Player $player,
        object $target,
        int    $type,
        float  $money,
        string $source,
        int    $deliveryType,
        int    $action = null
    ): float|string
    {
        if (!in_array($type, [PlayerMoneyEditLog::TYPE_DEDUCT, PlayerMoneyEditLog::TYPE_INCREASE])) {
            throw new Exception(admin_trans('player.wallet.wallet_type_error'));
        }

        if (!$player->id) {
            throw new Exception(admin_trans('player.wallet.player_error'));
        }

        if (!$target->id) {
            throw new Exception(admin_trans('player.wallet.wallet_action_log_not_found'));
        }

        //玩家加點數
        /** @var PlayerPlatformCash $machineWallet */
        $machineWallet = PlayerPlatformCash::query()->where('platform_id', PlayerPlatformCash::PLATFORM_SELF)->where('player_id',
            $player->id)->lockForUpdate()->first();
        $originMoney = $machineWallet->money;
        if ($type == PlayerMoneyEditLog::TYPE_INCREASE) {
            $machineWallet->money = bcadd($machineWallet->money, $money, 2);
            if (isset($player->national_promoter->status) && $player->national_promoter->status == 0 && in_array($deliveryType, [PlayerDeliveryRecord::TYPE_PRESENT_IN, PlayerDeliveryRecord::TYPE_RECHARGE])) {
                $player->national_promoter->created_at = date('Y-m-d H:i:s');
                $player->national_promoter->status = 1;
                $player->push();
                if (!empty($player->recommend_id) && $player->channel->national_promoter_status == 1) {
                    //玩家上级推广员信息
                    /** @var Player $recommendPlayer */
                    $recommendPlayer = Player::query()->find($player->recommend_id);
                    //推广员为全民代理
                    if (!empty($recommendPlayer->national_promoter) && $recommendPlayer->is_promoter < 1) {
                        //首充返佣金额
                        /** @var PlayerPlatformCash $recommendPlayerWallet */
                        $recommendPlayerWallet = PlayerPlatformCash::query()->where('player_id',
                            $player->recommend_id)->lockForUpdate()->first();
                        $beforeRechargeAmount = $recommendPlayerWallet->money;
                        $rechargeRebate = $recommendPlayer->national_promoter->level_list->recharge_ratio;
                        $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money, $rechargeRebate, 2);

                        //寫入首充金流明細
                        $playerDeliveryRecord = new PlayerDeliveryRecord;
                        $playerDeliveryRecord->player_id = $recommendPlayer->id;
                        $playerDeliveryRecord->department_id = $recommendPlayer->department_id;
                        $playerDeliveryRecord->target = $target->getTable();
                        $playerDeliveryRecord->target_id = $target->id;
                        $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE_REWARD;
                        $playerDeliveryRecord->source = 'national_promoter';
                        $playerDeliveryRecord->amount = $rechargeRebate;
                        $playerDeliveryRecord->amount_before = $beforeRechargeAmount;
                        $playerDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;
                        $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
                        $playerDeliveryRecord->remark = $target->remark ?? '';
                        $playerDeliveryRecord->save();

                        //首冲成功之后全民代理邀请奖励
                        $recommendPlayer->national_promoter->invite_num = bcadd($recommendPlayer->national_promoter->invite_num, 1, 0);
                        $recommendPlayer->national_promoter->settlement_amount = bcadd($recommendPlayer->national_promoter->settlement_amount, $rechargeRebate, 2);
                        /** @var NationalInvite $national_invite */
                        $national_invite = NationalInvite::where('min', '<=',
                            $recommendPlayer->national_promoter->invite_num)
                            ->where('max', '>=', $recommendPlayer->national_promoter->invite_num)->first();

                        if (!empty($national_invite) && $national_invite->interval > 0 && $recommendPlayer->national_promoter->invite_num % $national_invite->interval == 0) {
                            $money = $national_invite->money;
                            $amount_before = $recommendPlayerWallet->money;
                            $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money, $money, 2);
                            // 寫入金流明細
                            $playerDeliveryRecord = new PlayerDeliveryRecord;
                            $playerDeliveryRecord->player_id = $recommendPlayer->id;
                            $playerDeliveryRecord->department_id = $recommendPlayer->department_id;
                            $playerDeliveryRecord->target = $national_invite->getTable();
                            $playerDeliveryRecord->target_id = $national_invite->id;
                            $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_NATIONAL_INVITE;
                            $playerDeliveryRecord->source = 'national_promoter';
                            $playerDeliveryRecord->amount = $money;
                            $playerDeliveryRecord->amount_before = $amount_before;
                            $playerDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;
                            $playerDeliveryRecord->tradeno = '';
                            $playerDeliveryRecord->remark = '';
                            $playerDeliveryRecord->save();
                        }
                        $recommendPlayer->push();
                        $recommendPlayerWallet->save();

                        $nationalProfitRecord = new NationalProfitRecord();
                        $nationalProfitRecord->uid = $player->id;
                        $nationalProfitRecord->recommend_id = $player->recommend_id;
                        $nationalProfitRecord->money = $rechargeRebate;
                        $nationalProfitRecord->type = 0;
                        $nationalProfitRecord->status = 1;
                        $nationalProfitRecord->save();
                    }
                }
            }
        } else {
            if ($money > $originMoney) {
                throw new Exception(admin_trans('player.wallet.insufficient_player_money'));
            }
            $machineWallet->money = bcsub($machineWallet->money, $money, 2);
        }
        $machineWallet->save();
        switch ($deliveryType) {
            case PlayerDeliveryRecord::TYPE_PRESENT_IN:
                if (!empty($player->player_extend)) {
                    $playerExtend = $player->player_extend;
                } else {
                    $playerExtend = new PlayerExtend();
                    $playerExtend->player_id = $player->id;
                }
                $playerExtend->present_in_amount = bcadd($playerExtend->present_in_amount, $money, 2);
                $playerExtend->save();
                break;
            case PlayerDeliveryRecord::TYPE_PRESENT_OUT:
                if (!empty($player->player_extend)) {
                    $playerExtend = $player->player_extend;
                } else {
                    $playerExtend = new PlayerExtend();
                    $playerExtend->player_id = $player->id;
                }
                $playerExtend->present_out_amount = bcadd($playerExtend->present_out_amount, $money, 2);
                $playerExtend->save();
        }
        //寫入金流明細
        if ($action == PlayerMoneyEditLog::SPECIAL) {
            $deliveryType = PlayerDeliveryRecord::TYPE_SPECIAL;
        }
        $playerDeliveryRecord = new PlayerDeliveryRecord;
        $playerDeliveryRecord->player_id = $player->id;
        $playerDeliveryRecord->department_id = $player->department_id;
        $playerDeliveryRecord->target = $target->getTable();
        $playerDeliveryRecord->target_id = $target->id;
        $playerDeliveryRecord->type = $deliveryType;
        $playerDeliveryRecord->source = $source;
        $playerDeliveryRecord->amount = $money;
        $playerDeliveryRecord->amount_before = $originMoney;
        $playerDeliveryRecord->amount_after = $machineWallet->money;
        $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
        $playerDeliveryRecord->remark = $target->remark ?? '';
        $playerDeliveryRecord->user_id = Admin::id() ?? 0;
        $playerDeliveryRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : admin_trans('message.system_automatic');
        $playerDeliveryRecord->save();

        return $machineWallet->money;
    }
}

if (!function_exists('isTime')) {
    /**
     * 是否是时间格式
     * @param $timeStr
     * @return bool
     */
    function isTime($timeStr): bool
    {
        //年-月-日
        $regex1 = '/^\d{4}-\d{2}-\d{2}$/';
        //时:分:秒
        $regex2 = '/^\d{2}:\d{2}:\d{2}$/';
        //年-月-日 时:分:秒
        $regex3 = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

        if (preg_match($regex1, $timeStr) || preg_match($regex2, $timeStr) || preg_match($regex3, $timeStr)) {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('getCateListOptions')) {
    /**
     * 获取机台类别树型
     * @param array $data
     * @param bool $isTree
     * @return array
     */
    function getCateListOptions(array $data = [], bool $isTree = true): array
    {
        $optionList = [];
        $machineCategory = MachineCategory::query()->with(['gameType'])->where('status', 1)->whereNull('deleted_at');
        if (!empty($data)) {
            $machineCategory->whereHas('gameType', function ($query) use ($data) {
                $query->where('type', $data['type'])->where('status', 1)->whereNull('deleted_at');
            });
        }
        $cateList = $machineCategory->get();
        /** @var MachineCategory $item */
        foreach ($cateList as $item) {
            if ($item->gameType->type == GameType::TYPE_SLOT) {
                $optionList[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'pid' => -GameType::TYPE_SLOT
                ];
            }
            if ($item->gameType->type == GameType::TYPE_STEEL_BALL) {
                $optionList[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'pid' => -GameType::TYPE_STEEL_BALL,
                ];
            }
            if ($item->gameType->type == GameType::TYPE_FISH) {
                $optionList[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'pid' => -GameType::TYPE_FISH
                ];
            }
        }
        if (empty($data) || ($data['type'] == GameType::TYPE_SLOT)) {
            $optionList[] = [
                'id' => -GameType::TYPE_SLOT,
                'name' => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                'pid' => 0
            ];
        }
        if (empty($data) || ($data['type'] == GameType::TYPE_STEEL_BALL)) {
            $optionList[] = [
                'id' => -GameType::TYPE_STEEL_BALL,
                'name' => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                'pid' => 0
            ];
        }

        if (empty($data) || ($data['type'] == GameType::TYPE_FISH)) {
            $optionList[] = [
                'id' => -GameType::TYPE_FISH,
                'name' => admin_trans('game_type.game_type.' . GameType::TYPE_FISH),
                'pid' => 0
            ];
        }

        return $isTree ? Arr::tree($optionList) : $optionList;
    }
}

if (!function_exists('fishMachineWash')) {
    /**
     * 鱼机下分
     * @param Player $player
     * @param Machine $machine
     * @param FishServices $service
     * @param string $action
     * @return bool
     * @throws Exception
     */
    function fishMachineWash(Player $player, Machine $machine, FishServices $service, string $action = 'leave'): bool
    {
        $lang = Translation::getLocale();
        if (strtotime($machine->last_point_at) + 5 >= time()) {
            throw new Exception(admin_trans('message.exception_msg.point_must_5seconds'));
        }

        /** @var PlayerGameRecord $gameRecord */
        $gameRecord = PlayerGameRecord::query()->where('machine_id', $machine->id)
            ->where('player_id', $player->id)
            ->where('status', PlayerGameRecord::STATUS_START)
            ->orderBy('created_at', 'desc')
            ->first();
        // 关闭自动
        $autoResult = $service->machineAction('is_auto');
        if ($autoResult['result'] == 1) {
            $service->machineAction('auto_off');
        }
        // 洗分
        $service->machineAction('wash_point');
        // 图片识别
        $result = $service->machineAction('identify_image');

        DB::beginTransaction();
        try {
            $playerWashRecord = new PlayerWashRecord();
            $playerWashRecord->player_id = $player->id;
            $playerWashRecord->department_id = $player->department_id;
            $playerWashRecord->machine_id = $machine->id;
            $playerWashRecord->game_record_id = $gameRecord->id;
            $playerWashRecord->machine_score = $result['score'] ?? 0;
            $playerWashRecord->odds_x = $machine->odds_x;
            $playerWashRecord->odds_y = $machine->odds_y;
            $playerWashRecord->control_open_point = $machine->control_open_point;
            $playerWashRecord->machine_info = $result['info'] ?? '';
            $playerWashRecord->machine_image = $result['image'] ?? '';
            $playerWashRecord->save();

            if ($action == 'leave') {
                $machine->gaming_user_id = 0;
                $machine->gaming = 0;
                $machine->keeping_user_id = 0;
                $machine->keeping = 0;
                $machine->open_point = 0;
                // 更新游戏记录
                $gameRecord->status = PlayerGameRecord::STATUS_END;
                $gameRecord->save();
            }
            $machine->wash_limit = 0;
            $machine->is_opening = 0;
            $machine->last_game_at = date('YmdHis');
            $machine->last_point_at = date('YmdHis');
            $machine->is_open = 0;
            $machine->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage());
        }

        return true;
    }
}

if (!function_exists('getSeatOptions')) {
    /**
     * 炮台位置
     * @return array
     */
    function getSeatOptions(): array
    {
        return [
            1 => admin_trans('machine.seat.1'),
            2 => admin_trans('machine.seat.2'),
            3 => admin_trans('machine.seat.3'),
            4 => admin_trans('machine.seat.4'),
            5 => admin_trans('machine.seat.5'),
            6 => admin_trans('machine.seat.6'),
            7 => admin_trans('machine.seat.7'),
            8 => admin_trans('machine.seat.8'),
        ];
    }
}

if (!function_exists('getSeatOptions')) {
    /**
     * 炮台位置
     * @return array
     */
    function getSeatOptions(): array
    {
        return [
            1 => admin_trans('machine.seat.1'),
            2 => admin_trans('machine.seat.2'),
            3 => admin_trans('machine.seat.3'),
            4 => admin_trans('machine.seat.4'),
            5 => admin_trans('machine.seat.5'),
            6 => admin_trans('machine.seat.6'),
            7 => admin_trans('machine.seat.7'),
            8 => admin_trans('machine.seat.8'),
        ];
    }
}

if (!function_exists('getAdminUserListOptions')) {
    /**
     * 获取管理员列表
     * @param int $departmentId
     * @param int $type
     * @return array
     */
    function getAdminUserListOptions(int $departmentId = 1, int $type = 1): array
    {
        $optionList = [];
        $userList = AdminUser::query()->where('status', 1)->where('type', $type)->where('department_id',
            $departmentId)->whereNull('deleted_at')->get();
        /** @var AdminUser $item */
        foreach ($userList as $item) {
            $optionList[$item->id] = $item->nickname;
        }

        return $optionList;
    }
}

if (!function_exists('getActionListOptions')) {
    /**
     * 获取机台操作
     * @return array
     */
    function getActionListOptions(): array
    {
        $optionList = [
            [
                'id' => GameType::TYPE_SLOT,
                'name' => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                'pid' => 0
            ],
            [
                'id' => GameType::TYPE_STEEL_BALL,
                'name' => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                'pid' => 0
            ],
        ];
        $slotActionList = SlotService::getAction();
        $jackPotActionList = JackpotService::getAction();
        foreach ($slotActionList as $item) {
            $optionList[] = [
                'id' => $item . ',' . GameType::TYPE_SLOT,
                'name' => admin_trans('machine_operation_log.action.slot.' . $item),
                'pid' => GameType::TYPE_SLOT
            ];
        }
        foreach ($jackPotActionList as $item) {
            $optionList[] = [
                'id' => $item . ',' . GameType::TYPE_STEEL_BALL,
                'name' => admin_trans('machine_operation_log.action.jack_pot.' . $item),
                'pid' => GameType::TYPE_STEEL_BALL
            ];
        }
        return $optionList;
    }
}

if (!function_exists('getMachineAction')) {
    /**
     * 获取机台工控操作
     * @param $type
     * @param $controlType
     * @return array
     */
    function getMachineAction($type, $controlType): array
    {
        $data = [];
        switch ($type) {
            case GameType::TYPE_SLOT:
                $data = MachineServices::getSlotAction($controlType);
                break;
            case GameType::TYPE_STEEL_BALL:
                $data = MachineServices::getJackpotAction($controlType);
                break;
        }
        $optionList = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                $optionList[] = [
                    'key' => $item,
                    'action' => admin_trans('machine_action.machine_action.' . $type . '.' . $item),
                ];
            }
        }
        return $optionList;
    }
}

if (!function_exists('getMachineOpenAny')) {
    /**
     * 获取机台上分操作
     * @param $type
     * @param $controlType
     * @return array|string
     */
    function getMachineOpenAny($type, $controlType): array|string
    {
        $cmd = [];
        switch ($type) {
            case GameType::TYPE_SLOT:
                $cmd = $controlType == Machine::CONTROL_TYPE_SONG ? SongSlot::OPEN_ANY_POINT : Slot::OPEN_ANY_POINT;
                break;
            case GameType::TYPE_STEEL_BALL:
                $cmd = $controlType == Machine::CONTROL_TYPE_SONG ? SongJackpot::OPEN_ANY_POINT : Slot::OPEN_ANY_POINT;
                break;
        }

        return $cmd;
    }
}

if (!function_exists('getChannelMachineAction')) {
    /**
     * 获取机台工控操作
     * @param $type
     * @param $controlType
     * @return array
     */
    function getChannelMachineAction($type, $controlType): array
    {
        $data = [];
        switch ($type) {
            case GameType::TYPE_SLOT:
                $data = MachineServices::getChannelSlotAction($controlType);
                break;
            case GameType::TYPE_STEEL_BALL:
                $data = MachineServices::getChannelJackpotAction($controlType);
                break;
        }
        $optionList = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                $optionList[] = [
                    'key' => $item,
                    'action' => admin_trans('machine_action.machine_action.' . $type . '.' . $item),
                ];
            }
        }
        return $optionList;
    }
}

if (!function_exists('getWeekdayName')) {
    /**
     * 获取星期名称
     * @param $num
     * @return string
     */
    function getWeekdayName($num): string
    {
        $weekdays = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return $weekdays[$num] ?? 'Monday'; // 默认返回星期一
    }
}

if (!function_exists('getPlatformGameTypeOptions')) {
    /**
     * 获取游戏类型
     * @return array
     */
    function getPlatformGameTypeOptions(): array
    {
        return [
            GameType::CATE_LIVE_VIDEO => admin_trans('game_type.game_type_cate.' . GameType::CATE_LIVE_VIDEO),
            GameType::CATE_COMPUTER_GAME => admin_trans('game_type.game_type_cate.' . GameType::CATE_COMPUTER_GAME),
            GameType::CATE_FISH => admin_trans('game_type.game_type_cate.' . GameType::CATE_FISH),
            GameType::CATE_TABLE => admin_trans('game_type.game_type_cate.' . GameType::CATE_TABLE),
            GameType::CATE_P2P => admin_trans('game_type.game_type_cate.' . GameType::CATE_P2P),
            GameType::CATE_SLO => admin_trans('game_type.game_type_cate.' . GameType::CATE_SLO),
            GameType::CATE_ARCADE => admin_trans('game_type.game_type_cate.' . GameType::CATE_ARCADE),
        ];
    }
}

if (!function_exists('getMachineLabelOptions')) {
    /**
     * 获取机器标签
     * @return array
     */
    function getMachineLabelOptions(): array
    {
        $options = MachineLabel::query()
            ->orderBy('created_at', 'desc')
            ->get();
        $data = [];
        /** @var MachineLabel $machineLabel */
        foreach ($options as $machineLabel) {
            $data[$machineLabel->id] = $machineLabel->id . ' - ' . $machineLabel->name;
        }

        return $data;
    }
}

if (!function_exists('getPromoterTreeOptions')) {
    /**
     * 获取机台类别树型
     * @param $player_id
     * @param $departmentId
     * @param bool $isTree
     * @param bool $isTest
     * @return array
     */
    function getPromoterTreeOptions($player_id, $departmentId, bool $isTree = true, bool $isTest = false): array
    {
        $optionList = [];
        $playerPromoter = PlayerPromoter::query()
            ->when($isTest, function (Builder $q) {
                $q->whereHas('player', function ($query) {
                    $query->where('is_test', 1);
                });
            })
            ->where('status', 1)
            ->where('department_id', $departmentId)
            ->where('player_id', '<>', $player_id)
            ->get();
        /** @var PlayerPromoter $item */
        foreach ($playerPromoter as $item) {
            $optionList[] = [
                'id' => $item->player_id,
                'name' => $item->name,
                'pid' => $item->recommend_id
            ];
        }
        return $isTree ? Arr::tree($optionList) : $optionList;
    }
}

if (!function_exists('generate15DigitUniqueId')) {
    /**
     * 生成唯一15位UUID
     * @return string
     */
    function generate15DigitUniqueId(): string
    {
        do {
            $timestamp = time();
            $randomNumber = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
            $uniqueNumericId = substr($timestamp, -5) . $randomNumber;

        } while (Player::query()->where('uuid', $uniqueNumericId)->withTrashed()->exists());

        return $uniqueNumericId;
    }
}

if (!function_exists('machineMedia')) {
    /**
     * 重设推流
     * @return true
     */
    function machineMedia($id, $videos = []): bool
    {
        $machineMediaList = MachineMedia::query()->whereHas('machine', function ($query) {
            $query->whereNull('deleted_at')->where('status', 1);
        })->when($videos, function ($query) use ($videos) {
            $query->whereIn('push_ip', $videos);
        })
            ->get();
        /** @var MachineTencentPlay $machineTencentPlay */
        $machineTencentPlay = MachineTencentPlay::query()->find($id);
        /** @var MachineMedia $media */
        foreach ($machineMediaList as $media) {
            (new MediaServer($media->push_ip, $media->media_app))->deleteMachineStream($media->stream_name);
        }
        /** @var MachineMedia $media */
        foreach ($machineMediaList as $media) {
            Db::beginTransaction();
            try {
                $pushList = [];
                $insertData = [];
                $pushData = getPushUrl($media->machine->code, $machineTencentPlay->push_domain,
                    $machineTencentPlay->push_key);
                $pushList[] = [
                    'type' => 'generic',
                    'rtmpUrl' => $pushData['rtmp_url'],
                    'endpointServiceId' => $pushData['endpoint_service_id'],
                ];
                $insertData[] = [
                    'machine_id' => $media->machine_id,
                    'media_id' => $media->id,
                    'stream_name' => $media->stream_name,
                    'endpoint_service_id' => $pushData['endpoint_service_id'],
                    'expiration_date' => $pushData['expiration_date'],
                    'machine_code' => $media->machine->code,
                    'rtmp_url' => $pushData['rtmp_url'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'machine_tencent_play_id' => $machineTencentPlay->id,
                ];
                $mediaServer = new MediaServer($media->push_ip, $media->media_app);
                $result = $mediaServer->createMachineStream($media->machine->code, $media->media_ip,
                    $media->machine->type, $pushList);
                if ($result && $result['success']) {
                    $media->stream_name = $result['dataId'];
                } else {
                    $media->stream_name = -1;
                }

                $media->save();
                MachineMediaPush::query()->where('media_id', $media->id)->delete();
                if (!empty($insertData)) {
                    MachineMediaPush::query()->insert($insertData);
                }
                Db::commit();
            } catch (Exception) {
                Db::rollBack();
                continue;
            }
        }

        return true;
    }
}

if (!function_exists('getPushUrl')) {

    /**
     *  获取推流地址
     *  如果不传key和过期时间，将返回不含防盗链的url
     * @param $machineCode
     * @param string $pushDomain
     * @param string $pushKey
     * @return array
     */
    function getPushUrl($machineCode, string $pushDomain = '', string $pushKey = ''): array
    {
        $pushUrl = '';
        $endpointServiceId = uniqid();
        if (!empty($machineCode) && !empty($pushDomain)) {
            $name = $machineCode . '_' . $endpointServiceId;
            if (!empty($pushKey)) {
                $time = date('Y-m-d H:i:s'); // 获取当前时间
                $timePlus24Hours = date('Y-m-d H:i:s', strtotime($time) + 24 * 60 * 60 * 30 * 24);
                $txTime = strtoupper(base_convert(strtotime($timePlus24Hours), 10, 16));
                $txSecret = md5($pushKey . $name . $txTime);
                $ext_str = "?" . http_build_query(array(
                        "txSecret" => $txSecret,
                        "txTime" => $txTime
                    ));
            }
            $pushUrl = [
                'rtmp_url' => "rtmp://" . $pushDomain . "/live/" . $name . ($ext_str ?? ""),
                'expiration_date' => $timePlus24Hours ?? '',
                'endpoint_service_id' => $endpointServiceId,
                'machine_code' => $machineCode,
            ];
        }

        return $pushUrl;
    }
}
if (!function_exists('formatWinRatio')) {
    /**
     * 专门处理 decimal(10,9) 格式胜率的转换函数
     * 适用于 win_ratio 等概率字段
     * @param mixed $winRatio 胜率值（可能是字符串或浮点数）
     * @return string 可读的概率字符串
     */
    function formatWinRatio(mixed $winRatio): string
    {
        // 确保转换为浮点数
        $value = (float)$winRatio;

        // 处理边界值
        if ($value <= 0) {
            return "0%";
        }

        if ($value >= 1) {
            return "100%";
        }

        // 1. 处理极小的概率值（小于百万分之一）
        if ($value < 0.000001) {
            $denominator = round(1 / $value);
            if ($denominator > 1000000) {
                // 优化科学计数法的可读性
                return formatScientificProbability($value, $denominator);
            }
            return number_format($denominator) . " 分之一";
        }

        // 2. 极低概率（0.000001 到 0.0001）：万分之零点几
        if ($value < 0.0001) {
            $perTenThousand = $value * 10000;
            return sprintf("万分之 %.3f", $perTenThousand);
        }

        // 3. 较低概率（0.0001 到 0.01）：万分之几
        if ($value < 0.01) {
            $perTenThousand = $value * 10000;
            // 检查是否需要显示小数
            if (round($perTenThousand) == $perTenThousand) {
                return "万分之 " . round($perTenThousand);
            }
            return "万分之 " . round($perTenThousand, 2);
        }

        // 4. 中等概率（0.01 到 0.1）：千分之几
        if ($value < 0.1) {
            $perThousand = $value * 1000;
            if (round($perThousand) == $perThousand) {
                return "千分之 " . round($perThousand);
            }
            return "千分之 " . round($perThousand, 2);
        }

        // 5. 较高概率（0.1 到 1）：百分比
        $percentage = $value * 100;
        return round($percentage, 2) . "%";
    }
}
if (!function_exists('formatScientificProbability')) {

    /**
     * 优化科学计数法概率的可读性
     * 将 "约 1.0e-7 分之一" 转换为更友好的中文表达
     */
    function formatScientificProbability(float $value, float $denominator): string
    {
        // 首先尝试使用常见的大数单位
        $commonUnits = [
            1e12 => '万亿',
            1e11 => '千亿',
            1e10 => '百亿',
            1e9 => '十亿',
            1e8 => '亿',
            1e7 => '千万',
            1e6 => '百万',
            1e5 => '十万',
            1e4 => '万',
            1e3 => '千',
        ];

        // 尝试使用大数单位表达
        foreach ($commonUnits as $unitValue => $unitName) {
            if ($denominator >= $unitValue) {
                $count = $denominator / $unitValue;

                // 如果是整数或接近整数，用中文表达
                if (abs(round($count) - $count) < 0.01) {
                    $chineseNumbers = [
                        1 => '一', 2 => '两', 3 => '三', 4 => '四', 5 => '五',
                        6 => '六', 7 => '七', 8 => '八', 9 => '九', 10 => '十',
                        11 => '十一', 12 => '十二', 13 => '十三', 14 => '十四', 15 => '十五',
                        20 => '二十', 30 => '三十', 40 => '四十', 50 => '五十',
                        100 => '一百', 200 => '两百', 300 => '三百', 400 => '四百', 500 => '五百',
                        1000 => '一千'
                    ];

                    $roundedCount = round($count);
                    if (isset($chineseNumbers[$roundedCount])) {
                        return $chineseNumbers[$roundedCount] . $unitName . "分之一";
                    }
                }

                // 否则用数字加单位
                if ($count >= 100) {
                    return "约" . round($count) . $unitName . "分之一";
                } elseif ($count >= 10) {
                    return "约" . round($count, 1) . $unitName . "分之一";
                } else {
                    return "约" . round($count, 2) . $unitName . "分之一";
                }
            }
        }

        // 如果不能用常见单位表达，使用科学计数法但优化格式
        $scientific = sprintf("%.1e", $value);

        // 解析科学计数法：例如 "1.0e-7"
        if (preg_match('/(\d+\.?\d*)e([+-]\d+)/', $scientific, $matches)) {
            $coefficient = floatval($matches[1]);
            $exponent = intval($matches[2]);

            // 生成更友好的表达
            $expressions = [
                -7 => '千万分之一',
                -8 => '亿分之一',
                -9 => '十亿分之一',
                -10 => '百亿分之一',
                -11 => '千亿分之一',
                -12 => '万亿分之一',
            ];

            if (isset($expressions[$exponent])) {
                if ($coefficient == 1) {
                    return $expressions[$exponent];
                } else {
                    // 比如 2.5e-8 可以表达为 "2.5亿分之一"
                    $unit = str_replace('分之一', '', $expressions[$exponent]);
                    return round($coefficient, 2) . $unit . "分之一";
                }
            }

            // 使用10的幂次方表达
            $powerMap = [
                -6 => '百万',
                -7 => '千万',
                -8 => '亿',
                -9 => '十亿',
                -10 => '百亿',
                -11 => '千亿',
                -12 => '万亿',
            ];

            if (isset($powerMap[$exponent])) {
                $chinesePower = $powerMap[$exponent];
                if ($coefficient == 1) {
                    return "一" . $chinesePower . "分之一";
                } else {
                    return round($coefficient, 2) . $chinesePower . "分之一";
                }
            }

            // 使用通用表达
            $power10 = abs($exponent);
            $chineseExponent = [
                1 => '十', 2 => '百', 3 => '千', 4 => '万', 5 => '十万',
                6 => '百万', 7 => '千万', 8 => '亿', 9 => '十亿', 10 => '百亿',
                11 => '千亿', 12 => '万亿', 13 => '十万亿', 14 => '百万亿'
            ];

            if (isset($chineseExponent[$power10])) {
                if ($coefficient == 1) {
                    return "约一" . $chineseExponent[$power10] . "分之一";
                } else {
                    return "约" . round($coefficient, 2) . $chineseExponent[$power10] . "分之一";
                }
            }

            // 最后回退到科学计数法，但用中文表达
            return "约 10的" . $power10 . "次方分之一";
        }

        // 如果都不匹配，返回原始的科学计数法
        return "约 " . $scientific . " 分之一";
    }
}
if (!function_exists('encrypt_sensitive')) {
    /**
     * 加密敏感数据
     * @param string $value 需要加密的值
     * @return string 加密后的值
     */
    function encrypt_sensitive(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        $key = config('app.key', 'base64:' . base64_encode('webman_secret_key_32_chars!!'));
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }
}
if (!function_exists('decrypt_sensitive')) {
    /**
     * 解密敏感数据
     * @param string $value 加密的值
     * @return string 解密后的值
     */
    function decrypt_sensitive(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // 如果不是加密格式，直接返回原值（兼容旧数据）
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return $value;
        }

        $key = config('app.key', 'base64:' . base64_encode('webman_secret_key_32_chars!!'));
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);

        return $decrypted !== false ? $decrypted : $value;
    }
}

if (!function_exists('addPlayerExtend')) {
    /**
     * 创建玩家扩展信息
     * @param Player $player
     * @return void
     */
    function addPlayerExtend(Player $player): void
    {
        $registerPresent = SystemSetting::query()->where('feature', 'register_present')->where('status', 1)->value('num') ?? 0;

        PlayerPlatformCash::query()->firstOrCreate([
            'player_id' => $player->id,
            'platform_id' => PlayerPlatformCash::PLATFORM_SELF,
            'money' => $registerPresent,
        ]);

        PlayerExtend::query()->firstOrCreate([
            'player_id' => $player->id,
        ]);

        if (isset($registerPresent) && $registerPresent > 0) {
            //添加玩家钱包日志
            $playerMoneyEditLog = new PlayerMoneyEditLog;
            $playerMoneyEditLog->player_id = $player->id;
            $playerMoneyEditLog->department_id = $player->department_id;
            $playerMoneyEditLog->type = PlayerMoneyEditLog::TYPE_INCREASE;
            $playerMoneyEditLog->action = PlayerMoneyEditLog::OTHER;
            $playerMoneyEditLog->tradeno = date('YmdHis') . rand(10000, 99999);
            $playerMoneyEditLog->currency = $player->currency;
            $playerMoneyEditLog->money = $registerPresent;
            $playerMoneyEditLog->inmoney = $registerPresent;
            $playerMoneyEditLog->remark = '';
            $playerMoneyEditLog->user_id = 0;
            $playerMoneyEditLog->user_name = '系统自动';
            $playerMoneyEditLog->save();

            //寫入金流明細
            $playerDeliveryRecord = new PlayerDeliveryRecord;
            $playerDeliveryRecord->player_id = $player->id;
            $playerDeliveryRecord->department_id = $player->department_id;
            $playerDeliveryRecord->target = $playerMoneyEditLog->getTable();
            $playerDeliveryRecord->target_id = $playerMoneyEditLog->id;
            $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_REGISTER_PRESENT;
            $playerDeliveryRecord->source = 'register_present';
            $playerDeliveryRecord->amount = $playerMoneyEditLog->money;
            $playerDeliveryRecord->amount_before = 0;
            $playerDeliveryRecord->amount_after = $registerPresent;
            $playerDeliveryRecord->tradeno = $playerMoneyEditLog->tradeno ?? '';
            $playerDeliveryRecord->remark = $playerMoneyEditLog->remark ?? '';
            $playerDeliveryRecord->save();
        }
    }
}

if (!function_exists('addRegisterRecord')) {
    /**
     * 创建玩家注册记录
     * @param int $id 玩家ID
     * @param int $type 类型（1=管理后台，2=客户端，3=QTalk）
     * @param int $department_id 部门ID
     * @return PlayerRegisterRecord
     */
    function addRegisterRecord(int $id, int $type, int $department_id): PlayerRegisterRecord
    {
        $ip = request()->getRealIp();
        $country_name = '';
        $city_name = '';

        // 尝试获取IP地理位置信息（如果安装了相关包）
        if (!empty($ip) && class_exists('\Workbunny\WebmanIpAttribution\Location')) {
            try {
                $location = new \Workbunny\WebmanIpAttribution\Location();
                $result = $location->getLocation($ip);
                $country_name = ($result['country'] ?? '') . ($result['city'] ?? '');
                $city_name = $result['city'] ?? '';
            } catch (\Exception $exception) {
                Log::error('获取ip信息错误: ' . $exception->getMessage());
            }
        }

        $domain = isset($_SERVER['HTTP_ORIGIN']) ? parse_url($_SERVER['HTTP_ORIGIN']) : null;

        return PlayerRegisterRecord::query()->create([
            'player_id' => $id,
            'register_domain' => !empty($domain) ? $domain['host'] : null,
            'ip' => $ip,
            'country_name' => $country_name,
            'city_name' => $city_name,
            'device' => 'admin',
            'type' => $type,
            'department_id' => $department_id,
        ]);
    }
}