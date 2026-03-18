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
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['department_id'])) {
                $grid->model()->where('department_id', $exAdminFilter['department_id']);
            }
            if (!empty($exAdminFilter['action'])) {
                $grid->model()->where('action', $exAdminFilter['action']);
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
            if (!empty($exAdminFilter['player']['phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
                });
            }

            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }

            if (!empty($exAdminFilter['recommend_id'])) {
                $recommendId = Player::query()->where('uuid', 'like', '%' . $exAdminFilter['recommend_id'] . '%')->value('id');
                $grid->model()->whereHas('player', function ($query) use ($recommendId) {
                    if ($recommendId) {
                        $query->where('recommend_id', $recommendId);
                    } else {
                        $query->where(function ($query) {
                            $query->where('recommend_id',0)
                                ->orWhereNull('recommend_id');
                        });
                    }
                });
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }

            $grid->model()->orderBy('id', 'desc');
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'PlayerGameLog',
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
                    return Html::create()->content([
                        Html::div()->content($data->player->uuid ?? '')
                    ]);
                })->align('center');
                $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerGameLog $data) {
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
                        return '暂无推广员';
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
                $grid->column('game_amount', admin_trans('player_game_log.fields.game_amount'))->display(function ($val
                ) {
                    return Html::create()->content([
                        $val > 0 ? '+' . $val : $val,
                    ])->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
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
                    $actions->prepend(
                        $dropdown
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
                $grid->model()->where('created_at', '>=', Carbon::parse($requestFilter['start_date'])->startOfDay());
            }
            if (!empty($requestFilter['end_date'])) {
                $grid->model()->where('created_at', '<=', Carbon::parse($requestFilter['end_date'])->endOfDay());
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
                $grid->column('game_amount', admin_trans('player_game_log.fields.game_amount'))->display(function ($val
                ) {
                    return Html::create()->content([
                        $val > 0 ? '+' . $val : $val,
                    ])->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
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
}
