<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\ChannelRechargeMethod;
use addons\webman\model\PlayerWithdrawRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 代理后台 - 提现记录
 * @group agent
 */
class AgentPlayerWithdrawRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_withdraw_record_model');
    }

    /**
     * 提现记录
     * @group agent
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_withdraw_record.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            /** @var \addons\webman\model\AdminUser $admin */
            $admin = Admin::user();

            // 代理：只查询所有下级店家的玩家记录
            $storeIds = $admin->childStores()->where('type', \addons\webman\model\AdminUser::TYPE_STORE)->pluck('id');
            $playerIds = \addons\webman\model\Player::query()->whereIn('store_admin_id', $storeIds)->pluck('id');

            $grid->model()->with(['player'])
                ->whereIn('player_id', $playerIds)
                ->whereIn('type', [
                    PlayerWithdrawRecord::TYPE_SELF,
                    PlayerWithdrawRecord::TYPE_GB,
                    PlayerWithdrawRecord::TYPE_THIRD,
                ])
                ->orderBy('created_at', 'desc');

            $exAdminFilter = Request::input('ex_admin_filter', []);

            // 所属店家筛选
            if (!empty($exAdminFilter['player']['store_admin_id'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('store_admin_id', $exAdminFilter['player']['store_admin_id']);
                });
            }

            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['player']['name'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
                });
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['uuid']);
                });
            }
            if (!empty($exAdminFilter['type'])) {
                $grid->model()->where('type', $exAdminFilter['type']);
            }
            if (isset($exAdminFilter['status']) && $exAdminFilter['status'] != null) {
                $grid->model()->where('status', $exAdminFilter['status']);
            }
            if (!empty($exAdminFilter['tradeno'])) {
                $grid->model()->where('tradeno', 'like', '%' . $exAdminFilter['tradeno'] . '%');
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }

            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center')->width(80);
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->copy()->width(180);
            $grid->column('player.name', admin_trans('player.fields.device_name'))->align('center')->width(120);
            $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))->copy()->width(150);
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerWithdrawRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1
                        ? Tag::create(admin_trans('player.fields.is_test'))->color('red')
                        : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->align('center')->width(100);
            $grid->column('player.storeAdmin.nickname', admin_trans('admin.store'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $data->player->storeAdmin->nickname ?? $data->player->storeAdmin->username ?? '';
            })->width(150)->align('center');
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar)
                    ? Avatar::create()->src($data->player->avatar)
                    : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center')->width(150);
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_withdraw_record.talk_currency') : $data->currency);
            })->align('center')->width(120);
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center')->width(120);
            $grid->column('fee', admin_trans('player_withdraw_record.fields.fee'))->align('center')->width(100);
            $grid->column('inmoney', admin_trans('player_withdraw_record.fields.inmoney'))->align('center')->width(120);
            $grid->column('type', admin_trans('player_withdraw_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerWithdrawRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))->color('#55acee');
                    case PlayerWithdrawRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))->color('#3b5999');
                    case PlayerWithdrawRecord::TYPE_GB:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))->color('#87d068');
                    default:
                        return '';
                }
            })->align('center')->width(120);
            $grid->column('bank_type', admin_trans('player_withdraw_record.fields.bank_type'))->display(function ($val) {
                switch ($val) {
                    case ChannelRechargeMethod::TYPE_USDT:
                        return Tag::create(admin_trans('channel_recharge_method.type.' . $val))->color('#55acee');
                    case ChannelRechargeMethod::TYPE_ALI:
                        return Tag::create(admin_trans('channel_recharge_method.type.' . $val))->color('#3b5999');
                    case ChannelRechargeMethod::TYPE_WECHAT:
                        return Tag::create(admin_trans('channel_recharge_method.type.' . $val))->color('#87d068');
                    case ChannelRechargeMethod::TYPE_BANK:
                        return Tag::create(admin_trans('channel_recharge_method.type.' . $val))->color('#cd201f');
                    case ChannelRechargeMethod::TYPE_GB:
                        return Tag::create(admin_trans('channel_recharge_method.type.' . $val))->color('orange');
                    default:
                        return '';
                }
            })->align('center')->width(120);
            $grid->column('status', admin_trans('player_withdraw_record.fields.status'))->display(function ($val) {
                switch ($val) {
                    case PlayerWithdrawRecord::STATUS_WAIT:
                        return Tag::create(admin_trans('player_withdraw_record.status.' . $val))->color('orange');
                    case PlayerWithdrawRecord::STATUS_PENDING_REJECT:
                        return Tag::create(admin_trans('player_withdraw_record.status.' . $val))->color('red');
                    case PlayerWithdrawRecord::STATUS_SUCCESS:
                        return Tag::create(admin_trans('player_withdraw_record.status.' . $val))->color('green');
                    case PlayerWithdrawRecord::STATUS_PENDING_PAYMENT:
                        return Tag::create(admin_trans('player_withdraw_record.status.' . $val))->color('blue');
                    case PlayerWithdrawRecord::STATUS_FAIL:
                        return Tag::create(admin_trans('player_withdraw_record.status.' . $val))->color('volcano');
                    default:
                        return '';
                }
            })->align('center')->width(120);
            $grid->column('created_at', admin_trans('player_withdraw_record.fields.created_at'))->align('center')->width(160);
            $grid->column('finish_time', admin_trans('player_withdraw_record.fields.finish_time'))->align('center')->width(160);

            $grid->filter(function (Filter $filter) use ($admin) {
                // 所属店家筛选
                $filter->eq()->select('player.store_admin_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.store'))
                    ->remoteOptions(admin_url([ChannelAgentController::class, 'getStoreOptions']));

                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.device_name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.device_uuid'));
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));

                $filter->select('date_type')
                    ->placeholder(admin_trans('machine_report.fields.date_type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideDetail();
                $actions->hideDel();
                $actions->hideEdit();
            })->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
        });
    }
}
