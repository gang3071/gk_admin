<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicketRecord;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 店家后台-摸奖券中奖记录管理
 * @group store
 */
class StoreLotteryTicketRecordController
{
    /**
     * 中奖记录列表
     * @auth true
     * @group store
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new LotteryTicketRecord(), function (Grid $grid) {
            // 获取当前店家管理员信息
            $admin = Admin::user();

            // 预加载关联关系
            $grid->model()->with(['player', 'activity', 'ticket']);

            // 数据权限过滤：只显示当前店家下属玩家的中奖记录
            $grid->model()->whereHas('player', function ($query) use ($admin) {
                $query->where('store_admin_id', $admin->id);
            });

            $grid->title(admin_trans('lottery_ticket.title.record_list'));
            $grid->bordered(true);
            $grid->autoHeight();

            // 获取筛选条件
            $requestFilter = Request::input('ex_admin_filter', []);

            // 筛选条件
            if (!empty($requestFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                    $query->where('uuid', $requestFilter['player']['uuid']);
                });
            }
            if (!empty($requestFilter['player']['name'])) {
                $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                    $query->where('name', 'like', '%' . $requestFilter['player']['name'] . '%');
                });
            }
            if (!empty($requestFilter['activity']['name'])) {
                $grid->model()->whereHas('activity', function ($query) use ($requestFilter) {
                    $query->where('name', 'like', '%' . $requestFilter['activity']['name'] . '%');
                });
            }
            if (!empty($requestFilter['ticket_no'])) {
                $grid->model()->whereHas('ticket', function ($query) use ($requestFilter) {
                    $query->where('ticket_no', 'like', '%' . $requestFilter['ticket_no'] . '%');
                });
            }
            if (isset($requestFilter['prize_type']) && $requestFilter['prize_type'] !== '') {
                $grid->model()->where('prize_type', $requestFilter['prize_type']);
            }
            if (isset($requestFilter['status']) && $requestFilter['status'] !== '') {
                $grid->model()->where('status', $requestFilter['status']);
            }

            // 排序
            $grid->model()->orderBy('created_at', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))
                ->width(80)->align('center')->fixed(true);

            $grid->column('ticket.ticket_no', admin_trans('lottery_ticket.fields.ticket_no'))
                ->width(120)->align('center')->fixed(true);

            // 店家后台使用 player.uuid 和 player.name（离线渠道模式）
            $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))
                ->width(150)->align('center')->copy();

            $grid->column('player.name', admin_trans('player.fields.device_name'))
                ->width(150)->align('center');

            $grid->column('activity.name', admin_trans('lottery_ticket.fields.activity_name'))
                ->width(200)->align('left');

            $grid->column('prize_type', admin_trans('lottery_ticket.record_fields.prize_type'))
                ->width(120)->align('center')
                ->display(function ($val) {
                    $typeMap = [
                        LotteryTicketRecord::PRIZE_TYPE_EMPTY => ['text' => admin_trans('lottery_ticket.prize_type.empty'), 'color' => 'default'],
                        LotteryTicketRecord::PRIZE_TYPE_CASH => ['text' => admin_trans('lottery_ticket.prize_type.cash'), 'color' => 'success'],
                        LotteryTicketRecord::PRIZE_TYPE_BONUS => ['text' => admin_trans('lottery_ticket.prize_type.bonus'), 'color' => 'processing'],
                    ];
                    $config = $typeMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($config['text'])->color($config['color']);
                });

            $grid->column('prize_level_name', admin_trans('lottery_ticket.record_fields.prize_level_name'))
                ->width(150)->align('center');

            $grid->column('prize_amount', admin_trans('lottery_ticket.fields.prize_amount'))
                ->width(120)->align('center')
                ->display(function ($val) {
                    return $val > 0 ? Tag::create(number_format($val, 2))->color('red') : '-';
                });

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    $statusMap = [
                        LotteryTicketRecord::STATUS_PENDING => ['text' => admin_trans('lottery_ticket.record_status.pending'), 'color' => 'warning'],
                        LotteryTicketRecord::STATUS_CLAIMED => ['text' => admin_trans('lottery_ticket.record_status.claimed'), 'color' => 'success'],
                        LotteryTicketRecord::STATUS_CANCELLED => ['text' => admin_trans('lottery_ticket.record_status.cancelled'), 'color' => 'error'],
                    ];
                    $config = $statusMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($config['text'])->color($config['color']);
                });

            $grid->column('draw_time', admin_trans('lottery_ticket.record_fields.draw_time'))
                ->width(160)->align('center');

            $grid->column('created_at', admin_trans('lottery_ticket.fields.created_at'))
                ->width(160)->align('center');

            // 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')
                    ->placeholder(admin_trans('player.fields.device_uuid'));

                $filter->like()->text('player.name')
                    ->placeholder(admin_trans('player.fields.device_name'));

                $filter->like()->text('activity.name')
                    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'));

                $filter->like()->text('ticket_no')
                    ->placeholder(admin_trans('lottery_ticket.fields.ticket_no'));

                $filter->eq()->select('prize_type')
                    ->placeholder(admin_trans('lottery_ticket.record_fields.prize_type'))
                    ->options([
                        LotteryTicketRecord::PRIZE_TYPE_EMPTY => admin_trans('lottery_ticket.prize_type.empty'),
                        LotteryTicketRecord::PRIZE_TYPE_CASH => admin_trans('lottery_ticket.prize_type.cash'),
                        LotteryTicketRecord::PRIZE_TYPE_BONUS => admin_trans('lottery_ticket.prize_type.bonus'),
                    ]);

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('lottery_ticket.fields.status'))
                    ->options([
                        LotteryTicketRecord::STATUS_PENDING => admin_trans('lottery_ticket.record_status.pending'),
                        LotteryTicketRecord::STATUS_CLAIMED => admin_trans('lottery_ticket.record_status.claimed'),
                        LotteryTicketRecord::STATUS_CANCELLED => admin_trans('lottery_ticket.record_status.cancelled'),
                    ]);
            });

            // 店家后台只读：隐藏删除和选择
            $grid->hideDelete();
            $grid->hideSelection();

            // 操作栏 - 只读，隐藏所有操作
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
        });
    }
}
