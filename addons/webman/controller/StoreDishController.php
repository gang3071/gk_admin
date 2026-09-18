<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\form\MyEditor;
use addons\webman\model\DishCategory;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;

/**
 * 餐點
 */
class StoreDishController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.dish_model');
    }

    /**
     * 列表
     * @auth true
     */
    public function index(): Grid
    {
        $adminUser = Admin::user();
        $categories = self::getCategories();

        return Grid::create(new $this->model, function (Grid $grid) use ($adminUser, $categories) {
            $grid->title(admin_trans('dish.title'));
            $grid->hideDelete();
            $grid->hideSelection();

            $grid->model()->where('admin_user_id', $adminUser->id);
            $grid->model()->orderBy('category_id', 'asc')->orderBy('top', 'desc')->orderBy('sort', 'desc');

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) use ($categories) {
                $filter->like()->text('title')->placeholder(admin_trans('dish.fields.title'));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish.fields.status'))
                    ->options([
                        0 => admin_trans('dish.status.0'),
                        1 => admin_trans('dish.status.1')
                    ]);

                $filter->eq()->select('category_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish.fields.category_id'))
                    ->options($categories);
            });

            $grid->column('id', admin_trans('dish.fields.id'))->align('center');
            $grid->column('title', admin_trans('dish.fields.title'))->align('center');
            $grid->column('picture', admin_trans('dish.fields.picture'))->align('center')
                ->display(function ($value) {
                    $image = Image::create()
                        ->width(50)
                        ->height(50)
                        ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                        ->src($value);

                    return Html::create()->content([$image]);
                });
            $grid->column('price', admin_trans('dish.fields.price'))->align('center');
            $grid->column('category_id', admin_trans('dish.fields.category_id'))->align('center')
                    ->display(function ($value) use ($categories) {
                        return $categories[$value] ?? '類別遺失';
                    });
            $grid->sortInput('sort', admin_trans('dish.fields.sort'))->align('center');
            $grid->column('top', admin_trans('dish.fields.top'))->align('center')->switch();
            $grid->column('status', admin_trans('dish.fields.status'))->align('center')->switch();

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
            $adminUser = Admin::user();
            $form->title(admin_trans('dish.title'));
            $form->layout('vertical');

            $form->hidden('admin_user_id')->value($adminUser->id);
            $form->hidden('department_id')->value($adminUser->department_id);
            $form->select('category_id', admin_trans('dish.fields.category_id'))->options(self::getCategories())->required();
            $form->text('title', admin_trans('dish.fields.title'))->maxlength(200)->required();
            $form->image('picture', admin_trans('dish.fields.picture'))->required();
            $form->number('price', admin_trans('dish.fields.price'))->default(0)->required()->style(['width' => '100%']);

            // 修改時不能修改內容，只有新增的時候可以
            if ($form->isEdit()) {
                $form->myEditor('content_show', admin_trans('dish.fields.content'))->value($form->input('content'));
            } else {
                $form->myEditor('content', admin_trans('dish.fields.content'))->maxlength(200);
            }

            $form->number('daily_limit', admin_trans('dish.fields.daily_limit'))->default(0)->style(['width' => '100%'])->help(admin_trans('dish.help.daily_limit'));
            $form->number('sort', admin_trans('dish.fields.sort'))->default(0)->style(['width' => '100%']);
            $form->switch('top', admin_trans('dish.fields.top'))->default(0);
            $form->switch('status', admin_trans('dish.fields.status'))->default(1);
            $form->textarea('remark', admin_trans('dish.fields.remark'))->maxlength(200);
        });
    }

    /**
     * 類別清單
     * @return array
     */
    public function getCategories(): array
    {
        $dishCategory = DishCategory::query()
            ->orderBy('top', 'desc')
            ->orderBy('sort', 'desc')
            ->pluck('title','id')
            ->toArray();

        return $dishCategory;
    }
}
