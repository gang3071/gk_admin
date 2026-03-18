<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;

/**
 * 游戏类型
 */
class GameTypeController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.game_type_model');
    }

    /**
     * 游戏类型
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('game_type.title'));
            $grid->model()->orderBy('status', 'desc')->orderBy('sort', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->column('id', admin_trans('game_type.fields.id'))->align('center');
            $grid->column('picture_url', admin_trans('game_type.fields.picture_url'))->display(function ($val, $data) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data['picture_url']);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('name', admin_trans('game_type.fields.name'))->align('center');
            $grid->column('cate', admin_trans('game_type.fields.cate'))->display(function ($val) {
                $cateName = $val ? getGameTypeCateName($val) : '';
                return Html::create()->content([
                    $cateName,
                ]);
            })->align('center')->filter(
                FilterColumn::eq()->select('cate', admin_trans('game_type.fields.cate'))
                    ->options(getGameTypeCateOptions())
            );
            $grid->column('type', admin_trans('game_type.fields.type'))->display(function ($val) {
                $typeName = $val ? getGameTypeName($val) : '';
                return Html::create()->content([
                    $typeName,
                ]);
            })->align('center');
            $grid->sortInput('sort', admin_trans('game_type.fields.sort'))->align('center');
            $grid->column('status', admin_trans('game_type.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->modal($this->form());
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
        });
    }

    /**
     * 游戏类型
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('game_type.title'));
            $form->text('name', admin_trans('game_type.fields.name'))->maxlength(50)->required();
            $form->radio('cate', admin_trans('game_type.fields.cate'))
                ->options(getGameTypeCateOptions())
                ->bindAttr($form->isEdit() ? 'disabled' : '', $form->getModel() . '.cate')
                ->when(GameType::CATE_PHYSICAL_MACHINE, function (Form $form) {
                    if ($form->isEdit()) {
                        $form->select('type', admin_trans('game_type.fields.type'))
                            ->style(['width' => '200px'])
                            ->disabledValue($this->getGameList())
                            ->bindAttr('disabled', $form->getModel() . '.type')
                            ->options(getGameTypeOptions())
                            ->required();
                    } else {
                        $form->select('type', admin_trans('game_type.fields.type'))
                            ->style(['width' => '200px'])
                            ->disabledValue($this->getGameList())
                            ->options(getGameTypeOptions())
                            ->required();
                    }
                })
                ->required();
            $form->image('picture_url', admin_trans('game_type.fields.picture_url'))
                ->ext('jpg,png,jpeg')
                ->fileSize('5m')
                ->help(admin_trans('game_type.help.picture_url_size'))
                ->required();
            $form->number('sort', admin_trans('game_type.fields.sort'))->default($this->model::max('sort') + 1);
        });
    }

    /**
     * 获取一设置的游戏类型
     * @return array
     */
    public function getGameList(): array
    {
        return GameType::all()->pluck('type')->toArray();
    }
}
