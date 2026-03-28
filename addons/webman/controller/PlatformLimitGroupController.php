<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlatformLimitGroup;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Button;

/**
 * 限红组管理（总后台）
 */
class PlatformLimitGroupController
{
    protected $model;

    public function __construct()
    {
        $this->model = PlatformLimitGroup::class;
    }

    /**
     * 限红组列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title('限红组管理');
            $grid->model()->orderBy('sort', 'asc')->orderBy('id', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->column('id', 'ID')->align('center')->width(80);
            $grid->column('code', '限红组编码')->align('center');
            $grid->column('name', '限红组名称')->align('center');
            $grid->column('description', '描述')->align('center');

            $grid->column('status', '状态')->display(function ($value) {
                return $value == 1
                    ? Tag::create('启用')->color('success')
                    : Tag::create('禁用')->color('default');
            })->align('center');

            $grid->sortInput('sort');

            $grid->column('created_at', '创建时间')->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('code')->placeholder('限红组编码');
                $filter->like()->text('name')->placeholder('限红组名称');
                $filter->equal()->select('status')->placeholder('状态')->options([
                    ['value' => 1, 'label' => '启用'],
                    ['value' => 0, 'label' => '禁用'],
                ]);
            });
            $grid->expandFilter();

            $grid->setForm()->drawer($this->form());

            $grid->actions(function (Actions $actions, $data) {
                // 添加配置平台按钮
                $actions->prepend(
                    Button::create('配置平台')
                        ->navigate('ex-admin/addons-webman-controller-PlatformLimitGroupConfigController/index',
                            ['limit_group_id' => $data['id']])
                        ->type('primary')
                        ->size('small')
                );
            })->align('center');
        });
    }

    /**
     * 限红组表单
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title($form->isEdit() ? '编辑限红组' : '新建限红组');

            $form->text('code', '限红组编码')
                ->maxlength(50)
                ->required()
                ->disabled($form->isEdit())
                ->help('限红组唯一标识，如：A、B、C');

            $form->text('name', '限红组名称')
                ->maxlength(100)
                ->required()
                ->help('如：高额组、中额组、低额组');

            $form->textarea('description', '描述')
                ->maxlength(500)
                ->rows(3)
                ->help('描述该限红组的适用场景');

            $form->radio('status', '状态')
                ->options([
                    1 => '启用',
                    0 => '禁用',
                ])
                ->default(1)
                ->required();

            $form->number('sort', '排序')
                ->default(0)
                ->help('数值越小越靠前');

            // 自动设置部门ID
            if (!$form->isEdit()) {
                $form->saving(function (Form $form) {
                    $form->input('department_id', Admin::user()->department_id);
                });
            }
        });
    }
}
