<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\LotteryTicketPrizeLevel;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\VipLevel;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 渠道后台-摸奖券活动管理
 * @group channel
 */
class ChannelLotteryTicketActivityController
{
    /**
     * 进行中的活动列表 - 使用 Vue 组件展示
     * @auth true
     * @group channel
     * @return mixed
     */
    public function index()
    {
        $departmentId = Admin::user()->department_id;

        // 获取 VIP 等级列表
        $vipLevels = VipLevel::where('department_id', $departmentId)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->toArray();

        // 准备完整的翻译数据
        $trans = [
            // 标题
            'ex_admin_title' => admin_trans('lottery_ticket.title.main'),

            // 操作
            'createActivity' => admin_trans('lottery_ticket.action.create'),
            'createFirst' => admin_trans('lottery_ticket.action.create_first'),
            'edit' => admin_trans('lottery_ticket.action.edit'),
            'editActivity' => admin_trans('lottery_ticket.action.edit'),
            'view' => admin_trans('lottery_ticket.action.view'),
            'viewDetail' => admin_trans('lottery_ticket.action.view_detail'),
            'prizeConfig' => admin_trans('lottery_ticket.action.prize_config'),
            'closeActivity' => admin_trans('lottery_ticket.action.close'),
            'addPrizeLevel' => admin_trans('lottery_ticket.action.add_prize_level'),
            'recordWin' => admin_trans('lottery_ticket.action.record_win'),
            'addLiveUrl' => admin_trans('lottery_ticket.action.add_live_url'),
            'expand' => admin_trans('lottery_ticket.action.expand'),
            'collapse' => admin_trans('lottery_ticket.action.collapse'),
            'distributeByTicket' => admin_trans('lottery_ticket.action.distribute_by_ticket'),
            'viewTicketList' => admin_trans('lottery_ticket.action.view_ticket_list'),
            'addTicket' => admin_trans('lottery_ticket.action.add_ticket'),
            'selectImage' => admin_trans('lottery_ticket.action.select_image'),
            'confirmDistribute' => admin_trans('lottery_ticket.action.confirm_distribute'),
            'refresh' => admin_trans('common.refresh'),
            'save' => admin_trans('common.save'),
            'cancel' => admin_trans('common.cancel'),
            'submit' => admin_trans('common.submit'),

            // 状态
            'allStatus' => admin_trans('lottery_ticket.status.all'),
            'notStarted' => admin_trans('lottery_ticket.status.not_started'),
            'ongoing' => admin_trans('lottery_ticket.status.ongoing'),
            'ended' => admin_trans('lottery_ticket.status.ended'),
            'closed' => admin_trans('lottery_ticket.status.closed'),

            // 字段
            'activityName' => admin_trans('lottery_ticket.fields.activity_name'),
            'description' => admin_trans('lottery_ticket.fields.description'),
            'coverImage' => admin_trans('lottery_ticket.fields.cover_image_upload'),
            'startTime' => admin_trans('lottery_ticket.fields.start_time'),
            'endTime' => admin_trans('lottery_ticket.fields.end_time'),
            'status' => admin_trans('lottery_ticket.fields.status'),
            'totalTickets' => admin_trans('lottery_ticket.fields.total_tickets'),
            'usedTickets' => admin_trans('lottery_ticket.fields.used_tickets'),
            'usageRate' => admin_trans('lottery_ticket.fields.usage_rate'),
            'prizeConfig' => admin_trans('lottery_ticket.fields.prize_config'),
            'prizeLevelConfig' => admin_trans('lottery_ticket.fields.prize_level_config'),
            'level' => admin_trans('lottery_ticket.fields.level'),
            'levelRank' => admin_trans('lottery_ticket.prize_level_fields.level_rank'),
            'levelName' => admin_trans('lottery_ticket.prize_level_fields.level_name'),
            'prizeAmount' => admin_trans('lottery_ticket.fields.prize_amount'),
            'prizeCount' => admin_trans('lottery_ticket.fields.prize_count'),
            'vipLevel' => admin_trans('lottery_ticket.fields.vip_level'),
            'betAmountRequired' => admin_trans('lottery_ticket.fields.bet_amount_required'),
            'ticketCount' => admin_trans('lottery_ticket.fields.ticket_count'),
            'ticketNo' => admin_trans('lottery_ticket.fields.ticket_no'),
            'ticketNoInput' => admin_trans('lottery_ticket.fields.ticket_no_input'),
            'playerName' => admin_trans('lottery_ticket.fields.player_name'),
            'source' => admin_trans('lottery_ticket.fields.source'),
            'createdAt' => admin_trans('lottery_ticket.fields.created_at'),
            'usedAt' => admin_trans('lottery_ticket.fields.used_at'),
            'distributionRemark' => admin_trans('lottery_ticket.fields.distribution_remark'),

            // 占位符
            'activityNamePlaceholder' => admin_trans('lottery_ticket.placeholder.name'),
            'descriptionPlaceholder' => admin_trans('lottery_ticket.placeholder.description'),
            'liveUrlPlaceholder' => admin_trans('lottery_ticket.placeholder.live_url'),
            'ticketNoPlaceholder' => admin_trans('lottery_ticket.placeholder.ticket_no'),
            'distributeRemarkPlaceholder' => admin_trans('lottery_ticket.placeholder.distribute_remark'),

            // 模态框
            'modalRecordWinTitle' => admin_trans('lottery_ticket.modal.record_win_title'),
            'modalLiveUrlTitle' => admin_trans('lottery_ticket.modal.live_url_title'),
            'modalLiveUrlPrompt' => admin_trans('lottery_ticket.modal.live_url_prompt'),
            'modalLiveUrlRequired' => admin_trans('lottery_ticket.modal.live_url_required'),
            'modalDistributeTitle' => admin_trans('lottery_ticket.modal.distribute_by_ticket_title'),
            'modalTicketListTitle' => admin_trans('lottery_ticket.modal.ticket_list_title'),

            // 消息
            'loading' => admin_trans('common.loading'),
            'noActivities' => admin_trans('lottery_ticket.message.no_activities'),
            'noPrizeConfig' => admin_trans('lottery_ticket.message.no_prize_config'),
            'noPrizeLevel' => admin_trans('lottery_ticket.message.no_prize_level'),
            'activityDetail' => admin_trans('lottery_ticket.title.activity_detail'),
            'liveUrlUpdated' => admin_trans('lottery_ticket.message.live_url_updated'),
            'imageUploadSuccess' => admin_trans('lottery_ticket.message.image_upload_success'),
            'imageUploadFailed' => admin_trans('lottery_ticket.message.image_upload_failed'),
            'fetchFailed' => admin_trans('lottery_ticket.message.fetch_failed'),
            'fetchDetailFailed' => admin_trans('lottery_ticket.message.fetch_detail_failed'),
            'closeActivityFailed' => admin_trans('lottery_ticket.message.close_activity_failed'),
            'minOneTicket' => admin_trans('lottery_ticket.message.min_one_ticket'),
            'recordSuccessCount' => admin_trans('lottery_ticket.message.record_success_count'),
            'pleaseInputTicket' => admin_trans('lottery_ticket.message.please_input_ticket'),
            'ticketMust6Digits' => admin_trans('lottery_ticket.message.ticket_must_6_digits'),
            'distributeHint' => admin_trans('lottery_ticket.message.distribute_hint'),

            // 帮助文本
            'coverImageHelp' => admin_trans('lottery_ticket.help.cover_image'),
            'coverAlt' => admin_trans('lottery_ticket.help.cover_alt'),
            'coverPreview' => admin_trans('lottery_ticket.help.cover_preview'),
            'vipConfigHint' => admin_trans('lottery_ticket.help.vip_config_hint'),
            'prizeConfigHint' => admin_trans('lottery_ticket.help.prize_config_hint'),
            'inputTicketNo' => admin_trans('lottery_ticket.help.input_ticket_no'),

            // 表单
            'vipConfigSection' => admin_trans('lottery_ticket.form.vip_config_section'),
            'prizeConfigSection' => admin_trans('lottery_ticket.form.prize_config_section'),
            'noVipData' => admin_trans('lottery_ticket.form.no_vip_data'),
            'noVipConfig' => admin_trans('lottery_ticket.form.no_vip_config'),

            // 验证
            'nameRequired' => admin_trans('lottery_ticket.validation.name_required'),
            'nameMaxLength' => admin_trans('lottery_ticket.validation.name_max_length'),
            'startTimeRequired' => admin_trans('lottery_ticket.validation.start_time_required'),
            'endTimeRequired' => admin_trans('lottery_ticket.validation.end_time_required'),
            'ticketNoRequired' => admin_trans('lottery_ticket.validation.ticket_no_required'),
            'imageFormatError' => admin_trans('lottery_ticket.validation.image_format_error'),
            'imageSizeError' => admin_trans('lottery_ticket.validation.image_size_error'),

            // UI
            'yuan' => admin_trans('lottery_ticket.ui.yuan'),
            'uploadFailed' => admin_trans('lottery_ticket.ui.upload_failed'),
        ];

        // 使用 admin_view 返回 Vue 组件
        return admin_view(plugin()->webman->getPath() . '/views/lottery_ticket_activities.vue')->attrs([
            'department_id' => $departmentId,
            'vip_levels' => $vipLevels,
            'trans' => $trans,
        ]);
    }

