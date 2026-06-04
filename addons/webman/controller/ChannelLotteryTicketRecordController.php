<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketRecord;
use addons\webman\model\Player;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use support\Request;

/**
 * 渠道后台-摸奖券中奖记录
 * @group channel
 */
class ChannelLotteryTicketRecordController
{
    /**
     * 中奖记录列表
     * @auth true
     * @group channel
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new LotteryTicketRecord(), function (Grid $grid) {
            $grid->title(admin_trans('lottery_ticket.menu.records'));

            // 只显示当前渠道的记录
            $departmentId = Admin::user()->department_id;
            $grid->model()
                ->select([
                    'lottery_ticket_records.*',
                    'players.name as player_name',
                    'players.phone as player_phone',
                    'lottery_ticket_activities.activity_name'
                ])
                ->leftJoin('players', 'lottery_ticket_records.player_id', '=', 'players.id')
                ->leftJoin('lottery_ticket_activities', 'lottery_ticket_records.activity_id', '=', 'lottery_ticket_activities.id')
                ->where('lottery_ticket_records.department_id', $departmentId)
                ->orderBy('lottery_ticket_records.draw_time', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))->width(80)->sortable();

            $grid->column('activity_name', admin_trans('lottery_ticket.fields.activity_name'))->width(180);

            $grid->column('player_name', admin_trans('lottery_ticket.fields.player_name'))->width(120);

            $grid->column('player_phone', admin_trans('lottery_ticket.fields.player_phone'))->width(130);

            $grid->column('ticket_no', admin_trans('lottery_ticket.fields.ticket_no'))->width(160);

            $grid->column('prize_type', admin_trans('lottery_ticket.fields.prize_type'))->width(100)->display(function ($val) {
                $types = [
                    LotteryTicketRecord::PRIZE_TYPE_CASH => admin_trans('lottery_ticket.prize_type.cash'),
                    LotteryTicketRecord::PRIZE_TYPE_BONUS => admin_trans('lottery_ticket.prize_type.bonus'),
                    LotteryTicketRecord::PRIZE_TYPE_ITEM => admin_trans('lottery_ticket.prize_type.item'),
                    LotteryTicketRecord::PRIZE_TYPE_POINTS => admin_trans('lottery_ticket.prize_type.points'),
                    LotteryTicketRecord::PRIZE_TYPE_EMPTY => admin_trans('lottery_ticket.prize_type.empty'),
                ];
                return $types[$val] ?? admin_trans('lottery_ticket.prize_type.unknown');
            });

            $grid->column('prize_name', admin_trans('lottery_ticket.fields.prize_name'))->width(150);

            $grid->column('prize_amount', admin_trans('lottery_ticket.fields.prize_amount'))->width(120)->display(function ($val, $data) {
                if ($data['prize_type'] == LotteryTicketRecord::PRIZE_TYPE_EMPTY) {
                    return '-';
                }
                return $val > 0 ? number_format($val, 2) : '-';
            });

            $grid->column('status', admin_trans('lottery_ticket.fields.record_status'))->width(120)->display(function ($val) {
                $colors = [
                    LotteryTicketRecord::STATUS_PENDING => 'orange',
                    LotteryTicketRecord::STATUS_GRANTED => 'green',
                    LotteryTicketRecord::STATUS_FAILED => 'red',
                ];
                $labels = [
                    LotteryTicketRecord::STATUS_PENDING => admin_trans('lottery_ticket.record_status.pending'),
                    LotteryTicketRecord::STATUS_GRANTED => admin_trans('lottery_ticket.record_status.granted'),
                    LotteryTicketRecord::STATUS_FAILED => admin_trans('lottery_ticket.record_status.failed'),
                ];

                return \ExAdmin\ui\component\common\Tag::create($labels[$val] ?? admin_trans('lottery_ticket.record_status.unknown'))
                    ->color($colors[$val] ?? 'default');
            });

            $grid->column('draw_time', admin_trans('lottery_ticket.fields.draw_time'))->width(160);

            // 筛选
            $grid->filter(function (Filter $filter) use ($departmentId) {
                // 活动筛选
                $activities = LotteryTicketActivity::where('department_id', $departmentId)
                    ->orderBy('created_at', 'desc')
                    ->pluck('activity_name', 'id')
                    ->toArray();

                $filter->eq()->select('lottery_ticket_records.activity_id')
                    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'))
                    ->options($activities);

                // 玩家名称筛选
                $filter->like()->text('players.name')
                    ->placeholder(admin_trans('lottery_ticket.fields.player_name'));

                // 玩家手机筛选
                $filter->like()->text('players.phone')
                    ->placeholder(admin_trans('lottery_ticket.fields.player_phone'));

                // 奖品类型筛选
                $filter->eq()->select('lottery_ticket_records.prize_type')
                    ->placeholder(admin_trans('lottery_ticket.fields.prize_type'))
                    ->options([
                        LotteryTicketRecord::PRIZE_TYPE_CASH => admin_trans('lottery_ticket.prize_type.cash'),
                        LotteryTicketRecord::PRIZE_TYPE_BONUS => admin_trans('lottery_ticket.prize_type.bonus'),
                        LotteryTicketRecord::PRIZE_TYPE_ITEM => admin_trans('lottery_ticket.prize_type.item'),
                        LotteryTicketRecord::PRIZE_TYPE_POINTS => admin_trans('lottery_ticket.prize_type.points'),
                        LotteryTicketRecord::PRIZE_TYPE_EMPTY => admin_trans('lottery_ticket.prize_type.empty'),
                    ]);

                // 发放状态筛选
                $filter->eq()->select('lottery_ticket_records.status')
                    ->placeholder(admin_trans('lottery_ticket.fields.record_status'))
                    ->options([
                        LotteryTicketRecord::STATUS_PENDING => admin_trans('lottery_ticket.record_status.pending'),
                        LotteryTicketRecord::STATUS_GRANTED => admin_trans('lottery_ticket.record_status.granted'),
                        LotteryTicketRecord::STATUS_FAILED => admin_trans('lottery_ticket.record_status.failed'),
                    ]);

                // 时间范围筛选
                $filter->form()->dateRange('start_time', 'end_time', admin_trans('lottery_ticket.fields.draw_time'))
                    ->placeholder([
                        admin_trans('common.start_time'),
                        admin_trans('common.end_time')
                    ]);
            });

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                // 待发放的奖品可以手动发放
                if ($data['status'] == LotteryTicketRecord::STATUS_PENDING && $data['prize_type'] != LotteryTicketRecord::PRIZE_TYPE_EMPTY) {
                    $actions->button(admin_trans('lottery_ticket.action.grant'))
                        ->confirm('确认发放此奖品？')
                        ->ajax(admin_url([$this, 'grantPrize']), ['id' => $data['id']])
                        ->type('primary')
                        ->size('small');
                }

                $actions->hideEdit();
                $actions->hideDel();
            });

            // 导出按钮
            $grid->tools([
                \ExAdmin\ui\component\common\Button::create(admin_trans('lottery_ticket.action.export'))
                    ->ajax(admin_url([$this, 'exportRecords']))
                    ->type('default')
                    ->size('small')
            ]);

            $grid->hideSelection();
            $grid->hideTrashed();
        });
    }

    /**
     * 发放奖品
     * @auth true
     * @group channel
     * @param Request $request
     * @return mixed
     */
    public function grantPrize(Request $request)
    {
        $id = $request->input('id');
        $record = LotteryTicketRecord::find($id);

        if (!$record) {
            return message_error('记录不存在');
        }

        // 检查是否属于当前渠道
        if ($record->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 检查状态
        if ($record->status != LotteryTicketRecord::STATUS_PENDING) {
            return message_error('该记录已处理');
        }

        // 检查是否是未中奖
        if ($record->prize_type == LotteryTicketRecord::PRIZE_TYPE_EMPTY) {
            return message_error('未中奖无需发放');
        }

        try {
            \support\Db::beginTransaction();

            // 根据奖品类型发放
            $player = Player::find($record->player_id);
            if (!$player) {
                throw new \Exception('玩家不存在');
            }

            switch ($record->prize_type) {
                case LotteryTicketRecord::PRIZE_TYPE_CASH:
                    // 发放现金到玩家余额
                    $player->money += $record->prize_amount;
                    $player->save();

                    // 记录账变
                    // TODO: 调用账变记录方法
                    break;

                case LotteryTicketRecord::PRIZE_TYPE_BONUS:
                    // 发放红利
                    $player->bonus += $record->prize_amount;
                    $player->save();
                    break;

                case LotteryTicketRecord::PRIZE_TYPE_POINTS:
                    // 发放积分
                    $player->points += $record->prize_amount;
                    $player->save();
                    break;

                case LotteryTicketRecord::PRIZE_TYPE_ITEM:
                    // 实物奖品需要后续人工处理
                    // 这里只标记为已发放
                    break;
            }

            // 更新记录状态
            $record->status = LotteryTicketRecord::STATUS_GRANTED;
            $record->save();

            \support\Db::commit();
            return message_success('奖品发放成功');
        } catch (\Exception $e) {
            \support\Db::rollBack();
            return message_error('发放失败：' . $e->getMessage());
        }
    }

    /**
     * 导出记录
     * @auth true
     * @group channel
     * @param Request $request
     * @return mixed
     */
    public function exportRecords(Request $request)
    {
        // TODO: 实现导出功能
        return message_success('导出功能开发中');
    }
}
