<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\ChannelFinancialRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;

/**
 * 财务操作
 * @group channel
 */
class ChannelFinancialRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.channel_financial_record_model');
    }

    /**
     * 财务操作
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('channel_financial_record.title'));
            $grid->model()->with(['player'])->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('channel_financial_record.fields.id'))->align('center');
            $grid->column('tradeno', admin_trans('channel_financial_record.fields.tradeno'))->copy()->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))
                ->display(function ($val, ChannelFinancialRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                    ]);
                })
                ->copy();
            $grid->column('player.phone', admin_trans('channel_financial_record.fields.player'))->display(function ($val, ChannelFinancialRecord $data) {
                if (!empty($data->player)) {
                    $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($val)
                    ]);
                }
                return '';
            })->align('center');
            $grid->column('action', admin_trans('channel_financial_record.fields.action'))->display(function ($val, ChannelFinancialRecord $data) {
                switch ($val) {
                    case ChannelFinancialRecord::ACTION_RECHARGE_PASS:
                        return Tag::create(admin_trans('channel_financial_record.action.' . $val))
                            ->color('#108ee9');
                    case ChannelFinancialRecord::ACTION_RECHARGE_REJECT:
                        return Tag::create(admin_trans('channel_financial_record.action.' . $val))
                            ->color('#3b5999');
                    case ChannelFinancialRecord::ACTION_WITHDRAW_PASS:
                        return Tag::create(admin_trans('channel_financial_record.action.' . $val))
                            ->color('#87d068');
                    case ChannelFinancialRecord::ACTION_WITHDRAW_REJECT:
                        return Tag::create(admin_trans('channel_financial_record.action.' . $val))
                            ->color('#f50');
                    case ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT:
                        return Tag::create(admin_trans('channel_financial_record.action.' . $val))
                            ->color('#2db7f5');
                    case ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD:
                        return Tag::create(admin_trans('channel_financial_record.action.' . $val))
                            ->color('#2d7ef5');
                    case ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP:
                        return Html::create()->content([
                            Tag::create(admin_trans('channel_financial_record.action.' . $val))->color('#912df5'),
                            Html::div()->content(admin_trans('channel_financial_record.content', null, ['{setting_id}' => $data->target_id]))
                        ]);
                    case ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE:
                        return Html::create()->content([
                            Tag::create(admin_trans('channel_financial_record.action.' . $val))->color('#f5572d'),
                            Html::div()->content(admin_trans('channel_financial_record.content', null, ['{setting_id}' => $data->target_id]))
                        ]);
                    case ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT:
                    case ChannelFinancialRecord::ACTION_WITHDRAW_GB_ERROR:
                    case ChannelFinancialRecord::ACTION_WITHDRAW_EH_ERROR:
                        return Html::create()->content([
                            Tag::create(admin_trans('channel_financial_record.action.' . $val))->color('#f5c82d'),
                            Html::div()->content(admin_trans('channel_financial_record.content', null, ['{setting_id}' => $data->target_id]))
                        ]);
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('created_at', admin_trans('channel_financial_record.fields.created_at'))->align('center');
            $grid->column('user_name', admin_trans('channel_financial_record.fields.user_name'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideDeleteSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('user_id')
                    ->placeholder(admin_trans('channel_financial_record.fields.user_name'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->eq()->select('action')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel_financial_record.fields.action'))
                    ->options([
                        ChannelFinancialRecord::ACTION_RECHARGE_PASS => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_RECHARGE_PASS),
                        ChannelFinancialRecord::ACTION_RECHARGE_REJECT => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_RECHARGE_REJECT),
                        ChannelFinancialRecord::ACTION_WITHDRAW_PASS => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_WITHDRAW_PASS),
                        ChannelFinancialRecord::ACTION_WITHDRAW_REJECT => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_WITHDRAW_REJECT),
                        ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT),
                        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ADD),
                        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_RECHARGE_SETTING_STOP),
                        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_RECHARGE_SETTING_ENABLE),
                        ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT => admin_trans('channel_financial_record.action.' . ChannelFinancialRecord::ACTION_RECHARGE_SETTING_EDIT),
                    ]);
                $filter->like()->text('tradeno')->placeholder(admin_trans('channel_financial_record.fields.tradeno'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('public_msg.created_at_start'), admin_trans('public_msg.created_at_end')]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 筛选玩家下拉
     * @return mixed
     */
    public function getPlayerOptions()
    {
        $request = Request::input();
        $adminUser = AdminUser::where('department_id', Admin::user()->department_id)->orderBy('created_at', 'desc');
        if (!empty($request['search'])) {
            $adminUser->where('username', 'like', '%' . $request['search'] . '%');
        }
        $playerList = $adminUser->get();
        $data = [];
        /** @var AdminUser $adminUser */
        foreach ($playerList as $adminUser) {
            $data[] = [
                'value' => $adminUser->id,
                'label' => $adminUser->username,
            ];
        }

        return Response::success($data);
    }
}
