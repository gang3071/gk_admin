<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketPrizeLevel;
use addons\webman\model\LotteryTicketRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 代理后台-摸奖券活动管理
 * @group agent
 */
class AgentLotteryTicketActivityController
{
    /**
     * 摸奖券活动列表
     * @auth true
     * @group agent
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
            // 获取当前代理管理员信息
            $admin = Admin::user();
            $departmentId = $admin->department_id;

            // 数据权限过滤：只显示当前代理所属渠道的活动（代理查看整个渠道的活动）
            $grid->model()->where('department_id', $departmentId);

            $grid->title(admin_trans('lottery_ticket.title.main'));
            $grid->bordered(true);
            $grid->autoHeight();

            // 获取筛选条件
            $requestFilter = Request::input('ex_admin_filter', []);

            // 筛选条件
            if (!empty($requestFilter['activity_name'])) {
                $grid->model()->where('name', 'like', '%' . $requestFilter['activity_name'] . '%');
            }
            if (isset($requestFilter['status']) && $requestFilter['status'] !== '') {
                $grid->model()->where('status', $requestFilter['status']);
            }
            if (!empty($requestFilter['start_time'])) {
                $grid->model()->where('start_time', '>=', $requestFilter['start_time']);
            }
            if (!empty($requestFilter['end_time'])) {
                $grid->model()->where('end_time', '<=', $requestFilter['end_time']);
            }

            // 排序
            $grid->model()->orderBy('created_at', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))
                ->width(80)->align('center')->fixed(true);

            $grid->column('name', admin_trans('lottery_ticket.fields.activity_name'))
                ->width(200)->align('left')->fixed(true);

            $grid->column('start_time', admin_trans('lottery_ticket.fields.start_time'))
                ->width(160)->align('center');

            $grid->column('end_time', admin_trans('lottery_ticket.fields.end_time'))
                ->width(160)->align('center');

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    $statusMap = [
                        LotteryTicketActivity::STATUS_NOT_STARTED => ['text' => admin_trans('lottery_ticket.status.not_started'), 'color' => 'default'],
                        LotteryTicketActivity::STATUS_ONGOING => ['text' => admin_trans('lottery_ticket.status.ongoing'), 'color' => 'processing'],
                        LotteryTicketActivity::STATUS_ENDED => ['text' => admin_trans('lottery_ticket.status.ended'), 'color' => 'success'],
                        LotteryTicketActivity::STATUS_CLOSED => ['text' => admin_trans('lottery_ticket.status.closed'), 'color' => 'error'],
                    ];
                    $config = $statusMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($config['text'])->color($config['color']);
                });

            // ⭐ 最大券号 - 方便店家抽奖时知道放多少球的号码
            $grid->column('max_ticket_no', admin_trans('lottery_ticket.fields.max_ticket_no'))
                ->width(120)->align('center')
                ->display(function ($val, LotteryTicketActivity $data) {
                    // 查询当前活动的最大券号（从 lottery_ticket 表中查询）
                    $maxTicket = \addons\webman\model\LotteryTicket::where('activity_id', $data->id)
                        ->orderBy('ticket_no', 'desc')
                        ->value('ticket_no');

                    if ($maxTicket) {
                        return Tag::create($maxTicket)->color('blue');
                    } else {
                        return Tag::create('000000')->color('default');
                    }
                });

            // ⭐ 待发放奖励数 - 统计有实际奖金的待发放中奖记录
            $grid->column('pending_count', admin_trans('lottery_ticket.fields.pending_count'))
                ->width(100)->align('center')
                ->display(function ($val, LotteryTicketActivity $data) {
                    // 只统计有奖金的待发放记录（排除未中奖和0元奖）
                    $count = LotteryTicketRecord::where('activity_id', $data->id)
                        ->where('status', LotteryTicketRecord::STATUS_PENDING)
                        ->where('prize_type', '!=', LotteryTicketRecord::PRIZE_TYPE_EMPTY)
                        ->where('prize_amount', '>', 0)
                        ->count();
                    return $count > 0 ? Tag::create($count)->color('warning') : Tag::create('0')->color('success');
                });

            $grid->column('created_at', admin_trans('lottery_ticket.fields.created_at'))
                ->width(160)->align('center');

            // 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('activity_name')
                    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('lottery_ticket.fields.status'))
                    ->options([
                        LotteryTicketActivity::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
                        LotteryTicketActivity::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
                        LotteryTicketActivity::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
                        LotteryTicketActivity::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
                    ]);

                $filter->form()->dateRange('start_time', 'end_time', admin_trans('lottery_ticket.filter.time_range'))
                    ->placeholder([
                        admin_trans('lottery_ticket.fields.start_time'),
                        admin_trans('lottery_ticket.fields.end_time')
                    ]);
            });

            // 代理后台只读：隐藏删除和选择
            $grid->hideDelete();
            $grid->hideSelection();

            // 操作栏 - 只显示查看按钮
            $grid->actions(function (Actions $actions, LotteryTicketActivity $data) {
                $actions->prepend(
                    Button::create(admin_trans('lottery_ticket.action.view_detail'))
                        ->type('link')
                        ->size('small')
                        ->drawer([$this, 'getActivityDetail'], ['id' => $data->id])
                );

                $actions->hideEdit();
                $actions->hideDel();
            });
        });
    }

    /**
     * 获取活动详情（API接口）
     * @auth true
     * @group agent
     * @return mixed
     */
    public function getActivityDetail()
    {
        $id = Request::input('id');
        $activity = LotteryTicketActivity::with(['prizeLevels'])->find($id);

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        return \ExAdmin\ui\response\Response::success($activity->toArray());
    }

