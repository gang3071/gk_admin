<?php

namespace addons\webman\controller;

use addons\webman\model\Player;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerPlatformCash;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\field\select\SelectGroup;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 游戏日志
 * @auth true
 */
class PlayerGameLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_game_log_model');
    }

    /**
     * 上下分记录
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with([
                'player',
                'machine' => function ($query) {
                    return $query->with(['machineLabel']);
                },
                'player.channel',
                'machine_recording'
            ]);
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $this->applySearchFilter($grid->model(), $exAdminFilter);

            $grid->model()->orderBy('id', 'desc');
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/game_vendor_total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'api_url' => 'ex-admin/addons-webman-controller-PlayerGameLogController/totalInfo',
                    'trans' => [
                        'panelHeader' => admin_trans('player_game_log.stats.panel_header'),
                        'loading' => admin_trans('player_game_log.stats.loading'),
                        'refresh' => admin_trans('player_game_log.stats.refresh'),
                        'loadError' => admin_trans('player_game_log.stats.load_error'),
                        'retry' => admin_trans('player_game_log.stats.retry'),
                        'clickToView' => admin_trans('player_game_log.stats.click_to_view'),
                        'loadFailedMsg' => admin_trans('player_game_log.stats.load_failed_msg'),
                    ],
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('player_game_log.point_title'));
            $grid->column('id', admin_trans('player_game_log.fields.id'))->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    if ($data->player_id == 0 || !$data->player) {
                        return Html::create()->content([
                            Tag::create(admin_trans('player_game_log.no_player'))->color('gray')
                        ]);
                    }
                    return Html::create()->content([
                        Html::div()->content($data->player->uuid)
                    ]);
                })->align('center');
                $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerGameLog $data) {
                    if ($data->player_id == 0 || !$data->player) {
                        return '';
                    }
                    return Html::create()->content([
                        (isset($data->player->is_test) && $data->player->is_test == 1) ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                    ]);
                })->align('center');
                $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->player->phone ?? '';
                })->align('center');
                $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    // 优先从PlayerGameLog自己的channel关联获取（支持无玩家情况）
                    // 无玩家时（player_id=0），机台已绑定门店，可通过department_id获取渠道
                    if ($data->channel) {
                        return $data->channel->name;
                    }
                    // 降级：从玩家的channel获取
                    return $data->player->channel->name ?? '';
                })->width('150px')->align('center');
            }, admin_trans('player_game_log.player_info'));

            $grid->column('recommend_promoter.name', admin_trans('player.fields.recommend_promoter_name'))
                ->display(function ($value, $data) {
                    if (isset($data['player']['recommend_promoter'])) {
                        $promoterUuid = $data['player']['recommend_promoter']['player']['uuid'] ?? '';
                        return Html::create(Str::limit($promoterUuid, 20, ' (...)'))
                            ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                            ->modal(
                                [$this, 'playerInfo'],
                                ['player_id' => $data['player']['recommend_promoter']['player_id']]
                            )
                            ->width('60%')
                            ->title(admin_trans('player.fields.uuid') . ': ' . $promoterUuid);
                    } else {
                        return admin_trans('player.no_promoter');
                    }
                })
                ->align('center')->width(80);
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
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('machine.machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('recommend_id')->placeholder(admin_trans('player.fields.recommend_promoter_name'));
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
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $producer = plugin()->webman->config('database.machine_producer_model');
                $options = $producer::select(['id', 'name'])->pluck('name', 'id')->all();
                $filter->eq()->select('machine.producer_id')
                    ->placeholder(admin_trans('machine.fields.producer_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options($options);
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
                    $actions->prepend($dropdown);
                }
                if (!empty($data->machine_id)) {
                    $actions->prepend(
                        Button::create('控制日志')
                            ->type('link')
                            ->modal([$this, 'machineCtrlLogs'], ['id' => $data->id])
                            ->width('80%')
                            ->title('机台控制日志（' . ($data->machine->code ?? '') . '）')
                    );
                }
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
        });
    }

    /**
     * 机台报表明细
     * @auth true
     */
    public function list(): Grid
    {
        $requestFilter = \request()->all();
        return Grid::create(new $this->model(), function (Grid $grid) use ($requestFilter) {
            $grid->model()->with(['player', 'player.channel', 'machine'])->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (isset($requestFilter['machine_id']) && $requestFilter['machine_id']) {
                $grid->model()->where('machine_id', $requestFilter['machine_id']);
            }
            if (!empty($requestFilter['odds'])) {
                $grid->model()->where('odds', $requestFilter['odds']);
            }
            if (!empty($requestFilter['start_date'])) {
                $grid->model()->where('created_at', '>=', parseFilterTime($requestFilter['start_date']));
            }
            if (!empty($requestFilter['end_date'])) {
                $grid->model()->where('created_at', '<=', parseFilterTime($requestFilter['end_date'], true));
            }
            //筛选出用户
            if (!empty($requestFilter['is_test'])) {
                $grid->model()->where('player.is_test', $requestFilter['is_test']);
            }
            $grid->model()->when(!empty($requestFilter['is_test']), function ($query) use ($requestFilter) {
                return $query->whereHas('player', function ($subQuery) use ($requestFilter) {
                    $subQuery->where('is_test', $requestFilter['is_test']);
                });
            });
            if (isset($requestFilter['date_type'])) {
                $grid->model()->where(getDateWhere($requestFilter['date_type'], 'created_at'));
            }
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('player_game_log.point_title'));
            $grid->column(function (Grid $grid) {
                $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->player->phone ?? '';
                })->align('center');
                $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->player->uuid ?? '';
                })->align('center');
                $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    // 优先从PlayerGameLog自己的channel关联获取（支持无玩家情况）
                    // 无玩家时（player_id=0），机台已绑定门店，可通过department_id获取渠道
                    if ($data->channel) {
                        return $data->channel->name;
                    }
                    // 降级：从玩家的channel获取
                    return $data->player->channel->name ?? '';
                })->width('150px')->align('center');
            }, admin_trans('player_game_log.player_info'));
            $grid->column(function (Grid $grid) {
                $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    if ($data->machine) {
                        return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                            'addons-webman-controller-PlayerDeliveryRecordController',
                            'machineInfo'
                        ], ['data' => $data->machine->toArray()])->width('60%');
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
                $grid->column('open_point', admin_trans('player_game_log.fields.open_point'))->align('center');
                $grid->column('wash_point', admin_trans('player_game_log.fields.wash_point'))->align('center');
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
                $grid->column('pressure', admin_trans('player_game_log.fields.pressure'))->display(function ($val) {
                    return floatval($val);
                })->align('center');
                $grid->column('score', admin_trans('player_game_log.fields.score'))->display(function ($val) {
                    return floatval($val);
                })->align('center');
                $grid->column('turn_point', admin_trans('player_game_log.fields.turn_point'))->display(function ($val) {
                    return floatval($val);
                })->align('center');
            }, admin_trans('player_game_log.machine_data'));
            $grid->column('created_at', admin_trans('player_game_log.fields.create_at'))->align('center');
            $grid->filter(function (Filter $filter) use ($requestFilter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
        });
    }

    /**
     * 机台控制日志弹窗（Vue 组件）
     */
    public function machineCtrlLogs(int $id)
    {
        return admin_view(plugin()->webman->getPath() . '/views/machine_ctrl_logs.vue')
            ->attrs(['id' => $id]);
    }

    /**
     * 查询上下分事件前后的 ClickHouse 机台控制日志（JSON 接口）
     */
    public function fetchCtrlLogs()
    {
        $id = (int) Request::input('id');
        /** @var PlayerGameLog $record */
        $record = ($this->model)::findOrFail($id);

        $base      = Carbon::parse($record->created_at)->timezone('Asia/Shanghai');
        $createdAt = $base->format('Y-m-d H:i:s.000');
        $machineId = (int) $record->machine_id;

        $isOpen      = $record->action == PlayerGameLog::ACTION_OPEN;
        $startMarker = $isOpen ? '開分開始' : '洗分開始';
        $endMarker   = $isOpen ? '開分結束' : '下分結束';

        // 弃台/洗分操作：machine ctrl 在 created_at 之后才执行，向前后各找 5 分钟
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

        // Step 1: 在 created_at ±window 内找操作起止 marker
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
            \support\Log::channel('default')->error('ClickHouse marker查询失败', ['error' => $e->getMessage()]);
        }

        // Step 2: 找 op_start 之前最近一条心跳
        $startPivot = $opStart ?? $createdAt;
        $startTime  = Carbon::parse($startPivot)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s.000'); // 兜底
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
            \support\Log::channel('default')->error('ClickHouse pre_heartbeat查询失败', ['error' => $e->getMessage()]);
        }

        // Step 3: 找 op_end 之后最近一条心跳
        $endPivot = $opEnd ?? $createdAt;
        $endTime  = Carbon::parse($endPivot)->timezone('Asia/Shanghai')->addSeconds(5)->format('Y-m-d H:i:s.999'); // 兜底
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
            \support\Log::channel('default')->error('ClickHouse post_heartbeat查询失败', ['error' => $e->getMessage()]);
        }

        // Step 4: 主查询
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
            return json(['code' => 500, 'msg' => 'ClickHouse 返回异常：' . $response->getStatusCode()]);
        } catch (\Throwable $e) {
            \support\Log::channel('default')->error('ClickHouse 查询失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 将 ClickHouse 原始行结构化处理，供前端直接渲染
     */
    private function processCtrlLogs(array $rows): array
    {
        // 底层轮询指令：仅协议层噪音，对管理后台无业务价值，直接跳过
        $hiddenActions = ['read_point', 'read_win_number', 'push', 'clear_log'];

        $result  = [];
        $prevMs  = null;

        // 机台状态关键字段定义（字段名与 MachineLogger::normalizeMachineState 输出一致）
        // skip_zero=true：值为 0/空 时不展示（避免正常态产生噪音）
        // bool=true：值非零时显示「是」而非原始数字
        $stateKeys = [
            // 通用字段（所有机台类型）
            'point'             => ['label' => '當前分',  'color' => '#1890ff'],
            'gaming'            => ['label' => '遊戲中',  'color' => '#52c41a', 'skip_zero' => true, 'bool' => true],
            'gaming_user_id'    => ['label' => '玩家ID',  'color' => '#1890ff', 'skip_zero' => true],
            'reward_status'     => ['label' => '開獎中',  'color' => '#eb2f96', 'skip_zero' => true, 'bool' => true],
            'has_lock'          => ['label' => '鎖定中',  'color' => '#f5222d', 'skip_zero' => true, 'bool' => true],
            'keeping'           => ['label' => '保留中',  'color' => '#fa8c16', 'skip_zero' => true, 'bool' => true],
            'keeping_user_id'   => ['label' => '保留玩家','color' => '#fa8c16', 'skip_zero' => true],
            'open_point'        => ['label' => '開分',    'color' => '#13c2c2'],
            'wash_point'        => ['label' => '洗分',    'color' => '#52c41a'],
            // Slot 在线
            'pressure'          => ['label' => '壓分',    'color' => '#fa8c16'],
            'score'             => ['label' => '得分',    'color' => '#722ed1'],
            'bet'               => ['label' => '總押',    'color' => '#f5222d'],
            'win'               => ['label' => '總贏',    'color' => '#2f54eb'],
            // Slot 离线
            'card_score'        => ['label' => '卡分',    'color' => '#d46b08'],
            'machine_score'     => ['label' => '機台分',  'color' => '#08979c'],
            // Steel Ball
            'player_win_number' => ['label' => '打碼轉',  'color' => '#531dab'],
            'turn'              => ['label' => '剩餘轉',  'color' => '#595959'],
        ];

        foreach ($rows as $row) {
            if (in_array($row['action'] ?? '', $hiddenActions, true)) continue;

            // 解析毫秒时间戳（datetime 格式：2026-10-23 17:38:25.123）
            $dtStr = $row['datetime'] ?? '';
            preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})(?:\.(\d+))?/', $dtStr, $m);
            $baseTs = isset($m[1]) ? strtotime($m[1]) * 1000 : 0;
            $fracMs = isset($m[2]) ? (int) str_pad(substr($m[2], 0, 3), 3, '0') : 0;
            $curMs  = $baseTs + $fracMs;

            $elapsedMs = $prevMs !== null ? $curMs - $prevMs : null;
            $prevMs    = $curMs;

            // display_type 分类
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

            // 偵測開分/洗分 開始/結束標記（用於前端醒目顯示）
            $marker = null;
            $desc = $row['action_desc'] ?? '';
            if (str_contains($desc, '開分開始'))      { $marker = 'open_start'; }
            elseif (str_contains($desc, '開分結束'))  { $marker = 'open_end';   if ($displayType === 'noise') $displayType = 'response'; }
            elseif (str_contains($desc, '下分開始'))  { $marker = 'wash_start'; }
            elseif (str_contains($desc, '下分結束'))  { $marker = 'wash_end'; }

            // 解析机台状态
            $state = [];
            if (!empty($row['machine_state'])) {
                $parsed = json_decode($row['machine_state'], true) ?? [];
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

            // 解析扩展字段
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
                'time'         => substr($dtStr, 11, 8),   // HH:ii:ss
                'elapsed_ms'   => $elapsedMs,
                'level'        => $row['level'],
                'display_type' => $displayType,
                'source_type'  => $row['source_type'],
                'log_direction'=> $row['log_direction'],
                'action'       => $row['action'],
                'action_desc'  => $row['action_desc'],
                'marker'       => $marker,
                'state'        => $state,
                'extra'        => $extra,
                'raw_send'     => $row['raw_send'] ?? '',
                'raw_recv'     => $row['raw_recv'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * 玩家详情
     * @param $player_id
     * @return Detail
     */
    public function playerInfo($player_id): Detail
    {
        $player = Player::find($player_id);
        return Detail::create($player, function (Detail $detail) {
            $detail->item('name', admin_trans('player.fields.name'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('uuid', admin_trans('player.fields.uuid'));
            $detail->item('is_promoter', admin_trans('player.fields.is_promoter'))->display(function (
                $value,
                Player $data
            ) {
                return Html::create()->content([
                    Tag::create($value == 1 ? admin_trans('player.promoter') : admin_trans('player.national_promoter'))->color($value == 1 ? 'red' : 'orange'),
                    $data->player_promoter->name ?? ''
                ]);
            });
            $detail->item('national_promoter.level_list.damage_rebate_ratio',
                admin_trans('national_promoter.level_list.damage_rebate_ratio'))->display(function (
                $value,
                Player $data
            ) {
                return floatval($value) . ' %';
            });
            $detail->item('national_promoter.level_list.recharge_ratio',
                admin_trans('national_promoter.level_list.recharge_ratio'))->display(function ($value, Player $data) {
                return floatval($value) . ' %';
            });
            $detail->item('recommend_player.name',
                admin_trans('player_promoter.fields.recommend_promoter_name'))->display(function (
                $value,
                Player $data
            ) {
                if (isset($data->recommend_player) && !empty($data->recommend_player)) {
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data->recommend_player->id])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->recommend_player->phone);
                }
                return '';
            });
            $detail->item('address', admin_trans('player_extend.fields.address'));
            $detail->item('line', admin_trans('player_extend.fields.line'));
            $detail->item('email', admin_trans('player_extend.fields.email'));
            $detail->item('created_at', admin_trans('player.fields.created_at'))->display(function ($val) {
                return date('Y-m-d H:i:s', strtotime($val));
            });
            $detail->item('machine_wallet.money', admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF));

        })->bordered();
    }

    /**
     * 应用上下分记录搜索条件（列表与统计共用）
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $exAdminFilter
     * @return void
     */
    protected function applySearchFilter($query, array $exAdminFilter): void
    {
        if (empty($exAdminFilter)) {
            return;
        }

        if (!empty($exAdminFilter['created_at_start'])) {
            $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
        }
        if (!empty($exAdminFilter['created_at_end'])) {
            $query->where('created_at', '<=', $exAdminFilter['created_at_end']);
        }
        if (!empty($exAdminFilter['department_id'])) {
            $query->where('department_id', $exAdminFilter['department_id']);
        }
        if (!empty($exAdminFilter['action'])) {
            $query->where('action', $exAdminFilter['action']);
        }
        if (!empty($exAdminFilter['source_type'])) {
            $query->where('source_type', $exAdminFilter['source_type']);
        }
        if (!empty($exAdminFilter['action_type'])) {
            switch ($exAdminFilter['action_type']) {
                case 'admin':
                    $query->where('user_id', '!=', 0)->where('is_system', 0);
                    break;
                case 'system':
                    $query->where('is_system', 1);
                    break;
                case 'player':
                    $query->where('is_system', 0)->where('user_id', 0);
                    break;
            }
        }
        if (!empty($exAdminFilter['machine'])) {
            $query->whereHas('machine', function ($query) use ($exAdminFilter) {
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
            $query->whereHas('player', function ($query) use ($exAdminFilter) {
                $query->where('uuid', $exAdminFilter['player']['uuid']);
            });
        }
        if (!empty($exAdminFilter['player']['phone'])) {
            $query->whereHas('player', function ($query) use ($exAdminFilter) {
                $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
            });
        }
        if (isset($exAdminFilter['date_type'])) {
            $query->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
        }
        if (!empty($exAdminFilter['recommend_id'])) {
            $recommendId = Player::query()->where('uuid', 'like', '%' . $exAdminFilter['recommend_id'] . '%')->value('id');
            $query->whereHas('player', function ($query) use ($recommendId) {
                if ($recommendId) {
                    $query->where('recommend_id', $recommendId);
                } else {
                    $query->where(function ($query) {
                        $query->where('recommend_id', 0)
                            ->orWhereNull('recommend_id');
                    });
                }
            });
        }
        if (isset($exAdminFilter['search_type'])) {
            $query->whereHas('player', function ($query) use ($exAdminFilter) {
                $query->where('is_test', $exAdminFilter['search_type']);
            });
        }
    }

    /**
     * 上下分记录统计（独立接口，不走通用 totalInfo）
     * 汇总口径与列表列一致，随搜索条件实时变化
     * @auth true
     * @return \support\Response
     */
    public function totalInfo(): \support\Response
    {
        $exAdminFilter = Request::input('ex_admin_filter', []);

        $query = (new $this->model)->newQuery();
        $this->applySearchFilter($query, $exAdminFilter);

        // 总游戏点按实际方向累计：开分记负、其余记正，与列表符号口径一致。
        // game_amount 的落库符号各写入方并不统一（gk_work 上分写正数、gk_api 写负数、
        // 现金投币/管理员上分写 0），所以不能直接 sum(game_amount)，必须按 action 归一。
        $openAction = PlayerGameLog::ACTION_OPEN;
        $totalData = $query->selectRaw("
                sum(CASE WHEN action = {$openAction} THEN -ABS(game_amount) ELSE game_amount END) as total_game_amount,
                sum(open_point) as total_open_point,
                sum(wash_point) as total_wash_point,
                sum(gift_point) as total_gift_point,
                sum(pressure) as total_pressure,
                sum(score) as total_score,
                sum(turn_point) as total_turn_point,
                sum(chip_amount) as total_chip_amount")
            ->first();

        $val = fn($field) => $totalData && $totalData->$field !== null ? floatval($totalData->$field) : 0.0;

        $data = [
            ['title' => admin_trans('player_game_log.total_data.total_game_amount'), 'number' => $val('total_game_amount'), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('player_game_log.total_data.total_open_point'), 'number' => $val('total_open_point'), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('player_game_log.total_data.total_wash_point'), 'number' => $val('total_wash_point'), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('player_game_log.total_data.total_gift_point'), 'number' => $val('total_gift_point'), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('player_game_log.total_data.total_pressure'), 'number' => $val('total_pressure'), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('player_game_log.total_data.total_score'), 'number' => $val('total_score'), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('player_game_log.total_data.total_turn_point'), 'number' => $val('total_turn_point'), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('player_game_log.total_data.total_chip_amount'), 'number' => $val('total_chip_amount'), 'prefix' => '', 'suffix' => ''],
        ];

        return json(['code' => 200, 'data' => $data]);
    }
}