    /**
     * 获取活动列表（API 接口）
     * @return Msg|Response
     */
    public function getActivities()
    {
        $departmentId = Admin::user()->department_id;
        $status = Request::input('status', 'all'); // all, ongoing, ended

        $query = LotteryTicketActivity::where('department_id', $departmentId);

        // 状态筛选
        if ($status !== 'all') {
            if ($status === 'ongoing') {
                $query->where('status', LotteryTicketActivity::STATUS_ONGOING);
            } elseif ($status === 'ended') {
                $query->whereIn('status', [
                    LotteryTicketActivity::STATUS_ENDED,
                    LotteryTicketActivity::STATUS_CLOSED
                ]);
            }
        }

        $activities = $query->orderBy('created_at', 'desc')->get();

        // 添加 has_prize_config 字段
        $activities = $activities->map(function ($activity) {
            $activityArray = $activity->toArray();
            $activityArray['has_prize_config'] = LotteryTicketPrizeLevel::where('activity_id', $activity->id)
                ->exists();
            return $activityArray;
        });

        return Response::success($activities->toArray());
    }

    /**
     * 获取活动详情
     * @return Msg|Response
     */
    public function getActivityDetail()
    {
        $id = Request::input('id');
        $activity = LotteryTicketActivity::with(['prizeLevels', 'vipConfigs'])->find($id);

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        return Response::success($activity->toArray());
    }

