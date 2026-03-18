<?php

namespace addons\webman\controller;

use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;

/**
 * 机台厂商
 */
class MachineProducerController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_producer_model');
    }

    /**
     * 厂商
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('machine_producer.title'));
            $grid->model()->orderBy('status', 'desc')->orderBy('sort', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->column('id', admin_trans('machine_producer.fields.id'))->align('center');
            $grid->column('name', admin_trans('machine_producer.fields.name'))->align('center');
            $grid->sortInput('sort', admin_trans('machine_producer.fields.sort'))->align('center');
            $grid->column('status', admin_trans('machine_producer.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->modal($this->form())->width('20%');
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');
        });
    }

    /**
     * 厂商
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('machine_producer.title'));
            $form->text('name', admin_trans('machine_producer.fields.name'))->maxlength(50)->required();
            $form->layout('vertical');
        });
    }
}
