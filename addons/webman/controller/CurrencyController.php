<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Currency;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\support\Arr;


/**
 * 货币管理
 */
class CurrencyController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.currency_model');

    }

    /**
     * 货币
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('currency.title'));
            $grid->bordered();
            $grid->autoHeight();
            $grid->column('id', admin_trans('currency.fields.id'))->align('center');
            $grid->column('name', admin_trans('currency.fields.name'))->display(function ($val, Currency $data) {
                return admin_trans('currency.currency_name' . '.' . $data->identifying);
            })->align('center');
            $grid->column('identifying', admin_trans('currency.fields.identifying'))->align('center');
            $grid->column('ratio', admin_trans('currency.fields.ratio'))->display(function ($val) {
                return floatval($val);
            })->append(' ' . admin_trans('currency.currency'))->align('center');
            $grid->column('status', admin_trans('currency.fields.status'))->switch([[1 => ''], [0 => '']])->align('center');;
            $grid->column('admin_user.username', admin_trans('admin.fields.username'))->align('center');
            $grid->column('created_at', admin_trans('currency.fields.create_at'))->align('center');
            $grid->setForm()->modal($this->form());
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 货币
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $disabledValue = Arr::pluck($this->model::select('identifying')->get()->toArray(), 'identifying');
            $form->title(admin_trans('currency.title'));
            $form->select('identifying', admin_trans('currency.fields.identifying'))
                ->disabled($form->isEdit())
                ->options(plugin()->webman->config('currency'))
                ->disabledValue($disabledValue)
                ->required();
            $form->number('ratio', admin_trans('currency.fields.ratio') . '=')
                ->min(0)
                ->max(1000000)
                ->precision(4)
                ->required()
                ->style(['width' => '100%'])
                ->addonAfter(admin_trans('currency.currency'));
            $form->input('admin_id', Admin::id());
            $form->saving(function (Form $form) {
                if (!$form->isEdit()) {
                   $identifying = $form->input('identifying');
                   if ($this->model::where('identifying', $identifying)->first()) {
                       return message_error(admin_trans('currency.currency_has_exists'));
                   }
                }
            });
        });
    }
}
