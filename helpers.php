<?php
/**
 * YJB Admin - 管理后台全局函数库
 * 仅保留管理后台必需的基础工具函数
 */

use addons\webman\Admin;
use addons\webman\filesystem\Filesystem;
use addons\webman\model\ChannelFinancialRecord;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerGameRecord;
use addons\webman\model\PlayerPromoter;
use addons\webman\model\PromoterProfitRecord;
use addons\webman\model\PromoterProfitSettlementRecord;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Respect\Validation\Exceptions\AllOfException;
use support\Db;
use support\Log;
use support\Response;
use WebmanTech\LaravelHttpClient\Facades\Http;

// ==================== JSON响应函数 ====================

/**
 * JSON成功响应
 * @param string $message
 * @param array $data
 * @return Response
 */
function jsonSuccessResponse(string $message = '', array $data = []): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'code' => 200,
        'msg' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE));
}

/**
 * JSON失败响应
 * @param string $message
 * @param array $data
 * @param int $code
 * @return Response
 */
function jsonFailResponse(string $message = '', array $data = [], int $code = 100): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'code' => $code,
        'msg' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== ID生成函数 ====================

/**
 * 生成UUID
 * @return string
 */
function gen_uuid(): string
{
    $uuid['time_low'] = mt_rand(0, 0xffff) + (mt_rand(0, 0xffff) << 16);
    $uuid['time_mid'] = mt_rand(0, 0xffff);
    $uuid['time_hi'] = (4 << 12) | (mt_rand(0, 0x1000));
    $uuid['clock_seq_hi'] = (1 << 7) | (mt_rand(0, 128));
    $uuid['clock_seq_low'] = mt_rand(0, 255);

    for ($i = 0; $i < 6; $i++) {
        $uuid['node'][$i] = mt_rand(0, 255);
    }

    return sprintf('%08x-%04x-%04x-%02x%02x-%02x%02x%02x%02x%02x%02x',
        $uuid['time_low'],
        $uuid['time_mid'],
        $uuid['time_hi'],
        $uuid['clock_seq_hi'],
        $uuid['clock_seq_low'],
        $uuid['node'][0],
        $uuid['node'][1],
        $uuid['node'][2],
        $uuid['node'][3],
        $uuid['node'][4],
        $uuid['node'][5]
    );
}

/**
 * 生成唯一邀请码
 * @return string
 */
function createCode(): string
{
    $code = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    do {
        $rand = $code[rand(0, 25)] . strtoupper(dechex(date('m'))) . date('d') . substr(time(),
                -5) . substr(microtime(), 2,
                5) . sprintf('%02d', rand(0, 99));

        for ($a = md5($rand,
            true), $s = '0123456789ABCDEFGHIJKLMNOPQRSTUV', $d = '', $f = 0; $f < 8; $g = ord($a[$f]), $d .= $s[($g ^ ord($a[$f + 8])) - $g & 0x1F], $f++) {
        }
    } while (Player::query()->where('recommend_code', $d)->withTrashed()->exists());

    return $d;
}

/**
 * 生成订单号
 * @return string
 */
function createOrderNo(): string
{
    $yCode = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
    ];
    return $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(),
            -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
}

/**
 * 生成唯一15位数字ID
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

/**
 * 生成随机字符串
 * @param int $length
 * @return string
 */
function generateRandomString(int $length = 1): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
    }

    return $randomString;
}

// ==================== 验证函数 ====================

/**
 * 获取验证错误消息
 * @param AllOfException $e
 * @return mixed
 */