    /**
     * 查看奖品配置（已废弃，保留向后兼容）
     * @auth true
     * @group agent
     * @param int $activity_id
     * @return Grid
     */
    public function prizeConfig(int $activity_id): Grid
    {
        // 验证活动是否属于当前代理
        $admin = Admin::user();
        $activity = LotteryTicketActivity::where('id', $activity_id)
            ->where('department_id', $admin->department_id)
            ->first();

        if (!$activity) {
            throw new \Exception(admin_trans('common.no_permission'));
        }

        return Grid::create(new LotteryTicketPrizeLevel(), function (Grid $grid) use ($activity) {
            $grid->model()->where('activity_id', $activity->id)
                ->orderBy('level_rank', 'asc');

            $grid->title(admin_trans('lottery_ticket.fields.prize_level_config'));
            $grid->bordered(true);
            $grid->autoHeight();

            // 代理后台只读
            $grid->hideDelete();
            $grid->hideSelection();

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });

            // 列定义
            $grid->column('level_rank', admin_trans('lottery_ticket.prize_level_fields.level_rank'))
                ->width(100)->align('center');

            $grid->column('level_name', admin_trans('lottery_ticket.prize_level_fields.level_name'))
                ->width(150)->align('center');

            $grid->column('prize_amount', admin_trans('lottery_ticket.fields.prize_amount'))
                ->width(120)->align('center')
                ->display(function ($val) {
                    return number_format($val, 2);
                });

            $grid->column('prize_count', admin_trans('lottery_ticket.fields.prize_count'))
                ->width(100)->align('center');

            $grid->column('won_count', admin_trans('lottery_ticket.prize_level_fields.won_count'))
                ->width(100)->align('center')
                ->display(function ($val) {
                    return $val > 0 ? Tag::create($val)->color('success') : $val;
                });

            $grid->column('remaining_count', admin_trans('lottery_ticket.prize_level_fields.remaining_count'))
                ->width(120)->align('center')
                ->display(function ($val, LotteryTicketPrizeLevel $data) {
                    $remaining = $data->prize_count - $data->won_count;
                    if ($remaining <= 0) {
                        return Tag::create('0')->color('error');
                    } elseif ($remaining <= 3) {
                        return Tag::create($remaining)->color('warning');
                    } else {
                        return Tag::create($remaining)->color('success');
                    }
                });
        });
    }
}
