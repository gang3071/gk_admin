<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\PlayerRechargeRecord;
use ExAdmin\ui\component\common\Html;
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
 * 店机后台 - 充值记录
 * @group store
 */
class StorePlayerRechargeRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_recharge_record_model');
    }

    /**
     * 充值记录
     * @group store
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_recharge_record.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 店机数据权限：只查询自己的玩家记录
            $playerIds = \addons\webman\model\Player::query()
                ->where('store_admin_id', $admin->id)
                ->pluck('id');

            $grid->model()->with(['player', 'channel_recharge_setting'])
                ->whereIn('player_id', $playerIds)
                ->whereIn('type', [
                    PlayerRechargeRecord::TYPE_ARTIFICIAL,
                    PlayerRechargeRecord::TYPE_MACHINE,
                ])
                ->orderBy('created_at', 'desc');

            $exAdminFilter = Request::input('ex_admin_filter', []);

            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['uuid']);
                });
            }
            if (!empty($exAdminFilter['player']['name'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
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

            // 统计数据
            $query = clone $grid->model();
            $totalData = $query->selectRaw(
                'sum(IF(status = 2, point, 0)) as success_point'
            )->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                // 充值成功
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['success_point']) ? floatval($totalData['success_point']) : 0)
                            ->prefix(admin_trans('player_recharge_record.status.2'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#52c41a'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);
            })->style(['background' => '#fff']);

            $grid->tools($layout);

            $grid->column('id', admin_trans('player_recharge_record.fields.id'))->align('center')->width(80);
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->copy()->width(180);
            $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))->copy()->width(150);
            $grid->column('player.name', admin_trans('player.fields.device_name'))->align('center')->width(150);
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerRechargeRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1
                        ? Tag::create(admin_trans('player.fields.is_test'))->color('red')
                        : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->align('center')->width(100);
            $grid->column('money', admin_trans('player_recharge_record.fields.money'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_recharge_record.talk_currency') : $data->currency);
            })->align('center')->width(120);
            $grid->column('point', admin_trans('player_recharge_record.fields.point'))->align('center')->width(120);
            $grid->column('type', admin_trans('player_recharge_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('#55acee');
                    case PlayerRechargeRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('#3b5999');
                    case PlayerRechargeRecord::TYPE_ARTIFICIAL:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('#87d068');
                    case PlayerRechargeRecord::TYPE_BUSINESS:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('purple');
                    case PlayerRechargeRecord::TYPE_GB:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('orange');
                    case PlayerRechargeRecord::TYPE_MACHINE:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('cyan');
                    case PlayerRechargeRecord::TYPE_EH:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('geekblue');
                    default:
                        return '';
                }
            })->align('center')->width(120);
            $grid->column('status', admin_trans('player_recharge_record.fields.status'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::STATUS_WAIT:
                    case PlayerRechargeRecord::STATUS_RECHARGING:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))->color('orange');
                    case PlayerRechargeRecord::STATUS_RECHARGED_CANCEL:
                    case PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL:
                    case PlayerRechargeRecord::STATUS_RECHARGED_REJECT:
                    case PlayerRechargeRecord::STATUS_RECHARGED_FAIL:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))->color('red');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))->color('green');
                    default:
                        return '';
                }
            })->align('center')->width(120);
            $grid->column('created_at', admin_trans('player_recharge_record.fields.created_at'))->align('center')->width(160);
            $grid->column('finish_time', admin_trans('player_recharge_record.fields.finish_time'))->align('center')->width(160);

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.device_uuid'));
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.device_name'));
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_recharge_record.fields.tradeno'));

                $filter->select('type')
                    ->placeholder(admin_trans('player_recharge_record.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayerRechargeRecord::TYPE_ARTIFICIAL => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_ARTIFICIAL),
                        PlayerRechargeRecord::TYPE_MACHINE => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_MACHINE),
                    ]);

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
