<?php

namespace addons\webman\controller;

use addons\webman\controller\MachineStrategyController;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineLabel;
use addons\webman\model\MachineMedia;
use addons\webman\model\MachineProducer;
use addons\webman\model\MachineStrategy;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use Illuminate\Support\Str;
use support\Cache;

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

                $avatar = !empty($channelMachine->storeAdmin->avatar)
                    ? Avatar::create()
                        ->src(is_numeric($channelMachine->storeAdmin->avatar) ? config('def_avatar.' . $channelMachine->storeAdmin->avatar) : $channelMachine->storeAdmin->avatar)
                        ->size(30)
                    : Avatar::create()->text(mb_substr($channelMachine->storeAdmin->nickname ?: 'U', 0, 1))->size(30);

                return Html::create()->content([
                    $avatar,
                    Html::div()
                        ->content($channelMachine->storeAdmin->nickname ?: $channelMachine->storeAdmin->username)
                        ->style(['margin-left' => '8px'])
                ]);
            })->width(150);

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
            // 编辑模式：验证当前记录必须是线下机台
            if ($form->isEdit()) {
                $currentMachine = Machine::find($form->model()->getKey());
                if (!$currentMachine || $currentMachine->machine_source != Machine::MACHINE_SOURCE_OFFLINE) {
                    throw new \Exception(admin_trans('offline_machine.error.not_offline_machine'));
                }
            }

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
                    ->span(11);

                // 厂商
                $options = MachineProducer::where('status', 1)
                    ->select(['id', 'name'])
                    ->pluck('name', 'id')
                    ->all();

                $form->select('producer_id', admin_trans('machine.fields.producer_id'))
                    ->options($options)
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
}
