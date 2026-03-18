<?php

namespace addons\webman\controller;

use addons\webman\model\PhoneSmsLog;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Exception;

/**
 * 短信日志
 */
class PhoneSmsLogController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.phone_sms_log_model');
        
    }
    
    /**
     * 短信日志
     * @auth true
     * @return Grid
     * @throws Exception
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('phone_sms_log.title'));
            $grid->model()->orderBy('id', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('phone', admin_trans('phone_sms_log.fields.phone'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PhoneSmsLog $data
            ) {
                if (!empty($data->player)) {
                    $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($data->player->uuid)
                    ]);
                }
            })->fixed(true)->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function (
                $val,
                PhoneSmsLog $data
            ) {
                if (!empty($data->player)) {
                    return Html::create()->content([
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                    ]);
                }
            })->fixed(true)->align('center');
            $grid->column('code', admin_trans('phone_sms_log.fields.code'))->align('center');
            $grid->column('type', admin_trans('phone_sms_log.fields.type'))->display(function (
                $val,
                PhoneSmsLog $data
            ) {
                switch ($data->type) {
                    case PhoneSmsLog::TYPE_LOGIN:
                    case PhoneSmsLog::TYPE_CHANGE_PAY_PASSWORD:
                        return Tag::create(admin_trans('phone_sms_log.type.' . $val))->color('green');
                    case PhoneSmsLog::TYPE_REGISTER:
                    case PhoneSmsLog::TYPE_CHANGE_PHONE:
                        return Tag::create(admin_trans('phone_sms_log.type.' . $val))->color('red');
                    case PhoneSmsLog::TYPE_CHANGE_PASSWORD:
                    case PhoneSmsLog::TYPE_BIND_NEW_PHONE:
                        return Tag::create(admin_trans('phone_sms_log.type.' . $val))->color('orange');
                    case PhoneSmsLog::TYPE_TALK_BIND:
                    case PhoneSmsLog::TYPE_LINE_BIND:
                        return Tag::create(admin_trans('phone_sms_log.type.' . $val))->color('blue');
                    default:
                        return '';
                }
            })->align('center')->align('center');
            $grid->column('status', admin_trans('phone_sms_log.fields.status'))->display(function (
                $val,
                PhoneSmsLog $data
            ) {
                switch ($data->status) {
                    case 0:
                        return Tag::create(admin_trans('admin.error'))->color('red');
                    case 1:
                        return Tag::create(admin_trans('admin.success'))->color('green');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('created_at', admin_trans('phone_sms_log.fields.created_at'))->display(function ($val) {
                return date('Y-m-d H:i:s', strtotime($val));
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('phone')->placeholder(admin_trans('phone_sms_log.fields.phone'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->eq()->select('type')
                    ->placeholder(admin_trans('phone_sms_log.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PhoneSmsLog::TYPE_LOGIN => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_LOGIN),
                        PhoneSmsLog::TYPE_REGISTER => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_REGISTER),
                        PhoneSmsLog::TYPE_CHANGE_PASSWORD => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_CHANGE_PASSWORD),
                        PhoneSmsLog::TYPE_CHANGE_PAY_PASSWORD => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_CHANGE_PAY_PASSWORD),
                        PhoneSmsLog::TYPE_CHANGE_PHONE => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_CHANGE_PHONE),
                        PhoneSmsLog::TYPE_BIND_NEW_PHONE => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_BIND_NEW_PHONE),
                        PhoneSmsLog::TYPE_TALK_BIND => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_TALK_BIND),
                        PhoneSmsLog::TYPE_LINE_BIND => admin_trans('phone_sms_log.type.' . PhoneSmsLog::TYPE_LINE_BIND),
                    ]);
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->export();
        });
    }
}
