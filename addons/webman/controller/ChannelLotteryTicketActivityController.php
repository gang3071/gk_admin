<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketPrizeLevel;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use support\Db;
use support\Request;

/**
 * 渠道后台-摸奖券活动管理
 * @group channel
 */
class ChannelLotteryTicketActivityController
{
    /**
     * 进行中的活动列表
     * @auth true
     * @group channel
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
            $grid->title(admin_trans('lottery_ticket.menu.dashboard'));

            // 只显示当前渠道的活动
            $departmentId = Admin::user()->department_id;
            $grid->model()->where('department_id', $departmentId)
                ->whereIn('status', [
                    LotteryTicketActivity::STATUS_NOT_STARTED,
                    LotteryTicketActivity::STATUS_ONGOING
                ])
                ->orderBy('created_at', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))->width(80)->sortable();
            $grid->column('name', admin_trans('lottery_ticket.fields.name'))->width(200);
            $grid->column('start_time', admin_trans('lottery_ticket.fields.start_time'))->width(160);
            $grid->column('end_time', admin_trans('lottery_ticket.fields.end_time'))->width(160);

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))->width(120)->display(function ($val) {
                $colors = [
                    LotteryTicketActivity::STATUS_NOT_STARTED => 'blue',
                    LotteryTicketActivity::STATUS_ONGOING => 'green',
                    LotteryTicketActivity::STATUS_ENDED => 'gray',
                    LotteryTicketActivity::STATUS_CLOSED => 'red',
                ];
                $labels = [
                    LotteryTicketActivity::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
                    LotteryTicketActivity::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
                    LotteryTicketActivity::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
                    LotteryTicketActivity::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
                ];

                return \ExAdmin\ui\component\common\Tag::create($labels[$val] ?? admin_trans('lottery_ticket.status.unknown'))
                    ->color($colors[$val] ?? 'default');
            });

            $grid->column('total_tickets', admin_trans('lottery_ticket.fields.total_tickets'))->width(120);
            $grid->column('used_tickets', admin_trans('lottery_ticket.fields.used_tickets'))->width(120);
            $grid->column('usage_rate', admin_trans('lottery_ticket.fields.usage_rate'))->width(120)->display(function ($val, $data) {
                if ($data['total_tickets'] > 0) {
                    $rate = round(($data['used_tickets'] / $data['total_tickets']) * 100, 2);
                    return $rate . '%';
                }
                return '0%';
            });

            $grid->column('created_at', admin_trans('lottery_ticket.fields.created_at'))->width(160);

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')
                    ->placeholder(admin_trans('lottery_ticket.fields.name'));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('lottery_ticket.fields.status'))
                    ->options([
                        LotteryTicketActivity::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
                        LotteryTicketActivity::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
                    ]);
            });

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                // 奖品配置按钮
                $actions->button(admin_trans('lottery_ticket.action.view'))
                    ->modal([$this, 'prizeConfig'], ['id' => $data['id']])
                    ->type('primary')
                    ->size('small');

                // 关闭活动按钮（仅进行中的活动可关闭）
                if ($data['status'] == LotteryTicketActivity::STATUS_ONGOING) {
                    $actions->button(admin_trans('lottery_ticket.action.close'))
                        ->confirm(admin_trans('lottery_ticket.message.close_confirm'))
                        ->ajax(admin_url([$this, 'closeActivity']), ['id' => $data['id']])
                        ->type('danger')
                        ->size('small');
                }
            });

            // 表单配置
            $grid->setForm()->drawer($this->form());

            // 隐藏批量删除
            $grid->hideSelection();
            $grid->hideTrashed();
        });
    }

    /**
     * 活动表单
     * @auth true
     * @group channel
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new LotteryTicketActivity(), function (Form $form) {
            $form->title(admin_trans('lottery_ticket.title'));

            $form->text('name', admin_trans('lottery_ticket.fields.name'))
                ->required()
                ->maxlength(100);

            $form->textarea('description', admin_trans('lottery_ticket.fields.description'))
                ->maxlength(500)
                ->showCount();

            $form->datetime('start_time', admin_trans('lottery_ticket.fields.start_time'))
                ->required();

            $form->datetime('end_time', admin_trans('lottery_ticket.fields.end_time'))
                ->required();

            // 隐藏字段
            $form->hidden('department_id')->default(Admin::user()->department_id);
            $form->hidden('status')->default(LotteryTicketActivity::STATUS_NOT_STARTED);

            $form->saving(function (Form $form) {
                // 验证时间
                $startTime = strtotime($form->input('start_time'));
                $endTime = strtotime($form->input('end_time'));

                if ($endTime <= $startTime) {
                    return message_error('结束时间必须大于开始时间');
                }

                // 自动设置状态
                $now = time();
                $status = LotteryTicketActivity::STATUS_NOT_STARTED;

                if ($now < $startTime) {
                    $status = LotteryTicketActivity::STATUS_NOT_STARTED;
                } elseif ($now >= $startTime && $now <= $endTime) {
                    $status = LotteryTicketActivity::STATUS_ONGOING;
                } else {
                    $status = LotteryTicketActivity::STATUS_ENDED;
                }

                // 设置到模型属性，而不是 $form->data
                if ($form->isEdit()) {
                    $form->model()->status = $status;
                } else {
                    $form->input('status', $status);
                }

                return true;
            });

            $form->saved(function (Form $form) {
                return message_success(admin_trans('lottery_ticket.message.create_success'));
            });
        });
    }

    /**
     * 历史活动记录
     * @auth true
     * @group channel
     * @return Grid
     */
    public function historyList(): Grid
    {
        return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
            $grid->title(admin_trans('lottery_ticket.menu.history'));

            // 只显示当前渠道的已结束/已关闭活动
            $departmentId = Admin::user()->department_id;
            $grid->model()->where('department_id', $departmentId)
                ->whereIn('status', [
                    LotteryTicketActivity::STATUS_ENDED,
                    LotteryTicketActivity::STATUS_CLOSED
                ])
                ->orderBy('end_time', 'desc');

            // 列定义（与index相同）
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))->width(80)->sortable();
            $grid->column('name', admin_trans('lottery_ticket.fields.name'))->width(200);
            $grid->column('start_time', admin_trans('lottery_ticket.fields.start_time'))->width(160);
            $grid->column('end_time', admin_trans('lottery_ticket.fields.end_time'))->width(160);

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))->width(120)->display(function ($val) {
                $colors = [
                    LotteryTicketActivity::STATUS_ENDED => 'gray',
                    LotteryTicketActivity::STATUS_CLOSED => 'red',
                ];
                $labels = [
                    LotteryTicketActivity::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
                    LotteryTicketActivity::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
                ];

                return \ExAdmin\ui\component\common\Tag::create($labels[$val] ?? admin_trans('lottery_ticket.status.unknown'))
                    ->color($colors[$val] ?? 'default');
            });

            $grid->column('total_tickets', admin_trans('lottery_ticket.fields.total_tickets'))->width(120);
            $grid->column('used_tickets', admin_trans('lottery_ticket.fields.used_tickets'))->width(120);
            $grid->column('usage_rate', admin_trans('lottery_ticket.fields.usage_rate'))->width(120)->display(function ($val, $data) {
                if ($data['total_tickets'] > 0) {
                    $rate = round(($data['used_tickets'] / $data['total_tickets']) * 100, 2);
                    return $rate . '%';
                }
                return '0%';
            });

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                $actions->button(admin_trans('lottery_ticket.action.view'))
                    ->modal([$this, 'prizeConfig'], ['id' => $data['id']])
                    ->type('link')
                    ->size('small');

                $actions->hideEdit();
                $actions->hideDel();
            });

            // 禁用新增和批量删除
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->tools([]);
        });
    }

    /**
     * 奖品配置
     * @auth true
     * @group channel
     * @param Request $request
     * @return mixed
     */
    public function prizeConfig(Request $request)
    {
        $id = $request->input('id');
        $activity = LotteryTicketActivity::find($id);

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 检查是否属于当前渠道
        if ($activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        return Form::create(new LotteryTicketPrizeLevel(), function (Form $form) use ($activity) {
            $form->title(admin_trans('lottery_ticket.fields.prize_config') . ' - ' . $activity->name);

            // 获取现有奖品等级
            $existingLevels = LotteryTicketPrizeLevel::where('activity_id', $activity->id)
                ->orderBy('level_rank')
                ->get()
                ->toArray();

            // 使用table组件配置奖品等级
            $form->table('prize_levels', admin_trans('lottery_ticket.fields.prize_config'))
                ->columns([
                    ['title' => admin_trans('lottery_ticket.prize_level_fields.level_rank'), 'dataIndex' => 'level_rank', 'width' => 80],
                    ['title' => admin_trans('lottery_ticket.prize_level_fields.level_name'), 'dataIndex' => 'level_name', 'width' => 120],
                    ['title' => admin_trans('lottery_ticket.prize_level_fields.prize_type'), 'dataIndex' => 'prize_type', 'width' => 100],
                    ['title' => admin_trans('lottery_ticket.prize_level_fields.prize_amount'), 'dataIndex' => 'prize_amount', 'width' => 120],
                    ['title' => admin_trans('lottery_ticket.prize_level_fields.prize_count'), 'dataIndex' => 'prize_count', 'width' => 100],
                    ['title' => admin_trans('lottery_ticket.prize_level_fields.win_probability'), 'dataIndex' => 'win_probability', 'width' => 120],
                ])
                ->default($existingLevels)
                ->help('最多可配置10个奖品等级，中奖概率总和不能超过100%');

            $form->saving(function (Form $form) use ($activity) {
                $prizeLevels = $form->input('prize_levels', []);

                if (empty($prizeLevels)) {
                    return message_error(admin_trans('lottery_ticket.error.no_prize_levels'));
                }

                if (count($prizeLevels) > 10) {
                    return message_error(admin_trans('lottery_ticket.error.too_many_levels', null, ['max' => 10]));
                }

                // 验证概率总和
                $totalProbability = array_sum(array_column($prizeLevels, 'win_probability'));
                if ($totalProbability > 100) {
                    return message_error(admin_trans('lottery_ticket.error.probability_exceed', null, ['total' => $totalProbability]));
                }

                Db::beginTransaction();
                try {
                    // 删除旧的奖品等级
                    LotteryTicketPrizeLevel::where('activity_id', $activity->id)->delete();

                    // 创建新的奖品等级
                    foreach ($prizeLevels as $index => $level) {
                        LotteryTicketPrizeLevel::create([
                            'activity_id' => $activity->id,
                            'level_rank' => $level['level_rank'] ?? ($index + 1),
                            'level_name' => $level['level_name'],
                            'prize_type' => $level['prize_type'],
                            'prize_amount' => $level['prize_amount'] ?? 0,
                            'prize_item_name' => $level['prize_item_name'] ?? '',
                            'prize_count' => $level['prize_count'] ?? 0,
                            'win_probability' => $level['win_probability'] ?? 0,
                        ]);
                    }

                    Db::commit();
                    return message_success(admin_trans('lottery_ticket.message.prize_level_saved'));
                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error($e->getMessage());
                }
            });
        });
    }

    /**
     * 关闭活动
     * @auth true
     * @group channel
     * @param Request $request
     * @return mixed
     */
    public function closeActivity(Request $request)
    {
        $id = $request->input('id');
        $activity = LotteryTicketActivity::find($id);

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 检查是否属于当前渠道
        if ($activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 只能关闭进行中的活动
        if ($activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return message_error(admin_trans('lottery_ticket.message.activity_closed'));
        }

        $activity->status = LotteryTicketActivity::STATUS_CLOSED;
        $activity->save();

        return message_success(admin_trans('lottery_ticket.message.close_success'));
    }
}
