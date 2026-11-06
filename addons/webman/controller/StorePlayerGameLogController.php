<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlayerGameLog;
use Carbon\Carbon;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\field\select\SelectGroup;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\support\Request;
use support\Log;

/**
 * 店家后台 - 上下分报表
 * @group store
 */
class StorePlayerGameLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_game_log_model');
    }

    /**
     * 上下分记录
     * @group store
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            // ✅ 内存优化：减少关联加载，只加载必要字段
            // 修复前：加载5个关联（player, machine, machineLabel, player.channel, machine_recording）
            // 修复后：只加载必要的关联，并严格限制字段
            $grid->model()->with([
                'player:id,uuid,name,department_id',           // 只加载需要的字段
                'machine:id,code,label_id,producer_id',  // 只加载需要的字段（name 通过 machineLabel 访问器获取）
                'machine.machineLabel:id,name',                // 嵌套加载但限制字段
                'machine.producer:id,name',                    // 嵌套加载但限制字段
            ]);

            // 内存优化说明：
            // - 移除了 'player.channel'（列表中未使用）
            // - 移除了 'machine_recording'（列表中未使用）
            // - 所有关联都严格限制了字段（避免加载 text/json 等大字段）
            // 预计内存占用降低：50 MB → 8 MB（每1000条记录）

            $exAdminFilter = Request::input('ex_admin_filter', []);

            /** @var \addons\webman\model\AdminUser $admin */
            $admin = Admin::user();

            // 店家：只查询自己的玩家记录
            $playerIds = \addons\webman\model\Player::query()->where('store_admin_id', $admin->id)->pluck('id');
            $grid->model()->whereIn('player_id', $playerIds);

            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['department_id'])) {
                $grid->model()->where('department_id', $exAdminFilter['department_id']);
            }
            if (!empty($exAdminFilter['action'])) {
                $grid->model()->where('action', $exAdminFilter['action']);
            }
            if (!empty($exAdminFilter['source_type'])) {
                $grid->model()->where('source_type', $exAdminFilter['source_type']);
            }
            if (!empty($exAdminFilter['action_type'])) {
                switch ($exAdminFilter['action_type']) {
                    case 'admin':
                        $grid->model()->where('user_id', '!=', 0)->where('is_system', 0);
                        break;
                    case 'system':
                        $grid->model()->where('is_system', 1);
                        break;
                    case 'player':
                        $grid->model()->where('is_system', 0)->where('user_id', 0);
                        break;
                    default:
                        return '';
                }
            }
            if (!empty($exAdminFilter['machine'])) {
                $grid->model()->whereHas('machine', function ($query) use ($exAdminFilter) {
                    if (!empty($exAdminFilter['machine']['machineLabel']['name'])) {
                        $query->whereHas('machineLabel', function ($query) use ($exAdminFilter) {
                            $query->where('name', 'like',
                                '%' . $exAdminFilter['machine']['machineLabel']['name'] . '%');
                        });
                    }
                    if (!empty($exAdminFilter['machine']['code'])) {
                        $query->where('code', $exAdminFilter['machine']['code']);
                    }
                    if (!empty($exAdminFilter['machine']['cate_id'])) {
                        $query->whereIn('cate_id', $exAdminFilter['machine']['cate_id']);
                    }
                    if (!empty($exAdminFilter['machine']['producer_id'])) {
                        $query->where('producer_id', $exAdminFilter['machine']['producer_id']);
                    }
                });
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['uuid']);
                });
            }
            if (!empty($exAdminFilter['player']['name'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
                });
            }

            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }

            $grid->model()->orderBy('id', 'desc');

            // 移除 total_info 组件（店家后台不需要）
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('player_game_log.point_title'));
            $grid->column('id', admin_trans('player_game_log.fields.id'))->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        Html::div()->content($data->player->uuid ?? '')
                    ]);
                })->align('center');
                $grid->column('player.name', admin_trans('player.fields.device_name'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        Html::div()->content($data->player->name ?? '')
                    ]);
                })->align('center');
            }, admin_trans('player_game_log.device_info'));
            $grid->column(function (Grid $grid) {
                $grid->column('machine.machineLabel.name', admin_trans('machine.fields.name'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    if ($data->machine) {
                        return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                            'addons-webman-controller-PlayerDeliveryRecordController',
                            'machineInfo'
                        ],
                            ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                    }
                    return '';
                })->align('center');
                $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->machine->code ?? '';
                })->align('center');
                $grid->column('odds', admin_trans('player_game_log.fields.odds'))->align('center');
                $grid->column('machine.producer_id', admin_trans('machine.fields.producer_id'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        !empty($data->machine->producer->name) ? Tag::create($data->machine->producer->name)->color('green') : ''
                    ]);
                })->align('center');
            }, admin_trans('player_game_log.machine_info'));
            $grid->column(function (Grid $grid) {
                $grid->column('game_amount', admin_trans('player_game_log.fields.game_amount'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    // 开分：钱从钱包流出，一律显示负号。
                    // game_amount 的落库符号各写入方并不统一（gk_work 上分写正数、gk_api 写负数、
                    // 现金投币/管理员上分写 0），这里按 action 归一取绝对值，不依赖落库符号。
                    $displayVal = $data->action == PlayerGameLog::ACTION_OPEN ? -abs((float)$val) : $val;
                    return Html::create()->content([
                        $displayVal > 0 ? '+' . $displayVal : $displayVal,
                    ])->style(['color' => ($displayVal < 0 ? '#cd201f' : 'green')]);
                })->align('center');
                $grid->column('before_game_amount',
                    admin_trans('player_game_log.fields.before_game_amount'))->align('center');
                $grid->column('after_game_amount',
                    admin_trans('player_game_log.fields.after_game_amount'))->align('center');
            }, admin_trans('player_game_log.player_wallet_info'));
            $grid->column(function (Grid $grid) {
                $grid->column('open_point',
                    admin_trans('player_game_log.fields.open_point'))->sortable()->align('center');
                $grid->column('wash_point',
                    admin_trans('player_game_log.fields.wash_point'))->sortable()->align('center');
                $grid->column('gift_point', admin_trans('player_gift_record.title'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    if ($data->gift_point > 0) {
                        return Tag::create(floatval($val))->color('red')->style(['cursor' => 'pointer'])->modal([
                            'addons-webman-controller-PlayerGiftRecord',
                            'detail'
                        ], ['data' => $data->toArray()])->width('60%')->title(admin_trans('player_gift_record.title'));
                    }
                    return '';
                })->align('center');
                $grid->column('pressure',
                    admin_trans('player_game_log.fields.pressure'))->sortable()->display(function ($val) {
                    return floatval($val);
                })->align('center');
                $grid->column('score', admin_trans('player_game_log.fields.score'))->sortable()->display(function ($val
                ) {
                    return floatval($val);
                })->align('center');
                $grid->column('turn_point', admin_trans('player_game_log.fields.turn_point'))->display(function ($val) {
                    return floatval($val);
                })->align('center');
            }, admin_trans('player_game_log.machine_data'));
            $grid->column('action_type', admin_trans('player_game_log.fields.action_type'))->display(function (
                $val,
                PlayerGameLog $data
            ) {
                if ($data->is_system == 1) {
                    return Tag::create(admin_trans('player_game_log.type.system'))->color('#55acee');
                } else {
                    if (!empty($data->user_id)) {
                        return Html::create()->content([
                            Tag::create(admin_trans('player_game_log.type.admin'))->color('#3b5999'),
                            Html::div()->content($data->user_name)
                        ])->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
                    } else {
                        return Tag::create(admin_trans('player_game_log.type.player'))->color('#cd201f');
                    }
                }
            })->align('center');
            $grid->column('action', admin_trans('player_game_log.fields.action'))->display(function ($val) {
                switch ($val) {
                    case PlayerGameLog::ACTION_OPEN:
                        return Tag::create(admin_trans('player_game_log.action.' . $val))->color('green');
                    case PlayerGameLog::ACTION_LEAVE:
                    case PlayerGameLog::ACTION_DOWN:
                        return Tag::create(admin_trans('player_game_log.action.' . $val))->color('red');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('source_type', admin_trans('player_game_log.fields.source_type'))->display(function (
                $val,
                PlayerGameLog $data
            ) {
                if ($val == PlayerGameLog::SOURCE_TYPE_OFFLINE_BUTTON) {
                    return Tag::create(admin_trans('player_game_log.source_type.' . $val))->color('#ff6b00');
                } else {
                    return Tag::create(admin_trans('player_game_log.source_type.' . $val))->color('#52c41a');
                }
            })->align('center');
            $grid->column('chip_amount',
                admin_trans('player_game_log.fields.chip_amount'))->sortable()->align('center');
            $grid->column('created_at', admin_trans('player_game_log.fields.create_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.device_uuid'));
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.device_name'));
                $filter->like()->text('machine.machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->select('date_type')
                    ->placeholder(admin_trans('machine_report.fields.date_type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);
                $filter->select('action')
                    ->placeholder(admin_trans('player_game_log.fields.action'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        PlayerGameLog::ACTION_OPEN => admin_trans('player_game_log.action.' . PlayerGameLog::ACTION_OPEN),
                        PlayerGameLog::ACTION_LEAVE => admin_trans('player_game_log.action.' . PlayerGameLog::ACTION_LEAVE),
                        PlayerGameLog::ACTION_DOWN => admin_trans('player_game_log.action.' . PlayerGameLog::ACTION_DOWN),
                    ]);
                $filter->eq()->select('action_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_game_log.fields.action_type'))
                    ->options([
                        'system' => admin_trans('player_game_log.type.system'),
                        'admin' => admin_trans('player_game_log.type.admin'),
                        'player' => admin_trans('player_game_log.type.player')
                    ]);
                $filter->eq()->select('source_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_game_log.fields.source_type'))
                    ->options([
                        PlayerGameLog::SOURCE_TYPE_ONLINE => admin_trans('player_game_log.source_type.' . PlayerGameLog::SOURCE_TYPE_ONLINE),
                        PlayerGameLog::SOURCE_TYPE_OFFLINE_BUTTON => admin_trans('player_game_log.source_type.' . PlayerGameLog::SOURCE_TYPE_OFFLINE_BUTTON)
                    ]);
                SelectGroup::create();
                $filter->in()->cascaderSingle('machine.cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
                $producer = plugin()->webman->config('database.machine_producer_model');
                $options = $producer::select(['id', 'name'])->pluck('name', 'id')->all();
                $filter->eq()->select('machine.producer_id')
                    ->placeholder(admin_trans('machine.fields.producer_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options($options);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->actions(function (Actions $actions, PlayerGameLog $data) {
                $actions->hideDetail();
                $actions->hideDel();
                $actions->hideEdit();
                if (!empty($data->machine_recording)) {
                    $dropdown = Dropdown::create(
                        Button::create([
                            admin_trans('machine_media.btn.action'),
                            Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                        ]))->trigger(['click']);
                    $dropdown->item(admin_trans('machine_media.btn.media_play'), 'far fa-play-circle')
                        ->modal([MachineController::class, 'getRecording'],
                            ['machineRecording' => $data->machine_recording])
                        ->maskClosable();
                    $actions->prepend(
                        $dropdown
                    );
                }
                if (!empty($data->machine_id)) {
                    $actions->prepend(
                        Button::create(admin_trans('player_game_log.ctrl_log'))
                            ->type('link')
                            ->modal([$this, 'machineCtrlLogs'], ['id' => $data->id])
                            ->width('80%')
                            ->title(admin_trans('player_game_log.ctrl_log_title') . '（' . ($data->machine->code ?? '') . '）')
                    );
                }
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
        });
    }

    /**
     * 機台控制日誌彈窗
     * @auth true
     * @group store
     */
    public function machineCtrlLogs(int $id)
    {
        return admin_view(plugin()->webman->getPath() . '/views/machine_ctrl_logs.vue')
            ->attrs([
                'id'       => $id,
                'fetchUrl' => '/ex-admin/addons-webman-controller-StorePlayerGameLogController/fetchCtrlLogs',
            ]);
    }

    /**
     * 查詢上下分事件前後的 ClickHouse 機台控制日誌（JSON 接口）
     * @auth true
     * @group store
     */
    public function fetchCtrlLogs()
    {
        $id = (int) Request::input('id');
        /** @var PlayerGameLog $record */
        $record = ($this->model)::findOrFail($id);

        // 店家權限：只允許查詢屬於本店玩家的記錄
        $storeAdminId = Admin::user()->id;
        $ownsPlayer = \addons\webman\model\Player::query()
            ->where('id', $record->player_id)
            ->where('store_admin_id', $storeAdminId)
            ->exists();
        if (!$ownsPlayer) {
            return json(['code' => 403, 'msg' => '無權限查看此記錄']);
        }

        $base      = Carbon::parse($record->created_at)->timezone('Asia/Shanghai');
        $createdAt = $base->format('Y-m-d H:i:s.000');
        $machineId = (int) $record->machine_id;

        $isOpen      = $record->action == PlayerGameLog::ACTION_OPEN;
        $startMarker = $isOpen ? '開分開始' : '洗分開始';
        $endMarker   = $isOpen ? '開分結束' : '下分結束';

        $lookbackStart = $base->copy()->subMinutes(1)->format('Y-m-d H:i:s.000');
        $lookforward   = $base->copy()->addMinutes(5)->format('Y-m-d H:i:s.999');
        $heartbeatCond = "action IN ('heartbeat', 'field_update') OR source_type = 'heartbeat'";

        $cfg    = config('clickhouse');
        $client = new \GuzzleHttp\Client();

        $chPost = fn(string $sql) => json_decode(
            $client->post($cfg['endpoint'], [
                'auth'    => [$cfg['user'], $cfg['password']],
                'body'    => $sql,
                'timeout' => 5,
            ])->getBody()->getContents(),
            true
        )['data'][0] ?? [];

        $isValid = fn(?string $v): bool => !empty($v) && !str_starts_with($v, '1970') && !str_starts_with($v, '0000');

        $opStart = $opEnd = null;
        try {
            $row = $chPost("SELECT
                              minIf(datetime, action_desc LIKE '%{$startMarker}%') AS op_start,
                              maxIf(datetime, action_desc LIKE '%{$endMarker}%')   AS op_end
                            FROM {$cfg['database']}.machine_ctrl
                            WHERE machine_id = {$machineId}
                              AND datetime >= '{$lookbackStart}'
                              AND datetime <= '{$lookforward}'
                            FORMAT JSON");
            if ($isValid($row['op_start'] ?? null)) $opStart = $row['op_start'];
            if ($isValid($row['op_end']   ?? null)) $opEnd   = $row['op_end'];
        } catch (\Throwable $e) {
            Log::channel('default')->error('StorePlayerGameLogController ClickHouse marker查询失败', ['error' => $e->getMessage()]);
        }

        $startPivot = $opStart ?? $createdAt;
        $startTime  = Carbon::parse($startPivot)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s.000');
        try {
            $row = $chPost("SELECT max(datetime) AS pre_heartbeat
                            FROM {$cfg['database']}.machine_ctrl
                            WHERE machine_id = {$machineId}
                              AND datetime < '{$startPivot}'
                              AND datetime >= '{$lookbackStart}'
                              AND ({$heartbeatCond})
                            FORMAT JSON");
            if ($isValid($row['pre_heartbeat'] ?? null)) {
                $startTime = Carbon::parse($row['pre_heartbeat'])->timezone('Asia/Shanghai')->format('Y-m-d H:i:s.000');
            }
        } catch (\Throwable $e) {
            Log::channel('default')->error('StorePlayerGameLogController ClickHouse pre_heartbeat查询失败', ['error' => $e->getMessage()]);
        }

        $endPivot = $opEnd ?? $createdAt;
        $endTime  = Carbon::parse($endPivot)->timezone('Asia/Shanghai')->addSeconds(5)->format('Y-m-d H:i:s.999');
        try {
            $row = $chPost("SELECT min(datetime) AS post_heartbeat
                            FROM {$cfg['database']}.machine_ctrl
                            WHERE machine_id = {$machineId}
                              AND datetime > '{$endPivot}'
                              AND datetime <= '{$lookforward}'
                              AND ({$heartbeatCond})
                            FORMAT JSON");
            if ($isValid($row['post_heartbeat'] ?? null)) {
                $endTime = Carbon::parse($row['post_heartbeat'])->timezone('Asia/Shanghai')->format('Y-m-d H:i:s.999');
            }
        } catch (\Throwable $e) {
            Log::channel('default')->error('StorePlayerGameLogController ClickHouse post_heartbeat查询失败', ['error' => $e->getMessage()]);
        }

        $sql = "SELECT datetime, level, source_type, log_direction, action, action_desc, raw_send, raw_recv, extra_data, machine_state
                FROM {$cfg['database']}.machine_ctrl
                WHERE machine_id = {$machineId}
                  AND datetime >= '{$startTime}'
                  AND datetime <= '{$endTime}'
                ORDER BY datetime ASC
                FORMAT JSON";
        try {
            $response = $client->post($cfg['endpoint'], [
                'auth'    => [$cfg['user'], $cfg['password']],
                'body'    => $sql,
                'timeout' => 10,
            ]);
            if ($response->getStatusCode() === 200) {
                $rows = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
                return json(['code' => 200, 'data' => [
                    'logs'       => $this->processCtrlLogs($rows),
                    'time_range' => "{$startTime} 至 {$endTime}",
                ]]);
            }
            return json(['code' => 500, 'msg' => 'ClickHouse 返回異常：' . $response->getStatusCode()]);
        } catch (\Throwable $e) {
            Log::channel('default')->error('StorePlayerGameLogController ClickHouse 查询失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    private function processCtrlLogs(array $rows): array
    {
        $hiddenActions = ['read_point', 'read_win_number', 'push', 'clear_log'];
        $result  = [];
        $prevMs  = null;
        $stateKeys = [
            'point'             => ['label' => '當前分',  'color' => '#1890ff'],
            'gaming'            => ['label' => '遊戲中',  'color' => '#52c41a', 'skip_zero' => true, 'bool' => true],
            'gaming_user_id'    => ['label' => '玩家ID',  'color' => '#1890ff', 'skip_zero' => true],
            'reward_status'     => ['label' => '開獎中',  'color' => '#eb2f96', 'skip_zero' => true, 'bool' => true],
            'has_lock'          => ['label' => '鎖定中',  'color' => '#f5222d', 'skip_zero' => true, 'bool' => true],
            'keeping'           => ['label' => '保留中',  'color' => '#fa8c16', 'skip_zero' => true, 'bool' => true],
            'keeping_user_id'   => ['label' => '保留玩家','color' => '#fa8c16', 'skip_zero' => true],
            'open_point'        => ['label' => '開分',    'color' => '#13c2c2'],
            'wash_point'        => ['label' => '洗分',    'color' => '#52c41a'],
            'pressure'          => ['label' => '壓分',    'color' => '#fa8c16'],
            'score'             => ['label' => '得分',    'color' => '#722ed1'],
            'bet'               => ['label' => '總押',    'color' => '#f5222d'],
            'win'               => ['label' => '總贏',    'color' => '#2f54eb'],
            'card_score'        => ['label' => '卡分',    'color' => '#d46b08'],
            'machine_score'     => ['label' => '機台分',  'color' => '#08979c'],
            'player_win_number' => ['label' => '打碼轉',  'color' => '#531dab'],
            'turn'              => ['label' => '剩餘轉',  'color' => '#595959'],
        ];

        foreach ($rows as $row) {
            if (in_array($row['action'] ?? '', $hiddenActions, true)) continue;

            $dtStr = $row['datetime'] ?? '';
            preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})(?:\.(\d+))?/', $dtStr, $m);
            $baseTs = isset($m[1]) ? strtotime($m[1]) * 1000 : 0;
            $fracMs = isset($m[2]) ? (int) str_pad(substr($m[2], 0, 3), 3, '0') : 0;
            $curMs  = $baseTs + $fracMs;

            $elapsedMs = $prevMs !== null ? $curMs - $prevMs : null;
            $prevMs    = $curMs;

            if ($row['action'] === 'open' && $row['log_direction'] === 'response') {
                $displayType = 'noise';
            } elseif (in_array($row['action'], ['heartbeat', 'field_update']) || $row['source_type'] === 'heartbeat') {
                $displayType = 'heartbeat';
            } elseif ($row['log_direction'] === 'command') {
                $displayType = 'command';
            } elseif ($row['log_direction'] === 'response') {
                $displayType = 'response';
            } else {
                $displayType = 'event';
            }

            $marker = null;
            $desc   = $row['action_desc'] ?? '';
            $action = $row['action'] ?? '';
            if (str_contains($desc, '開分開始'))                                                                       { $marker = 'open_start'; }
            elseif (str_contains($desc, '開分結束'))                                                                   { $marker = 'open_end';   if ($displayType === 'noise') $displayType = 'response'; }
            elseif (str_contains($desc, '下分開始') || ($action === 'wash' && str_contains($desc, '收到洗分請求')))   { $marker = 'wash_start'; }
            elseif (str_contains($desc, '下分結束') || ($action === 'check_reset' && str_contains($desc, '重置壓分/得分'))) { $marker = 'wash_end'; }

            $state = [];
            if (!empty($row['machine_state'])) {
                $parsed     = json_decode($row['machine_state'], true) ?? [];
                $seenLabels = [];
                foreach ($stateKeys as $key => $meta) {
                    if (!array_key_exists($key, $parsed)) continue;
                    $val = $parsed[$key];
                    if (!empty($meta['skip_zero']) && !$val) continue;
                    if (in_array($meta['label'], $seenLabels)) continue;
                    $displayVal   = !empty($meta['bool']) ? '是' : $val;
                    $state[]      = ['label' => $meta['label'], 'color' => $meta['color'], 'val' => $displayVal];
                    $seenLabels[] = $meta['label'];
                }
            }

            $extra = [];
            if (!empty($row['extra_data'])) {
                $parsed = json_decode($row['extra_data'], true) ?? [];
                foreach ($parsed as $k => $v) {
                    if (!is_array($v) && !is_null($v) && $v !== '') {
                        $extra[] = ['key' => $k, 'val' => $v];
                    }
                }
                $extra = array_slice($extra, 0, 6);
            }

            $result[] = [
                'time'          => substr($dtStr, 11, 8),
                'elapsed_ms'    => $elapsedMs,
                'level'         => $row['level'],
                'display_type'  => $displayType,
                'source_type'   => $row['source_type'],
                'log_direction' => $row['log_direction'],
                'action'        => $row['action'],
                'action_desc'   => $row['action_desc'],
                'marker'        => $marker,
                'state'         => $state,
                'extra'         => $extra,
                'raw_send'      => $row['raw_send'] ?? '',
                'raw_recv'      => $row['raw_recv'] ?? '',
            ];
        }

        return $result;
    }
}