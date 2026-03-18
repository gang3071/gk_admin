<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\MachineStrategy;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tag\Tag;

/**
 * 机台攻略
 */
class MachineStrategyController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_strategy_model');
    }

    /**
     * 机台攻略
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->orderBy('status', 'desc')->orderBy('sort')->orderBy('id', 'desc');
            $grid->title(admin_trans('machine_strategy.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_strategy.fields.id'))->align('center');
            $grid->column('name', admin_trans('machine_strategy.fields.name'))->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->sortable()->display(function ($val, MachineStrategy $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->type))
                ]);
            })->align('center');
            $grid->column('picture_url', admin_trans('machine_strategy.fields.thumbnail'))->display(function ($val, MachineStrategy $data) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['objectFit' => 'cover'])
                    ->src($data->thumbnail);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->sortInput('sort', admin_trans('machine_strategy.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_strategy.fields.status'))->switch()->align('center');
            $grid->column('created_at', admin_trans('machine_strategy.fields.created_at'))->sortable()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
        });
    }

    /**
     * 机台攻略
     * @auth true
     */
    public function selectList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->orderBy('status', 'desc')->orderBy('sort', 'desc');
            $grid->title(admin_trans('machine_strategy.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideDelete();
            $grid->hideAdd();
            $grid->hideDeleteSelection();
            $grid->column('id', admin_trans('machine_strategy.fields.id'))->align('center');
            $grid->column('name', admin_trans('machine_strategy.fields.name'))->align('center');
            $grid->column('type', admin_trans('machine_category.fields.type'))->sortable()->display(function ($val, MachineStrategy $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->type))
                ]);
            })->align('center');
            $grid->column('picture_url', admin_trans('machine_strategy.fields.thumbnail'))->display(function ($val, MachineStrategy $data) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['objectFit' => 'cover'])
                    ->src($data->thumbnail);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->sortInput('sort', admin_trans('machine_strategy.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_strategy.fields.status'))->switch()->align('center');
            $grid->column('created_at', admin_trans('machine_strategy.fields.created_at'))->sortable()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            })->align('center');
        });
    }

    /**
     * 机台攻略
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->title(admin_trans('machine_strategy.title'));
            $form->text('name', admin_trans('machine_strategy.fields.name'))->required();
            $form->text('thumbnail', admin_trans('machine_strategy.fields.thumbnail'))->required();
            $form->radio('type', admin_trans('game_type.fields.type'))
                ->button()
                ->options([
                    GameType::TYPE_SLOT => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                    GameType::TYPE_STEEL_BALL => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                    GameType::TYPE_FISH => admin_trans('game_type.game_type.' . GameType::TYPE_FISH),
                ])->required();
            $form->hasMany('content', '', function (Form $form) {
                $form->row(function (Form $form) {
                    $form->text('content', admin_trans('machine_strategy.fields.content'))->required()->span(24);
                })->class(['activity-phase-has-many']);
            })->sortField('sort')->drag()->defaultRow(1);
            $form->layout('vertical');
            $form->saving(function (Form $form) {

                $form->input('content', json_encode($form->input('content')));
            });
        });
    }
}