function getValidationMessages(AllOfException $e)
{
    $message = $e->getMessages([
        'notOptional' => trans('required', [], 'validator'),
        'notEmpty' => trans('required', [], 'validator'),
        'email' => trans('email', [], 'validator'),
        'idCard' => trans('idCard', [], 'validator'),
        'url' => trans('url', [], 'validator'),
        'number' => trans('number', [], 'validator'),
        'integer' => trans('integer', [], 'validator'),
        'float' => trans('float', [], 'validator'),
        'mobile' => trans('mobile', [], 'validator'),
        'length' => trans('length', [], 'validator'),
        'alpha' => trans('alpha', [], 'validator'),
        'alnum' => trans('alnum', [], 'validator'),
        'alphaDash' => trans('alphaDash', [], 'validator'),
        'chs' => trans('chs', [], 'validator'),
        'chsAlpha' => trans('chsAlpha', [], 'validator'),
        'chsAlphaNum' => trans('chsAlphaNum', [], 'validator'),
        'chsDash' => trans('chsDash', [], 'validator'),
        'equals' => trans('equals', [], 'validator'),
        'in' => trans('in', [], 'validator'),
        'image' => trans('image', [], 'validator'),
        'creditCard' => trans('creditCard', [], 'validator'),
        'digit' => trans('digit', [], 'validator'),
        'base64' => trans('base64', [], 'validator'),
        'arrayVal' => trans('arrayVal', [], 'validator'),
    ])['key'];
    $message = is_array($message) ? Arr::first($message) : $message;

    return $message ?? trans('validation_error', [], 'message');
}

// ==================== 数字格式化函数 ====================

/**
 * 保留两位小数（向下取整）
 * @param $number
 * @return float|int
 */
function floorToPointSecondNumber($number)
{
    return floor($number * 100) / 100;
}

// ==================== 时间日期函数 ====================

/**
 * 获取毫秒时间戳
 * @return float
 */
function millisecond(): float
{
    [$millisecond, $sec] = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($millisecond) + floatval($sec)) * 1000);
}

/**
 * 获取微秒时间戳
 * @return float
 */
function getMillisecond(): float
{
    [$t1, $t2] = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000000);
}

/**
 * 毫秒转时间格式
 * @param $millisecond
 * @return string
 */
function millisecondsToTimeFormat($millisecond): string
{
    $seconds = floor($millisecond / 1000000);
    $date = new DateTime();
    $date->setTimestamp($seconds);
    return $date->format('Y-m-d H:i:s');
}

/**
 * 日期格式化
 * @param $datetimeString
 * @return string
 */
function dateFormat($datetimeString): string
{
    return (new DateTime($datetimeString))->format('Y-m-d H:i:s');
}

/**
 * 获取日期范围条件（用于结算）
 * @param $type
 * @param $column
 * @return array
 */
function getDateWhere($type, $column): array
{
    $where = [];

    switch ($type) {
        case 1: // 今天
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->startOfDay())
                        ->where($column, '<=', Carbon::today()->endOfDay());
                }
            ];
            break;
        case 2: // 昨天
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::yesterday()->startOfDay())
                        ->where($column, '<=', Carbon::yesterday()->endOfDay());
                }
            ];
            break;
        case 3: // 本周
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->startOfWeek()->startOfDay())
                        ->where($column, '<=', Carbon::today()->endOfWeek()->endOfDay());
                }
            ];
            break;
        case 4: // 上周
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->subWeek()->startOfWeek()->startOfDay())
                        ->where($column, '<=', Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                }
            ];
            break;
        case 5: // 本月
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->firstOfMonth()->startOfDay())
                        ->where($column, '<=', Carbon::today()->endOfMonth()->endOfDay());
                }
            ];
            break;
        case 6: // 上月
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->subMonth()->firstOfMonth()->startOfDay())
                        ->where($column, '<=', Carbon::today()->subMonth()->endOfMonth()->endOfDay());
                }
            ];
            break;
        default:
            break;
    }

    return $where;
}

/**
 * 获取日期范围条件（通用版本）
 * @param $type
 * @param $column
 * @return array
 */
function getWhereDate($type, $column): array
{
    $where = [];

    switch ($type) {
        case 1: // 今天
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->startOfDay())
                        ->where($column, '<=', Carbon::today()->endOfDay());
                }
            ];
            break;
        case 2: // 昨天
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::yesterday()->startOfDay())
                        ->where($column, '<=', Carbon::yesterday()->endOfDay());
                }
            ];
            break;
        case 3: // 本周
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->startOfWeek()->format('Y-m-d'))
                        ->where($column, '<=', Carbon::today()->endOfWeek()->format('Y-m-d'));
                }
            ];
            break;
        case 4: // 上周
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->subWeek()->startOfWeek()->format('Y-m-d'))
                        ->where($column, '<=', Carbon::today()->subWeek()->endOfWeek()->format('Y-m-d'));
                }
            ];
            break;
        case 5: // 本月
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->firstOfMonth()->format('Y-m-d'))
                        ->where($column, '<=', Carbon::today()->endOfMonth()->format('Y-m-d'));
                }
            ];
            break;
        case 6: // 上月
            $where[] = [
                function ($query) use ($column) {
                    $query->where($column, '>=', Carbon::today()->subMonth()->firstOfMonth()->format('Y-m-d'))
                        ->where($column, '<=', Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d'));
                }
            ];
            break;
        default:
            break;
    }

    return $where;
}

