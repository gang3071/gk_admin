<?php

namespace addons\webman\controller;

use addons\webman\model\AdminDepartment;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Carbon;


/**
 * 部门管理
 */
class DepartmentController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.department_model');

    }

    /**
     * 部门
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('department.title'));
            $grid->model()->where('type', AdminDepartment::TYPE_DEPARTMENT);
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (isset($exAdminFilter['created_at_start']) && !empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=',
                        Carbon::parse($exAdminFilter['created_at_start'])->startOfDay()->toDateTimeString());
                }
                if (isset($exAdminFilter['created_at_end']) && !empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=',
                        Carbon::parse($exAdminFilter['created_at_end'])->endOfDay()->toDateTimeString());
                }
            }
            $grid->autoHeight();
            $grid->expandFilter();
            $grid->tree();
            $grid->column('name', admin_trans('department.fields.name'));
            $grid->column('leader', admin_trans('department.fields.leader'));
            $grid->column('mobile', admin_trans('department.fields.mobile'));
            $grid->column('status', admin_trans('department.fields.status'))->switch([[1=>''],[0=>'']]);
            $grid->sortInput('sort', admin_trans('department.fields.sort'));
            $grid->column('created_at', admin_trans('department.fields.create_at'));
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('department.fields.name'));
                $filter->like()->text('leader')->placeholder(admin_trans('department.fields.leader'));
                $filter->like()->text('mobile')->placeholder(admin_trans('department.fields.mobile'));
                $filter->eq()->select('status')->placeholder(admin_trans('department.fields.status'))->options([
                    1 => admin_trans('department.normal'),
                    0 => admin_trans('department.disable')
                ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('public_msg.created_at_start'), admin_trans('public_msg.created_at_end')]);
            });
            $grid->setForm()->modal($this->form());
        });
    }

    /**
     * 部门
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->title(admin_trans('department.title'));
            $form->treeSelect('pid', admin_trans('department.fields.pid'))
                ->options($this->model::where('type', AdminDepartment::TYPE_DEPARTMENT)->get()->toArray());
            $form->text('name', admin_trans('department.fields.name'))
                ->required();
            $form->text('leader', admin_trans('department.fields.leader'));
            $form->text('mobile', admin_trans('department.fields.mobile'))
                ->ruleMobile();
            $form->number('sort', admin_trans('department.fields.sort'))->default(0);

            $form->saving(function (Form $form) {
                if ($form->isEdit() && $form->input('id') == $form->input('pid')) {
                   return message_error(admin_trans('department.parent_id_repeat'));
                }
            });
            $form->saved(function (Form $form) {
                $path = $this->model::where('id',$form->input('pid'))->value('path');
                $paths = explode(',',$path);
                $paths= array_filter($paths);
                $model = $form->driver()->model();
                $paths[] = $model->id;
                $model->path = implode(',',$paths);
                $model->save();
            });
        });
    }
}
