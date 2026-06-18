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
use support\Log;

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
            'closeActivity' => admin_trans('lottery_ticket.action.close'),
            'addPrizeLevel' => admin_trans('lottery_ticket.action.add_prize_level'),
            'recordWin' => admin_trans('lottery_ticket.action.record_win'),
            'startDrawing' => admin_trans('lottery_ticket.action.start_drawing'),
            'stopDrawing' => admin_trans('lottery_ticket.action.stop_drawing'),
            'addLiveUrl' => admin_trans('lottery_ticket.action.add_live_url'),
            'expand' => admin_trans('lottery_ticket.action.expand'),
            'collapse' => admin_trans('lottery_ticket.action.collapse'),
            'distributeByTicket' => admin_trans('lottery_ticket.action.distribute_by_ticket'),
            'distributeAllPending' => admin_trans('lottery_ticket.action.distribute_all_pending'),
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
            'pendingDraw' => admin_trans('lottery_ticket.status.pending_draw'),  // ⭐ 新增：待开奖状态
            'drawing' => admin_trans('lottery_ticket.status.drawing'),  // ⭐ 新增：开奖中状态
            'ended' => admin_trans('lottery_ticket.status.ended'),
            'closed' => admin_trans('lottery_ticket.status.closed'),
            'statusUnknown' => admin_trans('lottery_ticket.status.unknown'),  // ⭐ 新增：未知状态

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
            'pendingCount' => admin_trans('lottery_ticket.fields.pending_count'),
            'maxTicketNo' => admin_trans('lottery_ticket.fields.max_ticket_no'),  // ⭐ 新增
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

            // 确认对话框
            'confirm' => [
                'distribute' => admin_trans('lottery_ticket.confirm.distribute'),
                'distributeAllPending' => admin_trans('lottery_ticket.confirm.distribute_all_pending'),
            ],
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
        $status = Request::input('status', 'all');

        $query = LotteryTicketActivity::where('department_id', $departmentId);

        // ✅ 只显示30天内的活动（created_at 在最近30天内）
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        $query->where('created_at', '>=', $thirtyDaysAgo);

        // 状态筛选（支持数字状态值和字符串值）
        if ($status !== 'all' && $status !== null) {
            // 数字状态值（来自Vue组件）
            if (is_numeric($status)) {
                $query->where('status', (int)$status);
            }
            // 字符串值（向后兼容）
            elseif ($status === 'ongoing') {
                $query->where('status', LotteryTicketActivity::STATUS_ONGOING);
            } elseif ($status === 'ended') {
                $query->whereIn('status', [
                    LotteryTicketActivity::STATUS_ENDED,
                    LotteryTicketActivity::STATUS_CLOSED
                ]);
            }
        }

        $activities = $query->orderBy('created_at', 'desc')->get();

        // ✅ 批量查询优化（避免N+1）
        $activityIds = $activities->pluck('id')->toArray();

        // 批量查询：是否有奖品配置
        $hasPrizeConfig = LotteryTicketPrizeLevel::whereIn('activity_id', $activityIds)
            ->select('activity_id')
            ->distinct()
            ->pluck('activity_id')
            ->toArray();

        // 批量查询：待发放数量
        $pendingCounts = \addons\webman\model\LotteryTicketRecord::query()
            ->whereIn('activity_id', $activityIds)
            ->where('status', \addons\webman\model\LotteryTicketRecord::STATUS_PENDING)
            ->where('prize_type', '!=', \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_EMPTY)
            ->where('prize_amount', '>', 0)
            ->select('activity_id', Db::raw('COUNT(*) as count'))
            ->groupBy('activity_id')
            ->pluck('count', 'activity_id')
            ->toArray();

        // 批量查询：最大券号（保留6位格式，如：000123）
        // ✅ 先查出数值最大的券号，然后补0
        $maxTicketData = \addons\webman\model\LotteryTicket::query()
            ->whereIn('activity_id', $activityIds)
            ->select('activity_id', Db::raw('MAX(CAST(ticket_no AS UNSIGNED)) as max_no'))
            ->groupBy('activity_id')
            ->get();

        $maxTicketNos = [];
        foreach ($maxTicketData as $item) {
            // 将数字转换为6位字符串格式
            $maxTicketNos[$item->activity_id] = str_pad($item->max_no, 6, '0', STR_PAD_LEFT);
        }

        // 添加字段
        $activities = $activities->map(function ($activity) use ($hasPrizeConfig, $pendingCounts, $maxTicketNos) {
            $activityArray = $activity->toArray();

            $activityArray['has_prize_config'] = in_array($activity->id, $hasPrizeConfig);
            $activityArray['pending_count'] = $pendingCounts[$activity->id] ?? 0;
            $activityArray['max_ticket_no'] = $maxTicketNos[$activity->id] ?? '000000';

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

        $activityArray = $activity->toArray();

        // 添加额外的统计字段
        // ✅ 已发最大券号 - 查询实际最大券号（保留6位格式）
        $maxNo = \addons\webman\model\LotteryTicket::where('activity_id', $activity->id)
            ->selectRaw('MAX(CAST(ticket_no AS UNSIGNED)) as max_no')
            ->value('max_no');
        $activityArray['max_ticket_no'] = $maxNo ? str_pad($maxNo, 6, '0', STR_PAD_LEFT) : '000000';

        // ⭐ 统计待发放记录数量
        $activityArray['pending_count'] = \addons\webman\model\LotteryTicketRecord::where('activity_id', $activity->id)
            ->where('status', \addons\webman\model\LotteryTicketRecord::STATUS_PENDING)
            ->where('prize_type', '!=', \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_EMPTY)
            ->where('prize_amount', '>', 0)
            ->count();

        return Response::success($activityArray);
    }

    /**
     * 上传活动封面图片
     * @auth true
     * @return \support\Response
     */
    public function uploadCover()
    {
        try {
            $file = Request::file('file');

            if (!$file) {
                return Response::fail(admin_trans('lottery_ticket.error.invalid_file'));
            }

            // 验证文件类型
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedTypes = ['jpg', 'jpeg', 'png'];

            if (!in_array($extension, $allowedTypes)) {
                return Response::fail(admin_trans('lottery_ticket.error.invalid_image_type'));
            }

            // 获取MIME类型
            $mimeType = $file->getMimeType();
            $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($mimeType, $allowedMimes)) {
                return Response::fail(admin_trans('lottery_ticket.error.invalid_image_type'));
            }

            // 验证文件大小 (2MB)
            if ($file->getSize() > 2 * 1024 * 1024) {
                return Response::fail(admin_trans('lottery_ticket.error.file_too_large'));
            }

            // 生成唯一文件名
            $filename = time() . '_' . uniqid() . '.' . $extension;
            $cloudPath = 'lottery_tickets/' . date('Ymd') . '/' . $filename;

            // 使用 Google Cloud Storage
            $storage = \addons\webman\filesystem\Filesystem::disk('google_oss');

            // 读取文件内容
            $fileContent = file_get_contents($file->getPathname());

            // 上传到 GCS（参考 gk_api 实现）
            $result = $storage->put($cloudPath, $fileContent, [
                'metadata' => [
                    'contentType' => $mimeType,
                    'cacheControl' => 'public, max-age=31536000', // 缓存1年
                ]
            ]);

            if ($result) {
                // 获取公开访问 URL
                $url = $storage->url($cloudPath);
                Log::info('活动封面上传成功: ' . $url);
                return Response::success(['url' => $url]);
            } else {
                Log::error('活动封面上传失败，存储返回 false');
                return Response::fail(admin_trans('lottery_ticket.error.upload_failed'));
            }

        } catch (\Exception $e) {
            Log::error('活动封面上传异常: ' . $e->getMessage());
            return Response::fail(admin_trans('lottery_ticket.error.upload_failed'));
        }
    }

    /**
     * 创建/编辑活动
     * @return Msg|Response
     */
    public function saveActivity()
    {
        $data = Request::input();
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

            // 验证时间格式
            $startTime = strtotime($data['start_time']);
            $endTime = strtotime($data['end_time']);

            if ($startTime === false || $endTime === false) {
                throw new \Exception(admin_trans('lottery_ticket.error.invalid_time_format'));
            }

            // 验证结束时间必须晚于开始时间
            if ($endTime <= $startTime) {
                throw new \Exception(admin_trans('lottery_ticket.error.end_before_start'));
            }

            // 验证开始时间不能是过去的时间（创建新活动时）
            if (empty($data['id'])) {
                $now = time();
                if ($startTime < $now - 300) { // 允许5分钟误差
                    throw new \Exception(admin_trans('lottery_ticket.error.start_time_in_past'));
                }
            }

            // 验证活动时长（测试阶段无最小时长限制，最多30天）
            $duration = $endTime - $startTime;
            // ⚠️ 测试阶段：已移除最小1小时限制
            // $minDuration = 3600; // 1小时
            $maxDuration = 30 * 24 * 3600; // 30天

            // ⚠️ 测试阶段：已禁用最小时长检查
            // if ($duration < $minDuration) {
            //     throw new \Exception(admin_trans('lottery_ticket.error.duration_too_short', null, ['min' => '1小时']));
            // }

            if ($duration > $maxDuration) {
                throw new \Exception(admin_trans('lottery_ticket.error.duration_too_long', null, ['max' => '30天']));
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
                    LotteryTicketActivity::STATUS_PENDING_DRAW => 'orange',  // ⭐ 新增
                    LotteryTicketActivity::STATUS_DRAWING => 'purple',       // ⭐ 新增
                    LotteryTicketActivity::STATUS_ENDED => 'default',
                    LotteryTicketActivity::STATUS_CLOSED => 'red',
                ];
                $labels = [
                    LotteryTicketActivity::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
                    LotteryTicketActivity::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
                    LotteryTicketActivity::STATUS_PENDING_DRAW => admin_trans('lottery_ticket.status.pending_draw'),  // ⭐ 新增
                    LotteryTicketActivity::STATUS_DRAWING => admin_trans('lottery_ticket.status.drawing'),  // ⭐ 新增
                    LotteryTicketActivity::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
                    LotteryTicketActivity::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
                ];

                return Tag::create($labels[$val] ?? admin_trans('lottery_ticket.status.unknown'))
                    ->color($colors[$val] ?? 'default');
            });

            // ⭐ 中奖总数量
            $grid->column('total_winners', admin_trans('lottery_ticket.fields.total_winners'))
                ->width(120)->align('center')
                ->display(function ($val, LotteryTicketActivity $data) {
                    $count = \addons\webman\model\LotteryTicketRecord::where('activity_id', $data->id)
                        ->where('prize_type', '!=', \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_EMPTY)
                        ->count();
                    return $count > 0 ? Tag::create($count)->color('blue') : '0';
                });

            // ⭐ 中奖总金额
            $grid->column('total_prize_amount', admin_trans('lottery_ticket.fields.total_prize_amount'))
                ->width(120)->align('center')
                ->display(function ($val, LotteryTicketActivity $data) {
                    $amount = \addons\webman\model\LotteryTicketRecord::where('activity_id', $data->id)
                        ->where('prize_type', '!=', \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_EMPTY)
                        ->sum('prize_amount');
                    return '¥' . number_format($amount, 2);
                });

            // ⭐ 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')
                    ->placeholder(admin_trans('lottery_ticket.fields.activity_name'));

                $filter->form()->dateRange('start_time', 'end_time', admin_trans('lottery_ticket.filter.time_range'))
                    ->placeholder([
                        admin_trans('lottery_ticket.fields.start_time'),
                        admin_trans('lottery_ticket.fields.end_time')
                    ]);
            });

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                // ✅ 查看详情 - 使用新的详情展示方法
                $actions->prepend(
                    Button::create(admin_trans('lottery_ticket.action.view_detail'))
                        ->type('link')
                        ->size('small')
                        ->modal([$this, 'showActivityDetail'], ['id' => $data['id']])
                        ->width('800px')
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
        $activity = LotteryTicketActivity::find($id);

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // ✅ 修复：使用模型类而不是集合
        return Grid::create(new LotteryTicketPrizeLevel(), function (Grid $grid) use ($activity) {
            $grid->title($activity->name . ' - ' . admin_trans('lottery_ticket.fields.prize_config'));

            // ✅ 添加活动ID筛选
            $grid->model()->where('activity_id', $activity->id)->orderBy('level_rank', 'asc');

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
     * 显示活动详情（用于历史记录等Grid的Modal展示）
     * @auth true
     * @group channel
     * @return Detail
     */
    public function showActivityDetail(): Detail
    {
        $id = Request::input('id');
        $activity = LotteryTicketActivity::with(['prizeLevels', 'vipConfigs.vipLevel'])->find($id);

        if (!$activity) {
            throw new \Exception(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 检查权限
        if ($activity->department_id != Admin::user()->department_id) {
            throw new \Exception(admin_trans('common.no_permission'));
        }

        return Detail::create($activity, function (Detail $detail) use ($activity) {
            $detail->item('name', admin_trans('lottery_ticket.fields.activity_name'));
            $detail->item('start_time', admin_trans('lottery_ticket.fields.start_time'));
            $detail->item('end_time', admin_trans('lottery_ticket.fields.end_time'));

            // ⭐ 状态显示
            $detail->item('status', admin_trans('lottery_ticket.fields.status'))
                ->display(function ($val) {
                    return LotteryTicketActivity::getStatusText($val);
                });

            // ⭐ 已发最大券号（查询实际值）
            $detail->item('max_ticket_no', admin_trans('lottery_ticket.fields.max_ticket_no'))
                ->display(function ($val, LotteryTicketActivity $data) {
                    $maxNo = \addons\webman\model\LotteryTicket::where('activity_id', $data->id)
                        ->selectRaw('MAX(CAST(ticket_no AS UNSIGNED)) as max_no')
                        ->value('max_no');
                    return $maxNo ? str_pad($maxNo, 6, '0', STR_PAD_LEFT) : '000000';
                });

            // 券号统计
            $detail->item('total_tickets', admin_trans('lottery_ticket.fields.total_tickets'));
            $detail->item('used_tickets', admin_trans('lottery_ticket.fields.used_tickets'));

            // 奖品等级配置
            $detail->item('prize_config', admin_trans('lottery_ticket.fields.prize_level_config'))
                ->display(function ($val, LotteryTicketActivity $data) {
                    if (!$data->prizeLevels || count($data->prizeLevels) == 0) {
                        return '-';
                    }
                    $lines = [];
                    foreach ($data->prizeLevels->sortBy('level_rank') as $level) {
                        $remaining = $level->prize_count - $level->won_count;
                        $lines[] = $level->level_name . '：¥' . number_format($level->prize_amount, 2) . '（剩余' . $remaining . '/' . $level->prize_count . '）';
                    }
                    return implode("\n", $lines);
                });

            // VIP打码配置
            $detail->item('vip_bet_config', admin_trans('lottery_ticket.fields.vip_level'))
                ->display(function ($val, LotteryTicketActivity $data) {
                    if (!$data->vipConfigs || count($data->vipConfigs) == 0) {
                        return '-';
                    }
                    $lines = [];
                    foreach ($data->vipConfigs->sortBy('vip_level_id') as $config) {
                        $vipName = $config->vipLevel ? $config->vipLevel->level_name : 'VIP' . $config->vip_level_id;
                        $lines[] = $vipName . '：打码¥' . number_format($config->bet_amount_required, 2) . ' 获得' . $config->ticket_count . '张券';
                    }
                    return implode("\n", $lines);
                });
        })->bordered()->layout('vertical');
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
     * 获取历史活动列表（用于复制创建）⭐ 新增
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function getHistoryActivities()
    {
        $departmentId = Admin::user()->department_id;

        // 获取已结束的活动（最近10个）
        $activities = LotteryTicketActivity::where('department_id', $departmentId)
            ->whereIn('status', [
                LotteryTicketActivity::STATUS_ENDED,
                LotteryTicketActivity::STATUS_CLOSED
            ])
            ->orderBy('created_at', 'desc')
            ->limit(10)  // ✅ 改为10条
            ->get(['id', 'name', 'description', 'cover_image', 'created_at', 'status']);

        return Response::success([
            'activities' => $activities->toArray()
        ]);
    }

    /**
     * 复制活动创建新活动 ⭐ 新增
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function copyActivity()
    {
        $sourceId = Request::input('source_id');

        if (!$sourceId || !is_numeric($sourceId)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_activity_id'));
        }

        $departmentId = Admin::user()->department_id;

        Db::beginTransaction();
        try {
            // 1. 查找源活动
            $sourceActivity = LotteryTicketActivity::where('id', $sourceId)
                ->where('department_id', $departmentId)
                ->first();

            if (!$sourceActivity) {
                throw new \Exception(admin_trans('lottery_ticket.error.activity_not_found'));
            }

            // 2. 复制活动基本信息
            $newActivity = new LotteryTicketActivity();
            $newActivity->department_id = $departmentId;
            $newActivity->name = $sourceActivity->name . ' (副本)';
            $newActivity->description = $sourceActivity->description;
            $newActivity->cover_image = $sourceActivity->cover_image;
            $newActivity->status = LotteryTicketActivity::STATUS_NOT_STARTED;
            $newActivity->current_ticket_no = 1; // 重置券号
            // 不复制时间，让用户设置
            $newActivity->save();

            // 3. 复制奖品等级配置
            $prizeLevels = LotteryTicketPrizeLevel::where('activity_id', $sourceId)->get();
            foreach ($prizeLevels as $level) {
                $newLevel = new LotteryTicketPrizeLevel();
                $newLevel->activity_id = $newActivity->id;
                $newLevel->level_rank = $level->level_rank;
                $newLevel->level_name = $level->level_name;
                // prize_type字段已删除，prize_level表只支持现金奖励
                $newLevel->prize_name = $level->prize_name;
                $newLevel->prize_amount = $level->prize_amount;
                $newLevel->prize_count = $level->prize_count;
                $newLevel->win_probability = $level->win_probability;
                $newLevel->description = $level->description;
                $newLevel->save();
            }

            // 4. 复制VIP打码配置
            $vipConfigs = LotteryTicketVipConfig::where('activity_id', $sourceId)->get();
            foreach ($vipConfigs as $config) {
                $newConfig = new LotteryTicketVipConfig();
                $newConfig->activity_id = $newActivity->id;
                $newConfig->vip_level_id = $config->vip_level_id;
                $newConfig->bet_amount_required = $config->bet_amount_required;
                $newConfig->ticket_count = $config->ticket_count;
                $newConfig->save();
            }

            Db::commit();

            return Response::success([
                'activity_id' => $newActivity->id,
                'activity' => $newActivity->toArray()
            ]);

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error($e->getMessage());
        }
    }

    /**
     * 获取摸奖券发放列表
     * @auth true
     * @group channel
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
            ->orderBy('ticket_no', 'desc');  // ✅ 修改：按券号降序，最大的排在前面

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
     * 录入中奖（按券号批量录入）⭐ 核心方法
     *
     * 业务说明：
     * 线下物理摇球后，管理员根据摇球结果录入中奖券号
     * 例如：摇出 [6,5,4,3,2,1] → 中奖券号为 654321
     *
     * 流程：
     * 1. 线下摇球（实体球机现场直播）
     * 2. 管理员在此录入中奖券号和对应奖品等级
     * 3. 系统自动创建中奖记录
     * 4. 系统自动推送中奖通知给玩家
     *
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function recordWinByTickets()
    {
        $data = Request::input();
        $activityId = $data['activity_id'] ?? null;
        $records = $data['records'] ?? [];

        if (!$activityId || empty($records)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        // ⭐ 验证ID参数
        if (!is_numeric($activityId)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_activity_id'));
        }

        $successCount = 0;
        $errors = [];

        Db::beginTransaction();
        try {
            // ⭐ 使用悲观锁防止并发问题
            $activity = LotteryTicketActivity::where('id', $activityId)
                ->lockForUpdate()
                ->first();

            if (!$activity || $activity->department_id != Admin::user()->department_id) {
                Db::rollBack();
                return message_error(admin_trans('common.no_permission'));
            }

            // ⭐ 核心业务：线下摇球后录入中奖券号
            // 允许在以下状态录入：
            // - ONGOING（活动进行中）- 可提前录入
            // - PENDING_DRAW（待开奖）- 活动结束等待开奖
            // - DRAWING（开奖中）- 正在开奖
            // 不允许在 ENDED（已结束）或 CLOSED（已关闭）状态录入
            $allowedStatuses = [
                LotteryTicketActivity::STATUS_ONGOING,
                LotteryTicketActivity::STATUS_PENDING_DRAW,
                LotteryTicketActivity::STATUS_DRAWING,
            ];

            if (!in_array($activity->status, $allowedStatuses)) {
                Db::rollBack();
                return message_error(admin_trans('lottery_ticket.error.cannot_record_win_in_current_status'));
            }

            foreach ($records as $record) {
                $prizeLevelId = $record['prize_level_id'] ?? null;
                $ticketNo = $record['ticket_no'] ?? null;

                if (!$prizeLevelId || !$ticketNo) {
                    continue;
                }

                // 去除首尾空格
                $ticketNo = trim($ticketNo);

                // 验证券号：必须是纯数字且1-6位
                if (!preg_match('/^\d{1,6}$/', $ticketNo)) {
                    $errors[] = admin_trans('lottery_ticket.error.invalid_ticket_format', null, ['ticket_no' => $record['ticket_no'] ?? '']);
                    continue;
                }

                // 查找摸奖券（使用悲观锁防止并发重复录入）
                $ticket = LotteryTicket::where('ticket_no', $ticketNo)
                    ->where('activity_id', $activityId)
                    ->where('status', LotteryTicket::STATUS_UNUSED)
                    ->lockForUpdate()
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
                    'prize_type' => \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_CASH, // 固定为现金
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
        $data = Request::input();
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
                'prize_type' => \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_CASH, // 固定为现金
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
     * 开始开奖（手动触发）
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function startDrawing()
    {
        $id = Request::input('id');
        $liveUrl = Request::input('live_url');

        // ⭐ 验证ID参数
        if (!$id || !is_numeric($id)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_activity_id'));
        }

        // 验证直播地址
        if (empty($liveUrl)) {
            return message_error(admin_trans('lottery_ticket.error.live_url_required'));
        }

        if (strlen($liveUrl) > 500) {
            return message_error(admin_trans('lottery_ticket.error.live_url_too_long'));
        }

        Db::beginTransaction();
        try {
            // 使用悲观锁防止并发问题
            $activity = LotteryTicketActivity::where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$activity || $activity->department_id != Admin::user()->department_id) {
                Db::rollBack();
                return message_error(admin_trans('common.no_permission'));
            }

            // 检查是否可以开奖
            if (!$activity->canStartDrawing()) {
                Db::rollBack();
                return message_error(admin_trans('lottery_ticket.error.cannot_start_drawing'));
            }

            // 更新状态和直播地址
            $activity->status = LotteryTicketActivity::STATUS_DRAWING;
            $activity->live_url = $liveUrl;
            $activity->recordStatusChange(LotteryTicketActivity::STATUS_DRAWING, '管理员手动开奖');
            $activity->save();

            // 停止所有玩家的打码进度（不再发券）
            \addons\webman\service\LotteryTicketBetProgressService::endActivityProgress($id);

            // 推送开奖通知
            \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'drawing_start');

            Db::commit();

            Log::info('摸奖券活动手动开奖', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'live_url' => $liveUrl,
                'admin_id' => Admin::user()->id,
            ]);

            return message_success(admin_trans('lottery_ticket.message.drawing_started'));

        } catch (\Exception $e) {
            Db::rollBack();
            Log::error('摸奖券活动开奖失败', [
                'activity_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return message_error($e->getMessage());
        }
    }

    /**
     * 停止开奖（手动触发）
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function stopDrawing()
    {
        $id = Request::input('id');

        // ⭐ 验证ID参数
        if (!$id || !is_numeric($id)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_activity_id'));
        }

        Db::beginTransaction();
        try {
            // 使用悲观锁防止并发问题
            $activity = LotteryTicketActivity::where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$activity || $activity->department_id != Admin::user()->department_id) {
                Db::rollBack();
                return message_error(admin_trans('common.no_permission'));
            }

            // 检查是否可以停止开奖
            if (!$activity->canStopDrawing()) {
                Db::rollBack();
                return message_error(admin_trans('lottery_ticket.error.cannot_stop_drawing'));
            }
            // 更新状态
            $activity->status = LotteryTicketActivity::STATUS_ENDED;
            $activity->draw_completed_at = date('Y-m-d H:i:s');
            $activity->recordStatusChange(LotteryTicketActivity::STATUS_ENDED, '管理员手动结束');
            $activity->save();

            // 推送活动结束通知
            \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'ended');

            Db::commit();

            Log::info('摸奖券活动手动结束', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'admin_id' => Admin::user()->id,
            ]);

            return message_success(admin_trans('lottery_ticket.message.activity_ended'));

        } catch (\Exception $e) {
            Db::rollBack();
            Log::error('摸奖券活动结束失败', [
                'activity_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return message_error($e->getMessage());
        }
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

        // 验证状态值（仅允许核心状态）
        $validStatuses = [
            LotteryTicketActivity::STATUS_NOT_STARTED,
            LotteryTicketActivity::STATUS_ONGOING,
            LotteryTicketActivity::STATUS_ENDED,
            LotteryTicketActivity::STATUS_CLOSED,
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
     * ⭐ 废弃方法：getBallResult已删除
     *
     * 原因：系统不再使用ball_result字段（自动摇球功能已移除）
     * 替代方案：通过activity.status判断是否已开奖
     *   - STATUS_DRAWING(6) = 开奖中（线下摇球中）
     *   - STATUS_ENDED(2) = 已结束（开奖完成）
     */

    /**
     * 批量发放该活动所有已录入未发放的奖励
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function batchDistributeActivity()
    {
        $activityId = Request::input('activity_id');
        $adminId = Admin::user()->id;
        $departmentId = Admin::user()->department_id;

        // ⭐ 1. 参数验证
        if (!$activityId || !is_numeric($activityId)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_activity_id'));
        }

        Db::beginTransaction();
        try {
            // ⭐ 2. 验证活动并锁定
            $activity = LotteryTicketActivity::where('id', $activityId)
                ->lockForUpdate()
                ->first();

            if (!$activity) {
                throw new \Exception(admin_trans('lottery_ticket.message.activity_not_found'));
            }

            // ⭐ 3. 检查权限
            if ($activity->department_id != $departmentId) {
                throw new \Exception(admin_trans('common.no_permission'));
            }

            // ⭐ 4. 检查活动状态（线下摸奖流程：必须在 DRAWING 或 ENDED 状态才能发放）
            $allowedStatuses = [
                LotteryTicketActivity::STATUS_DRAWING,
                LotteryTicketActivity::STATUS_ENDED,
            ];

            if (!in_array($activity->status, $allowedStatuses)) {
                throw new \Exception('只能在开奖中或已结束状态发放奖励');
            }

            // ⭐ 5. 查询该活动所有待发放的中奖记录
            $pendingRecords = \addons\webman\model\LotteryTicketRecord::where('activity_id', $activityId)
                ->where('department_id', $departmentId)
                ->where('status', \addons\webman\model\LotteryTicketRecord::STATUS_PENDING)
                ->where('prize_type', '!=', \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_EMPTY)
                ->where('prize_amount', '>', 0)
                ->get();

            if ($pendingRecords->isEmpty()) {
                throw new \Exception(admin_trans('lottery_ticket.error.no_pending_records'));
            }

            $successCount = 0;
            $failCount = 0;
            $failReasons = [];

            // ⭐ 6. 逐条发放
            foreach ($pendingRecords as $record) {
                try {
                    // 锁定记录
                    $lockedRecord = \addons\webman\model\LotteryTicketRecord::where('id', $record->id)
                        ->lockForUpdate()
                        ->first();

                    // 再次检查状态
                    if (!$lockedRecord || $lockedRecord->status !== \addons\webman\model\LotteryTicketRecord::STATUS_PENDING) {
                        throw new \Exception(admin_trans('lottery_ticket.error.status_changed'));
                    }

                    // 更新状态为发放中
                    $lockedRecord->status = \addons\webman\model\LotteryTicketRecord::STATUS_PROCESSING;
                    $lockedRecord->save();

                    // 获取玩家并锁定
                    $player = \addons\webman\model\Player::where('id', $lockedRecord->player_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$player) {
                        throw new \Exception(admin_trans('lottery_ticket.error.player_not_found'));
                    }

                    // 检查玩家状态
                    if (isset($player->status) && $player->status != \addons\webman\model\Player::STATUS_ENABLE) {
                        throw new \Exception(admin_trans('lottery_ticket.error.player_disabled'));
                    }

                    // 检查是否超额发放
                    $newDistributedAmount = $activity->distributed_prize_amount + $lockedRecord->prize_amount;
                    if ($newDistributedAmount > $activity->total_prize_amount) {
                        throw new \Exception(admin_trans('lottery_ticket.error.amount_exceeded'));
                    }

                    // 发放奖励
                    if ($lockedRecord->prize_type == \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_CASH) {
                        // ✅ 步骤 1: 从 Redis 读取当前余额（唯一可信源）
                        $oldBalance = \addons\webman\service\WalletService::getBalance($player->id);

                        // ✅ 步骤 2: 使用 WalletService 原子性增加余额（自动同步数据库）
                        $newBalance = \addons\webman\service\WalletService::atomicIncrement($player->id, $lockedRecord->prize_amount);

                        // ⭐ 记录金流明细 (PlayerDeliveryRecord)
                        $playerDeliveryRecord = new \addons\webman\model\PlayerDeliveryRecord();
                        $playerDeliveryRecord->player_id = $player->id;
                        $playerDeliveryRecord->department_id = $player->department_id;
                        $playerDeliveryRecord->target = $lockedRecord->getTable(); // lottery_ticket_record
                        $playerDeliveryRecord->target_id = $lockedRecord->id;
                        $playerDeliveryRecord->type = \addons\webman\model\PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD; // ⭐ 摸奖券奖励 (支出)
                        $playerDeliveryRecord->source = 'lottery_ticket_reward';
                        $playerDeliveryRecord->amount = $lockedRecord->prize_amount;
                        $playerDeliveryRecord->amount_before = $oldBalance;
                        $playerDeliveryRecord->amount_after = $newBalance;
                        $playerDeliveryRecord->tradeno = $lockedRecord->ticket_no; // 使用券号作为交易号
                        $playerDeliveryRecord->remark = '摸奖券中奖发放：' . $activity->name . ' - ' . $lockedRecord->prize_name;
                        $playerDeliveryRecord->save();

                    } elseif ($lockedRecord->prize_type == \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_BONUS) {
                        // 红利奖励 - 增加玩家红利
                        $oldBonus = $player->bonus ?? 0;
                        $player->bonus = ($player->bonus ?? 0) + $lockedRecord->prize_amount;
                        $player->save();

                        // ⭐ 记录金流明细 (PlayerDeliveryRecord) - 红利也使用相同类型
                        $playerDeliveryRecord = new \addons\webman\model\PlayerDeliveryRecord();
                        $playerDeliveryRecord->player_id = $player->id;
                        $playerDeliveryRecord->department_id = $player->department_id;
                        $playerDeliveryRecord->target = $lockedRecord->getTable(); // lottery_ticket_record
                        $playerDeliveryRecord->target_id = $lockedRecord->id;
                        $playerDeliveryRecord->type = \addons\webman\model\PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD; // ⭐ 摸奖券奖励 (支出)
                        $playerDeliveryRecord->source = 'lottery_ticket_reward_bonus';
                        $playerDeliveryRecord->amount = $lockedRecord->prize_amount;
                        $playerDeliveryRecord->amount_before = $oldBonus;
                        $playerDeliveryRecord->amount_after = $player->bonus;
                        $playerDeliveryRecord->tradeno = $lockedRecord->ticket_no;
                        $playerDeliveryRecord->remark = '摸奖券红利发放：' . $activity->name . ' - ' . $lockedRecord->prize_name;
                        $playerDeliveryRecord->save();
                    }

                    // 更新中奖记录状态为已发放
                    $lockedRecord->status = \addons\webman\model\LotteryTicketRecord::STATUS_CLAIMED;
                    $lockedRecord->distributed_by = $adminId;
                    $lockedRecord->distributed_at = date('Y-m-d H:i:s');
                    $lockedRecord->distribution_note = '批量发放活动奖励';
                    $lockedRecord->save();

                    // 更新活动已发放金额
                    $activity->distributed_prize_amount = $newDistributedAmount;
                    $activity->save();

                    // 如果存在摸奖券,更新券状态为已使用
                    if ($lockedRecord->ticket_id > 0) {
                        $ticket = LotteryTicket::find($lockedRecord->ticket_id);
                        if ($ticket && $ticket->status == LotteryTicket::STATUS_UNUSED) {
                            $ticket->status = LotteryTicket::STATUS_USED;
                            $ticket->used_at = time();
                            $ticket->save();
                        }
                    }

                    $successCount++;

                    // 推送中奖通知(事务外,失败不影响发放)
                    try {
                        if (class_exists('\addons\webman\service\LotteryTicketPushService')) {
                            \addons\webman\service\LotteryTicketPushService::pushPrizeDistributed(
                                $player->id,
                                $activity,
                                $lockedRecord->ticket_no,
                                $lockedRecord->prize_name,
                                $lockedRecord->prize_amount
                            );
                        }
                    } catch (\Exception $e) {
                        \support\Log::warning('[摸奖券] 推送中奖通知失败', [
                            'record_id' => $lockedRecord->id,
                            'error' => $e->getMessage()
                        ]);
                    }

                } catch (\Exception $e) {
                    $failCount++;
                    $failReasons[] = '券号 ' . $record->ticket_no . ': ' . $e->getMessage();

                    // 如果记录状态是发放中,标记为失败
                    if (isset($lockedRecord) && $lockedRecord->status === \addons\webman\model\LotteryTicketRecord::STATUS_PROCESSING) {
                        try {
                            $lockedRecord->status = \addons\webman\model\LotteryTicketRecord::STATUS_FAILED;
                            $lockedRecord->distribution_note = '批量发放失败: ' . $e->getMessage();
                            $lockedRecord->save();
                        } catch (\Exception $e2) {
                            // 忽略
                        }
                    }

                    \support\Log::error('[摸奖券] 批量发放单条记录失败', [
                        'record_id' => $record->id,
                        'ticket_no' => $record->ticket_no,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Db::commit();

            // ⭐ 7. 记录操作日志
            \support\Log::info('[摸奖券] 批量发放活动奖励完成', [
                'activity_id' => $activityId,
                'activity_name' => $activity->name,
                'total' => $pendingRecords->count(),
                'success' => $successCount,
                'fail' => $failCount,
                'admin_id' => $adminId
            ]);

            // ⭐ 8. 返回结果
            $message = admin_trans('lottery_ticket.message.batch_complete', null, [
                'success' => $successCount,
                'fail' => $failCount
            ]);

            if ($failCount > 0 && $successCount > 0) {
                return \ExAdmin\ui\response\Response::success([
                    'message' => $message,
                    'fail_reasons' => $failReasons,
                    'success_count' => $successCount,
                    'fail_count' => $failCount
                ]);
            } elseif ($failCount > 0 && $successCount === 0) {
                return message_error($message . ' ' . implode('; ', $failReasons));
            }

            return message_success($message);

        } catch (\Exception $e) {
            Db::rollBack();

            \support\Log::error('[摸奖券] 批量发放活动奖励失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return message_error($e->getMessage());
        }
    }

    /**
     * 根据中奖券号发放奖励
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function distributeByTicketNo()
    {
        $activityId = Request::input('activity_id');
        $ticketNo = Request::input('ticket_no');
        $remark = Request::input('remark', '');
        $adminId = Admin::user()->id;
        $departmentId = Admin::user()->department_id;

        // ⭐ 1. 参数验证
        if (!$activityId || !is_numeric($activityId)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_activity_id'));
        }

        if (empty($ticketNo)) {
            return message_error(admin_trans('lottery_ticket.error.invalid_params'));
        }

        // 去除首尾空格
        $ticketNo = trim($ticketNo);

        // 券号格式验证：必须是纯数字且1-6位
        if (!preg_match('/^\d{1,6}$/', $ticketNo)) {
            return message_error(admin_trans('lottery_ticket.message.ticket_format_error'));
        }

        if (strlen($remark) > 255) {
            return message_error(admin_trans('lottery_ticket.error.note_too_long'));
        }

        Db::beginTransaction();
        try {
            // ⭐ 2. 验证活动并锁定
            $activity = LotteryTicketActivity::where('id', $activityId)
                ->lockForUpdate()
                ->first();

            if (!$activity) {
                throw new \Exception(admin_trans('lottery_ticket.message.activity_not_found'));
            }

            // ⭐ 3. 检查权限
            if ($activity->department_id != $departmentId) {
                throw new \Exception(admin_trans('common.no_permission'));
            }

            // ⭐ 4. 检查活动状态（线下摸奖流程：必须在 DRAWING 或 ENDED 状态才能发放）
            $allowedStatuses = [
                LotteryTicketActivity::STATUS_DRAWING,
                LotteryTicketActivity::STATUS_ENDED,
            ];

            if (!in_array($activity->status, $allowedStatuses)) {
                throw new \Exception('只能在开奖中或已结束状态发放奖励');
            }

            // ⭐ 5. 查找已录入的中奖记录（根据券号）
            $winRecord = \addons\webman\model\LotteryTicketRecord::where('activity_id', $activityId)
                ->where('ticket_no', $ticketNo)
                ->where('department_id', $departmentId)
                ->lockForUpdate()
                ->first();

            if (!$winRecord) {
                throw new \Exception('券号 ' . $ticketNo . ' 的中奖记录不存在，请先录入中奖');
            }

            // ⭐ 6. 检查记录状态 - 只能发放"待发放"状态的记录
            if ($winRecord->status !== \addons\webman\model\LotteryTicketRecord::STATUS_PENDING) {
                $statusText = [
                    \addons\webman\model\LotteryTicketRecord::STATUS_CLAIMED => '已发放',
                    \addons\webman\model\LotteryTicketRecord::STATUS_PROCESSING => '发放中',
                    \addons\webman\model\LotteryTicketRecord::STATUS_FAILED => '发放失败',
                ][$winRecord->status] ?? '未知状态';

                throw new \Exception('券号 ' . $ticketNo . ' 当前状态为：' . $statusText . '，无法发放');
            }

            // ⭐ 7. 检查奖品类型（空奖不能发放）
            if ($winRecord->prize_type === \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_EMPTY) {
                throw new \Exception(admin_trans('lottery_ticket.error.empty_prize'));
            }

            // ⭐ 8. 检查奖品金额（必须大于0）
            if ($winRecord->prize_amount <= 0) {
                throw new \Exception(admin_trans('lottery_ticket.error.invalid_amount'));
            }

            // ⭐ 9. 更新状态为发放中（防止重复发放）
            $winRecord->status = \addons\webman\model\LotteryTicketRecord::STATUS_PROCESSING;
            $winRecord->save();

            // ⭐ 10. 获取玩家并锁定
            $player = \addons\webman\model\Player::where('id', $winRecord->player_id)
                ->lockForUpdate()
                ->first();

            if (!$player) {
                throw new \Exception(admin_trans('lottery_ticket.error.player_not_found'));
            }

            // ⭐ 11. 检查玩家状态
            if (isset($player->status) && $player->status != \addons\webman\model\Player::STATUS_ENABLE) {
                throw new \Exception(admin_trans('lottery_ticket.error.player_disabled'));
            }

            // ⭐ 12. 检查是否超额发放
            $newDistributedAmount = $activity->distributed_prize_amount + $winRecord->prize_amount;
            if ($newDistributedAmount > $activity->total_prize_amount) {
                throw new \Exception(admin_trans('lottery_ticket.error.amount_exceeded'));
            }

            // ⭐ 13. 发放奖励（根据奖品类型）
            if ($winRecord->prize_type == \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_CASH) {
                // ✅ 步骤 1: 从 Redis 读取当前余额（唯一可信源）
                $oldBalance = \addons\webman\service\WalletService::getBalance($player->id);

                // ✅ 步骤 2: 使用 WalletService 原子性增加余额（自动同步数据库）
                $newBalance = \addons\webman\service\WalletService::atomicIncrement($player->id, $winRecord->prize_amount);

                // ⭐ 记录金流明细 (PlayerDeliveryRecord)
                $playerDeliveryRecord = new \addons\webman\model\PlayerDeliveryRecord();
                $playerDeliveryRecord->player_id = $player->id;
                $playerDeliveryRecord->department_id = $player->department_id;
                $playerDeliveryRecord->target = $winRecord->getTable(); // lottery_ticket_record
                $playerDeliveryRecord->target_id = $winRecord->id;
                $playerDeliveryRecord->type = \addons\webman\model\PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD; // ⭐ 摸奖券奖励 (支出)
                $playerDeliveryRecord->source = 'lottery_ticket_reward';
                $playerDeliveryRecord->amount = $winRecord->prize_amount;
                $playerDeliveryRecord->amount_before = $oldBalance;
                $playerDeliveryRecord->amount_after = $newBalance;
                $playerDeliveryRecord->tradeno = $winRecord->ticket_no;
                $playerDeliveryRecord->remark = '摸奖券中奖发放：' . $activity->name . ' - ' . $winRecord->prize_name . ($remark ? '（' . $remark . '）' : '');
                $playerDeliveryRecord->save();

            } elseif ($winRecord->prize_type == \addons\webman\model\LotteryTicketRecord::PRIZE_TYPE_BONUS) {
                // 红利奖励 - 增加玩家红利
                $oldBonus = $player->bonus ?? 0;
                $player->bonus = ($player->bonus ?? 0) + $winRecord->prize_amount;
                $player->save();

                // ⭐ 记录金流明细 (PlayerDeliveryRecord)
                $playerDeliveryRecord = new \addons\webman\model\PlayerDeliveryRecord();
                $playerDeliveryRecord->player_id = $player->id;
                $playerDeliveryRecord->department_id = $player->department_id;
                $playerDeliveryRecord->target = $winRecord->getTable(); // lottery_ticket_record
                $playerDeliveryRecord->target_id = $winRecord->id;
                $playerDeliveryRecord->type = \addons\webman\model\PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD; // ⭐ 摸奖券奖励 (支出)
                $playerDeliveryRecord->source = 'lottery_ticket_reward_bonus';
                $playerDeliveryRecord->amount = $winRecord->prize_amount;
                $playerDeliveryRecord->amount_before = $oldBonus;
                $playerDeliveryRecord->amount_after = $player->bonus;
                $playerDeliveryRecord->tradeno = $winRecord->ticket_no;
                $playerDeliveryRecord->remark = '摸奖券红利发放：' . $activity->name . ' - ' . $winRecord->prize_name . ($remark ? '（' . $remark . '）' : '');
                $playerDeliveryRecord->save();
            }

            // ⭐ 14. 更新中奖记录状态为已发放
            $winRecord->status = \addons\webman\model\LotteryTicketRecord::STATUS_CLAIMED;
            $winRecord->distributed_by = $adminId;
            $winRecord->distributed_at = date('Y-m-d H:i:s');
            $winRecord->distribution_note = $remark ?: '管理员根据券号手动发放';
            $winRecord->save();

            // ⭐ 15. 更新活动已发放金额
            $activity->distributed_prize_amount = $newDistributedAmount;
            $activity->save();

            // ⭐ 16. 如果存在摸奖券，更新券状态为已使用
            if ($winRecord->ticket_id > 0) {
                $ticket = LotteryTicket::find($winRecord->ticket_id);
                if ($ticket && $ticket->status == LotteryTicket::STATUS_UNUSED) {
                    $ticket->status = LotteryTicket::STATUS_USED;
                    $ticket->used_at = time();
                    $ticket->save();
                }
            }

            Db::commit();

            // ⭐ 17. 推送中奖通知（事务外，失败不影响发放）
            try {
                if (class_exists('\addons\webman\service\LotteryTicketPushService')) {
                    \addons\webman\service\LotteryTicketPushService::pushPrizeDistributed(
                        $player->id,
                        $activity,
                        $ticketNo,
                        $winRecord->prize_name,
                        $winRecord->prize_amount
                    );
                }
            } catch (\Exception $e) {
                \support\Log::warning('[摸奖券] 推送中奖通知失败', [
                    'ticket_no' => $ticketNo,
                    'error' => $e->getMessage()
                ]);
            }

            // ⭐ 18. 记录操作日志
            \support\Log::info('[摸奖券] 根据券号发放奖励成功', [
                'activity_id' => $activityId,
                'ticket_no' => $ticketNo,
                'player_id' => $player->id,
                'player_name' => $player->name,
                'prize_name' => $winRecord->prize_name,
                'prize_amount' => $winRecord->prize_amount,
                'old_balance' => $oldBalance,
                'new_balance' => $player->money,
                'admin_id' => $adminId,
                'remark' => $remark
            ]);

            return Response::success([
                'ticket_no' => $ticketNo,
                'player_name' => $player->name ?? '-',
                'prize_level' => $winRecord->prize_name,
                'prize_amount' => $winRecord->prize_amount,
                'prize_type' => $winRecord->prize_type,
            ], admin_trans('lottery_ticket.message.distribute_success'));

        } catch (\Exception $e) {
            Db::rollBack();

            // ⭐ 如果记录存在且状态是发放中，标记为失败
            if (isset($winRecord) && $winRecord->status === \addons\webman\model\LotteryTicketRecord::STATUS_PROCESSING) {
                try {
                    $winRecord->status = \addons\webman\model\LotteryTicketRecord::STATUS_FAILED;
                    $winRecord->distribution_note = admin_trans('lottery_ticket.message.distribute_failed') . ': ' . $e->getMessage();
                    $winRecord->save();
                } catch (\Exception $e2) {
                    // 忽略
                }
            }

            // ⭐ 记录错误日志
            \support\Log::error('[摸奖券] 根据券号发放奖励失败', [
                'activity_id' => $activityId,
                'ticket_no' => $ticketNo,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return message_error($e->getMessage());
        }
    }

    /**
     * 生成直播地址（基于machine_tencent_play配置）
     * @auth true
     */
    public function generateLiveUrls()
    {
        try {
            $configId = Request::input('config_id');
            $streamName = Request::input('stream_name', 'mojiangjuan');
            $expireDays = Request::input('expire_days', 30);

            if (empty($configId)) {
                return message_error(admin_trans('lottery_ticket.message.select_tencent_config'));
            }

            // 调用辅助函数生成地址
            $urls = generateLotteryLiveUrls($configId, $streamName, $expireDays);

            return Response::success($urls, admin_trans('lottery_ticket.message.live_url_generated'));

        } catch (\Exception $e) {
            \support\Log::error('[摸奖券] 生成直播地址失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return message_error($e->getMessage());
        }
    }

    /**
     * 获取直播播放器配置（用于预览页面）
     * @auth true
     */
    public function getLivePlayerConfig()
    {
        try {
            $streamName = Request::input('stream_name');
            $expireDays = Request::input('expire_days', 30); // ✅ 允许前端指定有效期，默认 30 天

            if (empty($streamName)) {
                return message_error(admin_trans('lottery_ticket.message.stream_name_required'));
            }

            // 获取腾讯云配置（包含license信息）
            /** @var \addons\webman\model\MachineTencentPlay $config */
            $config = \addons\webman\model\MachineTencentPlay::query()->find(1);

            if (!$config) {
                return message_error(admin_trans('lottery_ticket.message.tencent_config_not_found'));
            }

            // 生成播放地址（支持自定义有效期，默认 30 天）
            $urls = generateLotteryLiveUrls(1, $streamName, $expireDays);

            // 返回播放器配置
            return Response::success([
                'stream_name' => $streamName,
                'play_url' => $urls['webrtc'], // ⭐ 使用 WebRTC（超低延迟 <1秒）
                'urls' => [
                    'webrtc' => $urls['webrtc'], // ✅ WebRTC 放在第一位
                    'flv' => $urls['flv'],
                    'hls' => $urls['hls'],
                ],
                'push_url' => $urls['rtmp'], // OBS推流地址
                'expire_time' => $urls['expire_time'],
                'expire_timestamp' => $urls['expire_timestamp'],
                'region' => $urls['region'], // CN（大陆）或 Global（全球）
                'pull_domain' => $urls['pull_domain'], // 实际使用的播放域名
                'tx_time' => $urls['tx_time'], // 用于调试
                'tx_secret' => $urls['tx_secret'], // 用于调试
                'player_config' => [
                    'autoplay' => true,
                    'live' => true,
                    'language' => 'zh-TW',
                    // 腾讯云播放器 License 配置（提供多种字段名兼容不同版本）
                    'licenceUrl' => $config->license, // 英式拼写（官方推荐）
                    'licenceKey' => $config->license_key,
                    'licenseUrl' => $config->license, // 美式拼写（兼容）
                    'licenseKey' => $config->license_key,
                    'license' => $config->license, // 简写（兼容）
                ],
            ], admin_trans('lottery_ticket.message.player_config_loaded_with_region', null, [
                'region' => $urls['region']
            ]));

        } catch (\Exception $e) {
            \support\Log::error('[摸奖券] 获取播放器配置失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return message_error($e->getMessage());
        }
    }
}
