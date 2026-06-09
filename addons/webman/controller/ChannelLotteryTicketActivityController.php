<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketPrizeLevel;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\VipLevel;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
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

        // 获取当前渠道的VIP等级列表
        $vipLevels = VipLevel::query()
            ->where(function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId)
                    ->orWhere('department_id', 0); // 包含全局等级
            })
            ->where('status', VipLevel::STATUS_ENABLED)
            ->orderBy('sort', 'asc')
            ->get(['id', 'name', 'sort'])
            ->toArray();

        // 使用自定义 Vue 组件展示活动面板
        return admin_view(plugin()->webman->getPath() . '/views/lottery_ticket_activities.vue')->attrs([
            'department_id' => $departmentId,
            'vip_levels' => $vipLevels,
            'trans' => [
                'createActivity' => admin_trans('lottery_ticket.action.create'),
                'refresh' => admin_trans('common.refresh'),
                'loading' => admin_trans('common.loading'),
                'noActivities' => admin_trans('lottery_ticket.message.no_activities'),
                'createFirst' => admin_trans('lottery_ticket.action.create_first'),
                'viewDetail' => admin_trans('lottery_ticket.action.view_detail'),
                'prizeConfig' => admin_trans('lottery_ticket.action.prize_config'),
                'edit' => admin_trans('common.edit'),
                'closeActivity' => admin_trans('lottery_ticket.action.close'),
                'activityName' => admin_trans('lottery_ticket.fields.name'),
                'activityNamePlaceholder' => admin_trans('lottery_ticket.placeholder.name'),
                'description' => admin_trans('lottery_ticket.fields.description'),
                'descriptionPlaceholder' => admin_trans('lottery_ticket.placeholder.description'),
                'startTime' => admin_trans('lottery_ticket.fields.start_time'),
                'endTime' => admin_trans('lottery_ticket.fields.end_time'),
                'selectStartTime' => admin_trans('lottery_ticket.placeholder.start_time'),
                'selectEndTime' => admin_trans('lottery_ticket.placeholder.end_time'),
                'totalTickets' => admin_trans('lottery_ticket.fields.total_tickets'),
                'usedTickets' => admin_trans('lottery_ticket.fields.used_tickets'),
                'usageRate' => admin_trans('lottery_ticket.fields.usage_rate'),
                'noPrizeConfig' => admin_trans('lottery_ticket.message.no_prize_config'),
                'editActivity' => admin_trans('lottery_ticket.action.edit'),
                'cancel' => admin_trans('common.cancel'),
                'submit' => admin_trans('common.submit'),
                'prizeLevelConfig' => admin_trans('lottery_ticket.fields.prize_level_config'),
                'prizeLevelHint' => admin_trans('lottery_ticket.message.prize_level_hint'),
                'addPrizeLevel' => admin_trans('lottery_ticket.action.add_prize_level'),
                'level' => admin_trans('lottery_ticket.fields.level'),
                'levelRank' => admin_trans('lottery_ticket.prize_level_fields.level_rank'),
                'selectLevelRank' => admin_trans('lottery_ticket.placeholder.level_rank'),
                'prizeType' => admin_trans('lottery_ticket.prize_level_fields.prize_type'),
                'selectPrizeType' => admin_trans('lottery_ticket.placeholder.prize_type'),
                'prizeTypeCash' => admin_trans('lottery_ticket.prize_type.cash'),
                'prizeTypeBonus' => admin_trans('lottery_ticket.prize_type.bonus'),
                'prizeTypePoints' => admin_trans('lottery_ticket.prize_type.points'),
                'prizeTypeItem' => admin_trans('lottery_ticket.prize_type.item'),
                'prizeAmount' => admin_trans('lottery_ticket.prize_level_fields.prize_amount'),
                'itemName' => admin_trans('lottery_ticket.prize_level_fields.prize_item_name'),
                'prizeCount' => admin_trans('lottery_ticket.prize_level_fields.prize_count'),
                'winProbability' => admin_trans('lottery_ticket.prize_level_fields.win_probability'),
                'probabilityExceed' => admin_trans('lottery_ticket.error.probability_exceed'),
                'totalProbability' => admin_trans('lottery_ticket.fields.total_probability'),
                'activityDetail' => admin_trans('lottery_ticket.title.activity_detail'),
                'timeRange' => admin_trans('lottery_ticket.fields.time_range'),
                'status' => admin_trans('lottery_ticket.fields.status'),
                'allStatus' => admin_trans('lottery_ticket.status.all'),
                'notStarted' => admin_trans('lottery_ticket.status.not_started'),
                'ongoing' => admin_trans('lottery_ticket.status.ongoing'),
                'ended' => admin_trans('lottery_ticket.status.ended'),
                'closed' => admin_trans('lottery_ticket.status.closed'),
            ],
        ]);
    }

    /**
     * 获取活动列表 (API)
     * @auth true
     * @group channel
     * @return mixed
     */
    public function getActivities()
    {
        $statusFilter = request()->input('status');
        $departmentId = Admin::user()->department_id;

        $query = LotteryTicketActivity::query()
            ->with(['prizeLevels'])
            ->where('department_id', $departmentId);

        // 状态筛选
        if ($statusFilter !== null && $statusFilter !== 'all' && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }
        // 如果是'all'或者空，显示所有活动（不添加状态筛选）

        $activities = $query->orderBy('created_at', 'desc')->get();

        // 为每个活动添加是否配置了奖品的标记
        $activities->each(function ($activity) {
            $activity->has_prize_config = $activity->prizeLevels->count() > 0;
        });

        return jsonSuccessResponse('success', $activities->toArray());
    }

    /**
     * 获取活动详情 (API)
     * @auth true
     * @group channel
     * @return mixed
     */
    public function getActivityDetail()
    {
        $id = request()->input('id');
        $departmentId = Admin::user()->department_id;

        $activity = LotteryTicketActivity::query()
            ->with(['prizeLevels', 'vipConfigs'])
            ->where('id', $id)
            ->where('department_id', $departmentId)
            ->first();

        if (!$activity) {
            return jsonFailResponse(admin_trans('lottery_ticket.message.activity_not_found'), [], 404);
        }

        // 格式化奖品等级数据(仅现金奖励)
        $activity->prize_levels = $activity->prizeLevels->map(function ($level) {
            return [
                'id' => $level->id,
                'level_rank' => $level->level_rank,
                'level_name' => $level->level_name,
                'prize_amount' => $level->prize_amount,
            ];
        })->toArray();

        // 格式化VIP等级配置数据
        $activity->vip_configs = $activity->vipConfigs->map(function ($config) {
            return [
                'vip_level_id' => $config->vip_level_id,
                'bet_amount_required' => $config->bet_amount_required,
                'ticket_count' => $config->ticket_count,
            ];
        })->toArray();

        return jsonSuccessResponse('success', $activity->toArray());
    }

    /**
     * 保存活动 (API)
     * @auth true
     * @group channel
     * @return mixed
     */
    public function saveActivity()
    {
        $departmentId = Admin::user()->department_id;
        $id = request()->input('id');
        $name = request()->input('name');
        $description = request()->input('description', '');
        $coverImage = request()->input('cover_image', '');
        $startTime = request()->input('start_time');
        $endTime = request()->input('end_time');
        $prizeLevels = request()->input('prize_levels', []);

        // 验证必填字段
        if (empty($name)) {
            return jsonFailResponse(admin_trans('lottery_ticket.error.name_required'), [], 400);
        }

        if (empty($startTime) || empty($endTime)) {
            return jsonFailResponse(admin_trans('lottery_ticket.error.time_required'), [], 400);
        }

        // 验证时间
        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);

        if ($endTimestamp <= $startTimestamp) {
            return jsonFailResponse(admin_trans('lottery_ticket.error.invalid_time'), [], 400);
        }

        // 验证奖品等级
        if (!empty($prizeLevels)) {
            if (count($prizeLevels) > 10) {
                return jsonFailResponse(admin_trans('lottery_ticket.error.too_many_levels', null, ['max' => 10]), [], 400);
            }
        }

        // 自动设置状态
        $now = time();
        $status = LotteryTicketActivity::STATUS_NOT_STARTED;

        if ($now < $startTimestamp) {
            $status = LotteryTicketActivity::STATUS_NOT_STARTED;
        } elseif ($now >= $startTimestamp && $now <= $endTimestamp) {
            $status = LotteryTicketActivity::STATUS_ONGOING;
        } else {
            $status = LotteryTicketActivity::STATUS_ENDED;
        }

        Db::beginTransaction();
        try {
            if ($id) {
                // 编辑模式
                $activity = LotteryTicketActivity::query()
                    ->where('id', $id)
                    ->where('department_id', $departmentId)
                    ->first();

                if (!$activity) {
                    Db::rollBack();
                    return jsonFailResponse(admin_trans('lottery_ticket.message.activity_not_found'), [], 404);
                }

                // 只允许编辑未开始的活动
                if ($activity->status !== LotteryTicketActivity::STATUS_NOT_STARTED) {
                    Db::rollBack();
                    return jsonFailResponse(admin_trans('lottery_ticket.error.cannot_edit_started'), [], 400);
                }

                $activity->update([
                    'name' => $name,
                    'description' => $description,
                    'cover_image' => $coverImage,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $status,
                ]);
            } else {
                // 创建模式
                $activity = LotteryTicketActivity::create([
                    'department_id' => $departmentId,
                    'name' => $name,
                    'description' => $description,
                    'cover_image' => $coverImage,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $status,
                    'total_tickets' => 0,
                    'used_tickets' => 0,
                ]);
            }

            // 保存奖品等级(只支持现金奖励)
            if (!empty($prizeLevels)) {
                // 删除旧的奖品等级
                LotteryTicketPrizeLevel::where('activity_id', $activity->id)->delete();

                // 创建新的奖品等级
                foreach ($prizeLevels as $index => $level) {
                    LotteryTicketPrizeLevel::create([
                        'activity_id' => $activity->id,
                        'level_rank' => $level['level_rank'] ?? ($index + 1),
                        'level_name' => $this->getLevelName($level['level_rank'] ?? ($index + 1)),
                        'prize_amount' => $level['prize_amount'] ?? 0,
                        'sort_order' => $index + 1,
                        'status' => LotteryTicketPrizeLevel::STATUS_ENABLED,
                    ]);
                }
            }

            // 保存VIP等级配置
            $vipConfigs = request()->input('vip_configs', []);
            if (!empty($vipConfigs)) {
                // 删除旧的VIP配置
                LotteryTicketVipConfig::where('activity_id', $activity->id)->delete();

                // 创建新的VIP配置
                foreach ($vipConfigs as $config) {
                    if (isset($config['vip_level_id']) && isset($config['bet_amount_required']) && isset($config['ticket_count'])) {
                        LotteryTicketVipConfig::create([
                            'activity_id' => $activity->id,
                            'vip_level_id' => $config['vip_level_id'],
                            'bet_amount_required' => $config['bet_amount_required'] ?? 0,
                            'ticket_count' => $config['ticket_count'] ?? 1,
                            'status' => LotteryTicketVipConfig::STATUS_ENABLED,
                        ]);
                    }
                }
            }

            Db::commit();

            $message = $id ? admin_trans('lottery_ticket.message.update_success') : admin_trans('lottery_ticket.message.create_success');
            return jsonSuccessResponse($message, $activity->toArray());
        } catch (\Exception $e) {
            Db::rollBack();
            return jsonFailResponse($e->getMessage(), [], 500);
        }
    }

    /**
     * 获取等级名称
     * @param int $rank
     * @return string
     */
    private function getLevelName(int $rank): string
    {
        $levelNames = LotteryTicketPrizeLevel::getLevelNameOptions();
        return $levelNames[$rank] ?? admin_trans('lottery_ticket.level_name.default', null, ['rank' => $rank]);
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

            // 列定义(与index相同)
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
     * @return mixed
     */
    public function prizeConfig()
    {
        $id = request()->input('id');
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
                ->help('最多可配置10个奖品等级,中奖概率总和不能超过100%');

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
     * @return mixed
     */
    public function closeActivity()
    {
        $id = request()->input('id');
        $activity = LotteryTicketActivity::find($id);

        if (!$activity) {
            return jsonFailResponse(admin_trans('lottery_ticket.message.activity_not_found'), [], 404);
        }

        // 检查是否属于当前渠道
        if ($activity->department_id != Admin::user()->department_id) {
            return jsonFailResponse(admin_trans('common.no_permission'), [], 403);
        }

        // 只能关闭进行中的活动
        if ($activity->status != LotteryTicketActivity::STATUS_ONGOING) {
            return jsonFailResponse(admin_trans('lottery_ticket.message.activity_not_ongoing'), [], 400);
        }

        $activity->status = LotteryTicketActivity::STATUS_CLOSED;
        $activity->save();

        return jsonSuccessResponse(admin_trans('lottery_ticket.message.close_success'));
    }

    /**
     * 更新直播地址
     * @auth true
     * @group channel
     * @return mixed
     */
    public function updateLiveUrl()
    {
        $id = request()->input('id');
        $liveUrl = request()->input('live_url', '');

        if (!$id) {
            return jsonFailResponse(admin_trans('lottery_ticket.error.invalid_params'), [], 400);
        }

        $activity = LotteryTicketActivity::query()
            ->where('id', $id)
            ->where('department_id', Admin::user()->department_id)
            ->first();

        if (!$activity) {
            return jsonFailResponse(admin_trans('lottery_ticket.message.activity_not_found'), [], 404);
        }

        try {
            $activity->live_url = $liveUrl;
            $activity->save();

            return jsonSuccessResponse(admin_trans('lottery_ticket.message.live_url_updated'), $activity);
        } catch (\Exception $e) {
            return jsonFailResponse($e->getMessage(), [], 500);
        }
    }

    /**
     * 上传活动封面图片
     * @auth true
     * @group channel
     * @return mixed
     */
    public function uploadCover()
    {
        $file = request()->file('file');

        if (!$file || !$file->isValid()) {
            return json(['code' => 400, 'msg' => admin_trans('lottery_ticket.error.invalid_file')]);
        }

        // 验证文件类型
        $ext = strtolower($file->getUploadExtension());
        $allowedExts = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowedExts)) {
            return json(['code' => 400, 'msg' => admin_trans('lottery_ticket.error.invalid_image_type')]);
        }

        // 验证文件大小 (2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return json(['code' => 400, 'msg' => admin_trans('lottery_ticket.error.file_too_large')]);
        }

        try {
            // 上传文件
            $savePath = 'lottery_covers/' . date('Ym');
            $fileName = uniqid() . '_' . time() . '.' . $ext;

            // 移动文件到上传目录
            $file->move(public_path() . '/upload/' . $savePath, $fileName);

            $url = '/upload/' . $savePath . '/' . $fileName;

            return json([
                'code' => 0,
                'msg' => admin_trans('lottery_ticket.message.upload_success'),
                'data' => [
                    'url' => $url,
                    'full_url' => request()->host() . $url
                ]
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
}
