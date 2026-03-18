<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineLabel;
use addons\webman\model\MachineLabelExtend;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tabs\Tabs;

/**
 * 机台标签
 */
class MachineLabelController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_label_model');
    }

    /**
     * 机台标签
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->slotLabelList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->jackPotLabelList())
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * slot机台标签列表
     * @return Grid
     */
    public function slotLabelList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()
                ->where('type', GameType::TYPE_SLOT)
                ->orderBy('sort', 'desc')
                ->orderBy('status', 'desc');
            $grid->title(admin_trans('machine_label.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_label.fields.id'))->align('center');
            $grid->column('name', admin_trans('machine_label.fields.name'))->align('center');
            $grid->column('picture_url', admin_trans('machine_label.fields.picture_url'))->display(function (
                $val,
                $data
            ) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data['picture_url']);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('score', admin_trans('machine_label.fields.score'))->display(function (
                $val,
                MachineLabel $data
            ) {
                return Html::create()->content([
                    $data->point . admin_trans('machine_label.fields.point'),
                    $data->score . admin_trans('machine_label.fields.score'),
                ]);
            })->align('center');
            $grid->column('courtyard', admin_trans('machine_label.fields.courtyard'))->align('center');
            $grid->sortInput('sort', admin_trans('machine_label.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_label.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('machine_label.fields.name'));
            });
            $grid->deling(function ($ids) {
                if (Machine::query()->where('cate_id', $ids)->first()) {
                    return message_error(admin_trans('machine_label.delete_has_machine_error'));
                }
            });
        });
    }

    /**
     * 钢珠机台标签列表
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('machine_label.title'));
            $form->labelWidth(160);
            $gameType = $form->getBindField('game_type');
            $form->select('type', admin_trans('machine_label.fields.type'))
                ->style(['width' => '100%'])
                ->bindAttr($form->isEdit() ? 'disabled' : '', $form->getModel() . '.type')
                ->options([
                    GameType::TYPE_SLOT => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                    GameType::TYPE_STEEL_BALL => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                    GameType::TYPE_FISH => admin_trans('game_type.game_type.' . GameType::TYPE_FISH),
                ])
                ->required();
            $form->number('sort',
                admin_trans('machine_label.fields.sort'))->default($this->model::max('sort') + 1)->style(['width' => '100%']);
            $form->hidden('type')->bindAttr('value', $gameType)
                ->when(GameType::TYPE_STEEL_BALL, function (Form $form) use ($gameType) {
                    $form->row(function (Form $form) {
                        $form->text('point', admin_trans('machine_label.fields.point'))->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:10000000' => admin_trans('validator.max', null, ['{max}' => 10000000]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])->required()->default(0)->span(11);
                        $form->text('turn', admin_trans('machine_label.fields.turn'))->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:10000000' => admin_trans('validator.max', null, ['{max}' => 10000000]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])->required()->default(0)->span(11)->style(['margin-left' => '10px']);
                    });
                    $form->text('correct_rate', admin_trans('machine_label.fields.correct_rate'))->rule([
                        'integer' => admin_trans('validator.integer'),
                        'max:10000' => admin_trans('validator.max', null, ['{max}' => 10000]),
                        'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    ])->required();
                })->when(GameType::TYPE_SLOT, function (Form $form) use ($gameType) {
                    $form->row(function (Form $form) {
                        $form->text('point', admin_trans('machine_label.fields.point'))->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:10000000' => admin_trans('validator.max', null, ['{max}' => 10000000]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])->required()->default(0)->span(11);
                        $form->text('score', admin_trans('machine_label.fields.score'))->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:10000000' => admin_trans('validator.max', null, ['{max}' => 10000000]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])->required()->default(0)->span(11)->style(['margin-left' => '10px']);
                    });
                    $form->text('courtyard', admin_trans('machine_label.fields.courtyard'))->maxlength(80)->required();
                });
            $form->row(function (Form $form) {
                $langList = plugin()->webman->config('ui.lang.list');
                $tabs = $form->tabs()->destroyInactiveTabPane();
                $contents = [];
                if ($form->isEdit()) {
                    $contents = $form->driver()->get('machineLabelExtend')->mapWithKeys(function (
                        MachineLabelExtend $content
                    ) {
                        return [
                            $content->lang => [
                                'name' => $content->name,
                                'picture_url' => $content->picture_url,
                                'id' => $content->id,
                            ]
                        ];
                    });
                }
                $key = 0;
                foreach ($langList as $k => $v) {
                    $tabs->pane($v, function (Form $form) use ($k, $contents, $key) {
                        $form->text("machineLabelExtend." . $key . ".name", '分类名称')
                            ->value($contents[$k]['name'] ?? '')
                            ->required()
                            ->maxlength(50)
                            ->help('请填写分类名称');
                        $form->image("machineLabelExtend." . $key . ".picture_url",
                            admin_trans('machine_label.fields.picture_url'))
                            ->ext('jpg,png,jpeg')
                            ->fileSize('5m')
                            ->required();
                        if (!empty($contents[$k]['id'])) {
                            $form->hidden('machineLabelExtend.' . $key . '.id')->default($contents[$k]['id']);
                        }
                        $form->hidden('machineLabelExtend.' . $key . '.lang')->default($k);
                    });
                    $key++;
                }
            }, '多语言配置');
            $form->layout('vertical');
            // 提交前，开分赠点参数验证
            $form->saving(function (Form $form) {
                $input = $form->input();
                $machineLabelExtend = $input['machineLabelExtend'];
                $form->input('machineCategoryExtend', $machineLabelExtend);
                foreach ($machineLabelExtend as $content) {
                    if ($content['lang'] == 'zh-CN') {
                        if (empty($content['name'])) {
                            return message_error('请填写中文简体名称');
                        }
                        if (empty($content['picture_url'])) {
                            return message_error('请上传中文简体图');
                        }
                        $form->input('name', $content['name']);
                        $form->input('picture_url', $content['picture_url']);
                    }
                }
            });
        });
    }

    /**
     * 机台类别列表
     * @return Grid
     */
    public function jackPotLabelList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()
                ->where('type', GameType::TYPE_STEEL_BALL)
                ->orderBy('sort', 'desc')
                ->orderBy('status', 'desc');
            $grid->title(admin_trans('machine_label.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_label.fields.id'))->align('center');
            $grid->column('name', admin_trans('machine_label.fields.name'))->align('center');
            $grid->column('picture_url', admin_trans('machine_label.fields.picture_url'))->display(function (
                $val,
                $data
            ) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data['picture_url']);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('score', admin_trans('machine_label.fields.score'))->display(function (
                $val,
                MachineLabel $data
            ) {
                return Html::create()->content([
                    $data->point . admin_trans('machine_label.fields.score'),
                    $data->turn . admin_trans('machine_label.fields.turn'),
                ]);
            })->align('center');
            $grid->column('correct_rate', admin_trans('machine_label.fields.correct_rate'))->align('center');
            $grid->sortInput('sort', admin_trans('machine_label.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_label.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('machine_label.fields.name'));
            });
            $grid->hideDelete();
            $grid->deling(function ($ids) {
                if (Machine::query()->where('label_id', $ids)->first()) {
                    return message_error(admin_trans('machine_label.delete_has_machine_error'));
                }
            });
        });
    }
}
