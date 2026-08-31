<?php

namespace addons\webman\controller;

use addons\webman\form\MyEditor;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;

/**
 * 餐點類別
 */
class DishCategoryController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.dish_category_model');
    }

    /**
     * 列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('dish_category.title'));
            $grid->hideSelection();
            $grid->hideDelete();
            $grid->model()->orderBy('top', 'desc')->orderBy('sort', 'asc');

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('title')->placeholder(admin_trans('dish_category.fields.title'));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish_category.fields.status'))
                    ->options([
                        0 => admin_trans('dish_category.status.0'),
                        1 => admin_trans('dish_category.status.1')
                    ]);
            });

            $grid->column('id', admin_trans('dish_category.fields.id'))->align('center');
            $grid->column('title', admin_trans('dish_category.fields.title'))->align('center');
            $grid->column('picture', admin_trans('dish_category.fields.picture'))
                ->align('center')
                ->display(function ($value) {
                    $image = Image::create()
                        ->width(50)
                        ->height(50)
                        ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                        ->src($value);

                    return Html::create()->content([$image]);
                });
            $grid->sortInput('sort', admin_trans('dish_category.fields.sort'))->align('center');
            $grid->column('top', admin_trans('dish_category.fields.top'))->switch()->align('center');
            $grid->column('status', admin_trans('dish_category.fields.status'))->switch()->align('center');

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');

            $grid->setForm()->modal($this->form());
        });
    }

    /**
     * 新增 / 修改
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        Form::extend('myEditor', MyEditor::class);

        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('dish_category.title'));
            $form->layout('vertical');

            $form->text('title', admin_trans('dish_category.fields.title'))->maxlength(200)->required();
            $form->image('picture', admin_trans('dish_category.fields.picture'));
            $form->myEditor('content', admin_trans('dish_category.fields.content'))->maxlength(200);
            $form->number('sort', admin_trans('dish_category.fields.sort'))->default(0)->required();
            $form->switch('top', admin_trans('dish_category.fields.top'))->required();
            $form->switch('status', admin_trans('dish_category.fields.status'))->required();
            $form->textarea('remark', admin_trans('dish_category.fields.remark'))->maxlength(200);
        });
    }
}
