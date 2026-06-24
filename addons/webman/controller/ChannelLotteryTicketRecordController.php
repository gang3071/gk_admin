<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketRecord;
use addons\webman\model\Player;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

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

            // 获取玩家表名（使用配置，默认为'player'）
            $playerTable = config('plugin.rockys.ex-admin-webman.database.player_table', 'player');

            // ⭐ 顶部统计数据
            $stats = self::getRecordStats($departmentId);

            // ⭐ 顶部统计卡片
            $layout = Card::create()->content([
                Row::create()
                    ->column(
                        Statistic::create()
                            ->title(admin_trans('lottery_ticket.stats.pending_count'))
                            ->value($stats['pending_count'])
                            ->valueStyle(['color' => '#ff9800']),
                        6
                    )
                    ->column(
                        Statistic::create()
                            ->title(admin_trans('lottery_ticket.stats.pending_amount'))
                            ->value(number_format($stats['pending_amount'], 2))
                            ->prefix('¥')
                            ->valueStyle(['color' => '#ff9800']),
                        6
                    )
                    ->column(
                        Statistic::create()
                            ->title(admin_trans('lottery_ticket.stats.claimed_count'))
                            ->value($stats['claimed_count'])
                            ->valueStyle(['color' => '#4caf50']),
                        6
                    )
                    ->column(
                        Statistic::create()
                            ->title(admin_trans('lottery_ticket.stats.claimed_amount'))
                            ->value(number_format($stats['claimed_amount'], 2))
                            ->prefix('¥')
                            ->valueStyle(['color' => '#4caf50']),
                        6
                    )
            ])->bodyStyle(['padding' => '20px']);

            $grid->header($layout);

            $grid->model()
                ->with([
                    'activity:id,name',  // 活动名称
                    'player:id,name,phone,uuid'  // 玩家信息
                ])
                ->where('department_id', $departmentId)
                ->orderBy('created_at', 'desc');

            // 列定义
            $grid->column('id', admin_trans('lottery_ticket.fields.id'))->width(80)->sortable();

            // ⭐ 使用Eloquent关系显示活动名称
            $grid->column('activity.name', admin_trans('lottery_ticket.fields.activity_name'))->width(180);

            // ⭐ 使用Eloquent关系显示玩家信息
            $grid->column('player.name', admin_trans('lottery_ticket.fields.player_name'))->width(120);

            $grid->column('player.uuid', admin_trans('lottery_ticket.fields.player_uuid'))
                ->width(150)->copyable();

            $grid->column('player.phone', admin_trans('lottery_ticket.fields.player_phone'))->width(130);

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

                return Tag::create($labels[$val] ?? admin_trans('lottery_ticket.record_status.unknown'))
                    ->color($colors[$val] ?? 'default');
            });

            $grid->column('created_at', admin_trans('lottery_ticket.fields.created_at'))->width(160);

            // 筛选
            $grid->filter(function (Filter $filter) use ($departmentId) {
                // 活动筛选
                $activities = LotteryTicketActivity::where('department_id', $departmentId)
                    ->orderBy('created_at', 'desc')
                    ->pluck('name', 'id')
                    ->toArray();

                $filter->eq()->select('activity_id')
                    ->placeholder(admin_trans('lottery_ticket.fields.name'))
                    ->options($activities);

                // 玩家名称筛选（使用关系）
                $filter->like()->text('player.name')
                    ->placeholder(admin_trans('lottery_ticket.fields.player_name'));

                // 玩家UUID筛选（使用关系）
                $filter->like()->text('player.uuid')
                    ->placeholder(admin_trans('lottery_ticket.fields.player_uuid'));

                // 玩家手机筛选（使用关系）
                $filter->like()->text('player.phone')
                    ->placeholder(admin_trans('lottery_ticket.fields.player_phone'));

                // 券号筛选
                $filter->like()->text('ticket_no')
                    ->placeholder(admin_trans('lottery_ticket.fields.ticket_no'));

                // 奖品类型筛选
                $filter->eq()->select('prize_type')
                    ->placeholder(admin_trans('lottery_ticket.fields.prize_type'))
                    ->options([
                        LotteryTicketRecord::PRIZE_TYPE_CASH => admin_trans('lottery_ticket.prize_type.cash'),
                        LotteryTicketRecord::PRIZE_TYPE_BONUS => admin_trans('lottery_ticket.prize_type.bonus'),
                        LotteryTicketRecord::PRIZE_TYPE_ITEM => admin_trans('lottery_ticket.prize_type.item'),
                        LotteryTicketRecord::PRIZE_TYPE_POINTS => admin_trans('lottery_ticket.prize_type.points'),
                        LotteryTicketRecord::PRIZE_TYPE_EMPTY => admin_trans('lottery_ticket.prize_type.empty'),
                    ]);

                // 发放状态筛选
                $filter->eq()->select('lottery_ticket_record.status')
                    ->placeholder(admin_trans('lottery_ticket.fields.record_status'))
                    ->options([
                        LotteryTicketRecord::STATUS_PENDING => admin_trans('lottery_ticket.record_status.pending'),
                        LotteryTicketRecord::STATUS_GRANTED => admin_trans('lottery_ticket.record_status.granted'),
                        LotteryTicketRecord::STATUS_FAILED => admin_trans('lottery_ticket.record_status.failed'),
                    ]);

                // 中奖时间范围筛选
                $filter->between()->dateTimeRange('created_at')
                    ->placeholder([
                        admin_trans('common.start_time'),
                        admin_trans('common.end_time')
                    ]);
            });

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                // 待发放的奖品可以手动发放
                if ($data['status'] == LotteryTicketRecord::STATUS_PENDING && $data['prize_type'] != LotteryTicketRecord::PRIZE_TYPE_EMPTY) {
                    $actions->prepend(
                        Button::create(admin_trans('lottery_ticket.action.distribute'))
                            ->type('primary')
                            ->confirm(admin_trans('lottery_ticket.confirm.distribute'))
                            ->ajax('distribute', ['id' => $data['id']])
                    );
                }

                $actions->hideEdit();
            });

            // ⭐ 工具栏按钮
            $grid->tools([
                Button::create(admin_trans('lottery_ticket.action.batch_distribute'))
                    ->modal([$this, 'batchDistributeForm'])
                    ->width('50%')
                    ->title(admin_trans('lottery_ticket.modal.batch_distribute_title'))
                    ->size('small'),
            ]);
            $grid->hideTrashed();
        });
    }

    /**
     * 发放奖励（单个）⭐ 核心方法
     * @auth true
     * @group channel
     * @return mixed
     */
    public function distribute()
    {
        $id = Request::input('id');
        $note = Request::input('distribution_note', '');
        $adminId = Admin::user()->id;

        // ⭐ 输入验证
        if (empty($id) || !is_numeric($id)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_record_id'));
        }

        if (strlen($note) > 255) {
            return message_error(admin_trans('lottery_ticket.error.note_too_long'));
        }

        \support\Db::beginTransaction();
        try {
            // 1. 锁定中奖记录
            $record = LotteryTicketRecord::where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                throw new \Exception(admin_trans('lottery_ticket.error.record_not_found'));
            }

            // 2. 检查权限
            if ($record->department_id != Admin::user()->department_id) {
                throw new \Exception(admin_trans('common.no_permission'));
            }

            // 3. 检查状态
            if ($record->status !== LotteryTicketRecord::STATUS_PENDING) {
                throw new \Exception(admin_trans('lottery_ticket.error.invalid_status'));
            }

            // 3.1 检查奖品类型（空奖不能发放）⭐
            if ($record->prize_type === LotteryTicketRecord::PRIZE_TYPE_EMPTY) {
                throw new \Exception(admin_trans('lottery_ticket.error.empty_prize'));
            }

            // 3.2 检查奖品金额（必须大于0）⭐
            if ($record->prize_amount <= 0) {
                throw new \Exception(admin_trans('lottery_ticket.error.invalid_amount'));
            }

            // 4. 更新状态为发放中
            $record->status = LotteryTicketRecord::STATUS_PROCESSING;
            $record->save();

            // 5. 转账到玩家账户
            $player = Player::query()->lockForUpdate()->find($record->player_id);
            if (!$player) {
                throw new \Exception(admin_trans('lottery_ticket.error.player_not_found'));
            }

            // 5.1 检查玩家状态 ⭐
            if (isset($player->status) && $player->status != Player::STATUS_ENABLE) {
                throw new \Exception(admin_trans('lottery_ticket.error.player_disabled'));
            }

            $oldBalance = $player->balance;
            $player->balance += $record->prize_amount;
            $player->save();

            // 6. 更新中奖记录状态
            $record->status = LotteryTicketRecord::STATUS_CLAIMED;
            $record->distributed_by = $adminId;
            $record->distributed_at = date('Y-m-d H:i:s');
            $record->distribution_note = $note;
            $record->save();

            // 7. 更新活动已发放金额（使用悲观锁）⭐
            $activity = LotteryTicketActivity::where('id', $record->activity_id)
                ->lockForUpdate()
                ->first();

            if (!$activity) {
                throw new \Exception(admin_trans('lottery_ticket.error.activity_not_found'));
            }

            // 7.1 检查活动状态（线下摇球，只需检查状态）⭐
            $allowedStatuses = [
                LotteryTicketActivity::STATUS_DRAWING,
                LotteryTicketActivity::STATUS_ENDED,
            ];
            if (!in_array($activity->status, $allowedStatuses)) {
                throw new \Exception(admin_trans('lottery_ticket.error.activity_not_in_drawing_status'));
            }

            // 7.2 检查是否超额发放 ⭐
            $newDistributedAmount = $activity->distributed_prize_amount + $record->prize_amount;
            if ($newDistributedAmount > $activity->total_prize_amount) {
                throw new \Exception(admin_trans('lottery_ticket.error.amount_exceeded'));
            }

            $activity->distributed_prize_amount = $newDistributedAmount;
            $activity->save();

            \support\Db::commit();

            // 8. 推送中奖通知（事务外）
            try {
                \addons\webman\service\LotteryTicketPushService::pushPrizeDistributed(
                    $record->player_id,
                    $activity,
                    $record->ticket_no,
                    $record->prize_name,
                    $record->prize_amount
                );
            } catch (\Exception $e) {
                \support\Log::warning('[摸奖券] 推送中奖通知失败', [
                    'record_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }

            // 9. 记录日志
            \support\Log::info('[摸奖券] 发放奖励成功', [
                'record_id' => $id,
                'player_id' => $player->id,
                'prize_amount' => $record->prize_amount,
                'old_balance' => $oldBalance,
                'new_balance' => $player->balance,
                'admin_id' => $adminId,
                'note' => $note
            ]);

            return message_success(admin_trans('lottery_ticket.message.distribute_success'));

        } catch (\Exception $e) {
            \support\Db::rollBack();

            // 如果记录存在且状态是发放中，标记为失败
            if (isset($record) && $record->status === LotteryTicketRecord::STATUS_PROCESSING) {
                try {
                    $record->status = LotteryTicketRecord::STATUS_FAILED;
                    $record->distribution_note = admin_trans('lottery_ticket.message.distribute_failed') . ': ' . $e->getMessage();
                    $record->save();
                } catch (\Exception $e2) {
                    // 忽略
                }
            }

            \support\Log::error('[摸奖券] 发放奖励失败', [
                'record_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return message_error(admin_trans('lottery_ticket.message.distribute_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 批量发放奖励 ⭐ 核心方法
     * @auth true
     * @group channel
     * @return mixed
     */
    public function batchDistribute()
    {
        $activityId = Request::input('activity_id');
        $recordIds = Request::input('ids', []);
        $note = Request::input('distribution_note', '批量发放');
        $adminId = Admin::user()->id;
        $departmentId = Admin::user()->department_id;

        // ⭐ 输入验证
        if ($activityId && !is_numeric($activityId)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_activity_id'));
        }

        if (!empty($recordIds) && !is_array($recordIds)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_record_ids'));
        }

        if (strlen($note) > 255) {
            return message_error(admin_trans('lottery_ticket.error.note_too_long'));
        }

        // 查询待发放的记录
        $query = LotteryTicketRecord::where('status', LotteryTicketRecord::STATUS_PENDING)
            ->where('department_id', $departmentId);

        if ($activityId) {
            $query->where('activity_id', $activityId);
        } elseif (!empty($recordIds)) {
            // ⭐ 验证数组元素都是数字
            foreach ($recordIds as $id) {
                if (!is_numeric($id)) {
                    return message_error(admin_trans('lottery_ticket.error.invalid_record_id_value'));
                }
            }
            $query->whereIn('id', $recordIds);
        } else {
            return message_error(admin_trans('lottery_ticket.error.no_selection'));
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return message_error(admin_trans('lottery_ticket.error.no_pending_records'));
        }

        $successCount = 0;
        $failCount = 0;
        $failReasons = [];

        // 逐条发放
        foreach ($records as $record) {
            \support\Db::beginTransaction();
            try {
                // 锁定记录
                $record = LotteryTicketRecord::where('id', $record->id)
                    ->lockForUpdate()
                    ->first();

                // 再次检查状态
                if ($record->status !== LotteryTicketRecord::STATUS_PENDING) {
                    throw new \Exception(admin_trans('lottery_ticket.error.status_changed'));
                }

                // 检查奖品类型和金额 ⭐
                if ($record->prize_type === LotteryTicketRecord::PRIZE_TYPE_EMPTY) {
                    throw new \Exception(admin_trans('lottery_ticket.error.empty_prize'));
                }
                if ($record->prize_amount <= 0) {
                    throw new \Exception(admin_trans('lottery_ticket.error.invalid_amount'));
                }

                // 转账
                $player = Player::lockForUpdate()->find($record->player_id);
                if (!$player) {
                    throw new \Exception(admin_trans('lottery_ticket.error.player_not_found'));
                }

                // 检查玩家状态 ⭐
                if (isset($player->status) && $player->status != Player::STATUS_ENABLE) {
                    throw new \Exception(admin_trans('lottery_ticket.error.player_disabled'));
                }

                $player->balance += $record->prize_amount;
                $player->save();

                // 更新记录
                $record->status = LotteryTicketRecord::STATUS_CLAIMED;
                $record->distributed_by = $adminId;
                $record->distributed_at = date('Y-m-d H:i:s');
                $record->distribution_note = $note;
                $record->save();

                // 更新活动统计（使用悲观锁）⭐
                $activity = LotteryTicketActivity::where('id', $record->activity_id)
                    ->lockForUpdate()
                    ->first();

                if (!$activity) {
                    throw new \Exception(admin_trans('lottery_ticket.error.activity_not_found'));
                }

                // 检查活动状态（线下摇球，只需检查状态）⭐
                $allowedStatuses = [
                    LotteryTicketActivity::STATUS_DRAWING,
                    LotteryTicketActivity::STATUS_ENDED,
                ];
                if (!in_array($activity->status, $allowedStatuses)) {
                    throw new \Exception(admin_trans('lottery_ticket.error.activity_not_in_drawing_status'));
                }

                // 检查是否超额发放 ⭐
                $newDistributedAmount = $activity->distributed_prize_amount + $record->prize_amount;
                if ($newDistributedAmount > $activity->total_prize_amount) {
                    throw new \Exception(admin_trans('lottery_ticket.error.amount_exceeded'));
                }

                $activity->distributed_prize_amount = $newDistributedAmount;
                $activity->save();

                \support\Db::commit();

                $successCount++;

                // 推送通知
                try {
                    \addons\webman\service\LotteryTicketPushService::pushPrizeDistributed(
                        $record->player_id,
                        $activity,
                        $record->ticket_no,
                        $record->prize_name,
                        $record->prize_amount
                    );
                } catch (\Exception $e) {
                    // 忽略推送失败
                }

            } catch (\Exception $e) {
                \support\Db::rollBack();
                $failCount++;
                $failReasons[] = "记录ID {$record->id}: " . $e->getMessage();
            }
        }

        // 日志
        \support\Log::info('[摸奖券] 批量发放完成', [
            'activity_id' => $activityId,
            'total' => count($records),
            'success' => $successCount,
            'fail' => $failCount,
            'admin_id' => $adminId
        ]);

        $message = admin_trans('lottery_ticket.message.batch_complete', null, [
            'success' => $successCount,
            'fail' => $failCount
        ]);

        if ($failCount > 0) {
            return \ExAdmin\ui\response\Response::success([
                'message' => $message,
                'fail_reasons' => $failReasons
            ]);
        }

        return message_success($message);
    }

    /**
     * 获取统计数据（静态方法）
     * @param int $departmentId
     * @return array
     */
    private static function getRecordStats(int $departmentId): array
    {
        // 待发放
        $pendingData = LotteryTicketRecord::where('department_id', $departmentId)
            ->where('status', LotteryTicketRecord::STATUS_PENDING)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(prize_amount), 0) as amount')
            ->first();

        // 已发放
        $claimedData = LotteryTicketRecord::where('department_id', $departmentId)
            ->where('status', LotteryTicketRecord::STATUS_CLAIMED)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(prize_amount), 0) as amount')
            ->first();

        return [
            'pending_count' => $pendingData->count ?? 0,
            'pending_amount' => $pendingData->amount ?? 0,
            'claimed_count' => $claimedData->count ?? 0,
            'claimed_amount' => $claimedData->amount ?? 0,
        ];
    }


    /**
     * 批量发放表单
     * @auth true
     * @group channel
     * @return mixed
     */
    public function batchDistributeForm()
    {
        return Form::create(new LotteryTicketRecord(), function ($form) {
            $departmentId = Admin::user()->department_id;

            // 活动选择（显示已结束的活动，这些活动已经开奖）
            $activities = LotteryTicketActivity::where('department_id', $departmentId)
                ->where('status', LotteryTicketActivity::STATUS_ENDED)
                ->orderBy('created_at', 'desc')
                ->pluck('name', 'id')
                ->toArray();

            $form->select('activity_id', admin_trans('lottery_ticket.form.select_activity'))
                ->options($activities)
                ->required()
                ->help(admin_trans('lottery_ticket.form.select_activity_help'));

            $form->textarea('distribution_note', admin_trans('lottery_ticket.form.distribution_note'))
                ->placeholder(admin_trans('lottery_ticket.form.distribution_note_placeholder'))
                ->maxlength(255)
                ->showCount();

            // 提交处理
            $form->saving(function ($form) {
                $activityId = $form->input('activity_id');
                $note = $form->input('distribution_note', '批量发放');

                // 调用批量发放接口
                $request = new Request();
                $request->_data = [
                    'activity_id' => $activityId,
                    'distribution_note' => $note
                ];

                return $this->batchDistribute($request);
            });
        });
    }

    /**
     * 批量发放选中记录
     * @auth true
     * @group channel
     * @return mixed
     */
    public function batchDistributeSelected()
    {
        $ids = Request::input('ids', []);

        if (empty($ids)) {
            return message_error(admin_trans('lottery_ticket.error.no_selection'));
        }

        // 调用批量发放接口
        // ⚠️ 注意：这里直接合并到当前请求参数中
        request()->_data = array_merge(request()->_data ?? [], [
            'ids' => $ids,
            'distribution_note' => admin_trans('lottery_ticket.message.batch_distribute_selected')
        ]);

        return $this->batchDistribute();
    }
}
