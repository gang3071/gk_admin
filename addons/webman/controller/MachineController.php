<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineKeepingLog;
use addons\webman\model\MachineMedia;
use addons\webman\model\MachineMediaPush;
use addons\webman\model\MachineOpenCard;
use addons\webman\model\MachineRecording;
use addons\webman\model\MachineStrategy;
use addons\webman\model\MachineTencentPlay;
use addons\webman\model\Notice;
use addons\webman\model\Player;
use addons\webman\service\MediaServer;
use addons\webman\service\WalletService;
use app\service\machine\MachineServices;
use Carbon\CarbonInterval;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\field\select\SelectGroup;
use ExAdmin\ui\component\form\field\Switches;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\form\Watch;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\Col;
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Notification;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Exception;
use GatewayWorker\Lib\Gateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use support\Cache;
use support\Db;

/**
 * 机台
 */
class MachineController
{
    protected $model;

    protected $media_model;

    protected $machine_recording_model;

    protected $machine_media_push_model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_model');
        $this->media_model = plugin()->webman->config('database.machine_media_model');
        $this->machine_recording_model = plugin()->webman->config('database.machine_recording_model');
        $this->machine_media_push_model = plugin()->webman->config('database.machine_media_push_model');
    }

    /**
     * 机台
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->slotList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->steelBallList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_FISH), $this->fishList())
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * 斯洛列表
     * @param int $machineId
     * @return Grid
     */
    public function slotList(int $machineId = 0): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($machineId) {
            $grid->model()
                ->where('type', GameType::TYPE_SLOT)
                ->when($machineId, function (Builder $q, $value) {
                    $q->where('id', $value);
                })
                ->orderBy('sort')
                ->orderBy('id', 'desc');
            $this->getList($grid, GameType::TYPE_SLOT);
        });
    }

    /**
     * 机台
     * @param Grid $grid
     * @param $type
     * @return void
     */
    protected function getList(Grid $grid, $type)
    {
        $grid->driver()->setPk('id');
        $grid->autoHeight();
        $grid->bordered(true);
        $grid->title(admin_trans('machine.title'));
        $layout = Layout::create();
        $layout->row(function (Row $row) {
            $tencentTotalViewers = Cache::get('tencent_total_viewers', 0);
            $row->gutter([10, 0]);
            $row->column(
                Card::create([
                    Row::create()->column(Statistic::create()->value($tencentTotalViewers)
                        ->prefix(admin_trans('machine.machine_tencent_total_viewers'))
                        ->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center',
                    'height' => '30px',
                    'padding' => '0px'
                ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                , 4);
        })->style(['background' => '#fff', 'margin-left' => '10px']);
        $grid->tools([
            $layout
        ]);
        $requestFilter = Request::input('ex_admin_filter', []);
        if (!empty($requestFilter)) {
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
        }
        $grid->column('id', admin_trans('machine.fields.id'))->align('center');
        $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
            return Html::create()->content([
                Tag::create(getGameTypeName($data->type)),
                $data->machineCategory->name ?? '',
            ]);
        })->align('center');
        $grid->column('producer_id', admin_trans('machine.fields.producer_id'))->display(function (
            $val,
            Machine $data
        ) {
            return Html::create()->content([
                !empty($data->producer->name) ? Tag::create($data->producer->name)->color('green') : ''
            ]);
        })->align('center');
        $grid->column('control_type', admin_trans('machine.fields.control_type'))->display(function ($val) {
            return Html::create()->content([
                Tag::create(admin_trans('machine.control_type.' . $val))->color('orange')
            ]);
        })->align('center');
        $grid->column('name', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
            return Html::create()->content([
                $data->name
            ]);
        })->align('center');
        $grid->column('code', admin_trans('machine.fields.code'))->sortable()->align('center')
            ->filter(
                FilterColumn::like()->text('code')
            );
        if ($type == GameType::TYPE_FISH) {
            $grid->column('seat', admin_trans('machine.fields.seat'))->display(function ($val) {
                return admin_trans('machine.seat.' . $val);
            })->align('center');
        }
        $grid->column('odds_x', admin_trans('machine.fields.odds_x'))
            ->editable(
                Editable::number('odds_x')
                    ->min(1)
                    ->precision(3)
            )
            ->align('left');
        $grid->column('now_status', admin_trans('machine.fields.now_status'))
            ->display(function ($val, Machine $data) {
                $machineStatus = 'offline';
                try {
                    switch ($data->type) {
                        case GameType::TYPE_SLOT:
                            if (Gateway::isUidOnline($data->domain . ':' . $data->port) && Gateway::isUidOnline($data->auto_card_domain . ':' . $data->auto_card_port)) {
                                $machineStatus = 'online';
                            }
                            break;
                        case GameType::TYPE_STEEL_BALL:
                            if (Gateway::isUidOnline($data->domain . ':' . $data->port)) {
                                $machineStatus = 'online';
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    // Gateway 服务不可用时默认显示离线
                    \support\Log::warning('Gateway connection failed', [
                        'register_address' => Gateway::$registerAddress ?? 'not set',
                        'error' => $e->getMessage()
                    ]);
                }
                return admin_view(plugin()->webman->getPath() . '/views/machine_status.vue')->attrs([
                    'id' => $data->id,
                    'type' => Admin::user()->type == 1 ? 'admin' : 'channel',
                    'department_id' => Admin::user()->department_id,
                    'ws' => env('WS_URL', ''),
                    'machine_status' => $machineStatus,
                ]);
            })
            ->align('center');
        $grid->column('odds_y', admin_trans('machine.fields.odds_y'))->editable(
            Editable::number('odds_y')
                ->min(1)
                ->precision(3)
        )
            ->align('left');
        $grid->column('control_open_point', admin_trans('machine.fields.control_open_point'))->editable(
            Editable::number('control_open_point')
                ->min(1)
                ->precision(0)
        )
            ->align('left');
        $grid->column('viewers', admin_trans('machine.fields.viewers'))->display(function ($val, Machine $data) {
            return getViewers($data->id);
        })->align('center');
        $grid->column('min_point', admin_trans('machine.fields.min_point'))->align('center');
        $grid->column('max_point', admin_trans('machine.fields.max_point'))->align('center');
        $grid->column('status', admin_trans('machine.fields.status'))->switch()->align('center');
        $grid->column('is_use', admin_trans('machine.fields.is_use'))->switch()->align('center');
        $grid->column('maintaining', admin_trans('machine.fields.maintaining'))->switch()->align('center');
        $grid->column('has_lock', admin_trans('machine.fields.has_lock'))->display(function (
            $val,
            Machine $data
        ) {
            $services = MachineServices::createServices($data);
            return Switches::create(null, $services->has_lock)
                ->options([[1 => admin_trans('machine.lock')], [0 => admin_trans('machine.open')]])
                ->url('ex-admin/addons-webman-controller-MachineController/changeLock')
                ->field('has_lock')
                ->params([
                    'id' => [$data->id],
                ]);
        })->align('center');
        $grid->sortInput();
        $grid->column('created_at', admin_trans('machine.fields.created_at'))->sortable()->align('center');
        $grid->column('remark', admin_trans('machine.fields.remark'))
            ->editable(
                Editable::textarea()
                    ->showCount()
                    ->rule(['max:250' => admin_trans('machine.remark_limit')])
            )->display(function ($value, Machine $data) {
                return Str::of($data->remark)->limit(35, ' (...)');
            })->width('180px')->align('center');
        $grid->actions(function (Actions $action, Machine $data) {
            $action->edit()->drawer($this->form());
            $dropdown = Dropdown::create(
                Button::create(
                    [admin_trans('machine.btn.action'), Icon::create('DownOutlined')->style(['marginRight' => '5px'])])
            )->trigger(['click']);

            $dropdown->item(admin_trans('machine.media') . ' ' . $data->code, 'fas fa-file-video')
                ->modal([$this, 'mediaList'], ['id' => $data->id])
                ->width('70%');

            $dropdown->item(admin_trans('machine.btn.chang_point_card'), 'reload-outlined')
                ->confirm(admin_trans('machine.btn.chang_point_card_confirm'), [$this, 'changePointCard'],
                    ['id' => $data->id])
                ->gridRefresh();
            $dropdown->item(admin_trans('machine.btn.clear_bet'), 'far fa-life-ring')
                ->confirm(admin_trans('machine.btn.clear_bet_confirm'), [$this, 'clearBet'], ['id' => $data->id])
                ->gridRefresh();
            $dropdown->item(admin_trans('machine.media_recording') . ' ' . $data->code, 'fas fa-file-video')
                ->modal([$this, 'mediaRecording'], ['id' => $data->id])
                ->width('70%');
            $action->prepend(
                $dropdown
            );
        });

        //删除清除缓存
        $grid->deling(function ($ids) {
            $machineList = Machine::query()->whereIn('id', $ids)->get(['domain', 'port', 'type']);
            /** @var Machine $machine */
            foreach ($machineList as $machine) {
                // 格式化缓存key
                $cacheKey = sprintf('machine:domain:%s:port:%s:type:%s',
                    $machine->domain, $machine->port, $machine->type
                );

                Cache::delete($cacheKey);
            }
        });

        $grid->filter(function (Filter $filter) use ($type) {
            $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
            $filter->like()->text('code')->placeholder(admin_trans('machine.fields.code'));
            $filter->like()->text('port')->placeholder(admin_trans('machine.fields.port'));
            $filter->eq()->select('control_type')
                ->placeholder(admin_trans('machine.fields.control_type'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    Machine::CONTROL_TYPE_MEI => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_MEI),
                    Machine::CONTROL_TYPE_SONG => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_SONG),
                ]);
            $filter->eq()->select('status')
                ->placeholder(admin_trans('machine.fields.status'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    1 => admin_trans('admin.normal'),
                    0 => admin_trans('admin.disable')
                ]);
            $filter->eq()->select('gaming')
                ->placeholder(admin_trans('machine.has_gaming'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    1 => admin_trans('machine.gaming'),
                    0 => admin_trans('machine.not_gaming')
                ]);
            SelectGroup::create();
            $filter->in()->cascaderSingle('cate_id')
                ->showSearch()
                ->style(['width' => '150px'])
                ->placeholder(admin_trans('machine.fields.cate_id'))
                ->options(getCateListOptions())
                ->multiple();
            if ($type == GameType::TYPE_FISH) {
                $filter->eq()->select('seat')
                    ->showSearch()
                    ->style(['width' => '150px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('machine.fields.seat'))
                    ->options(getSeatOptions());
            }
            $producer = plugin()->webman->config('database.machine_producer_model');
            $options = $producer::select(['id', 'name'])->pluck('name', 'id')->all();
            $filter->eq()->select('producer_id')
                ->placeholder(admin_trans('machine.fields.producer_id'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options($options);
            $filter->form()->hidden('created_at_start');
            $filter->form()->hidden('created_at_end');
            $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                admin_trans('public_msg.created_at_start'),
                admin_trans('public_msg.created_at_end')
            ]);
        });
        $grid->addButton()
            ->content(admin_trans('grid.add'))
            ->drawer($this->form());
        $grid->hideDelete();
        $grid->expandFilter();
    }

    /**
     * 编辑机台
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('machine.machine_info'));
            $form->tabs()
                ->pane(admin_trans('machine.title'), function (Form $form) {
                    $gameType = $form->getBindField('game_type');
                    $form->row(function (Form $form) {
                        $form->column(function (Form $form) {
                            $form->cascaderSingle('cate_id',
                                admin_trans('machine.fields.cate_id'))->style(['width' => '200px'])->options(getCateListOptions($form->isEdit() ? $form->driver()->get() : []))->span(11)->required();
                            $form->image('picture_url',
                                admin_trans('machine.fields.picture_url'))->ext('jpg,png,jpeg')->fileSize('5m')->help(admin_trans('machine.help.picture_url_size'))->span(11);
                        })->span(12);
                        $form->column(function (Form $form) {
                            $form->select('label_id', admin_trans('machine.fields.label_id'))
                                ->options(getMachineLabelOptions())
                                ->required();
                            $form->text('code',
                                admin_trans('machine.fields.code'))->maxlength(10)->span(11)->required();
                        })->span(12);
                    });
                    $form->hidden('game_type')->bindAttr('value', $gameType)
                        ->when(GameType::TYPE_STEEL_BALL, function (Form $form) {
                            Card::create($form->row(function (Form $form) {
                                $form->text('domain',
                                    admin_trans('machine.fields.domain'))->maxlength(255)->required()->span(11);
                                $form->text('port', admin_trans('machine.fields.port'))
                                    ->rule([
                                        'regex:/^([1-9]|[1-9][0-9]{1,3}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/' => admin_trans('validator.machine_port'),
                                    ])
                                    ->maxlength(6)
                                    ->span(11)
                                    ->required()
                                    ->style(['margin-left' => '10px']);
                            }));
                        })
                        ->when(GameType::TYPE_SLOT, function (Form $form) {
                            $form->row(function (Form $form) {
                                $form->text('domain',
                                    admin_trans('machine.fields.domain'))->maxlength(255)->required()->span(11);
                                $form->text('port', admin_trans('machine.fields.port'))
                                    ->rule([
                                        'regex:/^([1-9]|[1-9][0-9]{1,3}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/' => admin_trans('validator.machine_port'),
                                    ])
                                    ->maxlength(6)
                                    ->span(11)
                                    ->required()
                                    ->style(['margin-left' => '10px']);
                            });
                            $form->row(function (Form $form) {
                                $form->text('auto_card_domain',
                                    admin_trans('machine.fields.auto_card_domain'))->maxlength(255)->required()->span(11);
                                $form->text('auto_card_port', admin_trans('machine.fields.auto_card_port'))
                                    ->rule([
                                        'regex:/^([1-9]|[1-9][0-9]{1,3}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/' => admin_trans('validator.machine_port'),
                                    ])
                                    ->maxlength(6)
                                    ->span(11)
                                    ->required()
                                    ->style(['margin-left' => '10px']);
                            });
                        });

                    $form->row(function (Form $form) {
                        $form->text('control_open_point', admin_trans('machine.fields.control_open_point'))->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                            'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                        ])->required()->span(11);
                        $form->text('sort', admin_trans('machine_category.fields.sort'))->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])->span(11)->default($this->model::max('sort') + 1)->style(['margin-left' => '10px']);
                    });
                    $form->row(function (Form $form) {
                        $form->number('odds_x', admin_trans('machine.fields.odds_x'))
                            ->max(100000)
                            ->min(0.01)
                            ->precision(2)
                            ->span(11)
                            ->required()->style(['width' => '100%']);
                        $form->number('odds_y', admin_trans('machine.fields.odds_y'))
                            ->max(100000)
                            ->min(0.01)
                            ->precision(2)
                            ->size('100%')
                            ->span(11)->required()->style(['margin-left' => '10px', 'width' => '100%']);
                    });

                    $form->row(function (Form $form) {
                        $form->text('min_point', admin_trans('machine.fields.min_point'))
                            ->rule([
                                'integer' => admin_trans('validator.integer'),
                                'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                                'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                            ])->span(11)->required()->help(admin_trans('machine.help.min_point'));
                        $form->text('max_point', admin_trans('machine.fields.max_point'))
                            ->rule([
                                'integer' => admin_trans('validator.integer'),
                                'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                                'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                            ])->span(11)->required()->help(admin_trans('machine.help.max_point'))->style(['margin-left' => '10px']);
                    });
                    $form->row(function (Form $form) {
                        $form->selectTable('strategy_id', admin_trans('machine.fields.strategy_id'))
                            ->grid([MachineStrategyController::class, 'selectList'])
                            ->display(function ($ids, $data) {
                                if ($ids) {
                                    /** @var MachineStrategy $strategy */
                                    $strategy = MachineStrategy::find($ids[0]);
                                    return Html::div()->content(admin_trans('machine.select') . $strategy->name);
                                } else {
                                    if ($data['strategy_id']) {
                                        /** @var MachineStrategy $strategy */
                                        $strategy = MachineStrategy::find($data['strategy_id']);
                                        return Html::div()->content(admin_trans('machine.select') . $strategy->name);
                                    }
                                }
                                return [];
                            })->span(11);
                        $producer = plugin()->webman->config('database.machine_producer_model');
                        $options = $producer::where('status', 1)->select(['id', 'name'])->pluck('name', 'id')->all();
                        $form->select('producer_id', admin_trans('machine.fields.producer_id'))
                            ->options($options)
                            ->span(11);
                    });
                    $form->hidden('game_type')->bindAttr('value', $gameType)->when(GameType::TYPE_STEEL_BALL,
                        function (Form $form) {
                            $form->text('correct_rate', admin_trans('machine.fields.correct_rate'))->maxlength(50);
                        })->when(GameType::TYPE_FISH, function (Form $form) {
                        $form->text('identify_url',
                            admin_trans('machine.fields.identify_url'))->maxlength(255)->required();
                        $form->select('seat', admin_trans('machine.fields.seat'))
                            ->options(getSeatOptions())
                            ->required();
                    });
                    $form->watch([
                        'cate_id' => function ($value, Watch $watch) {
                            /** @var MachineCategory $cate */
                            $cate = MachineCategory::find($value);
                            $watch->set('game_type', $cate->gameType->type ?? 0);
                        }
                    ]);
                    $form->switch('is_special', admin_trans('machine.fields.is_special'))->default(false);
                    $form->select('control_type', admin_trans('machine.fields.control_type'))
                        ->required()
                        ->options([
                            Machine::CONTROL_TYPE_MEI => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_MEI),
                            Machine::CONTROL_TYPE_SONG => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_SONG),
                        ]);
                    $form->textarea('remark', admin_trans('machine.fields.remark'))->maxlength(125)->bindAttr('rows',
                        3);
                })
                ->pane(admin_trans('machine_media.media_line'), function (Form $form) {
                    $form->hasMany('machine_media', '', function (Form $form) {
                        $form->row(function (Form $form) {
                            $form->hidden('id');
                            $form->password('push_ip',
                                admin_trans('machine_media.fields.push_ip'))->visibilityToggle(false)->span(11)->required();
                            $form->push(Divider::create()->content(' '));
                            $form->text('pull_ip', admin_trans('machine_media.fields.pull_ip'))->span(11)->required();
                            $form->text('media_ip', admin_trans('machine_media.fields.media_ip'))->span(11)->required();
                            $form->push(Divider::create()->content(' '));
                            $form->select('media_app', admin_trans('machine_media.fields.media_app'))
                                ->options([
                                    'WebRTCAppEE' => 'WebRTCAppEE',
                                    'ShenQi' => 'ShenQi',
                                    'h265' => 'h265',
                                    'life' => 'life',
                                ])
                                ->default('WebRTCAppEE')
                                ->required()
                                ->showSearch()
                                ->span(11)
                                ->dropdownMatchSelectWidth()
                                ->style(['width' => '100%']);
                            $form->text('stream_name',
                                admin_trans('machine_media.fields.stream_name'))->span(11)->disabled(true)->help(admin_trans('machine.help.stream_name'));
                            $form->push(Divider::create()->content(' '));
                            $form->switch('is_ams', admin_trans('machine_media.fields.is_ams'));
                        })->class(['activity-phase-has-many']);
                    })->sortField('sort')->defaultRow(1);
                })->destroyInactiveTabPane(false);

            $form->actions()->hideResetButton();
            $form->layout('vertical');
            $form->saving(function (Form $form) {
                if ($form->isEdit()) {
                    $orgData = $form->driver()->get();
                    /** @var Machine $machine */
                    $machine = Machine::find($orgData['id']);
                    if (empty($machine)) {
                        return message_error(admin_trans('machine.not_fount'));
                    }
                    DB::beginTransaction();
                    try {
                        $this->addMachine($form, $machine, true);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return message_error($e->getMessage() ?? admin_trans('form.save_fail'));
                    }
                    return message_success(admin_trans('form.save_success'));
                } else {
                    $cate_id = $form->input('cate_id');
                    /** @var MachineCategory $machineCategory */
                    $machineCategory = MachineCategory::find($cate_id);
                    if (empty($machineCategory)) {
                        return notification_warning(admin_trans('machine.machine_category_not_fount'),
                            Html::div()->content([
                                Html::create(admin_trans('machine.action.search_success')),
                            ]), ['duration' => 5]);
                    }
                    $form->input('type', $machineCategory->gameType->type);
                    DB::beginTransaction();
                    try {
                        $machine = new Machine();
                        $this->addMachine($form, $machine);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return message_error($e->getMessage() ?? admin_trans('form.save_fail'));
                    }
                    return message_success(admin_trans('form.save_success'));
                }
            });
        })->style(['margin-top' => '-30px']);
    }

    /**
     * 添加修改机台信息
     * @param Form $form
     * @param Machine $machine
     * @param bool $isEdit
     * @return void
     * @throws Exception
     * @throws \Exception
     */
    protected function addMachine(Form $form, Machine $machine, bool $isEdit = false): void
    {
        $machine->cate_id = $form->input('cate_id');
        $machine->producer_id = $form->input('producer_id');
        $machine->type = $form->input('type');
        $machine->picture_url = $form->input('picture_url');
        $machine->code = $form->input('code');
        $machine->domain = $form->input('domain');
        $machine->port = $form->input('port');
        $machine->auto_card_domain = $form->input('auto_card_domain');
        $machine->auto_card_port = $form->input('auto_card_port');
        $machine->ip = $form->input('ip') ?? '';
        $machine->identify_url = $form->input('identify_url') ?? '';
        $machine->seat = $form->input('seat') ?? 0;
        $machine->control_open_point = $form->input('control_open_point');
        $machine->sort = $form->input('sort');
        $machine->odds_x = $form->input('odds_x');
        $machine->odds_y = $form->input('odds_y');
        $machine->strategy_id = empty($form->input('strategy_id')) ? 0 : $form->input('strategy_id');
        $machine->min_point = $form->input('min_point');
        $machine->max_point = $form->input('max_point');
        $machine->control_type = $form->input('control_type');
        $machine->remark = $form->input('remark');
        $machine->label_id = $form->input('label_id');
        $machine->is_special = $form->input('is_special');
        $machine->save();
        //保存修改时进行缓存处理
        // 格式化缓存key
        $cacheKey = sprintf('machine:domain:%s:port:%s:type:%s',
            $machine->domain, $machine->port, $machine->type
        );

        Cache::set($cacheKey, $machine, 3600);
        $machineMedia = $form->input('machine_media');
        if (empty($machineMedia)) {
            throw new \Exception(admin_trans('machine_media.machine_media_require'));
        }
        $editMedia = [];
        // 删除无用的媒体服务
        if ($isEdit) {
            foreach ($machineMedia as $media) {
                if (!empty($media['id'])) {
                    $editMedia[] = $media['id'];
                }
            }
            $mediaModel = MachineMedia::where('machine_id', $machine->id)->whereNull('deleted_at');
            if (!empty($editMedia)) {
                $mediaList = $mediaModel->whereNotIn('id', $editMedia)->get();
            } else {
                $mediaList = $mediaModel->get();
            }
            /** @var MachineMedia $media */
            foreach ($mediaList as $media) {
                (new MediaServer($media->push_ip, $media->media_app))->deleteMachineStream($media->stream_name);
            }
            if (!empty($editMedia)) {
                MachineMedia::where('machine_id', $machine->id)->whereNull('deleted_at')->whereNotIn('id',
                    $editMedia)->delete();
            } else {
                MachineMedia::where('machine_id', $machine->id)->whereNull('deleted_at')->delete();
            }
        }
        foreach ($machineMedia as $media) {
            try {
                $this->getMedia($machine->type, $media['id'], $media['push_ip'], $media['media_ip'],
                    $media['media_app'], $machine->code, $machine->id, $media['pull_ip'], $form->input('sort'),
                    $media['is_ams']);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * @param string $pushIp 拉流IP
     * @param string $mediaIp 媒体IP
     * @param string $code 名称
     * @param string $id 机台id
     * @param string $mediaId 媒体id
     * @return MachineMedia|false
     * @throws Exception
     */
    protected function getMedia(
        $type,
        string $mediaId = '',
        string $pushIp = '',
        string $mediaIp = '',
        string $mediaApp = '',
        string $code = '',
        string $id = '',
        $pullIp = '',
        $sort = 0,
        $isAms = 0,
    ): bool|MachineMedia {
        if (!empty($pushIp) || !empty($mediaIp) || !empty($pullIp) || !empty($mediaApp)) {
            Db::beginTransaction();
            try {
                if (empty($pushIp)) {
                    throw new Exception(admin_trans('machine_media.push_ip_not_found'));
                }
                if (empty($pullIp)) {
                    throw new Exception(admin_trans('machine_media.pull_ip_not_found'));
                }
                if (empty($mediaIp)) {
                    throw new Exception(admin_trans('machine_media.media_ip_not_found'));
                }
                if (empty($code)) {
                    throw new Exception(admin_trans('machine_media.media_name_not_found'));
                }
                if (empty($mediaApp)) {
                    throw new Exception(admin_trans('machine_media.media_app_not_found'));
                }
                /** @var MachineMedia $media */
                if (!empty($mediaId)) {
                    $media = MachineMedia::find($mediaId);
                    if ($media->media_ip != $mediaIp || $media->push_ip != $pushIp || $media->media_app != $mediaApp || $media->is_ams != $isAms) {
                        $media->push_ip = $pushIp;
                        $media->pull_ip = $pullIp;
                        $media->media_ip = $mediaIp;
                        $media->media_app = $mediaApp;
                        $media->sort = $sort;
                        $media->is_ams = $isAms;
                        $pushList = [];
                        $insertData = [];
                        /** @var MachineTencentPlay $machineTencentPlay */
                        $machineTencentPlay = MachineTencentPlay::query()->where('status', 1)->first();
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
                            'endpoint_service_id' => $pushData['endpoint_service_id'],
                            'expiration_date' => $pushData['expiration_date'],
                            'machine_code' => $media->machine->code,
                            'rtmp_url' => $pushData['rtmp_url'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'machine_tencent_play_id' => $machineTencentPlay->id,
                        ];

                        $mediaServer = new MediaServer($media->push_ip, $media->media_app);
                        $result = $mediaServer->resetMachineStream($media->machine->type, $media->stream_name, $code,
                            $mediaIp, $media->push_ip, $pushIp, $media->media_app, $mediaApp, $pushList);
                        if ($result && $result['success']) {
                            $media->stream_name = $result['dataId'];
                        } else {
                            $media->stream_name = -1;
                        }
                        $media->save();
                        MachineMediaPush::query()->where('media_id', $media->id)->delete();
                        if ($media->stream_name !== -1 && !empty($insertData)) {
                            foreach ($insertData as &$datum) {
                                $datum['media_id'] = $media->id;
                            }
                            MachineMediaPush::query()->insert($insertData);
                        }
                    }
                } else {
                    $media = new MachineMedia();
                    $media->machine_id = $id;
                    $media->push_ip = $pushIp;
                    $media->pull_ip = $pullIp;
                    $media->media_ip = $mediaIp;
                    $media->media_app = $mediaApp;
                    $media->sort = $sort;
                    $media->is_ams = $isAms;
                    $media->user_id = Admin::id() ?? 0;
                    $media->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
                        [], 'message');
                    $pushList = [];
                    $insertData = [];
                    if ($isAms == 0) {
                        /** @var MachineTencentPlay $machineTencentPlay */
                        $machineTencentPlay = MachineTencentPlay::query()->where('status', 1)->first();
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
                            'endpoint_service_id' => $pushData['endpoint_service_id'],
                            'expiration_date' => $pushData['expiration_date'],
                            'machine_code' => $media->machine->code,
                            'rtmp_url' => $pushData['rtmp_url'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'machine_tencent_play_id' => $machineTencentPlay->id,
                        ];
                    }

                    $mediaServer = new MediaServer($pushIp, $mediaApp);
                    $result = $mediaServer->createMachineStream($code, $mediaIp, $type, $pushList);
                    if ($result && $result['success']) {
                        $media->stream_name = $result['dataId'];
                        $media->status = 1;
                    } else {
                        $media->stream_name = -1;
                    }
                    $media->save();
                    if ($media->status == 1 && !empty($insertData)) {
                        foreach ($insertData as &$datum) {
                            $datum['media_id'] = $media->id;
                        }
                        MachineMediaPush::query()->insert($insertData);
                    }
                }
                Db::commit();
            } catch (\Exception) {
                Db::rollback();
                throw new Exception(admin_trans('machine_media.get_media_fail'));
            }

            return $media;
        }

        return false;
    }

    /**
     * 钢珠列表
     * @return Grid
     */
    public function steelBallList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()
                ->where('type', GameType::TYPE_STEEL_BALL)
                ->orderBy('sort')
                ->orderBy('id', 'desc');
            $grid->title(admin_trans('machine.title'));
            $this->getList($grid, GameType::TYPE_STEEL_BALL);
        });
    }

    /**
     * 鱼机列表
     * @return Grid
     */
    public function fishList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()
                ->where('type', GameType::TYPE_FISH)
                ->orderBy('sort')
                ->orderBy('id', 'desc');
            $this->getList($grid, GameType::TYPE_FISH);
        });
    }

    /**
     * 刷新机台媒体
     * @auth true
     * @param $id
     * @return Msg
     * @throws Exception
     */
    public function rebuildMedia($id): Msg
    {
        /** @var MachineMedia $media */
        $media = $this->media_model::find($id);
        if (empty($media)) {
            return message_error(admin_trans('machine_media.not_fount'));
        }
        Db::beginTransaction();
        try {
            $pushList = [];
            $insertData = [];
            /** @var MachineTencentPlay $machineTencentPlay */
            $machineTencentPlay = MachineTencentPlay::query()->where('status', 1)->first();
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
                'endpoint_service_id' => $pushData['endpoint_service_id'],
                'expiration_date' => $pushData['expiration_date'],
                'machine_code' => $media->machine->code,
                'rtmp_url' => $pushData['rtmp_url'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'machine_tencent_play_id' => $machineTencentPlay->id,
            ];

            $mediaServer = new MediaServer($media->push_ip, $media->media_app);
            $result = $mediaServer->resetMachineStream($media->machine->type, $media->stream_name,
                $media->machine->code, $media->media_ip, '', '', 'WebRTCAppEE', '', $pushList);
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
        } catch (Exception $e) {
            Db::rollback();
            return message_error(admin_trans('machine_media.reset_failed') . $e->getMessage());
        }
        if ($media->stream_name == -1) {
            return message_error(admin_trans('machine_media.reset_failed'));
        }
        return message_success(admin_trans('machine_media.reset_success'));
    }

    /**
     * 机台资讯列表
     * @auth true
     * @return Card
     */
    public function infoList(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->slotInfoList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->steelBallInfoList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_FISH), $this->fishInfoList())
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * 斯洛机台资讯
     * @return Grid
     */
    public function slotInfoList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with(['gamingPlayer.channel', 'machine_media'])->where('type',
                GameType::TYPE_SLOT)->where('gaming_user_id', '!=', 0)->orderBy('sort', 'asc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['last_point_at_start'])) {
                    $grid->model()->where('last_point_at', '>=', $requestFilter['last_point_at_start']);
                }
                if (!empty($requestFilter['last_point_at_end'])) {
                    $grid->model()->where('last_point_at', '<=', $requestFilter['last_point_at_end']);
                }
                if (!empty($requestFilter['last_game_at_start'])) {
                    $grid->model()->where('last_game_at', '>=', $requestFilter['last_game_at_start']);
                }
                if (!empty($requestFilter['last_game_at_end'])) {
                    $grid->model()->where('last_game_at', '<=', $requestFilter['last_game_at_end']);
                }
                if (isset($requestFilter['search_type'])) {
                    $grid->model()->whereHas('gamingPlayer', function ($query) use ($requestFilter) {
                        $query->where('is_test', $requestFilter['search_type']);
                    });
                }
            }
            $grid->title(admin_trans('machine.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine.fields.id'))->align('center');
            $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->type)),
                    $data->machineCategory->name ?? '',
                ]);
            })->align('center');
            $grid->column('name', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    $data->name ?? '',
                ]);
            })->align('center');
            $grid->column('code', admin_trans('machine.fields.code'))->sortable()->display(function (
                $val,
                Machine $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->code ?? '',
                    ]), 10)->style(['line-height' => '28px']);
                    $row->column(Button::create()->icon(Icon::create('far fa-play-circle'))->shape('circle')->type('dashed')->size('small')->modal([
                        $this,
                        'mediaPlay'
                    ], [
                        'id' => $data->machine_media->where('status', 1)->where('stream_name', '!=',
                                '-1')->first()->id ?? 0
                    ])->maskClosable()->title(admin_trans('machine_media.btn.media_play')), 6);
                })->align('center');
            })->align('center')->filter(
                FilterColumn::like()->text('code')
            );
            $grid->column('gaming_user_id', admin_trans('machine.fields.gaming_user_id'))->display(function (
                $val,
                Machine $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->gamingPlayer->phone ?? ''
                    ]), 12)->style(['line-height' => '28px']);
                    $row->column(Button::create()->icon(Icon::create('fas fa-exchange-alt'))->shape('circle')->type('dashed')->size('small')->drawer('ex-admin/addons-webman-controller-PlayerController/changePlayerList',
                        ['machine_id' => $data->id]), 6);
                })->align('center');
            })->align('center');
            $grid->column('gaming_player.channel.name', admin_trans('channel.fields.name'))->display(function (
                $val,
                Machine $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->gamingPlayer->channel->name ?? '',
                    ]), 12)->style(['line-height' => '28px']);
                })->align('center');
            })->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    $data->gamingPlayer->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_platform_cash.fields.money'))->display(function ($val, $data) {
                // ✅ 从 Redis 读取实时余额
                $balance = $data->gaming_user_id ? WalletService::getBalance($data->gaming_user_id) : '';
                return Html::create()->content([$balance]);
            })->align('center');
            $grid->column('keep_seconds', admin_trans('machine.fields.keep_seconds'))->display(function (
                $val,
                Machine $data
            ) {
                $services = MachineServices::createServices($data, Container::getInstance()->translator->getLocale());
                $seconds = $services->keep_seconds;
                if ($seconds > 3600) {
                    $hours = intval($seconds / 3600);
                    $time = $hours . ":" . gmstrftime('%M:%S', $seconds);
                } else {
                    $time = gmstrftime('%H:%M:%S', $seconds);
                }
                return Html::create()->content([
                    $time ?? '',
                ]);
            })->align('center');
            $grid->column('has_lock', admin_trans('machine.fields.has_lock'))->display(function (
                $val,
                Machine $data
            ) {
                $services = MachineServices::createServices($data);
                return Switches::create(null, $services->has_lock)
                    ->options([[1 => admin_trans('machine.lock')], [0 => admin_trans('machine.open')]])
                    ->url('ex-admin/addons-webman-controller-MachineController/changeLock')
                    ->field('has_lock')
                    ->params([
                        'id' => [$data->id],
                    ]);
            })->align('center');
            $grid->column('last_point_at', admin_trans('machine.fields.last_point_at'))
                ->display(function ($val, Machine $data) {
                    $services = MachineServices::createServices($data,
                        Container::getInstance()->translator->getLocale());
                    return Html::create()->content([
                        $services->last_point_at ? date('Y-m-d H:i:s', $services->last_point_at) : '',
                    ]);
                })
                ->align('center');
            $grid->column('last_game_at', admin_trans('machine.fields.last_game_at'))
                ->display(function ($val, Machine $data) {
                    $services = MachineServices::createServices($data,
                        Container::getInstance()->translator->getLocale());
                    return Html::create()->content([
                        $services->last_play_time ? date('Y-m-d H:i:s', $services->last_play_time) : '',
                    ]);
                })->align('center');
            $grid->hideDelete();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('code')->placeholder(admin_trans('machine.fields.code'));
                SelectGroup::create();
                $filter->in()->cascaderSingle('cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
                $filter->form()->hidden('last_point_at_start');
                $filter->form()->hidden('last_point_at_end');
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->eq()->select('gamingPlayer.department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->dateTimeRange('last_point_at_start', 'last_point_at_start', '')->placeholder([
                    admin_trans('machine.fields.last_point_at'),
                    admin_trans('machine.fields.last_point_at')
                ]);
                $filter->form()->hidden('last_game_at_start');
                $filter->form()->hidden('last_game_at_end');
                $filter->form()->dateTimeRange('last_game_at_start', 'last_game_at_end', '')->placeholder([
                    admin_trans('machine.fields.last_game_at'),
                    admin_trans('machine.fields.last_game_at')
                ]);
            });
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->expandFilter();
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create(
                        [
                            admin_trans('machine.btn.action'),
                            Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                        ])
                )->trigger(['click']);

                $dropdown->item(admin_trans('machine.btn.keep_time_change'),
                    'FieldTimeOutlined')->modal($this->keepTimeChange($data['id']));

                $dropdown->item(admin_trans('machine.btn.open_custom'),
                    'AppstoreAddOutlined')->modal($this->openCustom($data['id']));

                $dropdown->item(admin_trans('machine.btn.down'), 'fas fa-arrow-down')
                    ->modal($this->down($data['id']));

                $dropdown->item(admin_trans('machine.btn.move_on'), 'fas fa-arrow-circle-up')
                    ->confirm(admin_trans('machine.btn.move_on_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'move_on'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.move_off'), 'fas fa-arrow-circle-down')
                    ->confirm(admin_trans('machine.btn.move_off_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'move_off'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.start'), 'fas fa-angle-down')
                    ->confirm(admin_trans('machine.btn.start_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'start'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.stop_1'), 'fas fa-hand-point-up')
                    ->confirm(admin_trans('machine.btn.stop_1_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'stop_1'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.stop_2'), 'fas fa-hand-point-down')
                    ->confirm(admin_trans('machine.btn.stop_2_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'stop_2'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.stop_3'), 'fas fa-hourglass-start')
                    ->confirm(admin_trans('machine.btn.stop_3_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'stop_3'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.auto'), 'fas fa-lock-open')
                    ->modal($this->autoOn($data['id']));

                $dropdown->item(admin_trans('machine.btn.stop_auto'), 'fas fa-lock')
                    ->modal($this->autoStop($data['id']));

                $dropdown->item(admin_trans('machine.btn.pressure'), 'MoneyCollectFilled')
                    ->ajax([$this, 'action'], ['id' => $data->id, 'action' => 'pressure'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.score'), 'MoneyCollectOutlined')
                    ->ajax([$this, 'action'], ['id' => $data->id, 'action' => 'score'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.kick_player'), 'StopFilled')
                    ->confirm(admin_trans('machine.btn.kick_player_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'kick_player'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.kick_force'), 'StopOutlined')
                    ->confirm(admin_trans('machine.btn.kick_force_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'kick_force'])
                    ->gridRefresh();

                $actions->prepend(
                    $dropdown
                );
            });
        });
    }

    /**
     * 调整保留时间
     * @auth true
     * @param $id
     * @return Form
     */
    public function keepTimeChange($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            /** @var Machine $data */
            $data = $form->driver()->model()->where('id', $id)->first();
            $services = MachineServices::createServices($data, Container::getInstance()->translator->getLocale());
            $data->keep_seconds = $services->keep_seconds;
            $data->keeping = $services->keeping;
            $form->push(Detail::create($data, function (Detail $detail) {
                $detail->item('keep_seconds', admin_trans('machine.fields.keep_seconds'))->display(function ($val) {
                    return CarbonInterval::seconds($val)->cascade()->forHumans(null, true);
                });
                $detail->item('keeping', admin_trans('machine.fields.keeping'))->display(function ($val) {
                    if ($val == 1) {
                        return Tag::create(admin_trans('machine.keeping'))->color('red');
                    }
                    return Tag::create(admin_trans('machine.un_keeping'))->color('green');
                });
            })->bordered());
            $select = $form->select('type', '')
                ->options([
                    1 => admin_trans('machine.second'),
                    2 => admin_trans('machine.minutes'),
                    3 => admin_trans('machine.hour')
                ])
                ->default(1)
                ->required()
                ->bordered(false);
            $form->popItem();
            $form->radio('action_type')
                ->button()
                ->default(1)
                ->options([
                    1 => admin_trans('machine.increase_keep'),
                    2 => admin_trans('machine.decrease_keep')
                ])
                ->when(1, function (Form $form) use ($select) {
                    $form->text('duration')->rule([
                        'integer' => admin_trans('validator.integer'),
                        'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                    ])->placeholder(admin_trans('machine.increase_keep_placeholder'))->suffix($select)->required();
                })->when(2, function (Form $form) use ($select) {
                    $form->text('duration')->rule([
                        'integer' => admin_trans('validator.integer'),
                        'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                    ])->placeholder(admin_trans('machine.decrease_keep_placeholder'))->suffix($select)->required();
                })->style(['margin-top' => '20px']);
            $form->text('remark', admin_trans('machine.fields.remark'))->value('')->required()->style(['width' => '80% !important']);
            $form->saving(function (Form $form) use ($id, $data) {
                /** @var Machine $machine */
                $machine = $form->driver()->model()->where('id', $id)->first();
                $services = MachineServices::createServices($machine,
                    Container::getInstance()->translator->getLocale());
                if ($machine->gaming == 0) {
                    return message_error(admin_trans('machine.has_un_gaming'));
                }
                $inputData = $form->input();
                $duration = 0;
                if (isset($inputData['duration']) && !empty($inputData['duration']) && $inputData['duration'] > 0) {
                    switch ($inputData['type']) {
                        case 1:
                            $duration = $inputData['duration'];
                            break;
                        case 2:
                            $duration = bcmul($inputData['duration'], 60);
                            break;
                        case 3:
                            $duration = bcmul(bcmul($inputData['duration'], 60), 60);
                            break;
                    }
                    if ($inputData['action_type'] == 1) {
                        $services->keep_seconds = bcadd($services->keep_seconds, $duration);
                    } else {
                        $services->keep_seconds = max(bcsub($services->keep_seconds, $duration), 0);
                    }
                }
                sendSocketMessage('player-' . $machine->gaming_user_id . '-' . $machine->id, [
                    'msg_type' => 'player_machine_keeping',
                    'player_id' => $machine->gaming_user_id,
                    'machine_id' => $machine->id,
                    'keep_seconds' => $services->keep_seconds,
                    'keeping' => $services->keeping
                ]);
                sendSocketMessage('player-' . $machine->gaming_user_id, [
                    'msg_type' => 'player_machine_keeping',
                    'player_id' => $machine->gaming_user_id,
                    'machine_id' => $machine->id,
                    'keep_seconds' => $services->keep_seconds,
                    'keeping' => $services->keeping
                ]);

                $machineKeepingLog = new MachineKeepingLog();
                $machineKeepingLog->player_id = 0;
                $machineKeepingLog->machine_id = $data->id;
                $machineKeepingLog->machine_name = $data->name;
                $machineKeepingLog->keep_seconds = $services->keep_seconds;
                $machineKeepingLog->is_system = 2;
                $machineKeepingLog->user_id = Admin::id();
                $machineKeepingLog->department_id = Admin::user()->department_id;
                $machineKeepingLog->remark = $inputData['remark'];
                $machineKeepingLog->save();


                return message_success(admin_trans('machine.action.action_success'));
            });

        });
    }

    /**
     * 开分自定
     * @auth true
     * @param $id
     * @return Form
     */
    public function openCustom($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->number('open', admin_trans('machine.action.open_num'))
                ->min(1)
                ->default(100)
                ->style(['width' => '80% !important'])
                ->required()
                ->precision(0);
            $form->text('remark', admin_trans('machine.fields.remark'))->value('')->required()->style(['width' => '80% !important']);
            $form->saving(function (Form $form) use ($id) {
                return $this->action($id, 'open_custom', ['open' => $form->input('open')]);
            });
        });
    }

    /**
     * 下分
     * @param $id
     * @return Form
     */
    public function down($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->text('remark', admin_trans('machine.fields.remark'))->value('')->required()->style(['width' => '80% !important']);
            $form->saving(function (Form $form) use ($id) {
                return $this->action($id, 'down', ['open' => $form->input('open')]);
            });
        });
    }

    /**
     * 自动on
     * @param $id
     * @return Form
     */
    public function autoOn($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->text('remark', admin_trans('machine.fields.remark'))->value('')->required()->style(['width' => '80% !important']);
            $form->saving(function (Form $form) use ($id) {
                return $this->action($id, 'auto');
            });
        });
    }

    /**
     * 自动stop
     * @param $id
     * @return Form
     */
    public function autoStop($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->text('remark', admin_trans('machine.fields.remark'))->value('')->required()->style(['width' => '80% !important']);
            $form->saving(function (Form $form) use ($id) {
                return $this->action($id, 'stop_auto');
            });
        });
    }

    /**
     * 钢珠自动开始/暂停
     * @param $id
     * @return Form
     */
    public function autoStartOrStop($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->text('remark', admin_trans('machine.fields.remark'))->value('')->required()->style(['width' => '80% !important']);
            $form->saving(function (Form $form) use ($id) {
                return $this->action($id, 'plc_start_or_stop');
            });
        });
    }

    /**
     * 机台操作
     * @param $id
     * @auth true
     * @param $action
     * @param array $params
     * @return Msg|Notification
     * @throws \Exception
     */
    public function action($id, $action, array $params = [])
    {
        /** @var Machine $machine */
        $machine = $this->model::find($id);
        if (empty($machine)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        /** @var Player $player */
        $player = Player::find($machine->gaming_user_id);
        if (empty($player)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        try {
            $services = MachineServices::createServices($machine, Container::getInstance()->translator->getLocale());
            switch ($action) {
                case 'open_custom': // 开分自定(斯洛+钢珠)
                    machineOpenAnyFree($player, $machine, $params['open']);
                    break;
                case 'down': // 下分(斯洛+钢珠)
                    machineWash($player, $machine, 'down');
                    break;
                case 'plc_push_5hz': // PUSH AUTO 啟動
                    $machine->push_auto = 1;
                    $machine->save();
                    break;
                case 'plc_push_stop': // PUSH AUTO 關閉
                    $machine->push_auto = 0;
                    $machine->save();
                    break;
                case 'reset_wash_limit': // 歸零下轉限制
                    $machine->wash_limit = 0;
                    $machine->save();
                    break;
                case 'kick_player': // 踢除遊戲中的玩家
                    machineWash($player, $machine);
                    break;
                case 'kick_force': // 強制踢出(分數將不會返回玩家)
                    resetMachineTrans($machine, $player);
                    break;
                case 'start': // 開始(斯洛)
                    if ($services->auto == 1) {
                        throw new Exception(admin_trans('machine.action.slot_machine_must_stop_auto'));
                    }
                    if ($services->move_point == 0 && $machine->control_type == Machine::CONTROL_TYPE_MEI) {
                        $services->sendCmd($services::MOVE_POINT_ON, 0, 'admin', Admin::id());
                    }
                    $services->sendCmd($services::PRESSURE, 0, 'admin', Admin::id());
                    $services->sendCmd($services::START, 0, 'admin', Admin::id());
                    break;
                case 'auto': // 自動ON(斯洛)
                    if ($machine->type == GameType::TYPE_SLOT) {
                        $services->sendCmd($services::OUT_ON, 0, 'admin', Admin::id());
                        if ($machine->control_type == Machine::CONTROL_TYPE_MEI) {
                            $services->sendCmd($services::GET_AUTO_STATUS, 0, 'admin', Admin::id());
                        }
                    }
                    break;
                case 'move_on': // 移分ON(斯洛)
                    $services->sendCmd($services::MOVE_POINT_ON, 0, 'admin', Admin::id());
                    break;
                case 'move_off': // 移分OFF(斯洛)
                    $services->sendCmd($services::MOVE_POINT_OFF, 0, 'admin', Admin::id());
                    break;
                case 'pressure': // 總壓分(斯洛)
                case 'score': // 總得分(斯洛)
                    return notification_success(admin_trans('machine.action.search_success'), Html::div()->content([
                        Html::create(admin_trans($action == 'pressure' ? 'machine.action.search_pressure' : 'machine.action.search_score',
                            '', [
                                '{code}' => $machine->code,
                                '{result}' => $action == 'pressure' ? ($services->bet ?? 0) : ($services->win ?? 0)
                            ]))->style(['color' => 'red']),
                    ]), ['duration' => 5])->refreshMenu();
                case 'plc_up_turn_100': // 上轉(钢珠)
                case 'plc_down_turn': // 下轉(钢珠)
                case 'plc_sub_point': // 下珠(钢珠)
                case 'all_up_turn': // 全部上轉(钢珠)
                case 'all_down_turn': // 全部下轉(钢珠)
                case 'plc_start_or_stop': // 自動開始/暫停(钢珠)
                case 'stop_1': // A(斯洛)
                if ($services->auto == 1) {
                    throw new Exception(admin_trans('machine.action.slot_machine_must_stop_auto'));
                }
                $services->sendCmd($services::STOP_ONE, 0, 'admin', Admin::id());
                break;
                case 'stop_2': // B(斯洛)
                    if ($services->auto == 1) {
                        throw new Exception(admin_trans('machine.action.slot_machine_must_stop_auto'));
                    }
                    $services->sendCmd($services::STOP_TWO, 0, 'admin', Admin::id());
                    break;
                case 'stop_3': // C(斯洛)
                    if ($services->auto == 1) {
                        throw new Exception(admin_trans('machine.action.slot_machine_must_stop_auto'));
                    }
                    $services->sendCmd($services::STOP_THREE, 0, 'admin', Admin::id());
                    break;
                case 'stop_auto': // 自動OFF(斯洛)
                    if ($machine->type == GameType::TYPE_STEEL_BALL) {
                        $services->sendCmd($services::AUTO_UP_TURN, 0, 'admin', Admin::id());
                    } elseif ($machine->type == GameType::TYPE_SLOT) {
                        $services->sendCmd($services::OUT_OFF, 0, 'admin', Admin::id());
                    } else {
                        throw new Exception(admin_trans('machine.action.action_game_type_error'));
                    }
                    break;
                default:
                    throw new Exception(admin_trans('machine.action.action_error'));

            }
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * 钢珠机台资讯
     * @return Grid
     */
    public function steelBallInfoList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with(['gamingPlayer.channel'])->where('type',
                GameType::TYPE_STEEL_BALL)->where('gaming_user_id', '!=', 0)->orderBy('sort', 'asc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['last_point_at_start'])) {
                    $grid->model()->where('last_point_at', '>=', $requestFilter['last_point_at_start']);
                }
                if (!empty($requestFilter['last_point_at_end'])) {
                    $grid->model()->where('last_point_at', '<=', $requestFilter['last_point_at_end']);
                }
                if (!empty($requestFilter['last_game_at_start'])) {
                    $grid->model()->where('last_game_at', '>=', $requestFilter['last_game_at_start']);
                }
                if (!empty($requestFilter['last_game_at_end'])) {
                    $grid->model()->where('last_game_at', '<=', $requestFilter['last_game_at_end']);
                }
                if (isset($requestFilter['search_type'])) {
                    $grid->model()->whereHas('gamingPlayer', function ($query) use ($requestFilter) {
                        $query->where('is_test', $requestFilter['search_type']);
                    });
                }
            }
            $grid->title(admin_trans('machine.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine.fields.id'))->align('center');
            $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->type)),
                    $data->machineCategory->name ?? '',
                ]);
            })->align('center');
            $grid->column('name', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    $data->name ?? '',
                ]);
            })->align('center');
            $grid->column('code', admin_trans('machine.fields.code'))->sortable()->display(function (
                $val,
                Machine $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->code ?? '',
                    ]), 10)->style(['line-height' => '28px']);
                    $row->column(Button::create()->icon(Icon::create('far fa-play-circle'))->shape('circle')->type('dashed')->size('small')->modal([
                        $this,
                        'mediaPlay'
                    ], [
                        'id' => $data->machine_media->where('status', 1)->where('stream_name', '!=',
                                '-1')->first()->id ?? 0
                    ])->maskClosable()->title(admin_trans('machine_media.btn.media_play')), 6);
                })->align('center');
            })->align('center')->filter(
                FilterColumn::like()->text('code')
            );
            $grid->column('gaming_user_id', admin_trans('machine.fields.gaming_user_id'))->display(function (
                $val,
                $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->gamingPlayer->phone ?? '',
                    ]), 12)->style(['line-height' => '28px']);
                    $row->column(Button::create()->icon(Icon::create('fas fa-exchange-alt'))->shape('circle')->type('dashed')->size('small')->drawer('ex-admin/addons-webman-controller-PlayerController/changePlayerList',
                        ['machine_id' => $data->id]), 6);
                })->align('center');
            })->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    $data->gamingPlayer->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->align('center');
            $grid->column('gaming_player.channel.name', admin_trans('channel.fields.name'))->display(function (
                $val,
                Machine $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->gamingPlayer->channel->name ?? '',
                    ]), 12)->style(['line-height' => '28px']);
                })->align('center');
            })->align('center');
            $grid->column('money', admin_trans('player_platform_cash.fields.money'))->display(function ($val, $data) {
                // ✅ 从 Redis 读取实时余额
                $balance = $data->gaming_user_id ? WalletService::getBalance($data->gaming_user_id) : '';
                return Html::create()->content([$balance]);
            })->align('center');
            $grid->column('keep_seconds', admin_trans('machine.fields.keep_seconds'))->display(function (
                $val,
                Machine $data
            ) {
                $seconds = $data->keep_seconds;
                if ($seconds > 3600) {
                    $hours = intval($seconds / 3600);
                    $time = $hours . ":" . gmstrftime('%M:%S', $seconds);
                } else {
                    $time = gmstrftime('%H:%M:%S', $seconds);
                }
                return Html::create()->content([
                    $time ?? '',
                ]);
            })->align('center');
            $grid->column('keep_seconds', admin_trans('machine.fields.keep_seconds'))->display(function (
                $val,
                Machine $data
            ) {
                $services = MachineServices::createServices($data, Container::getInstance()->translator->getLocale());
                $seconds = $services->keep_seconds;
                if ($seconds > 3600) {
                    $hours = intval($seconds / 3600);
                    $time = $hours . ":" . gmstrftime('%M:%S', $seconds);
                } else {
                    $time = gmstrftime('%H:%M:%S', $seconds);
                }
                return Html::create()->content([
                    $time ?? '',
                ]);
            })->align('center');
            $grid->column('push_auto', admin_trans('machine.fields.push_auto'))->display(function ($val, $data) {
                return Switches::create(null, $val)
                    ->options([[1 => admin_trans('admin.open')], [0 => admin_trans('admin.close')]])
                    ->field('push_auto')
                    ->url('ex-admin/addons-webman-controller-MachineController/pushAutoChange')
                    ->params([
                        'id' => $data['id'],
                    ]);
            })->align('center');
            $grid->column('has_lock', admin_trans('machine.fields.has_lock'))->display(function (
                $val,
                Machine $data
            ) {
                $services = MachineServices::createServices($data);
                return Switches::create(null, $services->has_lock)
                    ->options([[1 => admin_trans('machine.lock')], [0 => admin_trans('machine.open')]])
                    ->url('ex-admin/addons-webman-controller-MachineController/changeLock')
                    ->field('has_lock')
                    ->params([
                        'id' => [$data->id],
                    ]);
            })->align('center');
            $grid->column('last_point_at', admin_trans('machine.fields.last_point_at'))
                ->display(function ($val, Machine $data) {
                    $services = MachineServices::createServices($data,
                        Container::getInstance()->translator->getLocale());
                    return Html::create()->content([
                        $services->last_point_at ? date('Y-m-d H:i:s', $services->last_point_at) : '',
                    ]);
                })
                ->align('center');
            $grid->column('last_game_at', admin_trans('machine.fields.last_game_at'))
                ->display(function ($val, Machine $data) {
                    $services = MachineServices::createServices($data,
                        Container::getInstance()->translator->getLocale());
                    return Html::create()->content([
                        $services->last_play_time ? date('Y-m-d H:i:s', $services->last_play_time) : '',
                    ]);
                })->align('center');
            $grid->hideDelete();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('code')->placeholder(admin_trans('machine.fields.code'));
                SelectGroup::create();
                $filter->in()->cascaderSingle('cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->eq()->select('gamingPlayer.department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('last_point_at_start');
                $filter->form()->hidden('last_point_at_end');
                $filter->form()->dateTimeRange('last_point_at_start', 'last_point_at_start', '')->placeholder([
                    admin_trans('machine.fields.last_point_at'),
                    admin_trans('machine.fields.last_point_at')
                ]);
                $filter->form()->hidden('last_game_at_start');
                $filter->form()->hidden('last_game_at_end');
                $filter->form()->dateTimeRange('last_game_at_start', 'last_game_at_end', '')->placeholder([
                    admin_trans('machine.fields.last_game_at'),
                    admin_trans('machine.fields.last_game_at')
                ]);
            });
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->expandFilter();
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create(
                        [
                            admin_trans('machine.btn.action'),
                            Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                        ])
                )->trigger(['click']);
                $dropdown->item(admin_trans('machine.btn.keep_time_change'),
                    'AppstoreAddOutlined')->modal($this->keepTimeChange($data['id']));

                $dropdown->item(admin_trans('machine.btn.open_custom'),
                    'AppstoreAddOutlined')->modal($this->openCustom($data['id']));

                $dropdown->item(admin_trans('machine.btn.down'), 'fas fa-arrow-down')
                    ->modal($this->down($data['id']));

                $dropdown->item(admin_trans('machine.btn.plc_up_turn'), 'fas fa-arrow-circle-up')
                    ->confirm(admin_trans('machine.btn.plc_up_turn_100_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'plc_up_turn_100'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.plc_down_turn'), 'fas fa-arrow-circle-down')
                    ->confirm(admin_trans('machine.btn.plc_down_turn_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'plc_down_turn'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.plc_sub_point'), 'fas fa-angle-down')
                    ->confirm(admin_trans('machine.btn.plc_sub_point_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'plc_sub_point'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.all_up_turn'), 'fas fa-hand-point-up')
                    ->confirm(admin_trans('machine.btn.all_up_turn_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'all_up_turn'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.all_down_turn'), 'fas fa-hand-point-down')
                    ->confirm(admin_trans('machine.btn.all_down_turn_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'all_down_turn'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.plc_start_or_stop'), 'fas fa-hourglass-start')
                    ->modal($this->autoStartOrStop($data['id']));

                $dropdown->item(admin_trans('machine.btn.reset_wash_limit'), 'SettingFilled')
                    ->confirm(admin_trans('machine.btn.reset_wash_limit_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'reset_wash_limit'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.kick_player'), 'StopFilled')
                    ->confirm(admin_trans('machine.btn.kick_player_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'kick_player'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.kick_force'), 'StopOutlined')
                    ->confirm(admin_trans('machine.btn.kick_force_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'kick_force'])
                    ->gridRefresh();

                $actions->prepend(
                    $dropdown
                );
            });
        });
    }

    /**
     * 斯洛机台资讯
     * @return Grid
     */
    public function fishInfoList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with(['gamingPlayer.channel', 'gamingPlayer'])->where('type',
                GameType::TYPE_FISH)->where('gaming_user_id', '!=', 0)->orderBy('sort', 'asc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['last_point_at_start'])) {
                    $grid->model()->where('last_point_at', '>=', $requestFilter['last_point_at_start']);
                }
                if (!empty($requestFilter['last_point_at_end'])) {
                    $grid->model()->where('last_point_at', '<=', $requestFilter['last_point_at_end']);
                }
                if (!empty($requestFilter['last_game_at_start'])) {
                    $grid->model()->where('last_game_at', '>=', $requestFilter['last_game_at_start']);
                }
                if (!empty($requestFilter['last_game_at_end'])) {
                    $grid->model()->where('last_game_at', '<=', $requestFilter['last_game_at_end']);
                }
                if (isset($requestFilter['search_type'])) {
                    $grid->model()->whereHas('gamingPlayer', function ($query) use ($requestFilter) {
                        $query->where('is_test', $requestFilter['search_type']);
                    });
                }
            }
            $grid->title(admin_trans('machine.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine.fields.id'))->align('center');
            $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->type)),
                    $data->machineCategory->name ?? '',
                ]);
            })->align('center');
            $grid->column('name', admin_trans('machine.fields.name'))->align('center')->filter(
                FilterColumn::like()->text('name')
            );
            $grid->column('code', admin_trans('machine.fields.code'))->sortable()->display(function (
                $val,
                Machine $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->code ?? '',
                    ]), 10)->style(['line-height' => '28px']);
                    $row->column(Button::create()->icon(Icon::create('far fa-play-circle'))->shape('circle')->type('dashed')->size('small')->modal([
                        $this,
                        'mediaPlay'
                    ], [
                        'id' => $data->machine_media->where('status', 1)->where('stream_name', '!=',
                                '-1')->first()->id ?? 0
                    ])->maskClosable()->title(admin_trans('machine_media.btn.media_play')), 6);
                })->align('center');
            })->align('center')->filter(
                FilterColumn::like()->text('code')
            );
            $grid->column('seat', admin_trans('machine.fields.seat'))->display(function ($val) {
                return admin_trans('machine.seat.' . $val);
            })->align('center');
            $grid->column('gaming_user_id', admin_trans('machine.fields.gaming_user_id'))->display(function (
                $val,
                Machine $data
            ) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($data) {
                    $row->column(Html::create()->content([
                        $data->gamingPlayer->phone ?? '',
                    ]), 12)->style(['line-height' => '28px']);
                    $row->column(Button::create()->icon(Icon::create('fas fa-exchange-alt'))->shape('circle')->type('dashed')->size('small')->drawer('ex-admin/addons-webman-controller-PlayerController/changePlayerList',
                        ['machine_id' => $data->id]), 6);
                })->align('center');
            })->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    $data->gamingPlayer->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_platform_cash.fields.money'))->display(function (
                $val,
                Machine $data
            ) {
                return Html::create()->content([
                    $data->gamingPlayer->machine_wallet->money ?? '',
                ]);
            })->align('center');
            $grid->column('last_point_at', admin_trans('machine.fields.last_point_at'))->display(function ($val) {
                return $val > 0 ? date('Y-m-d H:i:s', strtotime($val)) : '';
            })->sortable()->align('center');
            $grid->column('last_game_at', admin_trans('machine.fields.last_game_at'))->display(function ($val) {
                return $val > 0 ? date('Y-m-d H:i:s', strtotime($val)) : '';
            })->align('center');
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('code')->placeholder(admin_trans('machine.fields.code'));
                SelectGroup::create();
                $filter->in()->cascaderSingle('cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->eq()->select('seat')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('machine.fields.seat'))
                    ->options(getSeatOptions());
                $filter->form()->dateTimeRange('last_point_at_start', 'last_point_at_end', '')->placeholder([
                    admin_trans('machine.fields.last_point_at'),
                    admin_trans('machine.fields.last_point_at')
                ]);
                $filter->form()->dateTimeRange('last_game_at_start', 'last_game_at_end', '')->placeholder([
                    admin_trans('machine.fields.last_game_at'),
                    admin_trans('machine.fields.last_game_at')
                ]);
            });
            $grid->hideSelection();
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create(
                        [
                            admin_trans('machine.btn.action'),
                            Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                        ])
                )->trigger(['click']);

                $actions->prepend(
                    $dropdown
                );
            });
        });
    }
    
    /**
     * 机台锁切换
     * @auth true
     * @param $id
     * @param $data
     * @return Msg
     */
    public function changeLock($id, $data): Msg
    {
        /** @var Machine $machine */
        $machine = Machine::query()->where('id', $id['0'])->first();
        if (empty($machine)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        try {
            $services = MachineServices::createServices($machine);
            $services->has_lock = $data['has_lock'];
            if ($services->has_lock == 1) {
                sendMachineException($machine, Notice::TYPE_MACHINE_LOCK, $machine->gaming_user_id);
            }
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }
        
        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * pushAuto切换
     * @auth true
     */
    public function pushAutoChange($id): Msg
    {
        /** @var Machine $machine */
        $machine = $this->model::find($id);
        if (empty($machine)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        try {
            $services = MachineServices::createServices($machine);
            if ($machine->control_type == Machine::CONTROL_TYPE_MEI) {
                if ($services->push_auto == 0) {
                    $services->sendCmd($services::PUSH . $services::PUSH_THREE, 0, 'admin', Admin::id());
                } else {
                    $services->sendCmd($services::PUSH_STOP, 0, 'admin', Admin::id());
                }
            }
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * keeping切换
     * @throws Exception
     */
    public function keepingChange($id, $data): Msg
    {
        /** @var Machine $machine */
        $machine = $this->model::find($id);
        if (empty($machine)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        try {
            $services = MachineServices::createServices($machine);
            if ($services->keeping == 1) {
                //鋼珠自動中不能保留
                if ($machine->type == 0) {
                    if ($services->auto == 1) {
                        throw new Exception(admin_trans('machine.btn.no_keeping'));
                    }
                }
                $services->keeping = 1;
                $services->keeping_user_id = $machine->gaming_user_id;
                $services->last_keep_at = time();
            } else {
                $services->keeping = 0;
                $services->keeping_user_id = 0;
            }
            $services->last_play_time = time();
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }
        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * 媒体服务
     * @auth true
     */
    public function mediaList($id): Grid
    {
        return Grid::create(new $this->media_model(), function (Grid $grid) use ($id) {
            $grid->model()->where('machine_id', $id)->orderBy('sort', 'asc');
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('machine_media.title'));
            $grid->column('id', admin_trans('machine_media.fields.id'))->align('center');
            $grid->column('stream_name', admin_trans('machine_media.fields.stream_name'))->display(function ($value) {
                $layout = Layout::create();
                return $layout->row(function (Row $row) use ($value) {
                    $row->column(function (Col $column) use ($value) {
                        $column->row($value == -1 ? Tag::create(admin_trans('machine.machine_media_abnormal'))->color('red') : Tag::create(admin_trans('machine.machine_media_normal'))->color('green'))->align('center');
                        $column->row($value == -1 ? '' : $value)->align('center');
                    }, 18);
                })->align('center');
            })->align('center');
            $grid->column('pull_ip', admin_trans('machine_media.fields.pull_ip'))->align('center');
            $grid->column('media_ip', admin_trans('machine_media.fields.media_ip'))->align('center');
            $grid->column('media_app', admin_trans('machine_media.fields.media_app'))->align('center');
            $grid->column('status', admin_trans('machine_media.fields.status'))->display(function ($value, $data) use (
                $grid,
                $id
            ) {
                return Switches::create(null, $value)
                    ->options([[1 => admin_trans('admin.open')], [0 => admin_trans('admin.close')]])
                    ->url($grid->attr('url'))
                    ->field('status')
                    ->params([
                        'ex_admin_action' => 'update',
                        'ids' => [$data[$grid->driver()->getPk()]],
                        'id' => $id
                    ]);
            })->align('center');
            $grid->actions(function (Actions $actions, MachineMedia $data) {
                $actions->hideDetail();
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create([
                        admin_trans('machine_media.btn.action'),
                        Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                    ]))->trigger(['click']);

                $dropdown->item(admin_trans('machine_media.btn.rebuild_media'), 'reload-outlined')
                    ->confirm(admin_trans('machine_media.btn.rebuild_media_confirm'), [$this, 'rebuildMedia'],
                        ['id' => $data->id])->gridRefresh();

                $dropdown->item(admin_trans('machine_media.btn.media_delete'), 'DeleteOutlined')
                    ->confirm(admin_trans('machine_media.btn.rebuild_delete_confirm'), [$this, 'mediaDelete'],
                        ['id' => $data->id])->gridRefresh();
                $dropdown->item(admin_trans('machine_media_push.tencent_media') . ' ' . $data->code,
                    'fas fa-file-video')
                    ->modal([$this, 'tencentMedia'], ['id' => $data->id])
                    ->width('70%');
                $dropdown->item(admin_trans('machine_media.btn.media_play'), 'far fa-play-circle')
                    ->modal([$this, 'mediaPlay'], ['id' => $data->id])
                    ->maskClosable();
                $dropdown->item(admin_trans('machine_media.btn.start_recording'), 'fas fa-video')
                    ->ajax([$this, 'startRecording'], ['id' => $data->id]);
                $dropdown->item(admin_trans('machine_media.btn.stop_recording'), 'fas fa-video-slash')
                    ->ajax([$this, 'stopRecording'], ['id' => $data->id]);
                $actions->prepend(
                    $dropdown
                );

            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
        });
    }

    /**
     * 腾讯推流地址列表
     * @auth true
     */
    public function tencentMedia($id): Grid
    {
        return Grid::create(new $this->machine_media_push_model(), function (Grid $grid) use ($id) {
            $grid->model()->where('media_id', $id)->orderBy('id', 'desc');
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('machine_media_push.title'));
            $grid->column('id', admin_trans('machine_media_push.fields.id'))->align('center');
            $grid->column('machine_code', admin_trans('machine.fields.code'))->align('center');
            $grid->column('rtmp_url', admin_trans('machine_media_push.fields.rtmp_url'))->align('center');
            $grid->column('expiration_date', admin_trans('machine_media_push.fields.expiration_date'))->align('center');
            $grid->column('created_at', admin_trans('machine.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            //删除清除缓存
            $grid->deling(function ($ids) {
                /** @var MachineMediaPush $machineMediaPush */
                $machineMediaPush = MachineMediaPush::query()->whereIn('id', $ids)->first();
                try {
                    (new MediaServer($machineMediaPush->media->push_ip,
                        $machineMediaPush->media->media_app))->deleteRtmpEndpoint($machineMediaPush->endpoint_service_id,
                        $machineMediaPush->media->stream_name);
                } catch (\Exception $e) {
                    return message_error($e->getMessage());
                }
                return message_success(admin_trans('machine.action.action_success'));
            });
        });
    }

    /**
     * 开始录制
     * @auth true
     * @param $id
     * @return Msg
     * @throws \Exception
     */
    public function stopRecording($id): Msg
    {
        /** @var MachineMedia $media */
        $media = $this->media_model::find($id);
        if (empty($media)) {
            return message_error(admin_trans('machine_media.not_fount'));
        }
        try {
            (new MediaServer($media->push_ip, $media->media_app))->stopRecording($media);
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }
    
    /**
     * 开始录制
     * @param MachineRecording $machineRecording
     * @return Html|Msg
     */
    public function getRecording(MachineRecording $machineRecording): Msg|Html
    {
        try {
            $playAddress = (new MediaServer($machineRecording->media->push_ip,
                $machineRecording->media->media_app))->getRecording($machineRecording);
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }
        return admin_view(plugin()->webman->getPath() . '/views/vod_play.vue')->attrs([
            'play_address' => $playAddress,
        ]);
    }

    /**
     * 开始录制
     * @auth true
     * @param $id
     * @return Msg
     * @throws Exception|\Exception
     */
    public function startRecording($id): Msg
    {
        /** @var MachineMedia $media */
        $media = $this->media_model::find($id);
        if (empty($media)) {
            return message_error(admin_trans('machine_media.not_fount'));
        }
        try {
            (new MediaServer($media->push_ip, $media->media_app))->startRecording($media);
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * 删除视讯流
     * @auth true
     * @param $id
     * @return Msg
     * @throws Exception|\Exception
     */
    public function mediaDelete($id): Msg
    {
        /** @var MachineMedia $media */
        $media = $this->media_model::find($id);
        if (empty($media)) {
            return message_error(admin_trans('machine_media.not_fount'));
        }
        Db::beginTransaction();
        try {
            (new MediaServer($media->push_ip, $media->media_app))->deleteMachineStream($media->stream_name);
            $media->forceDelete();
            MachineMediaPush::query()->where('media_id', $media->id)->delete();
            Db::commit();
        } catch (Exception) {
            Db::rollback();
            return message_error(admin_trans('machine_media.action_error'));
        }

        return message_success(admin_trans('machine_media.delete_success'));
    }

    /**
     * 播放视频流
     * @auth true
     * @param $id
     * @return Html|Msg
     */
    public function mediaPlay($id)
    {
        /** @var MachineMedia $media */
        $media = $this->media_model::find($id);
        $mediaList = $this->media_model::where('machine_id', $media->machine_id)->where('status',
            1)->whereNull('deleted_at')->get();
        $srcList = [];
        $mediaKey = 1;
        /** @var MachineMedia $value */
        foreach ($mediaList as $value) {
            $srcList[] = [
                'src' => 'https://' . $value->pull_ip . '/' . $value->media_app . '/play.html?id=' . $value->stream_name,
                'title' => admin_trans('machine_media.media_title') . $mediaKey,
                'desc' => admin_trans('machine_media.fields.pull_ip') . ':' . $value->pull_ip . ' ' . admin_trans('machine_media.fields.stream_name') . ':' . $value->stream_name,
            ];
            $mediaKey++;
        }
        if (empty($media)) {
            return message_error(admin_trans('machine_media.not_fount'));
        }
        return admin_view(plugin()->webman->getPath() . '/views/media_play.vue')->attrs([
            'iframe_src' => 'https://' . $media->pull_ip . '/' . $media->media_app . '/play.html?id=' . $media->stream_name,
            'iframe_list' => $srcList,
            'btn_text' => admin_trans('machine_media.btn_text'),
            'play_address' => admin_trans('machine_media.play_address'),
            'action_list' => getMachineAction($media->machine->type, $media->machine->control_type),
            'open_any_point_cmd' => getMachineOpenAny($media->machine->type, $media->machine->control_type),
            'type' => $media->machine->type,
            'machine_id' => $media->machine_id,
        ]);
    }

    /**
     * 更换开分卡
     * @param $id
     * @return Msg
     * @throws \Exception
     */
    public function changePointCard($id): Msg
    {
        /** @var Machine $machine */
        $machine = $this->model::find($id);
        if (empty($machine)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        if ($machine->gaming == 1) {
            return message_error(admin_trans('machine.change_point_gaming'));
        }
        try {
            $services = MachineServices::createServices($machine);
            switch ($machine->type) {
                case GameType::TYPE_STEEL_BALL:
                    $services->change_point_card_status = 1;
                    $services->sendCmd($services::WIN_NUMBER, 0, 'admin', Admin::id());
                    break;
                case GameType::TYPE_SLOT:
                    $services->change_point_card_status = 1;
                    $services->sendCmd($services::READ_WIN, 0, 'admin', Admin::id());
                    $services->sendCmd($services::READ_BET, 0, 'admin', Admin::id());
                    break;
                default:
                    throw new Exception();
            }
            $machineOpenCard = new MachineOpenCard();
            $machineOpenCard->machine_id = $machine->id;
            $machineOpenCard->user_id = Admin::id() ?? 0;
            $machineOpenCard->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
            $machineOpenCard->save();
        } catch (Exception $e) {
            return message_error(admin_trans('machine.action_error') . ':' . $e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * 压分异常清理
     * @param $id
     * @return Msg
     * @throws \Exception
     */
    public function clearBet($id): Msg
    {
        /** @var Machine $machine */
        $machine = $this->model::find($id);
        if (empty($machine)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        try {
            $services = MachineServices::createServices($machine);
            switch ($machine->type) {
                case GameType::TYPE_SLOT:
                    $services->sendCmd($services::ALL_DOWN, 0, 'admin', Admin::id());
                    $services->bet = 0;
                    $services->score = 0;
                    $services->player_pressure = 0;
                    break;
                case GameType::TYPE_STEEL_BALL:
                    $services->sendCmd($services::CLEAR_LOG, 0, 'admin', Admin::id());
                    $services->win_number = 0;
                    $services->player_win_number = 0;
                    break;
            }
        } catch (Exception $e) {
            return message_error(admin_trans('machine.action_error') . ':' . $e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * 媒体服务
     * @auth true
     */
    public function mediaRecording($id): Grid
    {
        return Grid::create(new $this->machine_recording_model(), function (Grid $grid) use ($id) {
            $grid->model()->where('machine_id', $id)->orderBy('id', 'desc');
            $grid->bordered();
            $grid->autoHeight();
            $grid->title('machine_recording.title');
            $grid->column('id', admin_trans('machine_recording.fields.id'))->align('center');
            $grid->column('media.stream_name', admin_trans('machine_media.fields.stream_name'))->align('center');
            $grid->column('machine_name', admin_trans('machine.fields.name'))->sortable()->align('center');
            $grid->column('machine_code', admin_trans('machine.fields.code'))->sortable()->align('center');
            $grid->column('status', admin_trans('machine_recording.fields.status'))->display(function ($value) {
                switch ($value) {
                    case MachineRecording::STATUS_STARTING:
                        $tag = Tag::create(admin_trans('machine_recording.status.' . MachineRecording::STATUS_STARTING))->color('#108ee9');
                        break;
                    case MachineRecording::STATUS_COMPLETE:
                        $tag = Tag::create(admin_trans('machine_recording.status.' . MachineRecording::STATUS_COMPLETE))->color('#f50');
                        break;
                    case MachineRecording::STATUS_FAIL:
                        $tag = Tag::create(admin_trans('machine_recording.status.' . MachineRecording::STATUS_FAIL))->color('red');
                        break;
                    default:
                        $tag = '';
                }
                return Html::create()->content([
                    $tag
                ]);
            })->align('center');
            $grid->column('type', admin_trans('machine_recording.fields.type'))->display(function ($value) {
                switch ($value) {
                    case MachineRecording::TYPE_TEST:
                        $tag = Tag::create(admin_trans('machine_recording.type.' . MachineRecording::TYPE_TEST))->color('#108ee9');
                        break;
                    case MachineRecording::TYPE_OPEN:
                        $tag = Tag::create(admin_trans('machine_recording.type.' . MachineRecording::TYPE_OPEN))->color('#f50');
                        break;
                    case MachineRecording::TYPE_WASH:
                        $tag = Tag::create(admin_trans('machine_recording.type.' . MachineRecording::TYPE_WASH))->color('#2db7f5');
                        break;
                    case MachineRecording::TYPE_REWARD:
                        $tag = Tag::create(admin_trans('machine_recording.type.' . MachineRecording::TYPE_REWARD))->color('#87d068');
                        break;
                    default:
                        $tag = '';
                }
                return Html::create()->content([
                    $tag
                ]);
            })->align('center');
            $grid->column('start_time',
                admin_trans('machine_recording.fields.start_time'))->sortable()->align('center');
            $grid->column('end_time', admin_trans('machine_recording.fields.end_time'))->sortable()->align('center');
            $grid->actions(function (Actions $actions, MachineRecording $data) {
                $actions->hideDetail();
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create([
                        admin_trans('machine_media.btn.action'),
                        Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                    ]))->trigger(['click']);
                $dropdown->item(admin_trans('machine_media.btn.get_recording'), 'fas fa-video')
                    ->modal([$this, 'getRecording'], ['machineRecording' => $data])
                    ->width('576px')
                    ->maskClosable();
                $dropdown->item(admin_trans('machine_media.btn.stop_recording'), 'fas fa-video-slash')
                    ->ajax([$this, 'stopRecording'], ['id' => $data->media_id]);
                $dropdown->item(admin_trans('machine_media.btn.delete_recording'), 'fas fa-video')
                    ->ajax([$this, 'deleteRecording'], ['id' => $data->id])->gridRefresh();
                $actions->prepend(
                    $dropdown
                );
            })->align('center');
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->hideTrashedDelete();
            $grid->tools(
                Button::create(admin_trans('machine_media.btn.batch_delete'))
                    ->confirm(admin_trans('machine_media.btn.batch_delete_confirm'), [$this, 'batchDelete'])
                    ->gridBatch()
                    ->gridRefresh()
            );
        });
    }

    /**
     * 批量删除录制视频
     * @auth true
     * @return Msg
     */
    public function batchDelete(): Msg
    {
        $data = Request::input();
        $selected = $data['selected'] ?? [];
        $machineRecording = MachineRecording::query()->whereIn('id', $selected)->get();
        /** @var MachineRecording $item */
        foreach ($machineRecording as $item) {
            try {
                (new MediaServer($item->media->push_ip, $item->media->media_app))->deleteRecording($item);
            } catch (\Exception $e) {
                continue;
            }
            $item->delete();
        }
        return message_success(admin_trans('grid.update_success'))->refreshMenu();
    }

    /**
     * 删除录制视频
     * @auth true
     * @param $id
     * @return Msg
     * @throws \Exception
     */
    public function deleteRecording($id): Msg
    {
        /** @var MachineRecording $machineRecording */
        $machineRecording = MachineRecording::query()->find($id);
        if (empty($machineRecording)) {
            return message_error(admin_trans('machine_media.not_fount'));
        }
        try {
            (new MediaServer($machineRecording->media->push_ip,
                $machineRecording->media->media_app))->deleteRecording($machineRecording);
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }
}
