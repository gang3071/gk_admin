<?php

namespace addons\webman\controller;

use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Carbon;


/**
 * 岗位管理
 */
class PostController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.post_model');

    }

    /**
     * 岗位
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('post.title'));
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=',
                    Carbon::parse($exAdminFilter['created_at_start'])->startOfDay()->toDateTimeString());
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=',
                    Carbon::parse($exAdminFilter['created_at_end'])->endOfDay()->toDateTimeString());
            }
            $grid->autoHeight();
            $grid->column('name', admin_trans('post.fields.name'));
            $grid->column('status', admin_trans('post.fields.status'))->switch([[1 => ''], [0 => '']]);
            $grid->sortInput('sort', admin_trans('post.fields.sort'));
            $grid->column('created_at', admin_trans('post.fields.create_at'));
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('post.fields.name'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('post.fields.status'))
                    ->options([
                        1 => admin_trans('post.normal'),
                        0 => admin_trans('post.disable')
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('public_msg.created_at_start'), admin_trans('public_msg.created_at_end')]);
            });
            $grid->setForm()->modal($this->form());
            $grid->expandFilter();
        });
    }

    /**
     * 岗位
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->title(admin_trans('post.title'));
            $form->text('name', admin_trans('post.fields.name'))
                ->required();
            $form->number('sort', admin_trans('post.fields.sort'))->default(0);
        });
    }
}