    /**
     * 创建/编辑活动
     * @return Msg|Response
     */
    public function saveActivity()
    {
        $data = Request::post();
        $departmentId = Admin::user()->department_id;

        Db::beginTransaction();
        try {
            // 验证数据
            if (empty($data['name'])) {
                throw new \Exception(admin_trans('lottery_ticket.error.name_required'));
            }

            if (empty($data['start_time']) || empty($data['end_time'])) {
                throw new \Exception(admin_trans('lottery_ticket.error.time_required'));
            }

            if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
                throw new \Exception(admin_trans('lottery_ticket.error.invalid_time'));
            }

            // 验证奖品等级
            $prizeLevels = $data['prize_levels'] ?? [];
            if (empty($prizeLevels)) {
                throw new \Exception(admin_trans('lottery_ticket.error.no_prize_levels'));
            }

            if (count($prizeLevels) > 10) {
                throw new \Exception(admin_trans('lottery_ticket.error.too_many_levels', null, ['max' => 10]));
            }

            // 创建或更新活动
            if (!empty($data['id'])) {
                // 编辑
                $activity = LotteryTicketActivity::find($data['id']);
                if (!$activity || $activity->department_id != $departmentId) {
                    throw new \Exception(admin_trans('common.no_permission'));
                }

                // 只能编辑未开始的活动
                if ($activity->status != LotteryTicketActivity::STATUS_NOT_STARTED) {
                    throw new \Exception(admin_trans('lottery_ticket.error.cannot_edit_started'));
                }

                $activity->update([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'cover_image' => $data['cover_image'] ?? '',
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                ]);
            } else {
                // 创建
                $activity = LotteryTicketActivity::create([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'cover_image' => $data['cover_image'] ?? '',
                    'department_id' => $departmentId,
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'status' => $this->getActivityStatus($data['start_time'], $data['end_time']),
                ]);
            }

            // 保存 VIP 配置
            if (!empty($data['vip_configs'])) {
                // 删除旧配置
                LotteryTicketVipConfig::where('activity_id', $activity->id)->delete();

                // 插入新配置
                foreach ($data['vip_configs'] as $config) {
                    if ($config['bet_amount_required'] > 0 && $config['ticket_count'] > 0) {
                        LotteryTicketVipConfig::create([
                            'activity_id' => $activity->id,
                            'vip_level_id' => $config['vip_level_id'],
                            'bet_amount_required' => $config['bet_amount_required'],
                            'ticket_count' => $config['ticket_count'],
                            'status' => LotteryTicketVipConfig::STATUS_ENABLED,
                        ]);
                    }
                }
            }

            // 保存奖品等级
            // 删除旧等级
            LotteryTicketPrizeLevel::where('activity_id', $activity->id)->delete();

            // 插入新等级
            foreach ($prizeLevels as $level) {
                LotteryTicketPrizeLevel::create([
                    'activity_id' => $activity->id,
                    'level_rank' => $level['level_rank'],
                    'level_name' => $level['level_name'],
                    'prize_type' => 'cash', // 固定为现金
                    'prize_amount' => $level['prize_amount'],
                    'prize_count' => $level['prize_count'] ?? 0,
                ]);
            }

            Db::commit();

            return Response::success($activity->toArray());

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error($e->getMessage());
        }
    }

    /**
     * 历史活动列表
     * @auth true
     * @group channel
     * @return Grid
     */
    public function historyList(): Grid
    {
        return Grid::create(new LotteryTicketActivity(), function (Grid $grid) {
            $departmentId = Admin::user()->department_id;

            $grid->model()->where('department_id', $departmentId)
                ->whereIn('status', [
                    LotteryTicketActivity::STATUS_ENDED,
                    LotteryTicketActivity::STATUS_CLOSED
                ])
                ->orderBy('created_at', 'desc');

            $grid->title(admin_trans('lottery_ticket.menu.history'));
            $grid->autoHeight();

            $grid->column('id', 'ID')->width(80)->sortable();
            $grid->column('name', admin_trans('lottery_ticket.fields.activity_name'))->width(200);
            $grid->column('start_time', admin_trans('lottery_ticket.fields.start_time'))->width(160);
            $grid->column('end_time', admin_trans('lottery_ticket.fields.end_time'))->width(160);

            $grid->column('status', admin_trans('lottery_ticket.fields.status'))->width(120)->display(function ($val) {
                $colors = [
                    LotteryTicketActivity::STATUS_NOT_STARTED => 'blue',
                    LotteryTicketActivity::STATUS_ONGOING => 'green',
                    LotteryTicketActivity::STATUS_ENDED => 'orange',
                    LotteryTicketActivity::STATUS_CLOSED => 'red',
                ];
                $labels = [
                    LotteryTicketActivity::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
                    LotteryTicketActivity::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
                    LotteryTicketActivity::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
                    LotteryTicketActivity::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
                ];

                return Tag::create($labels[$val] ?? admin_trans('lottery_ticket.status.unknown'))
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
                // ✅ 正确用法：使用 prepend() 添加 Button
                $actions->prepend(
                    Button::create(admin_trans('lottery_ticket.action.view'))
                        ->type('link')
                        ->size('small')
                        ->modal([$this, 'prizeConfig'], ['id' => $data['id']])
                        ->width('80%')
                        ->title(admin_trans('lottery_ticket.action.prize_config'))
                );

                $actions->hideEdit();
                $actions->hideDel();
            });

            // 禁用新增和批量删除
            $grid->hideAdd();
            $grid->hideDeleteSelection();
        });
    }

    /**
     * 查看奖品配置
     * @return mixed
     */
    public function prizeConfig()
    {
        $id = Request::input('id');
        $activity = LotteryTicketActivity::with('prizeLevels')->find($id);

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // TODO: 返回奖品配置展示界面
        return Grid::create($activity->prizeLevels, function (Grid $grid) use ($activity) {
            $grid->title($activity->name . ' - ' . admin_trans('lottery_ticket.fields.prize_config'));
            $grid->column('level_rank', admin_trans('lottery_ticket.prize_level_fields.level_rank'));
            $grid->column('level_name', admin_trans('lottery_ticket.prize_level_fields.level_name'));
            $grid->column('prize_amount', admin_trans('lottery_ticket.prize_level_fields.prize_amount'));
            $grid->column('prize_count', admin_trans('lottery_ticket.prize_level_fields.prize_count'));

            // 禁用所有操作
            $grid->hideAdd();
            $grid->hideDeleteSelection();
            $grid->hideDelete();
            $grid->actions(function ($actions) {
                $actions->hideEdit();
            });
        });
    }

    /**
     * 关闭活动
     * @return Msg|Response
     */
    public function closeActivity()
    {
        $id = Request::input('id');
        $activity = LotteryTicketActivity::find($id);

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 只能关闭进行中的活动
        if ($activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_ongoing'));
        }

        $activity->status = LotteryTicketActivity::STATUS_CLOSED;
        $activity->save();

        // 结束所有打码进度记录
        \addons\webman\service\LotteryTicketBetProgressService::endActivityProgress($activity->id);

        return message_success(admin_trans('lottery_ticket.message.close_success'));
    }

    /**
     * 获取摸奖券发放列表
     * @return Msg|Response
     */
    public function getTicketList()
    {
        $activityId = Request::input('activity_id');
        $page = Request::input('page', 1);
        $size = Request::input('size', 20);

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        $query = LotteryTicket::where('activity_id', $activityId)
            ->with(['player:id,name,uuid,phone'])
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $list = $query->forPage($page, $size)->get();

        // 格式化数据
        $formattedList = $list->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'player_name' => $ticket->player->name ?? '-',
                'player_uuid' => $ticket->player->uuid ?? '-',
                'player_phone' => $ticket->player->phone ?? '-',
                'source' => $this->getSourceText($ticket->source),
                'status' => $this->getTicketStatusText($ticket->status),
                'status_color' => $this->getTicketStatusColor($ticket->status),
                'created_at' => $ticket->created_at,
            ];
        });

        return Response::success([
            'list' => $formattedList,
            'total' => $total,
            'page' => $page,
            'size' => $size,
        ]);
    }

    /**
     * 录入中奖（按券号批量录入）
     * @return Msg|Response
     */
    public function recordWinByTickets()
    {
        $data = Request::post();
        $activityId = $data['activity_id'] ?? null;
        $records = $data['records'] ?? [];

        if (!$activityId || empty($records)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 只能在进行中的活动录入中奖
        if ($activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return message_error(admin_trans('lottery_ticket.error.activity_not_ongoing'));
        }

        $successCount = 0;
        $errors = [];

        Db::beginTransaction();
        try {
            foreach ($records as $record) {
                $prizeLevelId = $record['prize_level_id'] ?? null;
                $ticketNo = $record['ticket_no'] ?? null;

                if (!$prizeLevelId || !$ticketNo) {
                    continue;
                }

                // 查找摸奖券
                $ticket = LotteryTicket::where('ticket_no', $ticketNo)
                    ->where('activity_id', $activityId)
                    ->where('status', LotteryTicket::STATUS_UNUSED)
                    ->first();

                if (!$ticket) {
                    $errors[] = admin_trans('lottery_ticket.error.ticket_not_found_or_used', null, ['ticket_no' => $ticketNo]);
                    continue;
                }

                // 查找奖品等级
                $prizeLevel = LotteryTicketPrizeLevel::find($prizeLevelId);
                if (!$prizeLevel) {
                    $errors[] = admin_trans('lottery_ticket.error.prize_level_not_found_for_ticket', null, ['ticket_no' => $ticketNo]);
                    continue;
                }

                // 创建中奖记录
                $record = \addons\webman\model\LotteryTicketRecord::create([
                    'activity_id' => $activityId,
                    'player_id' => $ticket->player_id,
                    'department_id' => $activity->department_id,
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticketNo,
                    'prize_type' => $prizeLevel->prize_type,
                    'prize_name' => $prizeLevel->level_name,
                    'prize_amount' => $prizeLevel->prize_amount,
                    'status' => \addons\webman\model\LotteryTicketRecord::STATUS_PENDING,
                ]);

                // 更新摸奖券状态为已使用
                $ticket->status = LotteryTicket::STATUS_USED;
                $ticket->save();

                // 发送中奖推送通知
                \addons\webman\service\LotteryTicketPushService::pushWinNotification($record);

                $successCount++;
            }

            Db::commit();

            return Response::success([
                'success_count' => $successCount,
                'error_count' => count($errors),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error($e->getMessage());
        }
    }

    /**
     * 录入中奖（旧方法，按玩家录入，保留兼容）
     * @return Msg|Response
     */
    public function recordWin()
    {
        $data = Request::post();
        $activityId = $data['activity_id'] ?? null;
        $playerAccount = $data['player_account'] ?? null;
        $prizeLevelId = $data['prize_level_id'] ?? null;
        $remark = $data['remark'] ?? '';

        if (!$activityId || !$playerAccount || !$prizeLevelId) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 查找玩家
        $player = \addons\webman\model\Player::where('department_id', $activity->department_id)
            ->where(function ($query) use ($playerAccount) {
                $query->where('name', $playerAccount)
                    ->orWhere('phone', $playerAccount)
                    ->orWhere('uuid', $playerAccount);
            })
            ->first();

        if (!$player) {
            return message_error(admin_trans('lottery_ticket.error.player_not_found'));
        }

        // 查找奖品等级
        $prizeLevel = LotteryTicketPrizeLevel::find($prizeLevelId);
        if (!$prizeLevel || $prizeLevel->activity_id != $activityId) {
            return message_error(admin_trans('lottery_ticket.error.prize_level_not_found'));
        }

        Db::beginTransaction();
        try {
            // 创建中奖记录
            $record = \addons\webman\model\LotteryTicketRecord::create([
                'activity_id' => $activityId,
                'player_id' => $player->id,
                'department_id' => $activity->department_id,
                'ticket_id' => 0,
                'ticket_no' => '',
                'prize_type' => $prizeLevel->prize_type,
                'prize_name' => $prizeLevel->level_name,
                'prize_amount' => $prizeLevel->prize_amount,
                'status' => \addons\webman\model\LotteryTicketRecord::STATUS_PENDING,
                'remark' => $remark,
            ]);

            Db::commit();

            return Response::success($record->toArray());

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error($e->getMessage());
        }
    }

    /**
     * 更新直播地址
     * @return Msg|Response
     */
    public function updateLiveUrl()
    {
        $id = Request::input('id');
        $liveUrl = Request::input('live_url');

        $activity = LotteryTicketActivity::find($id);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        $activity->live_url = $liveUrl;
        $activity->save();

        return message_success(admin_trans('lottery_ticket.message.live_url_updated'));
    }

    /**
     * 上传封面图片
     * @return Msg|Response
     */
    public function uploadCover()
    {
        // TODO: 图片上传逻辑
        // 使用 ExAdmin 的图片上传功能
        return Response::success([
            'url' => 'https://example.com/image.jpg'
        ]);
    }

    /**
     * 获取摸奖券状态文本
     */
    protected function getTicketStatusText($status)
    {
        $map = [
            LotteryTicket::STATUS_UNUSED => admin_trans('lottery_ticket.ticket_status.unused'),
            LotteryTicket::STATUS_USED => admin_trans('lottery_ticket.ticket_status.used'),
            LotteryTicket::STATUS_EXPIRED => admin_trans('lottery_ticket.ticket_status.expired'),
        ];
        return $map[$status] ?? admin_trans('lottery_ticket.ticket_status.unknown');
    }

    /**
     * 获取摸奖券状态颜色
     */
    protected function getTicketStatusColor($status)
    {
        $map = [
            LotteryTicket::STATUS_UNUSED => 'green',
            LotteryTicket::STATUS_USED => 'orange',
            LotteryTicket::STATUS_EXPIRED => 'red',
        ];
        return $map[$status] ?? 'default';
    }

    /**
     * 获取来源文本
     */
    protected function getSourceText($source)
    {
        $map = [
            'recharge' => admin_trans('lottery_ticket.source.recharge'),
            'activity' => admin_trans('lottery_ticket.source.activity'),
            'betting' => admin_trans('lottery_ticket.source.manual'),
            'manual' => admin_trans('lottery_ticket.source.manual'),
        ];
        return $map[$source] ?? admin_trans('lottery_ticket.source.unknown');
    }

    /**
     * 判断活动状态
     */
    protected function getActivityStatus($startTime, $endTime)
    {
        $now = date('Y-m-d H:i:s');

        if ($now < $startTime) {
            return LotteryTicketActivity::STATUS_NOT_STARTED;
        } elseif ($now >= $startTime && $now <= $endTime) {
            return LotteryTicketActivity::STATUS_ONGOING;
        } else {
            return LotteryTicketActivity::STATUS_ENDED;
        }
    }

    /**
     * 获取活动所有中奖结果（公开查询）
     * @return Msg|Response
     */
    public function getWinners()
    {
        $activityId = Request::input('activity_id');
        $page = Request::input('page', 1);
        $size = Request::input('size', 50);

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 查询所有中奖记录
        $query = \addons\webman\model\LotteryTicketRecord::where('activity_id', $activityId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $records = $query->forPage($page, $size)->get();

        // 格式化数据
        $winners = $records->map(function ($record) {
            return [
                'ticket_no' => $record->ticket_no,
                'prize_level' => $record->prize_name,
                'prize_type' => $record->prize_type,
                'prize_amount' => $record->prize_amount,
                'created_at' => $record->created_at,
            ];
        });

        return Response::success([
            'total_winners' => $total,
            'winners' => $winners,
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    /**
     * 获取玩家打码进度（客户端查询）
     * @return Msg|Response
     */
    public function getBetProgress()
    {
        $activityId = Request::input('activity_id');
        $playerId = Request::input('player_id');

        if (!$activityId || !$playerId) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 查询打码进度
        $progress = LotteryTicketBetProgress::where('activity_id', $activityId)
            ->where('player_id', $playerId)
            ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
            ->first();

        if (!$progress) {
            return message_error(admin_trans('lottery_ticket.error.bet_progress_not_found'));
        }

        // 获取VIP等级名称
        $vipLevel = VipLevel::find($progress->vip_level_id);

        return Response::success([
            'activity_id' => $progress->activity_id,
            'activity_name' => $activity->name,
            'player_id' => $progress->player_id,
            'vip_level' => $vipLevel->name ?? 'VIP' . $progress->vip_level_id,
            'bet_amount_required' => $progress->bet_amount_required,
            'current_bet_amount' => $progress->current_bet_amount,
            'progress_percent' => $progress->progress_percent,
            'remaining_bet_amount' => $progress->remaining_bet_amount,
            'cycles_completed' => $progress->cycles_completed,
            'total_tickets_issued' => $progress->total_tickets_issued,
            'ticket_count_per_cycle' => $progress->ticket_count_per_cycle,
            'status' => $progress->status,
            'updated_at' => $progress->updated_at,
        ]);
    }

    /**
     * 获取玩家的摸奖券列表（客户端查询）
     * @return Msg|Response
     */
    public function getMyTickets()
    {
        $activityId = Request::input('activity_id');
        $playerId = Request::input('player_id');
        $status = Request::input('status'); // unused, used, expired

        if (!$activityId || !$playerId) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        $query = LotteryTicket::where('activity_id', $activityId)
            ->where('player_id', $playerId);

        // 状态筛选
        if ($status !== null && $status !== '') {
            $statusMap = [
                'unused' => LotteryTicket::STATUS_UNUSED,
                'used' => LotteryTicket::STATUS_USED,
                'expired' => LotteryTicket::STATUS_EXPIRED,
            ];
            if (isset($statusMap[$status])) {
                $query->where('status', $statusMap[$status]);
            }
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        // 格式化数据
        $formattedTickets = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'status' => $ticket->status,
                'status_text' => $this->getTicketStatusText($ticket->status),
                'source' => $ticket->source,
                'source_text' => $this->getSourceText($ticket->source),
                'created_at' => $ticket->created_at,
                'expired_at' => $ticket->expired_at,  // ✅ 修正字段名
            ];
        });

        return Response::success([
            'total' => $formattedTickets->count(),
            'tickets' => $formattedTickets,
        ]);
    }

    /**
     * 获取玩家中奖结果（客户端查询）
     * @return Msg|Response
     */
    public function getMyResult()
    {
        $activityId = Request::input('activity_id');
        $playerId = Request::input('player_id');

        if (!$activityId || !$playerId) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 查询玩家的所有券
        $myTickets = LotteryTicket::where('activity_id', $activityId)
            ->where('player_id', $playerId)
            ->get();

        $totalTickets = $myTickets->count();

        // 查询玩家的中奖记录
        $winRecords = \addons\webman\model\LotteryTicketRecord::where('activity_id', $activityId)
            ->where('player_id', $playerId)
            ->get();

        $hasWon = $winRecords->count() > 0;

        // 格式化中奖详情
        $myWins = $winRecords->map(function ($record) {
            return [
                'ticket_no' => $record->ticket_no,
                'prize_level' => $record->prize_name,
                'prize_type' => $record->prize_type,
                'prize_amount' => $record->prize_amount,
                'status' => $record->status,
                'created_at' => $record->created_at,
            ];
        });

        return Response::success([
            'activity_id' => $activityId,
            'activity_name' => $activity->name,
            'has_won' => $hasWon,
            'my_tickets_count' => $totalTickets,
            'winning_tickets_count' => $winRecords->count(),
            'losing_tickets_count' => $totalTickets - $winRecords->count(),
            'my_wins' => $myWins,
        ]);
    }

    /**
     * 开始直播
     * @return Msg|Response
     */
    public function startLive()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 检查直播地址
        if (empty($activity->live_url)) {
            return message_error(admin_trans('lottery_ticket.error.live_url_required'));
        }

        // 更新直播状态
        $activity->live_status = LotteryTicketActivity::LIVE_STATUS_ONGOING;
        $activity->save();

        // 推送直播开始通知
        \addons\webman\service\LotteryTicketPushService::pushLiveStarted($activity);

        return Response::success([
            'live_status' => $activity->live_status,
            'live_url' => $activity->live_url,
        ]);
    }

    /**
     * 结束直播
     * @return Msg|Response
     */
    public function endLive()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 更新直播状态
        $activity->live_status = LotteryTicketActivity::LIVE_STATUS_ENDED;
        $activity->save();

        // TODO: 推送直播结束通知
        // \Webman\Push\Api::trigger('activity_' . $activityId, 'live_end', [
        //     'activity_id' => $activityId,
        // ]);

        return Response::success([
            'live_status' => $activity->live_status,
        ]);
    }

    /**
     * 获取直播信息（客户端查询）
     * @return Msg|Response
     */
    public function getLiveInfo()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        return Response::success([
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
            'live_url' => $activity->live_url,
            'live_status' => $activity->live_status,
            'live_status_text' => LotteryTicketActivity::getLiveStatusText($activity->live_status),
            'has_live' => !empty($activity->live_url),
        ]);
    }

    /**
     * 更新活动状态（手动控制）
     * @return Msg|Response
     */
    public function updateActivityStatus()
    {
        $activityId = Request::input('activity_id');
        $newStatus = Request::input('status');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 验证状态值
        $validStatuses = [
            LotteryTicketActivity::STATUS_NOT_STARTED,
            LotteryTicketActivity::STATUS_ONGOING,
            LotteryTicketActivity::STATUS_ENDED,
            LotteryTicketActivity::STATUS_CLOSED,
            LotteryTicketActivity::STATUS_PREHEATING,
            LotteryTicketActivity::STATUS_BETTING,
            LotteryTicketActivity::STATUS_DRAWING,
        ];

        if (!in_array($newStatus, $validStatuses)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_status'));
        }

        // 记录状态变更
        $activity->recordStatusChange($newStatus, admin_trans('lottery_ticket.message.admin_manual_update'));
        $activity->save();

        return Response::success([
            'status' => $activity->status,
            'status_text' => LotteryTicketActivity::getStatusText($activity->status),
        ]);
    }

    /**
     * 执行摇球开奖
     * @return Msg|Response
     */
    public function performBallDraw()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 执行摇球
        $result = \addons\webman\service\LotteryBallDrawService::performDraw($activityId);

        if (!$result['success']) {
            return message_error($result['message']);
        }

        // 推送中奖通知
        if (!empty($result['data']['winning_tickets'])) {
            foreach ($result['data']['winning_tickets'] as $winData) {
                try {
                    $record = \addons\webman\model\LotteryTicketRecord::where('ticket_no', $winData['ticket_no'])
                        ->where('activity_id', $activityId)
                        ->first();

                    if ($record) {
                        \addons\webman\service\LotteryTicketPushService::pushWinNotification($record);
                    }
                } catch (\Exception $e) {
                    \support\Log::warning('推送中奖通知失败', [
                        'ticket_no' => $winData['ticket_no'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return Response::success($result['data']);
    }

    /**
     * 获取摇球范围（用于前端展示）
     * @return Msg|Response
     */
    public function getBallRanges()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        $maxTicketNo = $activity->current_ticket_no > 0 ? $activity->current_ticket_no - 1 : 0;

        $ranges = \addons\webman\service\LotteryBallDrawService::getBallRanges($maxTicketNo);

        return Response::success($ranges);
    }

    /**
     * 获取摇球结果
     * @return Msg|Response
     */
    public function getBallResult()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        $ballResult = null;
        if (!empty($activity->ball_result)) {
            $ballResult = json_decode($activity->ball_result, true);
        }

        return Response::success([
            'has_drawn' => !empty($ballResult),
            'ball_result' => $ballResult,
            'activity_status' => $activity->status,
        ]);
    }

    /**
     * 录入券号发放奖励
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function distributeByTicketNo()
    {
        $activityId = Request::input('activity_id');
        $ticketNo = Request::input('ticket_no');
        $remark = Request::input('remark', '');

        // 验证参数
        if (!$activityId || !$ticketNo) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        // 验证活动
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
        }

        // 检查活动状态 - 只能在进行中的活动发放
        if ($activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return message_error(admin_trans('lottery_ticket.error.activity_not_ongoing'));
        }

        Db::beginTransaction();
        try {
            // 查找券
            $ticket = LotteryTicket::where('activity_id', $activityId)
                ->where('ticket_no', $ticketNo)
                ->first();

            if (!$ticket) {
                throw new \Exception(admin_trans('lottery_ticket.error.ticket_not_found_or_used', null, ['ticket_no' => $ticketNo]));
            }

            // 检查券状态
            if ($ticket->status != LotteryTicket::STATUS_UNUSED) {
                throw new \Exception(admin_trans('lottery_ticket.error.ticket_not_found_or_used', null, ['ticket_no' => $ticketNo]));
            }

            // 获取奖品等级
            $prizeLevel = LotteryTicketPrizeLevel::find($ticket->prize_level_id);
            if (!$prizeLevel) {
                throw new \Exception(admin_trans('lottery_ticket.error.prize_level_not_found_for_ticket', null, ['ticket_no' => $ticketNo]));
            }

            // 获取打码进度
            $betProgress = LotteryTicketBetProgress::where('ticket_id', $ticket->id)->first();
            if (!$betProgress) {
                throw new \Exception(admin_trans('lottery_ticket.error.bet_progress_not_found'));
            }

            // 获取玩家
            $player = \addons\webman\model\Player::find($ticket->player_id);
            if (!$player) {
                throw new \Exception(admin_trans('lottery_ticket.error.player_not_found'));
            }

            // 检查玩家状态
            if ($player->status != 1) {
                throw new \Exception(admin_trans('lottery_ticket.error.player_disabled'));
            }

            // 空奖不需要发放
            if ($prizeLevel->prize_type == LotteryTicketPrizeLevel::PRIZE_TYPE_EMPTY) {
                throw new \Exception(admin_trans('lottery_ticket.error.empty_prize'));
            }

            // 发放奖励（根据奖品类型）
            if ($prizeLevel->prize_type == LotteryTicketPrizeLevel::PRIZE_TYPE_CASH) {
                // 现金奖励 - 增加玩家余额
                $player->money += $prizeLevel->prize_amount;
                $player->save();

                // 记录资金变动
                \addons\webman\model\PlayerMoneyLog::create([
                    'player_id' => $player->id,
                    'department_id' => $player->department_id,
                    'type' => \addons\webman\model\PlayerMoneyLog::TYPE_LOTTERY_REWARD,
                    'money' => $prizeLevel->prize_amount,
                    'before_money' => $player->money - $prizeLevel->prize_amount,
                    'after_money' => $player->money,
                    'remark' => '摸奖券中奖发放：' . $activity->name . ' - ' . $prizeLevel->level_name . ($remark ? '（' . $remark . '）' : ''),
                    'created_at' => time(),
                ]);
            } elseif ($prizeLevel->prize_type == LotteryTicketPrizeLevel::PRIZE_TYPE_BONUS) {
                // 红利奖励 - 增加玩家红利
                $player->bonus += $prizeLevel->prize_amount;
                $player->save();

                // 记录红利变动
                \addons\webman\model\PlayerBonusLog::create([
                    'player_id' => $player->id,
                    'department_id' => $player->department_id,
                    'type' => \addons\webman\model\PlayerBonusLog::TYPE_LOTTERY_REWARD,
                    'bonus' => $prizeLevel->prize_amount,
                    'before_bonus' => $player->bonus - $prizeLevel->prize_amount,
                    'after_bonus' => $player->bonus,
                    'remark' => '摸奖券中奖发放：' . $activity->name . ' - ' . $prizeLevel->level_name . ($remark ? '（' . $remark . '）' : ''),
                    'created_at' => time(),
                ]);
            }

            // 更新券状态为已使用
            $ticket->status = LotteryTicket::STATUS_USED;
            $ticket->used_at = time();
            $ticket->save();

            // 更新打码进度状态为已发放
            $betProgress->prize_distributed = 1;
            $betProgress->distributed_at = time();
            $betProgress->distribution_remark = $remark ?: '管理员手动发放';
            $betProgress->save();

            Db::commit();

            return Response::success([
                'ticket_no' => $ticketNo,
                'player_name' => $player->name,
                'prize_level' => $prizeLevel->level_name,
                'prize_amount' => $prizeLevel->prize_amount,
                'prize_type' => $prizeLevel->prize_type,
            ], admin_trans('lottery_ticket.message.distribute_success'));

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error($e->getMessage());
        }
    }
}
