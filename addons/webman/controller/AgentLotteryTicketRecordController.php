<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketRecord;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 代理后台-摸奖券中奖记录管理
 * @group agent
 */
class AgentLotteryTicketRecordController
{
    /**
     * 中奖记录列表
     * @auth true
     * @group agent
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new LotteryTicketRecord(), function (Grid $grid) {
            // 获取当前代理管理员信息
            $admin = Admin::user();
            $departmentId = $admin->department_id;

            // 数据权限过滤：直接过滤 department_id
            $grid->model()->where('department_id', $departmentId);

            $grid->title(admin_trans('lottery_ticket.title.record_list'));
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
            if (!empty($requestFilter['prize_type'])) {
                $grid->model()->where('prize_type', $requestFilter['prize_type']);
            }
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }

            // 排序
            $grid->model()->orderBy('created_at', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))
                ->width(80)->align('center')->fixed(true);

            $grid->column('ticket_no', admin_trans('lottery_ticket.fields.ticket_no'))
                ->width(150)->align('center')->fixed(true);

            $grid->column('activity.activity_name', admin_trans('lottery_ticket.fields.activity_name'))
                ->width(200)->align('left');

            $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))
                ->width(150)->align('center');

            $grid->column('player.name', admin_trans('player.fields.device_name'))
                ->width(150)->align('center');

            $grid->column('prize_type', admin_trans('lottery_ticket.fields.prize_type'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    $typeMap = [
                        LotteryTicketRecord::PRIZE_TYPE_CASH => ['text' => admin_trans('lottery_ticket.prize_type.cash'), 'color' => 'success'],
                        LotteryTicketRecord::PRIZE_TYPE_BONUS => ['text' => admin_trans('lottery_ticket.prize_type.bonus'), 'color' => 'processing'],
                        LotteryTicketRecord::PRIZE_TYPE_ITEM => ['text' => admin_trans('lottery_ticket.prize_type.item'), 'color' => 'warning'],
                        LotteryTicketRecord::PRIZE_TYPE_POINTS => ['text' => admin_trans('lottery_ticket.prize_type.points'), 'color' => 'default'],
                        LotteryTicketRecord::PRIZE_TYPE_EMPTY => ['text' => admin_trans('lottery_ticket.prize_type.empty'), 'color' => 'default'],
                    ];
                    $config = $typeMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($config['text'])->color($config['color']);
                });

            $grid->column('prize_name', admin_trans('lottery_ticket.fields.prize_name'))
                ->width(150)->align('center');

            $grid->column('prize_amount', admin_trans('lottery_ticket.fields.prize_amount'))
                ->width(120)->align('center')
                ->display(function ($val) {
                    return $val > 0 ? number_format($val, 2) : '-';
                });

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    $statusMap = [
                        LotteryTicketRecord::STATUS_PENDING => ['text' => admin_trans('lottery_ticket.record_status.pending'), 'color' => 'warning'],
                        LotteryTicketRecord::STATUS_CLAIMED => ['text' => admin_trans('lottery_ticket.record_status.claimed'), 'color' => 'success'],
                        LotteryTicketRecord::STATUS_EXPIRED => ['text' => admin_trans('lottery_ticket.record_status.expired'), 'color' => 'error'],
                        LotteryTicketRecord::STATUS_CANCELLED => ['text' => admin_trans('lottery_ticket.record_status.cancelled'), 'color' => 'default'],
                        LotteryTicketRecord::STATUS_PROCESSING => ['text' => admin_trans('lottery_ticket.record_status.processing'), 'color' => 'processing'],
                        LotteryTicketRecord::STATUS_FAILED => ['text' => admin_trans('lottery_ticket.record_status.failed'), 'color' => 'error'],
                    ];
                    $config = $statusMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($config['text'])->color($config['color']);
                });

            $grid->column('created_at', admin_trans('lottery_ticket.fields.created_at'))
                ->width(160)->align('center');

            $grid->column('remark', admin_trans('lottery_ticket.fields.remark'))
                ->width(200)->align('left')
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
                    ->get(['id', 'activity_name'])
                    ->pluck('activity_name', 'id')
                    ->toArray();

                $filter->eq()->select('activity_id')
                    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'))
                    ->options($activities);

                $filter->eq()->text('player_uuid')
                    ->placeholder(admin_trans('player.fields.device_uuid'));

                $filter->like()->text('player_name')
                    ->placeholder(admin_trans('player.fields.device_name'));

                $filter->eq()->select('prize_type')
                    ->placeholder(admin_trans('lottery_ticket.fields.prize_type'))
                    ->options([
                        LotteryTicketRecord::PRIZE_TYPE_CASH => admin_trans('lottery_ticket.prize_type.cash'),
                        LotteryTicketRecord::PRIZE_TYPE_BONUS => admin_trans('lottery_ticket.prize_type.bonus'),
                        LotteryTicketRecord::PRIZE_TYPE_ITEM => admin_trans('lottery_ticket.prize_type.item'),
                        LotteryTicketRecord::PRIZE_TYPE_POINTS => admin_trans('lottery_ticket.prize_type.points'),
                        LotteryTicketRecord::PRIZE_TYPE_EMPTY => admin_trans('lottery_ticket.prize_type.empty'),
                    ]);

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('lottery_ticket.fields.status'))
                    ->options([
                        LotteryTicketRecord::STATUS_PENDING => admin_trans('lottery_ticket.record_status.pending'),
                        LotteryTicketRecord::STATUS_CLAIMED => admin_trans('lottery_ticket.record_status.claimed'),
                        LotteryTicketRecord::STATUS_EXPIRED => admin_trans('lottery_ticket.record_status.expired'),
                        LotteryTicketRecord::STATUS_CANCELLED => admin_trans('lottery_ticket.record_status.cancelled'),
                        LotteryTicketRecord::STATUS_PROCESSING => admin_trans('lottery_ticket.record_status.processing'),
                        LotteryTicketRecord::STATUS_FAILED => admin_trans('lottery_ticket.record_status.failed'),
                    ]);

                $filter->form()->dateRange('created_at_start', 'created_at_end', admin_trans('lottery_ticket.filter.create_time_range'))
                    ->placeholder([
                        admin_trans('common.start_time'),
                        admin_trans('common.end_time')
                    ]);
            });

            // 隐藏操作列和批量操作（代理后台只查看，不操作）
            $grid->hideActions();
            $grid->hideBatchActions();
            $grid->hideCreateButton();
        });
    }
}
