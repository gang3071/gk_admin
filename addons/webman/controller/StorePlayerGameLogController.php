<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlayerGameLog;
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
            $grid->model()->with([
                'player',
                'machine' => function ($query) {
                    return $query->with(['machineLabel']);
                },
                'player.channel',
                'machine_recording'
            ]);
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
            if (!empty($exAdminFilter['player']['machine']['uuid'])) {
                $grid->model()->whereHas('player.machine', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['machine']['uuid']);
                });
            }
            if (!empty($exAdminFilter['player']['machine']['name'])) {
                $grid->model()->whereHas('player.machine', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player']['machine']['name'] . '%');
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
                $grid->column('player.machine.uuid', admin_trans('machine.fields.uuid'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        Html::div()->content($data->player->machine->uuid ?? '')
                    ]);
                })->align('center');
                $grid->column('player.machine.name', admin_trans('machine.fields.name'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        Html::div()->content($data->player->machine->name ?? '')
                    ]);
                })->align('center');
                $grid->column('player.type', admin_trans('player.fields.type'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                    ]);
                })->align('center');
                $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->player->channel->name ?? '';
                })->width('150px')->align('center');
            }, admin_trans('player_game_log.player_info'));
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
                $filter->like()->text('player.machine.uuid')->placeholder(admin_trans('machine.fields.uuid'));
                $filter->like()->text('player.machine.name')->placeholder(admin_trans('machine.fields.name'));
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
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
        });
    }
}