// ==================== 文件上传函数 ====================

/**
 * 上传 base64 图片到 Google Cloud Storage
 *
 * @param string $base64Data base64 图片数据
 * @param string $directory 存储目录
 * @return string|false 成功返回文件URL，失败返回false
 * @throws Exception
 */
function uploadBase64ToGCS(string $base64Data, string $directory = 'avatar'): bool|string
{
    // 检查 base64 数据格式
    if (str_contains($base64Data, ';base64,')) {
        [$type, $base64Data] = explode(';', $base64Data);
        [, $base64Data] = explode(',', $base64Data);
        [, $imageType] = explode('/', $type);
    } else {
        // 如果没有头部信息，尝试检测图片类型
        $imageInfo = getimagesizefromstring(base64_decode($base64Data));
        if (!$imageInfo) {
            throw new Exception(trans('invalid_image', [], 'message'));
        }
        $imageType = image_type_to_extension($imageInfo[2], false);
    }

    // 验证图片类型
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array(strtolower($imageType), $allowedTypes)) {
        throw new Exception(trans('image_format_error', [], 'message'));
    }

    // 解码 base64 数据
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        throw new Exception(trans('base64_decode_error', [], 'message'));
    }

    // 检查图片大小 (2MB限制)
    if (strlen($imageData) >= 1024 * 1024 * 2) {
        throw new Exception(trans('image_size_error', [], 'message'));
    }

    try {
        // 生成唯一文件名
        $filename = uniqid() . '_' . time() . '.' . $imageType;
        $cloudPath = $directory . '/' . date('Ymd') . '/' . $filename;

        // 使用 Google Cloud Storage
        $storage = Filesystem::disk('google_oss');

        // 上传到 GCS
        $result = $storage->put($cloudPath, $imageData, [
            'metadata' => [
                'contentType' => 'image/' . $imageType,
                'cacheControl' => 'public, max-age=31536000',
            ]
        ]);

        if ($result) {
            return $storage->url($cloudPath);
        }

        return false;

    } catch (Exception $e) {
        Log::error('GCS Base64 上传失败: ' . $e->getMessage());
        return false;
    }
}

/**
 * 从 Google Cloud Storage 删除文件
 * @param $imagePath
 * @return void
 */
function deleteToGCS($imagePath): void
{
    try {
        $storage = Filesystem::disk('google_oss');
        if ($storage->exists($imagePath)) {
            $storage->delete($imagePath);
            Log::info('图片删除成功: ' . $imagePath);
        }
    } catch (Exception $e) {
        Log::error('图片删除失败: ' . $e->getMessage());
    }
}

// ==================== 其他工具函数 ====================

/**
 * 获取USDT汇率
 * @param $currency
 * @return mixed|null
 */
function getUSDTExchangeRate($currency)
{
    $currency = strtolower($currency);
    $cacheKey = 'usdt_rate_' . $currency;
    $cacheData = \support\Cache::get($cacheKey);
    if (!empty($cacheData)) {
        return $cacheData;
    }

    try {
        $response = Http::timeout(5)->get('https://api.coingecko.com/api/v3/simple/price?ids=tether&vs_currencies=' . $currency);
        if ($response->successful()) {
            $data = $response->json();
            \support\Cache::set($cacheKey, $data['tether'][$currency], 30 * 60);
            return $data['tether'][$currency];
        }
    } catch (Exception $e) {
        Log::error('获取USDT汇率失败: ' . $e->getMessage());
    }

    return null;
}

// ==================== 管理后台业务函数 ====================

