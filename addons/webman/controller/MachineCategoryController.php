<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineCategoryExtend;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\form\Watch;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tabs\Tabs;

/**
 * 机台类型
 */
class MachineCategoryController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_category_model');
    }

    /**
     * 机台
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->slotCateList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->jackPotCateList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_FISH), $this->fishCateList())
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * 机台类别列表
     * @return Grid
     */
    public function fishCateList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with(['gameType'])
                ->whereHas('gameType', function ($query) {
                    $query->where('type', GameType::TYPE_FISH);
                })
                ->orderBy('sort', 'desc')
                ->orderBy('status', 'desc');
            $grid->title(admin_trans('machine_category.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_category.fields.id'))->align('center');
            $grid->column('game_id', admin_trans('machine_category.fields.game_id'))->display(function ($val, $data) {
                return Html::create()->content([
                    $data->gameType->name ?? '',
                ]);
            })->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function ($val) {
                return Html::create()->content([
                    admin_trans('game_type.game_type.' . $val)
                ]);
            })->align('center');
            $grid->column('name', admin_trans('machine_category.fields.name'))->align('center');
            $grid->column('picture_url', admin_trans('machine_category.fields.picture_url'))->display(function (
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
            $grid->column('keep_minutes', admin_trans('machine_category.fields.keep_minutes'))
                ->display(function ($val) {
                    if (!empty($val)) {
                        return $val . admin_trans('machine_category.fields.second');
                    }
                    return '';
                })
                ->editable(
                    (new Editable)->text('keep_minutes')
                        ->style(['width' => '100%'])
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:600' => admin_trans('validator.max', null, ['{max}' => 600]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])
                        ->addonAfter(admin_trans('machine_category.fields.second'))
                        ->required()
                )->width(150)->align('center');
            $grid->column('lottery_point', admin_trans('machine_category.fields.lottery_point'))->display(function ($val
            ) {
                return Html::create()->content([
                    $val > 0 ? $val : '',
                ]);
            })->align('center');
            $grid->column('lottery_add_status',
                admin_trans('machine_category.fields.lottery_add_status'))->switch()->align('center');
            $grid->column('lottery_rate', admin_trans('machine_category.fields.lottery_rate'))->display(function ($val
            ) {
                return Html::create()->content([
                    $val > 0 ? $val : '',
                ]);
            })->align('center');
            $grid->column('lottery_assign_status',
                admin_trans('machine_category.fields.lottery_assign_status'))->switch()->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function (
                $val,
                MachineCategory $data
            ) {
                $typeName = getGameTypeName($data->gameType->type);
                return Html::create()->content([
                    $typeName,
                ]);
            })->align('center');
            $grid->sortInput('sort', admin_trans('machine_category.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_category.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->deling(function ($ids) {
                if (Machine::query()->where('cate_id', $ids)->first()) {
                    return message_error(admin_trans('machine_category.delete_has_machine_error'));
                }
            });
        });
    }

    /**
     * 机台类别列表
     * @return Grid
     */
    public function slotCateList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with(['gameType'])
                ->whereHas('gameType', function ($query) {
                    $query->where('type', GameType::TYPE_SLOT);
                })
                ->orderBy('sort', 'desc')
                ->orderBy('status', 'desc');
            $grid->title(admin_trans('machine_category.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_category.fields.id'))->align('center');
            $grid->column('game_id', admin_trans('machine_category.fields.game_id'))->display(function ($val, $data) {
                return Html::create()->content([
                    $data->gameType->name ?? '',
                ]);
            })->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function ($val) {
                return Html::create()->content([
                    admin_trans('game_type.game_type.' . $val)
                ]);
            })->align('center');
            $grid->column('name', admin_trans('machine_category.fields.name'))->align('center');
            $grid->column('picture_url', admin_trans('machine_category.fields.picture_url'))->display(function ($val, $data) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data['picture_url']);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('keep_minutes', admin_trans('machine_category.fields.keep_minutes'))
                ->display(function ($val) {
                    if (!empty($val)) {
                        return $val . admin_trans('machine_category.fields.second');
                    }
                    return '';
                })
                ->editable(
                    (new Editable)->text('keep_minutes')
                        ->style(['width' => '100%'])
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:600' => admin_trans('validator.max', null, ['{max}' => 600]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])
                        ->addonAfter(admin_trans('machine_category.fields.second'))
                        ->required()
                )->width(150)->align('center');
            $grid->column('lottery_point', admin_trans('machine_category.fields.lottery_point'))->display(function ($val) {
                return Html::create()->content([
                    $val > 0 ? $val : '',
                ]);
            })->align('center');
            $grid->column('lottery_add_status', admin_trans('machine_category.fields.lottery_add_status'))->switch()->align('center');
            $grid->column('lottery_rate', admin_trans('machine_category.fields.lottery_rate'))->display(function ($val) {
                return Html::create()->content([
                    $val > 0 ? $val : '',
                ]);
            })->align('center');
            $grid->column('lottery_assign_status', admin_trans('machine_category.fields.lottery_assign_status'))->switch()->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function ($val, MachineCategory $data) {
                $typeName = getGameTypeName($data->gameType->type);
                return Html::create()->content([
                    $typeName,
                ]);
            })->align('center');
            $grid->sortInput('sort', admin_trans('machine_category.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_category.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->deling(function ($ids) {
                if (Machine::query()->where('cate_id', $ids)->first()) {
                    return message_error(admin_trans('machine_category.delete_has_machine_error'));
                }
            });
        });
    }

    /**
     * 机台类别列表
     * @return Grid
     */
    public function jackPotCateList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with(['gameType'])
                ->whereHas('gameType', function ($query) {
                    $query->where('type', GameType::TYPE_STEEL_BALL);
                })
                ->orderBy('sort', 'desc')
                ->orderBy('status', 'desc');
            $grid->title(admin_trans('machine_category.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_category.fields.id'))->align('center');
            $grid->column('game_id', admin_trans('machine_category.fields.game_id'))->display(function ($val, $data) {
                return Html::create()->content([
                    $data->gameType->name ?? '',
                ]);
            })->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function ($val) {
                return Html::create()->content([
                    admin_trans('game_type.game_type.' . $val)
                ]);
            })->align('center');
            $grid->column('name', admin_trans('machine_category.fields.name'))->align('center');
            $grid->column('turn_used_point', admin_trans('machine_category.fields.turn_used_point'))->align('center');
            $grid->column('picture_url', admin_trans('machine_category.fields.picture_url'))->display(function (
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
            $grid->column('keep_minutes', admin_trans('machine_category.fields.keep_minutes'))
                ->display(function ($val) {
                    if (!empty($val)) {
                        return $val . admin_trans('machine_category.fields.second');
                    }
                    return '';
                })
                ->editable(
                    (new Editable)->text('keep_minutes')
                        ->style(['width' => '100%'])
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:600' => admin_trans('validator.max', null, ['{max}' => 600]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])
                        ->addonAfter(admin_trans('machine_category.fields.second'))
                        ->required()
                )->width(150)->align('center');
            $grid->column('lottery_point', admin_trans('machine_category.fields.lottery_point'))->display(function ($val
            ) {
                return Html::create()->content([
                    $val > 0 ? $val : '',
                ]);
            })->align('center');
            $grid->column('lottery_add_status',
                admin_trans('machine_category.fields.lottery_add_status'))->switch()->align('center');
            $grid->column('lottery_rate', admin_trans('machine_category.fields.lottery_rate'))->display(function ($val
            ) {
                return Html::create()->content([
                    $val > 0 ? $val : '',
                ]);
            })->align('center');
            $grid->column('lottery_assign_status',
                admin_trans('machine_category.fields.lottery_assign_status'))->switch()->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function (
                $val,
                MachineCategory $data
            ) {
                $typeName = getGameTypeName($data->gameType->type);
                return Html::create()->content([
                    $typeName,
                ]);
            })->align('center');
            $grid->sortInput('sort', admin_trans('machine_category.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_category.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->deling(function ($ids) {
                if (Machine::query()->where('cate_id', $ids)->first()) {
                    return message_error(admin_trans('machine_category.delete_has_machine_error'));
                }
            });
        });
    }

    /**
     * 机台类别
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $optionList = [];
            $gameList = GameType::query()->where('cate', GameType::CATE_PHYSICAL_MACHINE)->get();
            /** @var GameType $item */
            foreach ($gameList as $item) {
                $optionList[$item->id] = $item->name;
            }
            $form->title(admin_trans('machine_category.title'));
            $form->labelWidth(160);
            $gameType = $form->getBindField('game_type');
            $form->select('game_id', admin_trans('machine_category.fields.game_id'))
                ->style(['width' => '100%'])
                ->bindAttr($form->isEdit() ? 'disabled' : '', $form->getModel() . '.game_id')
                ->options($optionList)
                ->required();
            $form->number('turn_used_point',
                admin_trans('machine_category.fields.turn_used_point'))->style(['width' => '100%']);
            $form->text('keep_minutes', admin_trans('machine_category.fields.keep_minutes'))
                ->rule([
                    'integer' => admin_trans('validator.integer'),
                    'max:600' => admin_trans('validator.max', null, ['{max}' => 600]),
                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                ])
                ->addonAfter(admin_trans('machine_category.fields.second'));
            $form->row(function (Form $form) {
                $langList = plugin()->webman->config('ui.lang.list');
                $tabs = $form->tabs()->destroyInactiveTabPane();
                $contents = [];
                if ($form->isEdit()) {
                    $contents = $form->driver()->get('machineCategoryExtend')->mapWithKeys(function (
                        MachineCategoryExtend $content
                    ) {
                        return [
                            $content->lang => [
                                'name' => $content->name,
                                'id' => $content->id,
                            ]
                        ];
                    });
                }
                $key = 0;
                foreach ($langList as $k => $v) {
                    $tabs->pane($v, function (Form $form) use ($k, $contents, $key) {
                        $form->text("machineCategoryExtend." . $key . ".name", admin_trans('machine_category.form.category_name'))
                            ->value($contents[$k]['name'] ?? '')
                            ->required()
                            ->maxlength(50)
                            ->help(admin_trans('machine_category.validation.please_fill_category_name'));
                        if (!empty($contents[$k]['id'])) {
                            $form->hidden('machineCategoryExtend.' . $key . '.id')->default($contents[$k]['id']);
                        }
                        $form->hidden('machineCategoryExtend.' . $key . '.lang')->default($k);
                    });
                    $key++;
                }
            }, admin_trans('machine_category.form.multilingual_config'));
            $form->layout('vertical');
            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->number('lottery_point', admin_trans('machine_category.fields.lottery_point'))
                        ->max(1000)
                        ->min(0.0001)
                        ->precision(4)
                        ->span(6)
                        ->rule([
                            'max:1000' => admin_trans('validator.max', null, ['{max}' => 1000]),
                            'min:0.0001)' => admin_trans('validator.min', null, ['{min}' => 0.0001]),
                        ])
                        ->help(admin_trans('machine_category.lottery_point_help'))
                        ->style(['width' => '100%']);
                    $form->number('lottery_rate', admin_trans('machine_category.fields.lottery_rate'))
                        ->max(1000)
                        ->min(0.01)
                        ->precision(2)
                        ->span(6)
                        ->help(admin_trans('machine_category.lottery_rate_help'))
                        ->rule([
                            'max:1000' => admin_trans('validator.max', null, ['{max}' => 1000]),
                            'min:0.01' => admin_trans('validator.min', null, ['{min}' => 0.01]),
                        ])
                        ->style(['width' => '100%']);
                })->span(15);
                $form->column(function (Form $form) {
                    $form->switch('lottery_add_status', admin_trans('machine_category.fields.lottery_add_status'))->span(6)->help(admin_trans('machine_category.lottery_add_status_help'));
                    $form->switch('lottery_assign_status', admin_trans('machine_category.fields.lottery_assign_status'))->span(6)->help(admin_trans('machine_category.lottery_assign_status_help'));
                })->span(6)->style(['margin-left' => '59px']);
            }, admin_trans('machine_category.lottery_setting'));
            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->number('sort', admin_trans('machine_category.fields.sort'))->default($this->model::max('sort') + 1)->style(['width' => '100%']);
                })->span(15);
                $form->column(function (Form $form) {
                    $form->image('picture_url', admin_trans('machine_category.fields.picture_url'))
                        ->ext('jpg,png,jpeg')
                        ->fileSize('5m')
                        ->required();
                })->span(6)->style(['margin-left' => '59px']);
            }, null);
            $form->hidden('game_type')->bindAttr('value', $gameType)
                ->when(GameType::TYPE_STEEL_BALL, function (Form $form) use ($gameType) {
                    $form->hasMany('machineCategoryGiveRule', admin_trans('machine_category.opening_gift.opening_gift'), function (Form $form) {
                        $form->text('open_num', admin_trans('machine_category.opening_gift.open_num'))->style(['width' => '100px']);
                        $form->text('give_num', admin_trans('machine_category.opening_gift.give_num'))->style(['width' => '100px']);
                        $form->text('give_rule_num', admin_trans('machine_category.opening_gift.give_rule_num'))->style(['width' => '100px'])
                            ->default(1);
                    })->sortField('sort')->table()->defaultRow(0);
                })->when(GameType::TYPE_SLOT, function (Form $form) use ($gameType) {
                    $form->hasMany('machineCategoryGiveRule', admin_trans('machine_category.opening_gift.opening_gift'), function (Form $form) {
                        $form->text('open_num', admin_trans('machine_category.opening_gift.open_num'))->style(['width' => '80px']);
                        $form->text('give_num', admin_trans('machine_category.opening_gift.give_num'))->style(['width' => '80px']);
                        $form->text('condition', admin_trans('machine_category.opening_gift.condition'))->default('0.00')->style(['width' => '80px']);
                        $form->text('give_rule_num', admin_trans('machine_category.opening_gift.give_rule_num'))->style(['width' => '80px'])
                            ->default(1);
                    })->sortField('sort')->table()->defaultRow(0);
                });
            $form->watch([
                'game_id' => function ($value, Watch $watch) {
                    /** @var GameType $gameType */
                    $gameType = GameType::find($value);
                    $watch->set('game_type', $gameType->type ?? 0);
                }
            ]);
            // 提交前，开分赠点参数验证
            $form->saving(function (Form $form) {
                $input = $form->input();
                $machineCategoryGiveRule = $input['machineCategoryGiveRule'];
                $machineCategoryExtend = $input['machineCategoryExtend'];
                $type = $input['game_type'];
                if (!empty($machineCategoryGiveRule)) {
                    $slotKeys = ['open_num', 'give_num'];
                    $steelBallKeys = $slotKeys;
                    array_unshift($slotKeys, 'condition');
                    foreach ($machineCategoryGiveRule as $key => $val) {
                        $machineCategoryGiveRule[$key]['sort'] = $key;
                        switch ($type) {
                            case GameType::TYPE_SLOT:
                                foreach ($slotKeys as $slotVal) {
                                    if (empty($val[$slotVal])) {
                                        return message_error('Validation fail:' . admin_trans('machine_category.opening_gift.' . $slotVal));
                                    }
                                }
                                break;
                            case GameType::TYPE_STEEL_BALL:
                                foreach ($steelBallKeys as $sVal) {
                                    if (empty($val[$sVal])) {
                                        return message_error('Validation fail:' . admin_trans('machine_category.opening_gift.' . $sVal));
                                    }
                                }
                                break;
                            default:
                        }
                    }
                }
                $form->input('machineCategoryGiveRule', $machineCategoryGiveRule);
                $form->input('machineCategoryExtend', $machineCategoryExtend);
                foreach ($machineCategoryExtend as $content) {
                    if ($content['lang'] == 'zh-CN') {
                        if (empty($content['name'])) {
                            return message_error(admin_trans('machine_category.validation.please_fill_simplified_chinese_name'));
                        }
                        $form->input('name', $content['name']);
                    }
                }
            });
        });
    }
}
