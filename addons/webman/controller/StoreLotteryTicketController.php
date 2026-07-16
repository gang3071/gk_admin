<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicket;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 店家后台-摸奖券管理
 * @group store
 */
class StoreLotteryTicketController
{
    /**
     * 摸奖券列表
     * @auth true
     * @group store
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new LotteryTicket(), function (Grid $grid) {
            // 获取当前店家管理员信息
            $admin = Admin::user();

            // 预加载关联关系
            $grid->model()->with(['player', 'activity']);

            // 数据权限过滤：只显示当前店家下属玩家的摸奖券
            $grid->model()->whereHas('player', function ($query) use ($admin) {
                $query->where('store_admin_id', $admin->id);
            });

            $grid->title(admin_trans('lottery_ticket.title.ticket_list'));
            $grid->bordered(true);
            $grid->autoHeight();

            // 获取筛选条件
            $requestFilter = Request::input('ex_admin_filter', []);

            // 筛选条件
            if (!empty($requestFilter['ticket_no'])) {
                $grid->model()->where('ticket_no', 'like', '%' . $requestFilter['ticket_no'] . '%');
            }
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
            if (isset($requestFilter['status']) && $requestFilter['status'] !== '') {
                $grid->model()->where('status', $requestFilter['status']);
            }
            if (isset($requestFilter['source']) && $requestFilter['source'] !== '') {
                $grid->model()->where('source', $requestFilter['source']);
            }

            // 排序
            $grid->model()->orderBy('created_at', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))
                ->width(80)->align('center')->fixed(true);

            $grid->column('ticket_no', admin_trans('lottery_ticket.fields.ticket_no'))
                ->width(120)->align('center')->fixed(true);

            // 店家后台使用 player.uuid 和 player.name（离线渠道模式）
            $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))
                ->width(150)->align('center')->copy();

            $grid->column('player.name', admin_trans('player.fields.device_name'))
                ->width(150)->align('center');

            $grid->column('activity.name', admin_trans('lottery_ticket.fields.activity_name'))
                ->width(200)->align('left');

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    $statusMap = [
                        LotteryTicket::STATUS_UNUSED => ['text' => admin_trans('lottery_ticket.ticket_status.unused'), 'color' => 'processing'],
                        LotteryTicket::STATUS_USED => ['text' => admin_trans('lottery_ticket.ticket_status.used'), 'color' => 'success'],
                        LotteryTicket::STATUS_EXPIRED => ['text' => admin_trans('lottery_ticket.ticket_status.expired'), 'color' => 'default'],
                    ];
                    $config = $statusMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($config['text'])->color($config['color']);
                });

            $grid->column('source', admin_trans('lottery_ticket.fields.source'))
                ->width(120)->align('center')
                ->display(function ($val) {
                    $sourceMap = [
                        LotteryTicket::SOURCE_RECHARGE => admin_trans('lottery_ticket.source.recharge'),
                        LotteryTicket::SOURCE_ACTIVITY => admin_trans('lottery_ticket.source.activity'),
                        LotteryTicket::SOURCE_MANUAL => admin_trans('lottery_ticket.source.manual'),
                    ];
                    return $sourceMap[$val] ?? $val;
                });

            $grid->column('expired_at', admin_trans('lottery_ticket.fields.expired_at'))
                ->width(160)->align('center');

            $grid->column('created_at', admin_trans('lottery_ticket.fields.created_at'))
                ->width(160)->align('center');


            // 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('ticket_no')
                    ->placeholder(admin_trans('lottery_ticket.fields.ticket_no'));

                $filter->like()->text('player.uuid')
                    ->placeholder(admin_trans('player.fields.device_uuid'));

                $filter->like()->text('player.name')
                    ->placeholder(admin_trans('player.fields.device_name'));

                $filter->like()->text('activity.name')
                    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('lottery_ticket.fields.status'))
                    ->options([
                        LotteryTicket::STATUS_UNUSED => admin_trans('lottery_ticket.ticket_status.unused'),
                        LotteryTicket::STATUS_USED => admin_trans('lottery_ticket.ticket_status.used'),
                        LotteryTicket::STATUS_EXPIRED => admin_trans('lottery_ticket.ticket_status.expired'),
                    ]);

                $filter->eq()->select('source')
                    ->placeholder(admin_trans('lottery_ticket.fields.source'))
                    ->options([
                        LotteryTicket::SOURCE_RECHARGE => admin_trans('lottery_ticket.source.recharge'),
                        LotteryTicket::SOURCE_ACTIVITY => admin_trans('lottery_ticket.source.activity'),
                        LotteryTicket::SOURCE_MANUAL => admin_trans('lottery_ticket.source.manual'),
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