/**
 * 发送WebSocket消息
 * @param $channels
 * @param $content
 * @param string $form
 * @return bool|mixed
 */
function sendSocketMessage($channels, $content, string $form = 'system')
{
    try {
        // 发送 WebSocket 消息
        $api = new \Webman\Push\Api(
            env('PUSH_API_URL', 'http://10.140.0.6:3232'),
            env('PUSH_APP_KEY', '20f94408fc4c52845f162e92a253c7a3'),
            env('PUSH_APP_SECRET', '3151f8648a6ccd9d4515386f34127e28')
        );
        return $api->trigger($channels, 'message', [
            'from_uid' => $form,
            'content' => json_encode($content)
        ]);
    } catch (Exception $e) {
        Log::error('sendSocketMessage', [$e->getMessage()]);
        return false;
    }
}

/**
 * 保存渠道财务记录
 * @param $target
 * @param $action
 * @return void
 */
function saveChannelFinancialRecord($target, $action)
{
    $channelFinancialRecord = new ChannelFinancialRecord();
    $channelFinancialRecord->action = $action;
    $channelFinancialRecord->department_id = Admin::user()->department_id ?? 0;
    $channelFinancialRecord->player_id = $target->player_id ?? 0;
    $channelFinancialRecord->target = $target->getTable();
    $channelFinancialRecord->target_id = $target->id;
    $channelFinancialRecord->user_id = Admin::id() ?? 0;
    $channelFinancialRecord->tradeno = $target->tradeno ?? '';
    $channelFinancialRecord->user_name = Admin::user()->username ?? '';
    $channelFinancialRecord->save();
}

/**
 * 创建游戏日志
 * @param $gameRecord
 * @param Machine $machine
 * @param Player $player
 * @param $openScore
 * @param int $money
 * @param float $afterGameAmount
 * @param $gift_score
 * @param float $beforeGameAmount
 * @return PlayerGameLog
 */
