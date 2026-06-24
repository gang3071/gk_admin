<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 代理后台-摸奖券列表管理
 * @group agent
 */
class AgentLotteryTicketController
{
    /**
     * 摸奖券列表
     * @auth true
     * @group agent
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new LotteryTicket(), function (Grid $grid) {
            // 获取当前代理管理员信息
            $admin = Admin::user();
            $departmentId = $admin->department_id;

            // 预加载关联数据
            $grid->model()->with(['player', 'activity']);

            // ⭐ 数据权限过滤：只显示当前代理下玩家获取的摸奖券
            $grid->model()->whereHas('player', function ($query) use ($admin) {
                $query->where('agent_admin_id', $admin->id);
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
            if (!empty($requestFilter['activity_id'])) {
                $grid->model()->where('activity_id', $requestFilter['activity_id']);
            }
            if (!empty($requestFilter['player_uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                    $query->where('uuid', $requestFilter['player_uuid']);
                });
            }
            if (!empty($requestFilter['player_name'])) {
                $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                    $query->where('name', 'like', '%' . $requestFilter['player_name'] . '%');
                });
            }
            if (isset($requestFilter['status']) && $requestFilter['status'] !== '') {
                $grid->model()->where('status', $requestFilter['status']);
            }
            if (!empty($requestFilter['source'])) {
                $grid->model()->where('source', $requestFilter['source']);
            }
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }

            // 排序：按券号降序（最大的在前）
            $grid->model()->orderBy('ticket_no', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))
                ->width(80)->align('center')->fixed(true);

            $grid->column('ticket_no', admin_trans('lottery_ticket.fields.ticket_no'))
                ->width(150)->align('center')->fixed(true);

            $grid->column('activity.name', admin_trans('lottery_ticket.fields.activity_name'))
                ->width(200)->align('left');

            $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))
                ->width(150)->align('center');

            $grid->column('player.name', admin_trans('player.fields.device_name'))
                ->width(150)->align('center');

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    $statusMap = [
                        LotteryTicket::STATUS_UNUSED => ['text' => admin_trans('lottery_ticket.ticket_status.unused'), 'color' => 'default'],
                        LotteryTicket::STATUS_USED => ['text' => admin_trans('lottery_ticket.ticket_status.used'), 'color' => 'success'],
                        LotteryTicket::STATUS_EXPIRED => ['text' => admin_trans('lottery_ticket.ticket_status.expired'), 'color' => 'error'],
                    ];
                    $config = $statusMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($config['text'])->color($config['color']);
                });

            $grid->column('source', admin_trans('lottery_ticket.fields.source'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    $sourceMap = [
                        LotteryTicket::SOURCE_RECHARGE => admin_trans('lottery_ticket.source.recharge'),
                        LotteryTicket::SOURCE_ACTIVITY => admin_trans('lottery_ticket.source.activity'),
                        LotteryTicket::SOURCE_MANUAL => admin_trans('lottery_ticket.source.manual'),
                    ];
                    return $sourceMap[$val] ?? $val;
                });

            $grid->column('created_at', admin_trans('lottery_ticket.fields.created_at'))
                ->width(160)->align('center');

            $grid->column('used_at', admin_trans('lottery_ticket.fields.used_at'))
                ->width(160)->align('center')
                ->display(function ($val) {
                    return $val ?: '-';
                });

            $grid->column('expired_at', admin_trans('lottery_ticket.fields.expired_at'))
                ->width(160)->align('center')
                ->display(function ($val) {
                    return $val ?: '-';
                });

            // 筛选器
            $grid->filter(function (Filter $filter) use ($departmentId) {
                $filter->like()->text('ticket_no')
                    ->placeholder(admin_trans('lottery_ticket.fields.ticket_no'));

                // 活动选择
                $activities = LotteryTicketActivity::where('department_id', $departmentId)
                    ->orderBy('created_at', 'desc')
                    ->get(['id', 'name'])
                    ->pluck('name', 'id')
                    ->toArray();

                $filter->eq()->select('activity_id')
                    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'))
                    ->options($activities);

                $filter->eq()->text('player_uuid')
                    ->placeholder(admin_trans('player.fields.device_uuid'));

                $filter->like()->text('player_name')
                    ->placeholder(admin_trans('player.fields.device_name'));

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

                $filter->form()->dateRange('created_at_start', 'created_at_end', admin_trans('lottery_ticket.filter.create_time_range'))
                    ->placeholder([
                        admin_trans('common.start_time'),
                        admin_trans('common.end_time')
                    ]);
            });

            // 代理后台只读
            $grid->hideDelete();
            $grid->hideSelection();

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
        });
    }
}
