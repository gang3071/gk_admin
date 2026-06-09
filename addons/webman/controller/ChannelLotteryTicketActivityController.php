<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\LotteryTicketPrizeLevel;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\VipLevel;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use support\Db;
use support\Request;

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

        $vipLevelsJson = json_encode($vipLevels);

        // 引入 Vue 组件
        return view('lottery_ticket_activities', [
            'department_id' => $departmentId,
            'vip_levels' => $vipLevelsJson,
        ]);
    }

    /**
     * 获取活动列表（API 接口）
     * @return \support\Response
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

        return response()->json([
            'code' => 200,
            'data' => $activities->toArray()
        ]);
    }

    /**
     * 获取活动详情
     * @return \support\Response
     */
    public function getActivityDetail()
    {
        $id = Request::input('id');
        $activity = LotteryTicketActivity::with(['prizeLevels', 'vipConfigs'])->find($id);

        if (!$activity) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.message.activity_not_found')
            ]);
        }

        // 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
        }

        return response()->json([
            'code' => 200,
            'data' => $activity->toArray()
        ]);
    }

    /**
     * 创建/编辑活动
     * @return \support\Response
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

            return response()->json([
                'code' => 200,
                'message' => admin_trans('lottery_ticket.message.create_success'),
                'data' => $activity->toArray()
            ]);

        } catch (\Exception $e) {
            Db::rollBack();
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage()
            ]);
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
                $actions->button(admin_trans('lottery_ticket.action.view'))
                    ->modal([$this, 'prizeConfig'], ['id' => $data['id']])
                    ->type('link')
                    ->size('small');

                $actions->hideEdit();
                $actions->hideDel();
            });

            // 禁用新增和批量删除
            $grid->hideAdd();
            $grid->hideBatchDel();
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
            $grid->hideAdd();
            $grid->hideBatchDel();
            $grid->hideEdit();
            $grid->hideDel();
        });
    }

    /**
     * 关闭活动
     * @return \support\Response
     */
    public function closeActivity()
    {
        $id = Request::input('id');
        $activity = LotteryTicketActivity::find($id);

        if (!$activity) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.message.activity_not_found')
            ]);
        }

        // 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
        }

        // 只能关闭进行中的活动
        if ($activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.message.activity_not_ongoing')
            ]);
        }

        $activity->status = LotteryTicketActivity::STATUS_CLOSED;
        $activity->save();

        // 结束所有打码进度记录
        \addons\webman\service\LotteryTicketBetProgressService::endActivityProgress($activity->id);

        return response()->json([
            'code' => 200,
            'message' => admin_trans('lottery_ticket.message.close_success')
        ]);
    }

    /**
     * 获取摸奖券发放列表
     * @return \support\Response
     */
    public function getTicketList()
    {
        $activityId = Request::input('activity_id');
        $page = Request::input('page', 1);
        $size = Request::input('size', 20);

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
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

        return response()->json([
            'code' => 200,
            'data' => [
                'list' => $formattedList,
                'total' => $total,
                'page' => $page,
                'size' => $size,
            ]
        ]);
    }

    /**
     * 录入中奖（按券号批量录入）
     * @return \support\Response
     */
    public function recordWinByTickets()
    {
        $data = Request::post();
        $activityId = $data['activity_id'] ?? null;
        $records = $data['records'] ?? [];

        if (!$activityId || empty($records)) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.invalid_params')
            ]);
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
        }

        // 只能在进行中的活动录入中奖
        if ($activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.activity_not_ongoing')
            ]);
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
                    $errors[] = "券号 {$ticketNo} 不存在或已使用";
                    continue;
                }

                // 查找奖品等级
                $prizeLevel = LotteryTicketPrizeLevel::find($prizeLevelId);
                if (!$prizeLevel) {
                    $errors[] = "券号 {$ticketNo} 的奖品等级不存在";
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

            return response()->json([
                'code' => 200,
                'message' => "成功录入 {$successCount} 条中奖记录",
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => count($errors),
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            Db::rollBack();
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 录入中奖（旧方法，按玩家录入，保留兼容）
     * @return \support\Response
     */
    public function recordWin()
    {
        $data = Request::post();
        $activityId = $data['activity_id'] ?? null;
        $playerAccount = $data['player_account'] ?? null;
        $prizeLevelId = $data['prize_level_id'] ?? null;
        $remark = $data['remark'] ?? '';

        if (!$activityId || !$playerAccount || !$prizeLevelId) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.invalid_params')
            ]);
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
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
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.error.player_not_found')
            ]);
        }

        // 查找奖品等级
        $prizeLevel = LotteryTicketPrizeLevel::find($prizeLevelId);
        if (!$prizeLevel || $prizeLevel->activity_id != $activityId) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.error.prize_level_not_found')
            ]);
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

            return response()->json([
                'code' => 200,
                'message' => admin_trans('lottery_ticket.message.record_success'),
                'data' => $record->toArray()
            ]);

        } catch (\Exception $e) {
            Db::rollBack();
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 更新直播地址
     * @return \support\Response
     */
    public function updateLiveUrl()
    {
        $id = Request::input('id');
        $liveUrl = Request::input('live_url');

        $activity = LotteryTicketActivity::find($id);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
        }

        $activity->live_url = $liveUrl;
        $activity->save();

        return response()->json([
            'code' => 200,
            'message' => admin_trans('lottery_ticket.message.live_url_updated')
        ]);
    }

    /**
     * 上传封面图片
     * @return \support\Response
     */
    public function uploadCover()
    {
        // TODO: 图片上传逻辑
        // 使用 ExAdmin 的图片上传功能
        return response()->json([
            'code' => 200,
            'data' => [
                'url' => 'https://example.com/image.jpg'
            ]
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
     * @return \support\Response
     */
    public function getWinners()
    {
        $activityId = Request::input('activity_id');
        $page = Request::input('page', 1);
        $size = Request::input('size', 50);

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
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

        return response()->json([
            'code' => 200,
            'data' => [
                'total_winners' => $total,
                'winners' => $winners,
                'page' => $page,
                'size' => $size,
                'total' => $total,
            ]
        ]);
    }

    /**
     * 获取玩家打码进度（客户端查询）
     * @return \support\Response
     */
    public function getBetProgress()
    {
        $activityId = Request::input('activity_id');
        $playerId = Request::input('player_id');

        if (!$activityId || !$playerId) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.invalid_params')
            ]);
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.message.activity_not_found')
            ]);
        }

        // 查询打码进度
        $progress = LotteryTicketBetProgress::where('activity_id', $activityId)
            ->where('player_id', $playerId)
            ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
            ->first();

        if (!$progress) {
            return response()->json([
                'code' => 404,
                'message' => '未找到打码进度记录'
            ]);
        }

        // 获取VIP等级名称
        $vipLevel = VipLevel::find($progress->vip_level_id);

        return response()->json([
            'code' => 200,
            'data' => [
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
            ]
        ]);
    }

    /**
     * 获取玩家的摸奖券列表（客户端查询）
     * @return \support\Response
     */
    public function getMyTickets()
    {
        $activityId = Request::input('activity_id');
        $playerId = Request::input('player_id');
        $status = Request::input('status'); // unused, used, expired

        if (!$activityId || !$playerId) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.invalid_params')
            ]);
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
                'expires_at' => $ticket->expires_at,
            ];
        });

        return response()->json([
            'code' => 200,
            'data' => [
                'total' => $formattedTickets->count(),
                'tickets' => $formattedTickets,
            ]
        ]);
    }

    /**
     * 获取玩家中奖结果（客户端查询）
     * @return \support\Response
     */
    public function getMyResult()
    {
        $activityId = Request::input('activity_id');
        $playerId = Request::input('player_id');

        if (!$activityId || !$playerId) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.invalid_params')
            ]);
        }

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.message.activity_not_found')
            ]);
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

        return response()->json([
            'code' => 200,
            'data' => [
                'activity_id' => $activityId,
                'activity_name' => $activity->name,
                'has_won' => $hasWon,
                'my_tickets_count' => $totalTickets,
                'winning_tickets_count' => $winRecords->count(),
                'losing_tickets_count' => $totalTickets - $winRecords->count(),
                'my_wins' => $myWins,
            ]
        ]);
    }

    /**
     * 开始直播
     * @return \support\Response
     */
    public function startLive()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
        }

        // 检查直播地址
        if (empty($activity->live_url)) {
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.live_url_required')
            ]);
        }

        // 更新直播状态
        $activity->live_status = LotteryTicketActivity::LIVE_STATUS_ONGOING;
        $activity->save();

        // 推送直播开始通知
        \addons\webman\service\LotteryTicketPushService::pushLiveStarted($activity);

        return response()->json([
            'code' => 200,
            'message' => admin_trans('lottery_ticket.message.live_started'),
            'data' => [
                'live_status' => $activity->live_status,
                'live_url' => $activity->live_url,
            ]
        ]);
    }

    /**
     * 结束直播
     * @return \support\Response
     */
    public function endLive()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
        }

        // 更新直播状态
        $activity->live_status = LotteryTicketActivity::LIVE_STATUS_ENDED;
        $activity->save();

        // TODO: 推送直播结束通知
        // \Webman\Push\Api::trigger('activity_' . $activityId, 'live_end', [
        //     'activity_id' => $activityId,
        // ]);

        return response()->json([
            'code' => 200,
            'message' => admin_trans('lottery_ticket.message.live_ended'),
            'data' => [
                'live_status' => $activity->live_status,
            ]
        ]);
    }

    /**
     * 获取直播信息（客户端查询）
     * @return \support\Response
     */
    public function getLiveInfo()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.message.activity_not_found')
            ]);
        }

        return response()->json([
            'code' => 200,
            'data' => [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'live_url' => $activity->live_url,
                'live_status' => $activity->live_status,
                'live_status_text' => LotteryTicketActivity::getLiveStatusText($activity->live_status),
                'has_live' => !empty($activity->live_url),
            ]
        ]);
    }

    /**
     * 更新活动状态（手动控制）
     * @return \support\Response
     */
    public function updateActivityStatus()
    {
        $activityId = Request::input('activity_id');
        $newStatus = Request::input('status');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
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
            return response()->json([
                'code' => 400,
                'message' => admin_trans('lottery_ticket.error.invalid_status')
            ]);
        }

        // 记录状态变更
        $activity->recordStatusChange($newStatus, '管理员手动更新');
        $activity->save();

        return response()->json([
            'code' => 200,
            'message' => admin_trans('lottery_ticket.message.status_updated'),
            'data' => [
                'status' => $activity->status,
                'status_text' => LotteryTicketActivity::getStatusText($activity->status),
            ]
        ]);
    }

    /**
     * 执行摇球开奖
     * @return \support\Response
     */
    public function performBallDraw()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity || $activity->department_id != Admin::user()->department_id) {
            return response()->json([
                'code' => 403,
                'message' => admin_trans('common.no_permission')
            ]);
        }

        // 执行摇球
        $result = \addons\webman\service\LotteryBallDrawService::performDraw($activityId);

        if (!$result['success']) {
            return response()->json([
                'code' => 400,
                'message' => $result['message']
            ]);
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

        return response()->json([
            'code' => 200,
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    /**
     * 获取摇球范围（用于前端展示）
     * @return \support\Response
     */
    public function getBallRanges()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.message.activity_not_found')
            ]);
        }

        $maxTicketNo = $activity->current_ticket_no > 0 ? $activity->current_ticket_no - 1 : 0;

        $ranges = \addons\webman\service\LotteryBallDrawService::getBallRanges($maxTicketNo);

        return response()->json([
            'code' => 200,
            'data' => $ranges
        ]);
    }

    /**
     * 获取摇球结果
     * @return \support\Response
     */
    public function getBallResult()
    {
        $activityId = Request::input('activity_id');

        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return response()->json([
                'code' => 404,
                'message' => admin_trans('lottery_ticket.message.activity_not_found')
            ]);
        }

        $ballResult = null;
        if (!empty($activity->ball_result)) {
            $ballResult = json_decode($activity->ball_result, true);
        }

        return response()->json([
            'code' => 200,
            'data' => [
                'has_drawn' => !empty($ballResult),
                'ball_result' => $ballResult,
                'activity_status' => $activity->status,
            ]
        ]);
    }
}
