<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineMedia;
use addons\webman\model\MachineProducer;
use addons\webman\model\MachineStrategy;
use app\service\MachineApiService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\field\Switches;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\support\Container;
use Illuminate\Support\Str;
use support\Cache;
use support\Log;

/**
 * 管理后台 - 线下机台管理
 * @group admin
 */
class AdminOfflineMachineController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_model');
    }

    /**
     * 线下机台列表
     * @group admin
     * @auth true
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->slotList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->steelBallList())
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * 斯洛机台列表
     * @group admin
     * @auth true
     * @return Grid
     */
    public function slotList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('offline_machine.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示线下斯洛机台
            $grid->model()
                ->where('machine_source', Machine::MACHINE_SOURCE_OFFLINE)
                ->where('type', GameType::TYPE_SLOT)
                ->with(['machineLabel', 'machineCategory', 'producer', 'channelMachines.storeAdmin', 'channelMachines.channel'])
                ->orderBy('sort')
                ->orderBy('id', 'desc');

            $this->buildGrid($grid, GameType::TYPE_SLOT);

            // 设置表单
            $grid->setForm()->drawer([$this, 'slotForm']);

            // 删除时清除缓存
            $grid->deling(function ($ids) {
                $machineList = Machine::query()->whereIn('id', $ids)->get(['domain', 'port', 'type']);
                foreach ($machineList as $machine) {
                    $cacheKey = sprintf('machine:domain:%s:port:%s:type:%s',
                        $machine->domain, $machine->port, $machine->type
                    );
                    Cache::delete($cacheKey);
                }
            });
        });
    }

    /**
     * 钢珠机台列表
     * @group admin
     * @auth true
     * @return Grid
     */
    public function steelBallList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('offline_machine.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示线下钢珠机台
            $grid->model()
                ->where('machine_source', Machine::MACHINE_SOURCE_OFFLINE)
                ->where('type', GameType::TYPE_STEEL_BALL)
                ->with(['machineLabel', 'machineCategory', 'producer', 'channelMachines.storeAdmin', 'channelMachines.channel'])
                ->orderBy('sort')
                ->orderBy('id', 'desc');

            $this->buildGrid($grid, GameType::TYPE_STEEL_BALL);

            // 设置表单
            $grid->setForm()->drawer([$this, 'steelBallForm']);

            // 删除时清除缓存
            $grid->deling(function ($ids) {
                $machineList = Machine::query()->whereIn('id', $ids)->get(['domain', 'port', 'type']);
                foreach ($machineList as $machine) {
                    $cacheKey = sprintf('machine:domain:%s:port:%s:type:%s',
                        $machine->domain, $machine->port, $machine->type
                    );
                    Cache::delete($cacheKey);
                }
            });
        });
    }

    /**
     * 构建Grid列定义
     * @param Grid $grid
     * @param int $gameType
     */
    private function buildGrid(Grid $grid, int $gameType): void
    {
        $grid->column('id', 'ID')->width(80)->align('center')->fixed(true);

        $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
            return Html::create()->content([
                Tag::create(getGameTypeName($data->type)),
                $data->machineCategory->name ?? '',
            ]);
        })->width(150)->align('center');

        $grid->column('producer_id', admin_trans('machine.fields.producer_id'))->display(function ($val, Machine $data) {
            return !empty($data->producer->name) ? Tag::create($data->producer->name)->color('green') : '-';
        })->width(120)->align('center');

        $grid->column('control_type', admin_trans('machine.fields.control_type'))->display(function ($val) {
            return Tag::create(admin_trans('machine.control_type.' . $val))->color('orange');
        })->width(100)->align('center');

        $grid->column('name', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
            return $data->machineLabel->name ?? '-';
        })->width(150)->align('center');

        $grid->column('code', admin_trans('machine.fields.code'))->width(120)->sortable();

        // 所属渠道
        $grid->column('channel', admin_trans('offline_machine.fields.channel'))
            ->display(function ($val, Machine $data) {
                $channelMachine = $data->channelMachines->first();
                if (!$channelMachine || !$channelMachine->channel) {
                    return Tag::create(admin_trans('offline_machine.status.unassigned'))->color('default');
                }
                return $channelMachine->channel->name;
            })->width(120);

        // 绑定店家
        $grid->column('store', admin_trans('offline_machine.fields.store'))
            ->display(function ($val, Machine $data) {
                $channelMachine = $data->channelMachines->first();
                if (!$channelMachine) {
                    return Tag::create(admin_trans('offline_machine.status.unassigned'))->color('default');
                }

                if (!$channelMachine->storeAdmin) {
                    return Tag::create(admin_trans('offline_machine.status.unbound'))->color('orange');
                }

                return Tag::create($channelMachine->storeAdmin->nickname ?: $channelMachine->storeAdmin->username)->color('blue');
            })->width(150);

        // 实时状态
        $onlineStatusCache = null;
        $grid->column('now_status', admin_trans('machine.fields.now_status'))
            ->display(function ($val, Machine $data) use (&$onlineStatusCache, $gameType) {
                // 首次调用时批量获取所有机台在线状态
                if ($onlineStatusCache === null) {
                    $onlineStatusCache = [];
                    try {
                        // 获取当前页所有机台ID
                        $allMachines = $this->model::query()
                            ->where('type', $gameType)
                            ->where('machine_source', Machine::MACHINE_SOURCE_OFFLINE)
                            ->get(['id']);
                        $machineIds = $allMachines->pluck('id')->toArray();

                        // 批量检查在线状态
                        if (!empty($machineIds)) {
                            $result = MachineApiService::getAllOnlineStatus(
                                departmentId: Admin::user()->department_id,
                                type: $gameType,
                                adminId: Admin::id(),
                                machineIds: $machineIds
                            );
                            if (is_array($result)) {
                                foreach ($result as $item) {
                                    if (isset($item['id']) && isset($item['online'])) {
                                        $onlineStatusCache[$item['id']] = $item['online'];
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \support\Log::warning('Batch check offline machine online status failed', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // 从缓存中读取在线状态
                $machineStatus = ($onlineStatusCache[$data->id] ?? false) ? 'online' : 'offline';
                return admin_view(plugin()->webman->getPath() . '/views/machine_status.vue')->attrs([
                    'id' => $data->id,
                    'type' => Admin::user()->type == 1 ? 'admin' : 'channel',
                    'department_id' => Admin::user()->department_id,
                    'ws' => config('app.ws_url', ''),
                    'machine_status' => $machineStatus,
                ]);
            })
            ->width(100)->align('center');

        $grid->column('odds_x', admin_trans('machine.fields.odds_x'))
            ->editable(Editable::number('odds_x')->min(1)->precision(3))
            ->width(100)->align('center');

        $grid->column('odds_y', admin_trans('machine.fields.odds_y'))
            ->editable(Editable::number('odds_y')->min(1)->precision(3))
            ->width(100)->align('center');

        $grid->column('control_open_point', admin_trans('machine.fields.control_open_point'))
            ->editable(Editable::number('control_open_point')->min(1)->precision(0))
            ->width(120)->align('center');

        $grid->column('min_point', admin_trans('machine.fields.min_point'))->width(100)->align('center');
        $grid->column('max_point', admin_trans('machine.fields.max_point'))->width(100)->align('center');

        $grid->column('status', admin_trans('machine.fields.status'))->switch()->width(80)->align('center');
        $grid->column('is_use', admin_trans('machine.fields.is_use'))->switch()->width(80)->align('center');
        $grid->column('maintaining', admin_trans('machine.fields.maintaining'))->switch()->width(100)->align('center');
        $grid->column('has_lock', admin_trans('machine.fields.has_lock'))->display(function (
            $val,
            Machine $data
        ) {
            try {
                $service = \app\service\machine\MachineServices::createServices(
                    $data,
                    Container::getInstance()->translator->getLocale()
                );
                $hasLock = (int)($service->has_lock ?? 0);
            } catch (\Exception $e) {
                $hasLock = 0;
            }

            return Switches::create(null, $hasLock)
                ->options([[1 => admin_trans('machine.lock')], [0 => admin_trans('machine.open')]])
                ->url('ex-admin/addons-webman-controller-MachineController/changeLock')
                ->field('has_lock')
                ->params([
                    'id' => [$data->id],
                ]);
        })->align('center');
        $grid->column('gaming', admin_trans('offline_machine.fields.gaming'))
            ->display(function ($val) {
                return $val == 1
                    ? Tag::create(admin_trans('offline_machine.status.gaming'))->color('processing')
                    : Tag::create(admin_trans('offline_machine.status.idle'))->color('default');
            })->width(100)->align('center');

        $grid->sortInput();

        $grid->column('created_at', admin_trans('common.created_at'))->width(170)->sortable()->align('center');

        $grid->column('remark', admin_trans('machine.fields.remark'))
            ->editable(Editable::textarea()->showCount()->rule(['max:250' => admin_trans('machine.remark_limit')]))
            ->display(function ($value, Machine $data) {
                return Str::of($data->remark)->limit(35, ' (...)');
            })->width('180px')->align('center');

        // ✅ 新增：操作按钮（解锁和归0）- 使用ExAdmin的Actions
        $grid->actions(function (Actions $action, Machine $data) use ($gameType) {
            // 编辑按钮
            if ($gameType == GameType::TYPE_SLOT) {
                $action->edit()->drawer([$this, 'slotForm']);
            } else {
                $action->edit()->drawer([$this, 'steelBallForm']);
            }

            // ✅ 指令测试按钮（独立按钮，所有线下机台显示）
            $action->prepend(
                Button::create('指令测试')
                    ->type('primary')
                    ->size('small')
                    ->modal([$this, 'commandTest'], [
                        'machine_id' => $data->id,
                        'game_type' => $gameType,
                        'control_type' => $data->control_type
                    ])
                    ->width('90%')
            );

            // 操作下拉菜单
            $dropdown = Dropdown::create(
                Button::create(['操作', Icon::create('DownOutlined')->style(['marginRight' => '5px'])])
            )->trigger(['click']);

            // 解锁机台（仅锁定时显示）
            if ($data->has_lock == 1) {
                $dropdown->item('解锁机台', 'unlock')
                    ->confirm('确定要解锁此机台吗？', [$this, 'unlockMachine'], ['machine_id' => $data->id])
                    ->gridRefresh();
            }

            // 归0机板（仅小淞线下版Slot显示）
            if ($data->control_type === Machine::CONTROL_TYPE_SONG && $gameType === GameType::TYPE_SLOT) {
                $dropdown->item('归0机板', 'reload-outlined')
                    ->confirm(
                        '确定要归0机板 ' . $data->code . ' 吗？',
                        [$this, 'resetMachine'],
                        ['machine_id' => $data->id]
                    )
                    ->gridRefresh();
            }

            $action->prepend($dropdown);
        });

        // 筛选器
        $grid->filter(function (Filter $filter) use ($gameType) {
            $filter->like()->text('machineLabel.name')
                ->placeholder(admin_trans('machine.fields.name'));

            $filter->like()->text('code')
                ->placeholder(admin_trans('machine.fields.code'));

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
                ->style(['width' => '120px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    '' => admin_trans('public_msg.all'),
                    1 => admin_trans('common.status.enable'),
                    0 => admin_trans('common.status.disable'),
                ]);

            $filter->eq()->select('gaming')
                ->placeholder(admin_trans('offline_machine.fields.gaming'))
                ->showSearch()
                ->style(['width' => '120px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    '' => admin_trans('public_msg.all'),
                    1 => admin_trans('offline_machine.status.gaming'),
                    0 => admin_trans('offline_machine.status.idle'),
                ]);

            $filter->in()->cascaderSingle('cate_id')
                ->showSearch()
                ->style(['width' => '150px'])
                ->placeholder(admin_trans('machine.fields.cate_id'))
                ->options(getCateListOptions())
                ->multiple();

            $producerModel = plugin()->webman->config('database.machine_producer_model');
            $producerOptions = $producerModel::select(['id', 'name'])->pluck('name', 'id')->all();
            $filter->eq()->select('producer_id')
                ->placeholder(admin_trans('machine.fields.producer_id'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options($producerOptions);
        });

        $grid->expandFilter();
        $grid->hideDelete();
    }

    /**
     * 斯洛机台表单
     * @group admin
     * @auth true
     */
    public function slotForm(): Form
    {
        return $this->buildForm(GameType::TYPE_SLOT);
    }

    /**
     * 钢珠机台表单
     * @group admin
     * @auth true
     */
    public function steelBallForm(): Form
    {
        return $this->buildForm(GameType::TYPE_STEEL_BALL);
    }

    /**
     * 构建表单
     * @param int $gameType
     * @return Form
     */
    private function buildForm(int $gameType): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($gameType) {
            $form->title(admin_trans('offline_machine.title'));
            $form->hidden('type')->default($gameType);
            $form->hidden('machine_source')->default(Machine::MACHINE_SOURCE_OFFLINE);
            $form->hidden('is_live')->default(0);

            // 基本信息
            $form->row(function (Form $form) use ($gameType) {
                $form->column(function (Form $form) use ($gameType) {
                    // 机台类别
                    $form->cascaderSingle('cate_id', admin_trans('machine.fields.cate_id'))
                        ->style(['width' => '100%'])
                        ->options(getCateListOptions($form->isEdit() ? $form->driver()->get() : []))
                        ->required();

                    // 图片
                    $form->image('picture_url', admin_trans('machine.fields.picture_url'))
                        ->ext('jpg,png,jpeg')
                        ->fileSize('5m')
                        ->help(admin_trans('machine.help.picture_url_size'));
                })->span(12);

                $form->column(function (Form $form) use ($gameType) {
                    // 标签
                    $form->select('label_id', admin_trans('machine.fields.label_id'))
                        ->options($this->getMachineLabelOptions($gameType))
                        ->required();

                    // 机台编号
                    $form->text('code', admin_trans('machine.fields.code'))
                        ->maxlength(10)
                        ->required();
                })->span(12);
            });

            // 连接信息
            $form->row(function (Form $form) {
                $form->text('domain', admin_trans('machine.fields.domain'))
                    ->maxlength(255)
                    ->required()
                    ->span(11);

                $form->text('port', admin_trans('machine.fields.port'))
                    ->rule([
                        'regex:/^([1-9]|[1-9][0-9]{1,3}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/' => admin_trans('validator.machine_port'),
                    ])
                    ->maxlength(6)
                    ->required()
                    ->span(11)
                    ->style(['margin-left' => '10px']);
            });

            // 斯洛机台的开分卡配置
            if ($gameType == GameType::TYPE_SLOT) {
                $form->row(function (Form $form) {
                    $form->text('auto_card_domain', admin_trans('machine.fields.auto_card_domain'))
                        ->maxlength(255)
                        ->span(11);

                    $form->text('auto_card_port', admin_trans('machine.fields.auto_card_port'))
                        ->rule([
                            'regex:/^([1-9]|[1-9][0-9]{1,3}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/' => admin_trans('validator.machine_port'),
                        ])
                        ->maxlength(6)
                        ->span(11)
                        ->style(['margin-left' => '10px']);
                });
            }

            // 配置信息
            $form->row(function (Form $form) {
                $form->text('control_open_point', admin_trans('machine.fields.control_open_point'))
                    ->rule([
                        'integer' => admin_trans('validator.integer'),
                        'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                        'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                    ])
                    ->required()
                    ->span(11);

                $form->text('sort', admin_trans('machine_category.fields.sort'))
                    ->rule([
                        'integer' => admin_trans('validator.integer'),
                        'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                        'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    ])
                    ->default($this->model::where('machine_source', Machine::MACHINE_SOURCE_OFFLINE)->max('sort') + 1)
                    ->span(11)
                    ->style(['margin-left' => '10px']);
            });

            $form->row(function (Form $form) {
                $form->number('odds_x', admin_trans('machine.fields.odds_x'))
                    ->max(100000)
                    ->min(0.01)
                    ->precision(2)
                    ->required()
                    ->span(11)
                    ->style(['width' => '100%']);

                $form->number('odds_y', admin_trans('machine.fields.odds_y'))
                    ->max(100000)
                    ->min(0.01)
                    ->precision(2)
                    ->required()
                    ->span(11)
                    ->style(['margin-left' => '10px', 'width' => '100%']);
            });

            $form->row(function (Form $form) {
                $form->text('min_point', admin_trans('machine.fields.min_point'))
                    ->rule([
                        'integer' => admin_trans('validator.integer'),
                        'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                        'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                    ])
                    ->required()
                    ->help(admin_trans('machine.help.min_point'))
                    ->span(11);

                $form->text('max_point', admin_trans('machine.fields.max_point'))
                    ->rule([
                        'integer' => admin_trans('validator.integer'),
                        'max:100000' => admin_trans('validator.max', null, ['{max}' => 100000]),
                        'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                    ])
                    ->required()
                    ->help(admin_trans('machine.help.max_point'))
                    ->span(11)
                    ->style(['margin-left' => '10px']);
            });

            // 关联信息
            $form->row(function (Form $form) {
                // 攻略
                $form->selectTable('strategy_id', admin_trans('machine.fields.strategy_id'))
                    ->grid([MachineStrategyController::class, 'selectList'])
                    ->display(function ($ids, $data) {
                        if ($ids) {
                            $strategy = MachineStrategy::find($ids[0]);
                            return $strategy ? Html::div()->content(admin_trans('machine.select') . $strategy->name) : [];
                        } else if ($data['strategy_id']) {
                            $strategy = MachineStrategy::find($data['strategy_id']);
                            return $strategy ? Html::div()->content(admin_trans('machine.select') . $strategy->name) : [];
                        }
                        return [];
                    })
                    ->required()
                    ->span(11);

                // 厂商
                $options = MachineProducer::where('status', 1)
                    ->select(['id', 'name'])
                    ->pluck('name', 'id')
                    ->all();

                $form->select('producer_id', admin_trans('machine.fields.producer_id'))
                    ->options($options)
                    ->required()
                    ->span(11);
            });

            // 钢珠机台特有字段
            if ($gameType == GameType::TYPE_STEEL_BALL) {
                $form->text('correct_rate', admin_trans('machine.fields.correct_rate'))
                    ->maxlength(50);
            }

            // 其他配置
            $form->switch('is_special', admin_trans('machine.fields.is_special'))
                ->default(false);

            $form->select('control_type', admin_trans('machine.fields.control_type'))
                ->required()
                ->options([
                    Machine::CONTROL_TYPE_MEI => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_MEI),
                    Machine::CONTROL_TYPE_SONG => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_SONG),
                ])
                ->default(Machine::CONTROL_TYPE_MEI);

            $form->textarea('remark', admin_trans('machine.fields.remark'))
                ->maxlength(250)
                ->rows(3);

            // 保存前处理
            $form->saving(function (Form $form) {
                // 确保是线下机台
                $form->machine_source = Machine::MACHINE_SOURCE_OFFLINE;
                $form->is_live = 0;
            });

            $form->saved(function (Form $form, $result) {
                if ($result && $form->model()->id) {
                    /** @var Machine $machine */
                    $machine = Machine::find($form->model()->id);

                    if ($machine) {
                        // 线下机台不允许配置直播流，删除可能存在的媒体配置
                        MachineMedia::where('machine_id', $machine->id)->delete();

                        // 更新缓存
                        $cacheKey = sprintf('machine:domain:%s:port:%s:type:%s',
                            $machine->domain, $machine->port, $machine->type
                        );
                        Cache::set($cacheKey, $machine, 3600);
                    }
                }

                return message_success(admin_trans('common.save_success'));
            });

            $form->actions()->hideResetButton();
            $form->layout('vertical');
            $form->labelWidth('150px');
        })->style(['margin-top' => '-30px']);
    }

    /**
     * 获取机台标签选项（按游戏类型过滤）
     */
    private function getMachineLabelOptions(int $gameType): array
    {
        // 直接使用全局 helper 函数，不按游戏类型过滤
        // 因为 MachineLabel 本身已经通过 cate_id 关联到具体分类
        // 在创建表单时通过 cascaderSingle 选择分类会自动过滤对应的标签
        return getMachineLabelOptions();
    }

    /**
     * 指令测试页面
     * @auth true
     */
    public function commandTest($machine_id, $game_type, $control_type)
    {
        // ✅ 参数从 modal() 的第二个参数传递过来，作为方法参数接收（不是 request()->get()）
        $machine = Machine::find($machine_id);
        if (!$machine) {
            Log::error('commandTest 机台不存在', ['machine_id' => $machine_id]);
            return message_error('机台不存在（ID: ' . $machine_id . '）');
        }

        // 根据控制类型和游戏类型定义可用指令
        $commandList = [];

        // 小淞工控
        if ($control_type === Machine::CONTROL_TYPE_SONG) {
            if ($game_type == GameType::TYPE_SLOT) {
                // 小淞Slot（收账小卡协议）
                $commandList = [
                    '查询指令' => [
                        ['name' => '查询账目', 'cmd' => 'eac4', 'desc' => '查询开分码表+洗分码表+开分卡分数+机台分数', 'danger' => false],
                        ['name' => '查询总玩总赢', 'cmd' => 'ead8', 'desc' => '查询总押分和总得分', 'danger' => false],
                        ['name' => '查询机台情况', 'cmd' => 'ead4', 'desc' => '查询开分状态+洗分状态+转数', 'danger' => false],
                    ],
                    '登入指令' => [
                        ['name' => '登入', 'cmd' => 'eac3', 'desc' => '玩家登入机台', 'danger' => false],
                        ['name' => '查询登入状态', 'cmd' => 'eac5', 'desc' => '检查是否已登入', 'danger' => false],
                    ],
                    '管理指令' => [
                        ['name' => '清除账目', 'cmd' => 'eade', 'desc' => '清除开洗分账+回补数', 'danger' => true],
                        ['name' => '归0机板', 'cmd' => 'a37005e0f8ce', 'desc' => '⚠️ 重置机板（清空所有数据）', 'danger' => true],
                    ],
                ];
            } else {
                // 小淞钢珠（85x协议）
                $commandList = [
                    '查询指令' => [
                        ['name' => '查询机台状态', 'cmd' => 'b5', 'desc' => '查询机台当前状态和数据', 'danger' => false],
                        ['name' => '查询分数', 'cmd' => 'b7', 'desc' => '查询机台分数信息', 'danger' => false],
                    ],
                    '管理指令' => [
                        ['name' => '归0机板', 'cmd' => 'b5归0', 'desc' => '⚠️ 重置机板数据', 'danger' => true],
                    ],
                ];
            }
        }
        // 双美工控
        else if ($control_type === Machine::CONTROL_TYPE_MEI) {
            if ($game_type == GameType::TYPE_SLOT) {
                // 双美Slot
                $commandList = [
                    '查询指令' => [
                        ['name' => '读取押分', 'cmd' => 'afcbc7', 'desc' => '查询当前押分金额', 'danger' => false],
                        ['name' => '读取得分', 'cmd' => 'afcbc9', 'desc' => '查询当前得分金额', 'danger' => false],
                        ['name' => '机台自检', 'cmd' => 'afcc00', 'desc' => '查询机台自检状态', 'danger' => false],
                    ],
                    '控制指令' => [
                        ['name' => '机台开始', 'cmd' => 'afcc01', 'desc' => '启动机台游戏', 'danger' => false],
                        ['name' => '机台停止', 'cmd' => 'afcc02', 'desc' => '停止机台游戏', 'danger' => false],
                        ['name' => '压分关闭', 'cmd' => 'afcc0a', 'desc' => '关闭压分功能', 'danger' => false],
                        ['name' => '压分开启', 'cmd' => 'afcc0b', 'desc' => '开启压分功能', 'danger' => false],
                    ],
                    '管理指令' => [
                        ['name' => '洗分清零', 'cmd' => 'afcc', 'desc' => '⚠️ 洗分并清零机台', 'danger' => true],
                        ['name' => '强制清零', 'cmd' => 'afcc10', 'desc' => '⚠️ 强制清零所有数据', 'danger' => true],
                    ],
                ];
            } else {
                // 双美钢珠
                $commandList = [
                    '查询指令' => [
                        ['name' => '查询状态', 'cmd' => 'query_status', 'desc' => '查询机台当前状态', 'danger' => false],
                    ],
                    '控制指令' => [
                        ['name' => '启动', 'cmd' => 'start', 'desc' => '启动机台', 'danger' => false],
                        ['name' => '停止', 'cmd' => 'stop', 'desc' => '停止机台', 'danger' => false],
                    ],
                    '管理指令' => [
                        ['name' => '清零', 'cmd' => 'reset', 'desc' => '⚠️ 重置机台', 'danger' => true],
                    ],
                ];
            }
        }

        return admin_view(plugin()->webman->getPath() . '/views/command_test.vue')->attrs([
            'machine_id' => $machine->id,
            'machine_code' => $machine->code,
            'machine_name' => $machine->machineLabel->name ?? '',
            'control_type' => $machine->control_type,
            'control_type_name' => admin_trans('machine.control_type.' . $machine->control_type),
            'game_type' => $machine->type,
            'game_type_name' => $machine->type == GameType::TYPE_SLOT ? 'Slot' : '钢珠',
            'command_list' => $commandList,
        ]);
    }

    /**
     * 发送指令（供指令测试页面调用）
     * @auth true
     */
    public function sendCommand(): array
    {
        try {
            $machineId = request()->post('machine_id');
            $cmd = request()->post('cmd');
            $cmdName = request()->post('cmd_name');

            // ✅ 调试日志：记录原始请求数据
            \support\Log::debug('sendCommand 接收到的参数', [
                'raw_post' => request()->post(),
                'raw_body' => request()->rawBody(),
                'machine_id' => $machineId,
                'machine_id_type' => gettype($machineId),
                'cmd' => $cmd,
            ]);

            if (!$machineId || !$cmd) {
                \support\Log::error('sendCommand 缺少必要参数', [
                    'machine_id' => $machineId,
                    'cmd' => $cmd,
                ]);
                return ['code' => 0, 'msg' => '缺少必要参数', 'data' => []];
            }

            // 尝试查找机台（添加详细日志）
            $machine = Machine::find($machineId);
            if (!$machine) {
                \support\Log::error('sendCommand 机台不存在', [
                    'machine_id' => $machineId,
                    'machine_id_type' => gettype($machineId),
                    'all_machines_count' => Machine::count(),
                ]);
                return ['code' => 0, 'msg' => '机台不存在（ID: ' . $machineId . '）', 'data' => []];
            }

            // 记录日志
            \support\Log::info('线下机台指令测试', [
                'admin_id' => Admin::id(),
                'machine_id' => $machineId,
                'machine_code' => $machine->code,
                'cmd' => $cmd,
                'cmd_name' => $cmdName,
            ]);

            // 调用gk_work API发送指令
            $result = MachineApiService::executeAction(
                $machineId,
                'send_raw_cmd',
                [
                    'cmd' => $cmd,
                    'data' => 0,
                    'is_system' => 0,
                ],
                Admin::id()
            );

            return ['code' => 1, 'msg' => '指令发送成功', 'data' => $result];

        } catch (\Exception $e) {
            \support\Log::error('线下机台指令测试失败', [
                'admin_id' => Admin::id(),
                'machine_id' => request()->post('machine_id'),
                'cmd' => request()->post('cmd'),
                'error' => $e->getMessage(),
            ]);

            return ['code' => 0, 'msg' => '指令发送失败: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * ✅ 新增：解锁机台
     * @group admin
     * @auth true
     */
    public function unlockMachine(): array
    {
        try {
            // ✅ 权限验证
            $permissions = Admin::permission();
            $requiredPermission = 'ex-admin/addons-webman-controller-AdminOfflineMachineController/unlockMachine';
            if (!in_array($requiredPermission, $permissions)) {
                \support\Log::warning('解锁机台权限不足', [
                    'admin_id' => Admin::id(),
                    'machine_id' => request()->post('machine_id'),
                ]);
                return message_error('权限不足，无法执行解锁操作');
            }

            $machineId = request()->post('machine_id');

            if (!$machineId) {
                return message_error('缺少机台ID');
            }

            // 调用gk_work API执行解锁操作
            $result = MachineApiService::executeAction(
                $machineId,
                'unlock',
                [],
                Admin::id()
            );

            // 更新本地数据库
            $machine = Machine::find($machineId);
            if ($machine) {
                $machine->has_lock = 0;
                $machine->save();
            }

            // 记录操作日志
            \support\Log::info('解锁机台成功', [
                'admin_id' => Admin::id(),
                'admin_username' => Admin::user()->username ?? '',
                'machine_id' => $machineId,
                'machine_code' => $machine->code ?? '',
            ]);

            return message_success('机台解锁成功', $result);

        } catch (\Exception $e) {
            \support\Log::error('解锁机台失败', [
                'admin_id' => Admin::id(),
                'machine_id' => request()->post('machine_id'),
                'error' => $e->getMessage()
            ]);
            return message_error('解锁失败: ' . $e->getMessage());
        }
    }

    /**
     * ✅ 新增：归0机板
     * @group admin
     * @auth true
     */
    public function resetMachine(): array
    {
        try {
            // ✅ 权限验证
            $permissions = Admin::permission();
            $requiredPermission = 'ex-admin/addons-webman-controller-AdminOfflineMachineController/resetMachine';
            if (!in_array($requiredPermission, $permissions)) {
                \support\Log::warning('归0机台权限不足', [
                    'admin_id' => Admin::id(),
                    'machine_id' => request()->post('machine_id'),
                ]);
                return message_error('权限不足，无法执行归0操作');
            }

            $machineId = request()->post('machine_id');

            if (!$machineId) {
                return message_error('缺少机台ID');
            }

            // 验证是否为小淞线下版
            $machine = Machine::find($machineId);
            if (!$machine) {
                return message_error('机台不存在');
            }

            if ($machine->control_type !== Machine::CONTROL_TYPE_SONG ||
                $machine->machine_source !== Machine::MACHINE_SOURCE_OFFLINE) {
                return message_error('归0操作仅支持小淞线下版机台');
            }

            // 调用gk_work API执行归0操作
            $result = MachineApiService::executeAction(
                $machineId,
                'reset',
                [],
                Admin::id()
            );

            // 归0操作会自动解锁，更新本地数据库
            $machine->has_lock = 0;
            $machine->save();

            // 记录操作日志
            \support\Log::info('归0机台成功', [
                'admin_id' => Admin::id(),
                'admin_username' => Admin::user()->username ?? '',
                'machine_id' => $machineId,
                'machine_code' => $machine->code,
                'control_type' => $machine->control_type,
                'machine_source' => $machine->machine_source,
            ]);

            return message_success('归0成功，机台已解锁', $result);

        } catch (\Exception $e) {
            \support\Log::error('归0机台失败', [
                'admin_id' => Admin::id(),
                'machine_id' => request()->post('machine_id'),
                'error' => $e->getMessage()
            ]);
            return message_error('归0失败: ' . $e->getMessage());
        }
    }
}
