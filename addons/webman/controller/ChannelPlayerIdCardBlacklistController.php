<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlayerIdCardBlacklist;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\support\Request;

/**
 * 渠道-玩家身份证黑名单
 * @group channel
 */
class ChannelPlayerIdCardBlacklistController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_id_card_blacklist_model');
    }

    /**
     * 黑名单列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $requestFilter = Request::input('ex_admin_filter', []);
            $grid->model()->orderBy('created_at', 'desc');

            if (!empty($requestFilter['id_number'])) {
                $grid->model()->where('id_number', 'like', '%' . $requestFilter['id_number'] . '%');
            }

            $grid->title(admin_trans('player_blacklist.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', 'ID')->align('center');
            $grid->column('id_number', admin_trans('player_blacklist.fields.id_number'))->align('center');
            $grid->column('player_name', admin_trans('player_blacklist.fields.player_name'))->align('center');
            $grid->column('admin_name', admin_trans('player_blacklist.fields.admin_name'))->align('center');
            $grid->column('remark', admin_trans('player_blacklist.fields.remark'))->align('left');
            $grid->column('created_at', admin_trans('player_blacklist.fields.created_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('id_number')
                    ->placeholder(admin_trans('player_blacklist.filter.id_number'));
            });
        });
    }

    /**
     * 加入黑名单
     * @group channel
     * @auth true
     */
    public function addToBlacklist(): Form
    {
        return Form::create(function (Form $form) {
            $form->text('id_number', admin_trans('player_blacklist.fields.id_number'))
                ->required()
                ->placeholder(admin_trans('player_blacklist.placeholder.id_number'));
            $form->textarea('remark', admin_trans('player_blacklist.fields.remark'))
                ->rows(3)
                ->placeholder(admin_trans('player_blacklist.placeholder.remark'));

            $form->saved(function (Form $form) {
                $idNumber = trim($form->input('id_number') ?? '');
                if (empty($idNumber)) {
                    return message_error(admin_trans('player_blacklist.error.id_number_required'));
                }

                PlayerIdCardBlacklist::create([
                    'id_number'  => $idNumber,
                    'admin_id'   => Admin::id(),
                    'admin_name' => Admin::user()->name ?? '',
                    'remark'     => $form->input('remark') ?? '',
                ]);

                return message_success(admin_trans('player_blacklist.message.add_success'));
            });

            $form->layout('vertical');
        });
    }
}