function createGameLog(
    $gameRecord,
    $machine,
    $player,
    $openScore,
    int $money,
    float $afterGameAmount,
    $gift_score,
    float $beforeGameAmount
) {
    if (empty($gameRecord) || $gameRecord->status == PlayerGameRecord::STATUS_END) {
        $gameRecord = new PlayerGameRecord();
        $gameRecord->game_id = $machine->machineCategory->game_id;
        $gameRecord->machine_id = $machine->id;
        $gameRecord->player_id = $player->id;
        $gameRecord->parent_player_id = $player->recommend_id ?? 0;
        $gameRecord->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
        $gameRecord->type = $machine->type;
        $gameRecord->code = $machine->code;
        $gameRecord->odds = $machine->odds_x . ':' . $machine->odds_y;
        $gameRecord->open_point = $openScore ?? 0;
        $gameRecord->open_amount = $money ?? 0;
        $gameRecord->after_game_amount = $afterGameAmount;
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
    $playerGameLog->control_open_point = $machine->control_open_point;
    $playerGameLog->open_point = $openScore;
    $playerGameLog->wash_point = 0;
    $playerGameLog->action = PlayerGameLog::ACTION_OPEN;
    $playerGameLog->gift_point = $gift_score ?? 0;
    $playerGameLog->game_amount = (0 - $money);
    $playerGameLog->before_game_amount = $beforeGameAmount;
    $playerGameLog->after_game_amount = $afterGameAmount;
    $playerGameLog->is_test = $player->is_test ?? 0;
    $playerGameLog->save();
    return $playerGameLog;
}

/**
 * 创建玩家投币记录
 * @param Player $player
 * @param PlayerGameLog $playerGameLog
 * @param Machine $machine
 * @param int $money
 * @param float $beforeGameAmount
 * @param float $afterGameAmount
 * @return void
 */
function createPlayerDeliveryRecord(
    $player,
    $playerGameLog,
    $machine,
    int $money,
    float $beforeGameAmount,
    float $afterGameAmount
): void {
    $playerDeliveryRecord = new PlayerDeliveryRecord;
    $playerDeliveryRecord->player_id = $player->id;
    $playerDeliveryRecord->department_id = $player->department_id;
    $playerDeliveryRecord->target = $playerGameLog->getTable();
    $playerDeliveryRecord->target_id = $playerGameLog->id;
    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_MACHINE_UP;
    $playerDeliveryRecord->machine_id = $machine->id;
    $playerDeliveryRecord->machine_name = $machine->name;
    $playerDeliveryRecord->machine_type = $machine->type;
    $playerDeliveryRecord->code = $machine->code;
    $playerDeliveryRecord->source = 'game_machine';
    $playerDeliveryRecord->amount = $money;
    $playerDeliveryRecord->amount_before = $beforeGameAmount;
    $playerDeliveryRecord->amount_after = $afterGameAmount;
    $playerDeliveryRecord->tradeno = '';
    $playerDeliveryRecord->remark = '';
    $playerDeliveryRecord->save();
}

/**
 * 执行推广分润结算
 * @param $id
 * @param int $userId
 * @param string $userName
 * @return void
 * @throws Exception
 */
function doSettlement($id, int $userId = 0, string $userName = '')
{
    /** @var PlayerPromoter $playerPromoter */
    $playerPromoter = PlayerPromoter::where('player_id', $id)->first();
    if (empty($playerPromoter)) {
        throw new Exception(trans('profit_amount_not_found', [], 'message'));
    }
    if ($playerPromoter->status == 0) {
        throw new Exception(trans('player_promoter_has_disable', [], 'message'));
    }
    if (!isset($playerPromoter->profit_amount)) {
        throw new Exception(trans('profit_amount_not_found', [], 'message'));
    }
    $profitAmount = PromoterProfitRecord::where('status', PromoterProfitRecord::STATUS_UNCOMPLETED)
        ->where('promoter_player_id', $id)
        ->first([
            DB::raw('SUM(`withdraw_amount`) as total_withdraw_amount'),
            DB::raw('SUM(`recharge_amount`) as total_recharge_amount'),
            DB::raw('SUM(`commission`) as total_commission_amount'),
            DB::raw('SUM(`bonus_amount`) as total_bonus_amount'),
            DB::raw('SUM(`admin_deduct_amount`) as total_admin_deduct_amount'),
            DB::raw('SUM(`admin_add_amount`) as total_admin_add_amount'),
            DB::raw('SUM(`present_amount`) as total_present_amount'),
            DB::raw('SUM(`machine_up_amount`) as total_machine_up_amount'),
            DB::raw('SUM(`machine_down_amount`) as total_machine_down_amount'),
            DB::raw('SUM(`lottery_amount`) as total_lottery_amount'),
            DB::raw('SUM(`profit_amount`) as total_profit_amount'),
            DB::raw('SUM(`player_profit_amount`) as total_player_profit_amount'),
            DB::raw('SUM(`game_amount`) as total_game_amount'),
        ])
        ->toArray();

    DB::beginTransaction();
    try {
        $promoterProfitSettlementRecord = new PromoterProfitSettlementRecord();
        $promoterProfitSettlementRecord->department_id = $playerPromoter->player->department_id;
        $promoterProfitSettlementRecord->promoter_player_id = $playerPromoter->player_id;
        $promoterProfitSettlementRecord->total_withdraw_amount = $profitAmount['total_withdraw_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_recharge_amount = $profitAmount['total_recharge_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_commission_amount = $profitAmount['total_commission_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_bonus_amount = $profitAmount['total_bonus_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_admin_deduct_amount = $profitAmount['total_admin_deduct_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_admin_add_amount = $profitAmount['total_admin_add_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_present_amount = $profitAmount['total_present_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_machine_up_amount = $profitAmount['total_machine_up_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_machine_down_amount = $profitAmount['total_machine_down_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_lottery_amount = $profitAmount['total_lottery_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_profit_amount = $profitAmount['total_profit_amount'];
        $promoterProfitSettlementRecord->total_player_profit_amount = $profitAmount['total_player_profit_amount'] ?? 0;
        $promoterProfitSettlementRecord->total_game_amount = $profitAmount['total_game_amount'] ?? 0;
        $promoterProfitSettlementRecord->last_profit_amount = $playerPromoter->last_profit_amount;
        $promoterProfitSettlementRecord->adjust_amount = $playerPromoter->adjust_amount;
        $promoterProfitSettlementRecord->type = PromoterProfitSettlementRecord::TYPE_SETTLEMENT;
        $promoterProfitSettlementRecord->tradeno = createOrderNo();
        $promoterProfitSettlementRecord->user_id = $userId;
        $promoterProfitSettlementRecord->user_name = $userName;
        $settlement = $amount = bcsub(bcadd($promoterProfitSettlementRecord->total_profit_amount,
            $promoterProfitSettlementRecord->adjust_amount, 2),
            $promoterProfitSettlementRecord->total_commission_amount, 2);
        if ($amount > 0) {
            if ($playerPromoter->settlement_amount < 0) {
                $diffAmount = bcadd($amount, $playerPromoter->settlement_amount, 2);
                $settlement = max($diffAmount, 0);
            }
        }
        $promoterProfitSettlementRecord->actual_amount = $settlement;
        $promoterProfitSettlementRecord->save();
        // 更新结算报表
        PromoterProfitRecord::where('status', PromoterProfitRecord::STATUS_UNCOMPLETED)
            ->where('promoter_player_id', $id)
            ->update([
                'status' => PromoterProfitRecord::STATUS_COMPLETED,
                'settlement_time' => date('Y-m-d H:i:s'),
                'settlement_tradeno' => $promoterProfitSettlementRecord->tradeno,
                'settlement_id' => $promoterProfitSettlementRecord->id,
            ]);
        // 结算后这些数据清零
        $playerPromoter->profit_amount = 0;
        $playerPromoter->player_profit_amount = 0;
        $playerPromoter->team_recharge_total_amount = 0;
        $playerPromoter->total_commission = 0;
        $playerPromoter->team_withdraw_total_amount = 0;
        $playerPromoter->adjust_amount = 0;
        // 更新数据
        $playerPromoter->team_profit_amount = bcsub($playerPromoter->team_profit_amount,
            $promoterProfitSettlementRecord->total_profit_amount, 2);
        $playerPromoter->last_profit_amount = $settlement;
        $playerPromoter->settlement_amount = bcadd($playerPromoter->settlement_amount, $amount, 2);
        $playerPromoter->team_settlement_amount = bcadd($playerPromoter->team_settlement_amount,
            $promoterProfitSettlementRecord->total_profit_amount, 2);
        $playerPromoter->last_settlement_time = date('Y-m-d', strtotime('-1 day'));

        if (!empty($playerPromoter->path)) {
            PlayerPromoter::where('player_id', '!=', $playerPromoter->player_id)
                ->whereIn('player_id', explode(',', $playerPromoter->path))
                ->update([
                    'team_profit_amount' => DB::raw("team_profit_amount - {$promoterProfitSettlementRecord->total_profit_amount}"),
                    'team_settlement_amount' => DB::raw("team_settlement_amount + $promoterProfitSettlementRecord->total_profit_amount"),
                ]);
        }
        if ($settlement > 0) {
            // 增加钱包余额
            $amountBefore = $playerPromoter->player->machine_wallet->money;
            $amountAfter = bcadd($amountBefore, $settlement, 2);
            $playerDeliveryRecord = new PlayerDeliveryRecord;
            $playerDeliveryRecord->player_id = $playerPromoter->player_id;
            $playerDeliveryRecord->department_id = $playerPromoter->department_id;
            $playerDeliveryRecord->target = $promoterProfitSettlementRecord->getTable();
            $playerDeliveryRecord->target_id = $promoterProfitSettlementRecord->id;
            $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_PROFIT;
            $playerDeliveryRecord->source = 'profit';
            $playerDeliveryRecord->amount = $settlement;
            $playerDeliveryRecord->amount_before = $amountBefore;
            $playerDeliveryRecord->amount_after = $amountAfter;
            $playerDeliveryRecord->tradeno = $promoterProfitSettlementRecord->tradeno ?? '';
            $playerDeliveryRecord->remark = '';
            $playerDeliveryRecord->save();

            $playerPromoter->player->machine_wallet->money = $amountAfter;
        }
        $playerPromoter->push();
        DB::commit();
    } catch (\Exception $e) {
        DB::rollback();
        throw new Exception($e->getMessage());
    }
}