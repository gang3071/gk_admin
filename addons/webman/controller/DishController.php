<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\form\MyEditor;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminUser;
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
class DishController
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
        $adminType = Admin::user()->type;
        $categories = self::getCategories();
        $stores = [];

        // 非門店角色可以看到對應的門店清單
        if ($adminType != AdminDepartment::TYPE_STORE) {
            $stores = self::getStores($adminType);
        }

        return Grid::create(new $this->model, function (Grid $grid) use ($adminType, $categories, $stores) {
            $grid->title(admin_trans('dish.title'));
            $grid->hideSelection();
            $grid->hideDelete();
            $grid->model()->orderBy('admin_user_id', 'asc')->orderBy('category_id', 'asc')->orderBy('top', 'desc')->orderBy('sort', 'asc');

            if ($adminType != AdminDepartment::TYPE_STORE) {
                $grid->model()->whereIn('admin_user_id', array_keys($stores));
            } else {
                $grid->model()->where('admin_user_id', Admin::user()->id);
            }

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) use ($adminType, $categories, $stores) {
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

                if ($adminType != AdminDepartment::TYPE_STORE) {
                    $filter->eq()->select('admin_user_id')
                        ->showSearch()
                        ->style(['width' => '200px'])
                        ->dropdownMatchSelectWidth()
                        ->placeholder(admin_trans('dish.fields.admin_user_id'))
                        ->options($stores);
                }
            });

            $grid->column('id', admin_trans('dish.fields.id'))->align('center');
            $grid->column('title', admin_trans('dish.fields.title'))->align('center');
            $grid->column('picture', admin_trans('dish.fields.picture'))
                ->align('center')
                ->display(function ($value) {
                    $image = Image::create()
                        ->width(50)
                        ->height(50)
                        ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                        ->src($value);

                    return Html::create()->content([$image]);
                });
            $grid->column('price', admin_trans('dish.fields.price'))->align('center');
            $grid->column('category_id', admin_trans('dish.fields.category_id'))
                    ->align('center')
                    ->display(function ($value) use ($categories) {
                        return $categories[$value] ?? '類別遺失';
                    });
            $grid->sortInput('sort', admin_trans('dish.fields.sort'))->align('center');
            $grid->column('top', admin_trans('dish.fields.top'))->switch()->align('center');
            $grid->column('status', admin_trans('dish.fields.status'))->switch()->align('center');

            if ($adminType != AdminDepartment::TYPE_STORE) {
                $grid->column('admin_user_id', admin_trans('dish.fields.admin_user_id'))
                    ->align('center')
                    ->display(function ($value) use ($stores) {
                        return $stores[$value] ?? '門店遺失';
                    });
            }

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
            $adminType = Admin::user()->type;
            $form->title(admin_trans('dish.title'));
            $form->layout('vertical');

            if ($adminType != AdminDepartment::TYPE_STORE) {
                $form->select('admin_user_id', admin_trans('dish.fields.admin_user_id'))->options(self::getStores($adminType))->required();
            } else {
                $form->hidden('admin_user_id')->value(Admin::user()->id);
            }

            $form->hidden('department_id')->value(Admin::user()->department_id);
            $form->select('category_id', admin_trans('dish.fields.category_id'))->options(self::getCategories())->required();
            $form->text('title', admin_trans('dish.fields.title'))->maxlength(200)->required();
            $form->image('picture', admin_trans('dish.fields.picture'));
            $form->myEditor('content', admin_trans('dish.fields.content'))->maxlength(200);
            $form->number('price', admin_trans('dish.fields.price'))->default(0)->required();
            $form->number('sort', admin_trans('dish.fields.sort'))->default(0)->required();
            $form->switch('top', admin_trans('dish.fields.top'))->required();
            $form->switch('status', admin_trans('dish.fields.status'))->required();
            $form->textarea('remark', admin_trans('dish.fields.remark'))->maxlength(200);

            $form->saving(function (Form $form) use ($adminType) {
                $department_id = Admin::user()->department_id;

                // 當角色為非門店時，department_id 從門店獲取
                if ($adminType != AdminDepartment::TYPE_STORE) {
                    $adminUser = AdminUser::query()->where('id', $form->input('admin_user_id'))->first();

                    if ($adminUser) {
                        $department_id = $adminUser->department_id;
                    }
                }

                $form->input('department_id', $department_id);
            });
        });
    }

    /**
     * 門店清單
     * @return array
     */
    public function getStores(int $type): array
    {
        $adminUser = AdminUser::query()->where('type', 4);

        switch ($type) {
            case '2':  // 渠道/部門
                $adminUser->where('department_id', Admin::user()->department_id);
                break;

            case '3':  // 代理
                $adminUser->where('parent_admin_id', Admin::user()->id);
                break;
        }

        $adminUser = $adminUser->pluck('nickname','id')->toArray();

        return $adminUser;
    }

    /**
     * 類別清單
     * @return array
     */
    public function getCategories(): array
    {
        $dishCategory = DishCategory::query()
            ->orderBy('top', 'desc')
            ->orderBy('sort', 'asc')
            ->pluck('title','id')
            ->toArray();

        return $dishCategory;
    }
}
