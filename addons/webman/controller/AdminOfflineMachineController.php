<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineLabel;
use addons\webman\model\MachineMedia;
use addons\webman\model\Channel;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Response;
use support\Db;

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
                ->with(['machineLabel', 'channelMachines.storeAdmin', 'channelMachines.channel'])
                ->orderBy('sort')
                ->orderBy('id', 'desc');

            $this->buildGrid($grid, GameType::TYPE_SLOT);
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
                ->with(['machineLabel', 'channelMachines.storeAdmin', 'channelMachines.channel'])
                ->orderBy('sort')
                ->orderBy('id', 'desc');

            $this->buildGrid($grid, GameType::TYPE_STEEL_BALL);
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
        $grid->column('code', admin_trans('offline_machine.fields.code'))->width(120);

        $grid->column('label_id', admin_trans('offline_machine.fields.name'))
            ->display(function ($val, Machine $data) {
                return $data->machineLabel->name ?? '-';
            })->width(150);

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

        $grid->column('status', admin_trans('offline_machine.fields.status'))
            ->display(function ($val) {
                return $val == 1
                    ? Tag::create(admin_trans('common.status.enable'))->color('green')
                    : Tag::create(admin_trans('common.status.disable'))->color('red');
            })->width(80)->align('center');

        $grid->column('gaming', admin_trans('offline_machine.fields.gaming'))
            ->display(function ($val) {
                return $val == 1
                    ? Tag::create(admin_trans('offline_machine.status.gaming'))->color('processing')
                    : Tag::create(admin_trans('offline_machine.status.idle'))->color('default');
            })->width(100)->align('center');

        $grid->column('ip', admin_trans('offline_machine.fields.ip'))->width(120);
        $grid->column('port', admin_trans('offline_machine.fields.port'))->width(80);

        $grid->column('control_type', admin_trans('offline_machine.fields.control_type'))
            ->display(function ($val) {
                return match($val) {
                    Machine::CONTROL_TYPE_MEI => Tag::create(admin_trans('machine.control_type.mei'))->color('blue'),
                    Machine::CONTROL_TYPE_SONG => Tag::create(admin_trans('machine.control_type.song'))->color('green'),
                    default => '-'
                };
            })->width(100)->align('center');

        $grid->column('created_at', admin_trans('common.created_at'))->width(180)->align('center');

        // 操作列
        $grid->actions(function (Actions $actions) use ($gameType) {
            $actions->edit()->modal([$this, 'save'], ['game_type' => $gameType])->width('70%');
            $actions->delete();
        });

        // 工具栏
        $grid->createButton()->modal([$this, 'save'], ['game_type' => $gameType])->width('70%');

        // 筛选器
        $grid->filter(function (Filter $filter) use ($gameType) {
            $filter->like()->text('code')
                ->placeholder(admin_trans('offline_machine.fields.code'));

            $filter->eq()->select('status')
                ->options([
                    '' => admin_trans('public_msg.all'),
                    1 => admin_trans('common.status.enable'),
                    0 => admin_trans('common.status.disable'),
                ])
                ->placeholder(admin_trans('offline_machine.fields.status'));

            $filter->eq()->select('gaming')
                ->options([
                    '' => admin_trans('public_msg.all'),
                    1 => admin_trans('offline_machine.status.gaming'),
                    0 => admin_trans('offline_machine.status.idle'),
                ])
                ->placeholder(admin_trans('offline_machine.fields.gaming'));
        });
    }

    /**
     * 创建/编辑线下机台
     * @group admin
     * @auth true
     */
    public function save(): Form
    {
        $gameType = request()->input('game_type', GameType::TYPE_SLOT);

        return Form::create(new $this->model(), function (Form $form) use ($gameType) {
            // 编辑模式：验证当前记录必须是线下机台
            if ($form->isEdit()) {
                $currentMachine = Machine::find($form->model()->getKey());
                if (!$currentMachine || $currentMachine->machine_source != Machine::MACHINE_SOURCE_OFFLINE) {
                    abort(403, admin_trans('offline_machine.error.not_offline_machine'));
                }
            }

            $form->hidden('type')->default($gameType);
            $form->hidden('machine_source')->default(Machine::MACHINE_SOURCE_OFFLINE);

            // 基本信息
            $form->text('code', admin_trans('offline_machine.fields.code'))
                ->required()
                ->maxlength(50);

            $form->select('label_id', admin_trans('offline_machine.fields.label'))
                ->options($this->getMachineLabelOptions($gameType))
                ->required();

            // 工控配置
            $form->text('ip', admin_trans('offline_machine.fields.ip'))
                ->required()
                ->maxlength(50);

            $form->text('port', admin_trans('offline_machine.fields.port'))
                ->required()
                ->rule([
                    'regex:/^([1-9]|[1-9][0-9]{1,3}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/' => admin_trans('validator.machine_port'),
                ])
                ->maxlength(6);

            if ($gameType == GameType::TYPE_SLOT) {
                $form->text('domain', admin_trans('offline_machine.fields.domain'))
                    ->maxlength(255);

                $form->text('auto_card_domain', admin_trans('machine.fields.auto_card_domain'))
                    ->maxlength(255);

                $form->text('auto_card_port', admin_trans('machine.fields.auto_card_port'))
                    ->rule([
                        'regex:/^([1-9]|[1-9][0-9]{1,3}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/' => admin_trans('validator.machine_port'),
                    ])
                    ->maxlength(6);
            }

            $form->select('control_type', admin_trans('offline_machine.fields.control_type'))
                ->options([
                    Machine::CONTROL_TYPE_MEI => admin_trans('machine.control_type.mei'),
                    Machine::CONTROL_TYPE_SONG => admin_trans('machine.control_type.song'),
                ])
                ->default(Machine::CONTROL_TYPE_MEI);

            $form->radio('status', admin_trans('offline_machine.fields.status'))
                ->options([
                    1 => admin_trans('common.status.enable'),
                    0 => admin_trans('common.status.disable'),
                ])
                ->default(1);

            $form->number('sort', admin_trans('offline_machine.fields.sort'))->default(0);

            $form->textarea('remark', admin_trans('offline_machine.fields.remark'))->rows(3);

            // 保存前处理
            $form->saving(function (Form $form) {
                // 确保是线下机台
                $form->machine_source = Machine::MACHINE_SOURCE_OFFLINE;

                // 线下机台不需要直播
                $form->is_live = 0;
            });

            $form->saved(function (Form $form, $result) {
                // 线下机台不允许配置直播流，删除可能存在的媒体配置
                if ($result) {
                    MachineMedia::where('machine_id', $form->model()->id)->delete();
                }

                return message_success(admin_trans('common.save_success'));
            });

            $form->isDialog();
            $form->labelWidth('150px');
        });
    }

    /**
     * 获取机台标签选项
     */
    private function getMachineLabelOptions(int $gameType): array
    {
        return MachineLabel::query()
            ->leftJoin('yjb_machine_category', 'yjb_machine_label.cate_id', '=', 'yjb_machine_category.id')
            ->where('yjb_machine_category.type', $gameType)
            ->where('yjb_machine_label.status', 1)
            ->orderBy('yjb_machine_label.id', 'desc')
            ->get(['yjb_machine_label.id', 'yjb_machine_label.name'])
            ->pluck('name', 'id')
            ->toArray();
    }
}
